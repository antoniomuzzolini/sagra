# ADR-004 — Identità e accesso

- **Raccomandazione:** MANTENERE l'identità unica (D19) e il flusso magic link
  (D6, D16, D17). MODIFICARE l'ambito dell'unicità di email e telefono, il login
  con password e l'obbligo di account per i responsabili.
- **Dipendenze:** ADR-003 (tenancy), ADR-005 (autorizzazione), ADR-008
  (accesso per dispositivo alla cassa), ADR-010 (GDPR).

## Contesto e decisione attuale

- **Una tabella `people` per tenant**, che è anche il modello autenticato
  (`Person extends Authenticatable`, `Person.php:18`). L'organizzatore è un flag
  (`is_organizer`); il responsabile è un `person_role` legato ad area ed evento.
- **Volontari senza password:**
  - magic link monouso a 7 giorni, di cui si salva solo l'hash
    (`MagicLink.php`);
  - sessione remember di circa un anno;
  - rigenerare il link funziona da kill switch (`Person.php:189-213`);
  - su un dispositivo già collegato a un'altra persona compare la pagina
    "continua come X / entra come Y" (`MagicLinkController.php:32-40`);
  - è disponibile il link d'invito del tenant (D16) e il recupero self-service.
- **Account con password** per organizzatori e responsabili (D19). Il codice
  però **non lo impone**:
  - `AreaManagerController::store` crea responsabili con solo nome e telefono;
  - `PersonController::magicLink` genera link per chiunque;
  - le azioni di gestione sotto `/me` richiedono solo `auth`.
- **Unicità:**
  - `unique(tenant_id, phone)` e `unique(tenant_id, email)` contano anche le
    righe soft-deleted (`create_people_table.php:21-22`), mentre la validazione
    le esclude (`withoutTrashed()`). Risultato: un 500 quando si reinserisce un
    numero (verificato in 01, §8.5);
  - la registrazione richiede un'email **globalmente** unica
    (`RegisteredUserController.php:40`);
  - il login usa `Auth::attempt(email, password)` **senza tenant**
    (`LoginRequest.php:56`).
- **Telefono salvato com'è stato digitato.** Il recupero carica tutte le persone
  con telefono di tutti i tenant e confronta le ultime 9 cifre
  (`RecoveryController.php:53-73`).

## Cosa è fatto bene

Il flusso magic link è la parte più riuscita del progetto, perché parte dagli
utenti reali:

- il link arriva via WhatsApp e può essere inoltrato: per questo è monouso;
- i telefoni di famiglia sono condivisi: per questo il cambio di identità è
  esplicito e non dirotta mai una sessione;
- in caso di furto c'è il kill switch;
- nel database c'è solo l'hash del token.

Anche l'**identità unica** (D19) è corretta: la stessa persona può essere
volontario, responsabile e organizzatore. Due tabelle generavano bug tra guard
e duplicati.

## Il problema di principio

Non è stato deciso **qual è l'ambito di un'identità**: l'associazione o la
persona fisica. Il codice risponde in modi diversi in punti diversi:

- per tenant nello schema;
- globale in registrazione;
- ambigua nel login e nel reset della password.

Finché ogni persona ha un solo tenant non si vede. Con il SaaS aperto
diventa probabile: in un paese la stessa persona fa il responsabile alla Pro
Loco e alla sagra della parrocchia.

## Alternative

| | A. Status quo | B. Identità per tenant, login per tenant | C. Account globale + membership per tenant | D. Email globale solo per chi ha la password |
|---|---|---|---|---|
| Modello | `people` per tenant, login globale | `people` per tenant; il login richiede il tenant (`/{slug}/login` o sottodominio) | `accounts` (email, password, globale) 1—N `people` (membership: nome, telefono, ruoli); scelta del tenant dopo il login | `people` per tenant; indice unico parziale `email WHERE password IS NOT NULL` |
| Complessità | — | Bassa | Alta: cambia `$request->user()` e serve un contesto "membership attiva" | Minima |
| UX | Login ambiguo, errori rari ma gravi | L'organizzatore deve ricordare l'URL dell'associazione | La migliore: una password, più associazioni | Chi gestisce due associazioni usa due email |
| Manutenibilità | Incoerente | Coerente | Coerente, ma ogni query "chi sono" passa dalla membership | Coerente e semplice |
| Costo di migrazione | — | 1 giorno (login, reset, link) | 1-2 settimane (auth, sessioni, test, tenancy ADR-003) | Ore (una migrazione e la validazione) |
| Reversibilità | — | Buona | Difficile da tornare indietro | Ottima: si passa a C quando serve |

## Raccomandazione

**MODIFICARE: D ora, C solo con un caso reale.**

- **D** rende il login deterministico e allinea registrazione, invito account
  e login a una sola regola: "le credenziali sono globali, l'anagrafica è per
  tenant". Non chiude nessuna strada: se arrivano richieste di responsabili
  multi-associazione, C si costruisce estraendo le credenziali dalle righe che
  già le hanno.
- **Indici unici parziali `WHERE deleted_at IS NULL`** su telefono ed email.
  Postgres li supporta, e SQLite anche. Elimina il 500.
- **Telefono normalizzato in scrittura** (E.164, `+39` di default). È una
  colonna in più, oppure si normalizza la colonna esistente con un backfill.
  Il recupero diventa una query indicizzata; si eliminano i duplicati del tipo
  "333 1234567" contro "+393331234567" e la scansione dell'intera tabella su
  una rotta pubblica.
- **Il recupero non deve fare da kill switch.** Oggi chiama `createMagicLink()`,
  che ruota `remember_token` (`Person.php:209`). Chiunque conosca l'email di
  qualcuno può quindi invalidare le sue sessioni "ricordate" su tutti i
  dispositivi. Il kill switch deve restare un'azione dell'organizzatore.
- **D19 e i responsabili: decidere.** Il codice permette responsabili senza
  password e i documenti no. Dai principi, propendo per **ammettere il magic
  link anche per i responsabili**:
  - il motivo originale della password (bug tra guard) non esiste più con
    una sola guard;
  - la regola "semplicità per l'utente finale" pesa;
  - il rischio vero (link inoltrato a chi vede i telefoni dei volontari) si
    riduce meglio con la minimizzazione dei dati visibili (ADR-010) e con
    l'accesso per dispositivo alla cassa (ADR-008).

  In ogni caso la scelta va scritta, perché oggi codice e documenti si
  contraddicono.

## Rischi e dipendenze

- **D** impedisce a una persona di avere la stessa email con password in due
  associazioni. Va spiegato nell'errore ("questa email ha già un account su
  Fieste") e tenuto come segnale per passare a C.
- La normalizzazione del telefono richiede una libreria matura, per esempio il
  port PHP di libphonenumber, oppure una regola semplice limitata all'Italia.
  La scelta dipende dalla domanda sul mercato (solo Italia?).
- ADR-003: con C, il contesto di tenant si ricava dalla membership attiva.
  Decidere qui prima di implementare lo scope.
- ADR-010: la cancellazione da parte dell'organizzatore deve passare dalla
  stessa anonimizzazione della cancellazione volontaria, altrimenti gli indici
  parziali lasciano dati personali nelle righe cancellate.
