# ADR-002 — Dove vive la logica applicativa

- **Raccomandazione:** MODIFICARE, in modo incrementale. Mantenere le
  invarianti nei modelli; introdurre classi di caso d'uso solo per le mutazioni
  con effetti collaterali o con più ingressi; eliminare il doppio percorso.
- **Dipendenze:** ADR-001 (use-case-first), ADR-005 (autorizzazione), ADR-009
  (notifiche), ADR-012 (test).

## Contesto e decisione attuale

La logica è distribuita su quattro livelli, senza una regola esplicita.

1. **Controller "grassi".** Validazione con messaggi in italiano, query,
   transazioni ed effetti collaterali. Esempi: `EventController.php` (278 righe,
   compresa la replica), `Volunteer/SignupController.php` (244 righe:
   iscrizione, moderazione, assegnazione con creazione della persona al volo,
   notifiche).
2. **Classi statiche in `app/Support`.** `CurrentEvent`, `ManagerScope`,
   `PersonRoster`, `NewShiftsNotifier`, `SeatFreedNotifier`. Sono funzioni pure
   o quasi.
3. **Invarianti nei modelli**, tramite hook `saving` che lanciano
   `DomainException`: `Shift.php:37-44`, `Phase.php:34-53`, `PersonRole`.
4. **Effetti collaterali** (notifiche) **innescati dai controller**, per esempio
   `Volunteer/SignupController.php:59-65` e `:149-157`.

Lo stesso caso d'uso è implementato due volte:

- `SignupController::update` e `Volunteer\SignupController::moderate`;
- `ShiftController::store/update/destroy` e
  `Volunteer\ManagerShiftController`.

Le due copie divergono già nell'autorizzazione: `ShiftController::store`
controlla solo il tenant, `ManagerShiftController` anche l'area. Le rotte
organizzatore (`routes/manage.php:35-40`) non sono usate dal frontend.

## Cosa è fatto bene

- **Invarianti nel modello.** Proteggono il dominio da qualsiasi ingresso:
  seeder, tinker, un futuro comando o tool MCP. È la scelta giusta e va
  mantenuta.
- **`Support/*` come funzioni pure** per calcoli condivisi tra viste di ruoli
  diversi ("same numbers the same way", `ManagerScope.php:12-16`). Sono
  semplici da testare e non hanno stato nascosto.

## Il problema di principio

Un caso d'uso è *"cosa succede quando un responsabile conferma una
disponibilità"*: cambia lo stato, registra chi ha confermato, notifica il
volontario e, se si libera un posto, avvisa gli altri. Oggi questa sequenza non
ha un nome né una casa. È scritta nel controller, quindi:

- ogni nuovo ingresso deve ricordarsi di riprodurla: una seconda rotta (è già
  successo), un comando, un tool MCP (ADR-001);
- un `DomainException` lanciato dentro una richiesta che ha superato la
  validazione diventa un **500**. Non c'è mappatura in
  `bootstrap/app.php:59-61` (`withExceptions` è vuoto). Per esempio due
  aggiornamenti concorrenti delle fasi superano entrambi la validazione e uno
  dei due esplode nel modello.

## Alternative

| | A. Status quo, solo rimozione dei duplicati | B. Action per caso d'uso (classi invocabili) | C. Service per aggregato (`ShiftService`) | D. Eventi di dominio + listener | E. Esagonale/DDD completo |
|---|---|---|---|---|---|
| Complessità | Minima | Bassa: una classe per caso d'uso, nessun framework | Bassa all'inizio, poi cresce | Media: flusso implicito, ordine dei listener | Alta: repository, entità separate da Eloquent |
| Manutenibilità | Il problema si ripresenta al prossimo ingresso | Alta: nomi espliciti (`AssignVolunteer`, `RegisterOrder`), facili da trovare e testare | I service tendono a diventare classi enormi | Effetti difficili da seguire per chi legge | Molto codice di supporto per un dominio piccolo |
| Performance | = | = | = | = (con coda: latenza) | = |
| Costo di migrazione | Ore | Un caso d'uso alla volta, ciascuno di 1-3 ore con i test esistenti | Simile a B | Medio | Riscrittura |

## Raccomandazione

**MODIFICARE** con B, applicato in modo selettivo.

1. **Subito:** eliminare il doppio percorso. Tenere un solo set di rotte di
   gestione turni e iscrizioni, con l'autorizzazione per area (ADR-005), e
   rimuovere le rotte organizzatore inutilizzate con i loro controller,
   spostandone i test. È un'operazione di sottrazione, a basso rischio.
2. **Action** solo per le mutazioni che hanno effetti collaterali o più
   ingressi:
   - iscrizione, moderazione e assegnazione;
   - creazione e cancellazione di turni, compresa la cancellazione del giorno,
     che oggi **non notifica gli assegnati** (`ShiftController.php:100-110`),
     mentre la rimozione di una singola persona sì;
   - registrazione di un ordine (ADR-008);
   - replica dell'edizione (ADR-006/007);
   - cancellazione o anonimizzazione di una persona (ADR-010).

   Le letture restano nei controller e in `Support`.
3. **Notifiche dentro le Action**, chiamate in modo esplicito. Niente eventi e
   listener (D): per un solo sviluppatore un flusso leggibile dall'alto in
   basso vale più del disaccoppiamento.
4. **Mappare `DomainException` a 422** in `withExceptions`, con un messaggio
   generico. Le invarianti diventano errori dell'utente, non 500.
5. **Mantenere** gli hook dei modelli. Dove il database lo permette, affiancare
   un vincolo DB (ADR-012): l'hook spiega il problema, il vincolo lo garantisce
   anche sotto concorrenza.

Non serve uno strato di repository né una separazione tra entità ed Eloquent
(E): il dominio è piccolo ed Eloquent lo rappresenta bene.

## Rischi e dipendenze

- **Rischio:** "Action per tutto", che produce boilerplate senza valore. Il
  criterio di ingresso (effetti collaterali o più ingressi) va scritto nella
  decisione.
- La rimozione del doppio percorso tocca i test di `ManageSignupsTest`: vanno
  riscritti sulle rotte rimaste, non cancellati.
- ADR-005 deve arrivare prima o insieme: le Action non devono contenere
  l'autorizzazione, che resta al controller (Policy). Altrimenti la regola si
  sparpaglia di nuovo.
