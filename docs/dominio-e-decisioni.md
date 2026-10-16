# Fieste — gestionale modulare per sagre ed eventi con volontari

> Documento di dominio e decisioni. Versione 0.1 — giugno 2026; aggiornato a
> ottobre 2026 con le decisioni D22–D42 della revisione architetturale.
> Da tenere aggiornato: è la fonte di verità del progetto, pensato per la
> conoscenza del progetto Claude e per il futuro repository (`docs/`).

## 1. Visione

Un gestionale **open source** con offerta **SaaS** (open core / hosting
gestito) per la gestione completa di sagre, feste paesane ed eventi gestiti
da volontari. Architettura **modulare**: un core stabile e moduli verticali
abilitabili, il più possibile isolati tra loro. In prospettiva, agenti AI e
tool **MCP** come livello di interfaccia per semplificare l'uso a utenti
poco tecnologici.

**Utenti finali:** volontari con competenze tecnologiche molto variabili,
prevalentemente da smartphone. La semplicità d'uso è un requisito di
prodotto, non un dettaglio.

## 2. Decisioni prese

| # | Decisione | Motivazione |
|---|-----------|-------------|
| D1 | Modello open core: codice libero + SaaS gestito a basso costo | Le associazioni non si auto-ospitano; l'open source è garanzia e canale di adozione |
| D2 | Architettura a due piani: core + moduli verticali. *(Le persone restano trasversali, i turni no: sono il modulo di base, D21 e D24.)* | I turni e le persone sono trasversali per natura; i verticali restano isolati |
| D3 | I moduli non comunicano tra loro, solo con il core | Mantiene l'isolamento e rende i moduli abilitabili singolarmente |
| D4 | MVP = core con gestione volontari e turni. *(MVP completato; dopo sono arrivati i moduli Forniture e Ordini/Cassa, §8. Con D21 e D24 i turni non sono più nel core: sono il modulo di base.)* | Minor rischio tecnico/normativo, tocca tutti i volontari, valida l'idea |
| D5 | AI/MCP rimandati a dopo l'MVP, ma API dei moduli progettate per essere esposte come tool MCP. *(Parte API superata da D23: l'API non esiste; la direzione è use-case-first, con le Action introdotte dagli step indicati in D23.)* | L'AI ragiona bene solo su un dominio dati solido |
| D6 | Accesso volontari senza password (link magico, es. via WhatsApp) | Abbattere la barriera d'ingresso per utenti poco tecnologici |
| D7 | Documentazione in Markdown nel repo, referenziata da CLAUDE.md | Permette il riuso del contesto tra claude.ai e Claude Code |
| D8 | Stack: Laravel + Inertia + Vue 3, monolite modulare | Sviluppatore solo con anni di esperienza Laravel/Vue; ecosistema ricco per SaaS (auth magic link, notifiche, code, Cashier, Filament) |
| D9 | PostgreSQL singolo, tenancy a `tenant_id` | Semplicità operativa; isolamento per schema/database è complessità prematura |
| D10 | Frontend PWA mobile-first; notifiche base via push web | I volontari usano lo smartphone; WhatsApp Business API rimandata per costi e vincoli |
| D11 | Deploy: VPS economico + Docker; stessa immagine per SaaS e self-hosting. *(Vedi D37–D38: oggi la produzione gira su un PC di casa; le associazioni esterne solo su una VPS in UE.)* | Costi minimi; l'immagine Docker è anche il prodotto open source installabile |
| D12 | Futuro livello AI/MCP come servizio Python separato che consuma l'API del core. *(Sospesa da D23.)* | Usa ogni linguaggio dove rende; valida i confini dell'architettura |
| D13 | Per l'MVP il core usa la struttura Laravel standard (`app/Models`, `app/Enums`...); niente cartella `Modules/` né convenzioni di confine finché non esiste il primo modulo verticale. *(Condizione scaduta: i moduli verticali sono tre, Turni, Forniture e Ordini/Cassa, nell'enum `App\Enums\Module`. Il codice resta nella struttura standard, senza `Modules/`; l'unico confine è il flag per evento con il middleware `module:<chiave>` (D21). D42 aggiunge un test architetturale (step 0.6) e rinvia il registro dei moduli.)* | Evita architettura speculativa; il confine core/moduli si disegna sul primo caso reale |
| D14 | Schema dati del core come da `docs/schema-core.md`: `tenant_id` su ogni tabella, disponibilità+assegnazione in un'unica `shift_signups` con stato, volontari in `people` distinti dagli `users` con password. *(Lo split `people`/`users` è superato da D19: `users` non esiste più. `docs/schema-core.md` è aggiornato nei punti superati.)* | Vedi motivazioni nel documento di schema |
| D15 | L'anno di un'edizione è **derivato** dalle date delle fasi, mai dichiarato; le viste di gestione sono filtrate per anno (default: l'anno più vicino a oggi); niente entità "manifestazione" che raggruppa le edizioni finché un caso d'uso concreto non la richiede | Stesso principio della fase derivata dalla data; la replica delle edizioni (post-MVP) avverrà per duplicazione di un evento esistente |
| D16 | Auto-registrazione volontari via **link d'invito** del tenant (condiviso una volta: gruppo WhatsApp, QR), rigenerabile; basta il nome, contatto facoltativo da completare poi (abilita promemoria e recupero accesso self-service) | Elimina il collo di bottiglia "il gestore crea ogni persona e invia ogni link" senza reintrodurre password (conferma D6) |
| D17 | Link magici personali **monouso** con scadenza breve (7 giorni); la permanenza è data dalla sessione remember lunga (~1 anno); rigenerare il link revoca link e sessioni remember (kill switch); su un dispositivo già collegato a un'altra persona il link **chiede** ("continua come X / entra come Y"), mai blocca o scambia in silenzio | Il link viaggia su WhatsApp e può essere inoltrato; il telefono condiviso in famiglia è un caso legittimo e frequente |
| D18 | Gerarchia organizzatore → responsabili d'area → volontari con **delega facoltativa**: l'organizzatore può tutto, il responsabile gestisce turni e volontari del proprio reparto (via link magico, senza account); appartenenza dei volontari ai reparti **morbida e derivata dalla storia delle iscrizioni** (niente da dichiarare o amministrare: prima i turni dei reparti dove hai già lavorato, i buchi altrui restano visibili); sovrapposizioni di turno permesse ma **segnalate** (al volontario e al responsabile che conferma). *(La delega al responsabile via link magico, senza account, è superata da D19 e D26; nel codice è ancora possibile fino allo step 1.9, vedi lo stato di D19. L'appartenenza ai reparti tra un'edizione e l'altra è precisata da D28.)* | Rispecchia l'organizzazione reale delle sagre; la divisione rigida sprecherebbe la flessibilità dei volontari, che è la risorsa principale |
| D19 | **Identità unica con ruoli ortogonali** (rivede D14 e D18). Una sola entità persona, non più lo split `users`/`people`: password **facoltativa**, ruoli separati dall'identità (organizzatore / responsabile d'area / volontario). **Chiunque può iscriversi ai turni**, a prescindere dal ruolo. Volontari semplici: accesso solo con **link magico** (D6/D17). Responsabili e organizzatori: **account con password** (email + password). Il responsabile **non entra più via link magico** (supera la delega senza account di D18). Invito account (organizzatore → responsabile) con **link a scelta del canale**: email, WhatsApp o copia-incolla (riusa il pattern di condivisione dei link già presente). *(Precisata da D26 per l'accesso dei responsabili.)* | Lo split a due tabelle era la radice dell'attrito (responsabile "a metà" tra i due mondi, bug cross-guard). Un'unica identità con password opzionale rispecchia la realtà — è la stessa persona con ruoli diversi — e semplifica auth, sessioni e viste |
| D20 | **Un guscio, contesto "evento corrente", turni divisi per responsabilità.** Navigazione unica (sidebar) uguale per organizzatore e responsabile; le voci cambiano solo lo *scope*. Un **selettore di evento corrente** in alto (tenuto in sessione, default = edizione più vicina a oggi per D15; per i responsabili mostra solo gli eventi in cui hanno un ruolo; nascosto/passivo con un solo evento). Voci **evento-scoped**: Panoramica, Calendario, Gestione turni, Prenotazione turni. Voci **cross-evento** (separatore, in basso): Volontari, Eventi (solo organizzatore, dove si definiscono eventi → aree → responsabili). **Split dei turni per responsabilità** (Opzione 1): *Gestione turni* (org/responsabile, scoped alle aree gestite) crea/configura turni, vede le disponibilità e **assegna/modera**; *Prenotazione turni* (**chiunque, identica per ruolo**) dà disponibilità ("ci sono"), recap dei propri impegni, sostituzioni | Separa due compiti mentali diversi ("allestire il tabellone" vs "prenotarsi per lavorare"); l'assegnazione è una **decisione di gestione**, coerente con la distinzione Disponibilità/Assegnazione del glossario; tiene *Prenotazione* universale e semplice (D19/D5), senza rami per ruolo; l'evento corrente rende esplicito il filtro per edizione già previsto (D15) |
| D21 | **I turni sono un modulo, non una capacità speciale del core.** Due piani netti: (1) **kernel** sempre attivo — identità persone, eventi, fasi, **aree + sotto-reparti**, ruoli, notifiche, API, layer di **aggregazione/metriche**; (2) **moduli peer, attivabili per evento e isolati** (turni, ordini/cassa, forniture, statistiche…), che parlano **solo col core** (D3), tutti spegnibili allo stesso modo. Nessun modulo *operativo* ha bisogno di sapere "chi è di turno" (comande instrada a un sotto-reparto/schermo, la cassa al dispositivo/area), quindi i turni **non sono un substrato condiviso**: sono un modulo come gli altri. L'unico caso cross-cutting è **statistiche/contabilità**, che legge il layer di aggregazione del core su cui ogni modulo spinge i propri numeri (modulo→core, **mai** modulo→modulo). **Implementato**: `events.enabled_modules` (JSON) porta le chiavi attive dell'edizione, il middleware `module:<chiave>` chiude le rotte di un modulo spento (404), una prop Inertia condivisa filtra le voci di sidebar, e l'organizzatore accende/spegne dalla pagina dell'evento. Un evento nuovo nasce con i soli **Turni**; spegnere **non cancella** i dati, li nasconde. *(Sostituisce la prima stesura di D21, che li trattava come "capacità orizzontale del core": sovradimensionata, perché nessuno li consuma.)* *(Precisata da D24: Turni è il modulo di base, e spento nasconde l'interfaccia e silenzia le notifiche automatiche. Oggi spegnerlo toglie Gestione turni e Prenotazione turni dalla sidebar e chiude `/manage/shifts` (404), ma le altre rotte dei turni e le notifiche automatiche non guardano il flag; le notifiche le sistema lo step 1.6. L'API del kernel è superata da D23; il layer di aggregazione non esiste e Statistiche sarà pull, §8.)* | Semplicità e simmetria: se nessun modulo consuma i turni, farne una capacità core speciale era architettura per un bisogno inesistente. La linea vera è **kernel** ("chi/dove/quando" + servizi condivisi) vs **moduli** ("cosa ci fai"), spegnibili tutti uguali — coerente con D13 (il confine si disegna sul caso reale) |

### Decisioni D22–D42 (revisione architetturale, ottobre 2026)

Trascritte da `docs/review/03-roadmap.md` §2, che le motiva e le traduce in
step: la colonna *Step* rimanda a quel documento (§9 = traccia C). Gli ADR
citati sono in `docs/review/adr/`, le «domande» sono quelle di
`docs/review/02-sintesi.md`. Salvo dove è detto, descrivono la direzione: il
codice ci arriva con gli step indicati. Le decisioni precedenti che superano
o precisano sono marcate nella tabella sopra.

Stato: **default** = scelta che la roadmap usa, cambiarla tocca solo gli step
indicati; **✔ confermata** = confermata dal maintainer; **⚠ azione del
maintainer** = dipende da informazioni o da un'azione che ha solo il
maintainer.

| # | Decisione | Sostituisce / precisa | Stato | Step |
|---|-----------|-----------------------|-------|------|
| D22 | **Il 2027 è una stagione pilota** con 1-5 associazioni accompagnate. Registrazione pubblica **chiusa** (flag `REGISTRATION_ENABLED`, spento di default); le associazioni entrano con un invito monouso. Oggi non ci sono associazioni esterne. | ADR-003, ADR-006, ADR-010 (perimetro) | ✔ confermata (16/10/2026); registrazione chiusa fatta (S.2a) | S.2a, 8.0, 8, §9 |
| D23 | **Use-case-first** al posto di API-first. Ogni mutazione con effetti collaterali o con più ingressi passa da una Action, che riceve l'attore e verifica l'autorizzazione d'ingresso. D12 (MCP in Python) sospesa. | Regola 4 «API-first» del `CLAUDE.md` originale (`docs/review/CLAUDE.original.md`), D5 (parte API), D12 | default | 1.4, 1.7, 4.1, 5.4, 9, 11 |
| D24 | **I Turni sono un modulo di base.** Il flag per evento nasconde l'interfaccia **e** silenzia le notifiche automatiche. | D21 (parte Turni) | default | 1.6 |
| D25 | **Credenziali globali.** Un'email associata a una password è unica su tutta l'istanza; l'anagrafica resta per tenant. Il **login** considera solo le righe con password, ordinate per id. **Recupero e reset preferiscono** la riga con password senza escludere le altre, perché l'invito imposta la prima password. L'**invito account** rifiuta un'email presente in qualunque altro tenant, con o senza password, finché il token resta legato all'email. Per lo stesso motivo **chi ha una password** non può passare a un'email presente in un altro tenant (S.2b). **Limite noto:** un volontario presente in più associazioni con la stessa email non può ricevere un invito account, e chi ha una password non può prendere un'email che altrove usa un omonimo senza password (fino ad ADR-004 C). | ADR-004 D, corretta | default; codice fatto (S.2b, 16/10/2026), deploy da fare | S.2b, 6 |
| D26 | **Accesso per ruolo.** **Responsabili e organizzatori** entrano con invito, completano la registrazione con una password e poi fanno login (il D19 originale; l'invito account esiste già). **I volontari** restano con il link magico. Per nominare un responsabile serve l'email; chi ha un ruolo o una password non riceve link magici. | D19 | ✔ confermata (16/10/2026) | 1.9, 7, 9.4 |
| D27 | **Ruolo per edizione.** Dà accesso operativo finché l'edizione non è conclusa, più **30 giorni** (per le fatture che arrivano dopo la sagra). Dopo, l'edizione resta in **sola lettura** per chi vi ha avuto un ruolo: Panoramica, Calendario, Volontari dell'edizione (contatti mascherati), Gestione turni senza azioni, senza elenco di tutte le persone e senza link d'invito, Forniture senza scrittura. Cassa e Comande rispondono 403. `auth.role` resta `manager`. Le Policy controllano l'evento del **record**, non quello della sessione. Tabella completa nello step 9.4. | ADR-005, domanda 9 | default | 0.3, 9.4 |
| D28 | **Reparto stabile per tenant** (`departments`) per appartenenza, notifiche e replica. Ruoli e roster restano per edizione. Il roster del responsabile mostra anche i **veterani del reparto delle ultime 3 edizioni**, con i contatti mascherati. | ADR-007, domanda 10 | default | 5 |
| D29 | **Cassa.** Contatore con lock e idempotenza obbligatoria; ogni ordine registra la sua cassa, e una cassa con ordini non si cancella; cambi di pagamento tracciati; riepilogo per cassa e per serata. **Numerazione e tagliandi** (dal flusso reale della sagra pilota, 16/10/2026): un **numero unico per ordine**, assegnato **solo agli ordini con piatti da preparare**, così la cucina chiama una sequenza senza buchi anche quando la cassa bevande vende; **un tagliando per ogni piatto** con il punto di ritiro stampato (oggi scritto a mano); **le bevande a tagliandino**, senza numero e senza coda, ritirate quando si vuole. Fieste stampa tutti i tagliandi (step 4.7). Caso reale: 2 casse in contemporanea, una per tutto e una per bevande e patatine. **Alla sagra 2027 resta la cassa a noleggio** (configurata a inizio sagra, stampa scontrini e tagliandini, ha un display per i numeri). La cassa di Fieste si prova prima su **eventi piccoli**; sostituire il noleggio si valuta per il 2028, con dispositivi, stampanti e un display propri (step 4.7 e 4.8). | ADR-008, domanda 2 | default; ✔ cassa a noleggio per il 2027 decisa il 16/10/2026 | 1.1, 4, 11 |
| D30 | **Casse nel 2027: un account dedicato** con password e ruolo solo sull'area Cassa, già collegato sui tablet. Nessun terzo sistema di accesso. L'accesso per dispositivo con QR resta in traccia C. | ADR-008 B (8b) | ✔ confermata (16/10/2026) | 4.5, 11 |
| D31 | **Cassa offline** decisa a **ottobre 2027** sui dati raccolti durante gli eventi piccoli in cui si prova la cassa di Fieste (D29). La strumentazione va attivata prima del primo di questi eventi. | ADR-008 B | default | 3.2, 4.5, §9 |
| D32 | **Fiscalità:** Fieste **non emette documenti fiscali**: è una cassa gestionale, che per chi deve certificare lavora affiancata a un registratore telematico o alla procedura web dell'Agenzia. Le comande sono marcate "documento non fiscale". La ricerca del 16/10/2026 (`docs/review/04-fiscalita-cassa.md`) ha trovato che **le Pro Loco hanno perso il regime 398/91 dal 2026**. Consulto con un commercialista **entro novembre 2026** con le domande di quel documento. L'organizzazione pilota è una **ODV iscritta al RUNTS**: se è nel regime forfettario dell'art. 86 e resta sotto 85.000 € di proventi commerciali, secondo le fonti non deve emettere scontrini fiscali (domanda 2 del documento, da confermare). | ADR-008 | ⚠ azione del maintainer | 4.4, §9 |
| D33 | **Un evento con ordini, forniture o allegati non si cancella.** | nuova | default | 1.3 |
| D34 | **Un solo percorso di cancellazione delle persone**, cioè l'anonimizzazione, con indici unici parziali `WHERE deleted_at IS NULL`. | ADR-010 A, ADR-004 | default | 1.4 |
| D35 | **Errori di dominio come errori di validazione** in italiano (redirect con errore), mai un 500 né un 422. | ADR-002 p.4, corretta | default | 1.2, 1.3, 1.4 |
| D36 | **Fuso orario.** La connessione PostgreSQL usa `UTC` in modo esplicito: è il comportamento attuale di produzione, da confermare con la query 6 di S.1. Gli orari dei turni sono **l'ora locale dell'evento**. In lettura, un cast comune reinterpreta i timestampTz come ora locale. Finché il cast non c'è, niente confronti in PHP sui timestampTz. | nuova | default | S.1, 0.1, 1.8 |
| D37 | **Infrastruttura:** volume Docker per gli allegati (S3 rinviato); runtime PHP 8.4; immagine costruita in CI e pubblicata su GHCR (pubblica, amd64); deploy per tag con le migrazioni come passo separato; freeze calcolato dalle fasi di servizio dei tenant. | ADR-011 | default | S.3, 2.1, 2.2a, 2.2b, 2.3 |
| D38 | **Associazioni esterne solo su una VPS in UE.** Il tunnel domestico serve solo per sviluppo e demo. Il trasloco conserva dominio, `APP_KEY` e chiavi VAPID. Oggi la produzione gira su un PC di casa: lo step 8.1 è consigliato prima di maggio anche per la sola sagra del maintainer (se cadono la connessione o il PC di casa, la cassa si ferma), ed è obbligatorio prima della prima associazione esterna. | ADR-011, domanda 14 | default | 8.1 |
| D39 | **Conservazione fino alla richiesta di cancellazione**: lo storico resta finché la persona non chiede di essere cancellata, e la cancellazione la anonimizza (step 1.4). In più, **chi non ha attività da 5 anni viene anonimizzato**, con un report in dry-run prima dell'esecuzione. Si fa dopo la stagione. | ADR-010, domanda 11 | ✔ scelta del maintainer (16/10/2026), 5 anni confermati | §9 |
| D40 | **Mercato solo Italia:** `Europe/Rome`, normalizzazione dei telefoni `+39`, niente i18n del frontend. | domanda 16 | default | §9 |
| D41 | **Regole di scrittura del codice e dei dati:** niente `try/catch` su errori DB dentro una transazione (si usano `createOrFirst`, savepoint o `ON CONFLICT`); ogni migrazione di dati è preceduta da un comando di controllo con `--dry-run` eseguito in produzione e provata su un dump. | nuova | default | 1.4, 4.1, 5.2, 6, §9 |
| D42 | **Open core:** non blocca nulla. Per ora: test architetturale e flag; il registro dei moduli è rinviato al secondo chiamante o alla risposta sul modello open core. | ADR-006 B, ridotta | default | 0.6, 5.4, §9 |

### Piano D19 — implementazione (sessione dedicata)

Refactor corposo, da fare a contesto pieno. Traccia di massima:

1. **Modello dati**: `people` diventa la tabella identità unica → aggiungere `password` (nullable), `email` già presente, e ruoli. Ricondurre `users` (organizzatori) a `people` con ruolo organizzatore, oppure retire di `users`. Migrazione dati semplice (siamo in test).
2. **Ruoli/permessi**: ruolo a livello persona (organizzatore, responsabile-scoped-area, volontario); `person_roles` già modella lo scoping per area — estenderlo/riusarlo.
3. **Auth**: una guardia sola. Login con password per chi ce l'ha; magic link per chi non ce l'ha. Rivedere `bootstrap/app.php` (redirect ospiti), `config/auth.php` (unificare guard `web`/`volunteer`), controller Auth.
4. **Invito responsabile**: endpoint che crea l'account (email obbligatoria) e genera un **link di set-password**, condivisibile via email / WhatsApp / copia (dialog come per il magic link).
5. **Viste**: i responsabili usano le **pagine scoped** già estratte (Panoramica/Calendario/Persone/gestione area), non più `/me`. Chiunque (anche organizzatore/responsabile) può avere "i miei turni" e iscriversi.
6. **Iscrizione ai turni universale**: `shift_signups` riferisce l'identità unica → ogni ruolo può iscriversi.
7. **Test**: coprire login-con-password, magic-link-per-volontari, invito responsabile, iscrizione ai turni per ogni ruolo, scoping responsabile.

#### D19 — stato: **implementato**

Realizzato come da piano, con questi affinamenti (fonte di verità = codice):

- **Identità**: `people` è l'unica tabella persona; `password`,
  `email_verified_at`, `is_organizer` aggiunti; `users` ritirata con
  migrazione dati (organizzatori → `people`, `is_organizer = true`).
- **Ruoli**: l'organizzatore è un **flag tenant-wide** (`is_organizer`), non
  un `person_role` — l'organizzatore è trasversale a tutte le edizioni e
  precede qualsiasi evento, quindi non stava bene in `person_roles`
  (event-scoped). `person_roles` resta per il solo **responsabile d'area**
  (scoped ad area). L'enum `Role` ora ha solo `AreaManager`.
- **Auth**: una sola guardia `web` sul provider `people`; guardia
  `volunteer` rimossa. Password per chi ce l'ha, magic link per chi no.
  Broker password su `people`. Middleware `organizer` a protezione dell'area
  di gestione (le rotte `auth` sono aperte a chiunque, il **ruolo** decide).
  Login role-aware: organizzatore → dashboard, altri → `/me`. *(Dopo D20 i
  responsabili atterrano su `/manage/overview`.)*
- **Invito account**: `POST people/{person}/account-invite` genera un link
  di set-password (broker `people`), condivisibile via email/WhatsApp/copia
  come il magic link. L'email è obbligatoria (chiave dell'account).
- **`assigned_by`** ora riferisce `people` (non più `users`).
- **Iscrizione universale**: le rotte di iscrizione sono sotto `auth`, quindi
  ogni ruolo (organizzatore incluso) può iscriversi ai turni.
- **Roster**: la pagina Persone esclude gli organizzatori (tengono l'account
  ma non sono nel roster che amministrano); riga roster con `hasAccount`.
- **Responsabili con password: ancora aperto.** Il codice permette ancora la
  delega di D18: un responsabile si nomina anche con il solo nome, senza
  email (`AreaManagerController::store`), riceve link magici come chiunque
  (`PersonController::magicLink`) e gestisce l'area con le azioni sotto
  `/me`, che chiedono solo `auth` (lo scope sulle aree è nei controller).
  Anche «magic link per chi no» non è esclusivo: il recupero da `/recover`
  (`RecoveryController`) manda un link magico a chiunque abbia quell'email,
  password o ruolo compresi, e da «password dimenticata» chiunque abbia
  un'email può darsi una password. D26 conferma l'obiettivo (chi ha un ruolo
  o una password non riceve link magici). Lo step 1.9 della roadmap chiude la
  nomina e i link generati dal gestore (`PersonController::magicLink`,
  `ManagerPeopleController`); il recupero da `/recover` non è tra i suoi
  punti.

Punto aperto (non nel MVP di D19): al passo 5 i responsabili usano ancora la
pagina `/me` scoped (toolkit responsabile), non pagine dedicate separate.
**Chiuso da D20**: responsabili e volontari sono ora nel guscio con sidebar;
i menu di organizzatore e responsabile coincidono (scope diverso).

### Piano D20 — implementazione (per fasi)

1. **Evento corrente**: id in sessione (default: edizione più vicina a oggi,
   D15), esposto come prop Inertia condivisa (come `role`); selettore in alto
   (nascosto con un solo evento; per i responsabili solo gli eventi in cui
   hanno un ruolo).
2. **Scoping**: Panoramica, Calendario e Gestione turni filtrano per evento
   corrente (il responsabile resta scoped anche alle sue aree).
3. **Split turni**: nuova **Gestione turni** che assorbe la gestione oggi
   sparsa in `Events/Show` e nelle tab-area di `/me`; **Prenotazione turni**
   = l'attuale `/me` partecipativo, reso identico per ogni ruolo.
4. **Menu**: separatore tra voci evento-scoped e cross-evento (Volontari,
   Eventi); riordino.

## 3. Glossario ed entità di dominio

**Evento** — Una singola edizione di sagra/festa. È composto da una
sequenza di **Fasi** ed è il contenitore di tutto il resto.

**Fase** — Periodo tipizzato dell'evento: `preparazione`, `fruizione`
(apertura al pubblico) o `smaltimento`. Ogni fase ha un intervallo di date;
i tipi possono ripetersi (es. sagra su due weekend = due fasi di
fruizione). I tipi di fase sono un insieme chiuso definito dal sistema.
La fase di un turno è **derivata** dalla sua data, mai dichiarata a mano.

**Area** — Unità organizzativa dell'evento: cucina, bar, cassa, pulizie,
parcheggi, montaggio... Risponde alla domanda "*dove* si lavora". Le aree
sono definite liberamente da chi configura l'evento. Un'area può esistere
senza alcun modulo associato (es. pulizie: solo turni). *(Con D21 e D24
«solo turni» vuol dire solo il modulo Turni.)*

**Turno** — Fascia oraria su una specifica area, con un fabbisogno di
persone (es. "cucina, sabato 18–22, servono 6 persone"). I turni
appartengono al core e si agganciano sempre a un'area. *(Superato da D21 e
D24: i turni non sono del core ma del modulo Turni, il modulo di base,
spegnibile per evento; vedi §8.)*

**Persona** — Anagrafica di un volontario o organizzatore. Una persona può
avere ruoli diversi (vedi Ruolo) e disponibilità dichiarate sui turni.

**Disponibilità** — Dichiarazione di una persona di poter coprire un turno.
Distinta dall'**Assegnazione**, che è la conferma (automatica o del
responsabile) che quella persona copre quel turno.

**Ruolo / Permesso** — Cosa può fare una persona: organizzatore evento,
responsabile d'area, volontario semplice. I permessi possono essere
limitati a una o più aree. *(Con D19 l'organizzatore non è un ruolo per
evento ma un flag della persona valido per tutta l'associazione,
`people.is_organizer`; il ruolo per area, in `person_roles`, è solo quello
di responsabile.)*

**Modulo** — Pacchetto funzionale verticale, abilitabile per evento.
Risponde alla domanda "*cosa* sai fare". Esempi: cucina/comande, bar,
magazzino, prenotazioni tavoli, pubblicità/sponsor, contabilità.

**Binding modulo→area** — Per i soli moduli che operano su aree (es.
comande → cucina, bar), la configurazione di quali aree il modulo copre. I
moduli trasversali (pubblicità, contabilità) operano a livello evento e
non hanno binding. *(Superato dal codice: non esiste una configurazione di
binding. Le entità dei moduli portano direttamente l'area, ed eventualmente
il sotto-reparto: `products.area_id`, `order_items.area_id`,
`tills.area_id`, `supplies.area_id`.)*

**Notifica** — Messaggio del sistema verso una persona (promemoria turno,
richiesta sostituzione, turno scoperto). Servizio del core, usato anche
dai moduli.

### Relazioni in sintesi

```
Evento 1—N Fase           (tipizzate: preparazione / fruizione / smaltimento)
Evento 1—N Area
Area   1—N Turno          (la fase del turno è derivata dalla data)
Turno  N—M Persona        (via Disponibilità / Assegnazione)
Evento N—M Modulo         (moduli abilitati: events.enabled_modules)
Modulo N—M Area           (binding: superato, vedi glossario)
Persona N—M Ruolo         (eventualmente scoped per area)
```

## 4. Architettura: core e moduli

**Core (sempre attivo):** anagrafica persone, eventi e aree, turni
(disponibilità, assegnazioni, sostituzioni, copertura), ruoli e permessi,
notifiche, API (in futuro esposta come tool MCP). *(Superato in due punti:
i turni sono un modulo, D21 e D24; l'API non esiste e D23 la sostituisce con
i casi d'uso, superando anche la regola 4 «API-first» del `CLAUDE.md`
originale. Il kernel di oggi è in §8.)*

Regola fondante: **il core non sa cosa si fa nelle aree**. Sa solo che
esistono, che hanno turni e persone assegnate. *(Con i turni nel modulo
Turni, D21 e D24, il core deve sapere solo che le aree esistono e chi ne è
responsabile. Oggi `Area::shifts()`, `Person::signups()`,
`Person::anonymize()` e la replica dell'evento conoscono ancora i turni:
sono violazioni note, che lo step 0.6 mette nella whitelist del test di
confine, D42.)*

**Moduli verticali (abilitabili):** ognuno parla solo con il core, mai con
altri moduli. Candidati, in ordine indicativo di priorità futura:
comande/cucina e bar, magazzino e acquisti, prenotazioni tavoli,
contabilità e rendicontazione (attenzione: fiscalità italiana — 
corrispettivi, enti del terzo settore), pubblicità/sponsor, comunicazione.

### Flusso di configurazione evento

1. Crea l'evento (nome, date).
2. Definisci le aree → da qui nasce la griglia dei turni.
3. (Opzionale) Abilita i moduli; quelli ad area chiedono il binding.
   *(Nel codice i moduli si accendono dalla pagina dell'evento e un evento
   nuovo nasce con i soli Turni (D21). Il binding non c'è: l'area si sceglie
   su ogni prodotto, cassa o fornitura.)*

Un'associazione minimale si ferma al passo 2 e ha già un gestore turni
completo.

## 5. MVP — Modulo volontari e turni (= il core)

*(«= il core» valeva per l'MVP: con D21 e D24 i turni sono il modulo di
base.)*

Contesto rilevato: oggi i turni si gestiscono con fogli Google, con un
collo di bottiglia umano (una persona che raccoglie disponibilità via
WhatsApp e rincorre i buchi).

**Dentro l'MVP:**
- L'organizzatore configura l'evento (fasi, aree, responsabili);
  l'organizzatore e i responsabili definiscono i turni con il fabbisogno
  di persone, ciascun responsabile limitato alle proprie aree (D18–D20).
  *(La formulazione originale "il responsabile definisce aree e turni"
  è superata: la definizione delle aree è passata all'organizzatore.)*
- I volontari accedono da smartphone con link magico (no password),
  dichiarano disponibilità e vedono i propri turni.
- Auto-registrazione via link d'invito dell'associazione (D16): il
  gestore condivide un solo link, i volontari si iscrivono da soli.
- Vista copertura in tempo reale: turni scoperti evidenziati.
- Richiesta e gestione sostituzioni.
- Promemoria e notifiche automatiche.

**Fuori dall'MVP (esplicitamente):** tutti i moduli verticali, AI/agenti,
pagamenti, qualsiasi integrazione hardware, la duplicazione di edizioni
precedenti ("replica la sagra dell'anno scorso": copia aree e responsabili,
rimappa i turni sulle nuove date in modo relativo, mai le disponibilità).

*(L'MVP è completato. Dopo sono arrivati i moduli Forniture e Ordini/Cassa
(§8) e la replica delle edizioni, che oggi copia fasi, aree, responsabili,
turni e moduli attivi, ma non sotto-reparti, listino e casse: la completa lo
step 5.4 della roadmap.)*

## 6. Domande aperte

- Modello di prezzo SaaS (per evento? per associazione? freemium?).
- Privacy/GDPR: l'anagrafica volontari è dato personale; titolarità del
  trattamento in capo all'associazione, da gestire nel design. *(In parte
  deciso: cancellazione con D34, conservazione con D39; informativa, accordo
  art. 28 e accettazione per le associazioni esterne sono lo step 8 della
  roadmap.)*
- Canali notifiche oltre il push web (e-mail di servizio? Telegram?
  WhatsApp Business API più avanti?).

**Chiuse:**
- Nome del progetto: **Fieste** (impostato come `APP_NAME`, da cui derivano
  brand in-app, `<title>` e manifest PWA).
- Conservazione delle edizioni passate (disponibilità e assegnazioni restano
  consultabili per anno, D15, ma sono dati personali): decisa con D39, lo
  storico resta finché la persona non chiede la cancellazione, e chi non ha
  attività da 5 anni viene anonimizzato.

## 7. Catalogo delle aree tipiche

Principio: il sistema non assume una sagra specifica. Le aree sono libere,
ma in fase di setup viene proposto un **catalogo di template** da cui
scegliere e personalizzare, raggruppato per natura:

| Famiglia | Aree tipiche | Note |
|----------|--------------|------|
| Somministrazione | Cucina, griglia/friggitoria, bar, cassa, servizio ai tavoli | Famiglia più ricca di futuri moduli verticali (comande, listini, prenotazioni) |
| Logistica | Montaggio, smontaggio, pulizie, magazzino, parcheggi/viabilità | Montaggio e smontaggio operano nelle fasi di preparazione e smaltimento |
| Intrattenimento | Musica/spettacoli, animazione, lotteria/pesca di beneficenza | Possibili adempimenti (es. SIAE) → candidati per moduli dedicati |
| Supporto | Segreteria, sicurezza/primo soccorso, comunicazione | Spesso trasversali, ma con turni propri |

Implicazione di design — **l'evento è una sequenza di fasi**: preparazione,
fruizione (anche ripetuta, es. sagra su più weekend), smaltimento. I turni
vivono nelle fasi (montaggio in preparazione, servizio in fruizione...), e
la fase di ciascun turno è derivata automaticamente dalla data. Le fasi
abilitano viste contestuali ("siamo in preparazione"), moduli attivi per
fase e, in futuro, contesto per gli agenti AI.

Il core tratta tutte le aree allo stesso modo (turni + persone); le
famiglie servono per il setup guidato e come mappa dei futuri moduli.
*(Con D21 e D24 i turni non sono del core ma del modulo Turni: delle aree
il core sa solo che esistono e chi ne è responsabile, vedi la regola
fondante al §4.)*

## 8. Mappa dei moduli (post-MVP)

Pianificazione, non ancora impegno. Serve a scegliere l'ordine di
costruzione e a tenere pulito il confine core/moduli (D2, D3, D21).

### Kernel (sempre attivo, mai un modulo)

Identità persone, eventi, fasi, **aree e sotto-reparti**, ruoli/permessi,
notifiche, API, e un **layer di aggregazione/metriche** su cui i moduli
spingono i propri numeri per il reporting. Tutto ciò che *ogni*
installazione ha e che più moduli riferiscono. *(Superato in due punti:
l'API non esiste e D23 la sostituisce con i casi d'uso; il layer di
aggregazione non esiste e non si costruisce in anticipo, perché Statistiche
sarà pull, vedi «Ordine di costruzione consigliato». Lo stesso vale per le
dipendenze «layer metriche» della tabella qui sotto.)*

### Moduli (peer, attivabili per evento, isolati — solo core, mai tra loro)

| Modulo | Responsabilità | Dipendenze dal core | Rischi / note |
|--------|----------------|---------------------|----------------|
| **Turni** | Programmazione e prenotazione turni (l'MVP) | persone, aree, notifiche | Fatto. È il primo modulo, sempre attivo per ora. *(Superato: è il modulo di base, D24. Un evento nuovo nasce con i soli Turni e l'organizzatore può spegnerli come gli altri moduli; cosa nasconde oggi lo spegnimento è nella nota a D21.)* |
| **Ordini / Cassa** | POS (tablet/telefono), pagamenti, **comande** verso la cucina con schermi per sotto-reparto (KDS). Comande e cassa sono due facce dello stesso "ordine" → **un modulo solo**, non due che si coordinano | aree + **sotto-reparti**, layer metriche | ⚠️ **Fiscalità italiana** (corrispettivi, scontrino elettronico, ETS/terzo settore): la parte più regolamentata. **Slice A (fatto)**: listino per evento (prodotti, prezzo, area/sotto-reparto), POS con carrello e totale, contante + "segna pagato", numero ordine progressivo per evento, righe con snapshot del prodotto. Listino = organizzatore, cassa = staff. **Punti cassa (fatto)**: la *cassa* è un'entità che **appartiene a un'area** — così regge sia la sagra con un reparto "Cassa" dedicato (il suo responsabile configura tutto, e ha turni propri come ogni area) sia quella dove **ogni reparto si autogestisce** la propria; chi configura una cassa è il responsabile della sua area (o l'organizzatore), riusando `managesArea()` senza nuovi concetti di permesso. Più casse possono avere **menù diversi**; menù vuoto = vende tutto il listino, così chi ha una cassa sola non configura niente. Due assi ortogonali: `prodotto.area` = *chi prepara* (instrada le comande), `cassa.area` = *chi vende e configura*. I **prodotti** restano dell'organizzatore. **Slice B (fatto)**: comande/KDS — ogni voce d'ordine ha uno stato (`pending → ready → served`) e la pagina Comande è uno schermo per area, restringibile a un sotto-reparto, con auto-refresh; le voci senza area nascono già servite. **Poi**: pagamenti elettronici (SumUp/Satispay/Nexi) e scontrino. *(Precisata da D29: contatore con lock e idempotenza, numero solo agli ordini con piatti da preparare, un tagliando per piatto; oggi ogni ordine prende il massimo + 1. Lo «scontrino» è superato da D32: Fieste non emette documenti fiscali e, per chi deve certificare, lavora affiancata a un registratore telematico o alla procedura web dell'Agenzia. Alla sagra 2027 resta la cassa a noleggio.)* |
| **Forniture** | Contatti fornitori per reparto/sotto-reparto, acquisto/noleggio/prestito consumabili e attrezzature, storico con upload fatture/note | aree + sotto-reparti, storage documenti | Basso rischio, valore chiaro. **Primo modulo "vero" dopo i turni — in sviluppo.** Slice 1 (fatto): anagrafica fornitori tenant-level + forniture per evento/area/sotto-reparto (tipo, costo, data, note), accesso organizzatore + responsabili scoped. **Fase 2 (fatta)**: allegati (fatture/note) su disco privato `local`, download autorizzato con lo stesso scope della fornitura, cleanup dei file su cancellazione. Struttura Laravel standard, nessuna cartella `Modules/` (D13) |
| **Statistiche** | Somme e confronti tra edizioni/eventi | layer di aggregazione del core | Legge il core, non gli altri moduli (D3). Facile e utile presto |
| **Contabilità fiscale** | Rendicontazione regolamentata | layer metriche | Pesante e regolamentata (ETS). Molto dopo. Distinta da "Statistiche" |

**Candidati ulteriori:** Comunicazione/bacheca (annunci ai volontari, info
evento), Prenotazioni tavoli (customer-facing), Magazzino/inventario
(scorte *durante* l'evento, distinto da Forniture), Lotteria/pesca
(possibile SIAE), Sponsor/pubblicità, Ordini online/asporto
(customer-facing).

### Ordine di costruzione consigliato

**Forniture** per primo (fatto), leggero e a basso rischio, per rodare
l'infrastruttura moduli. Poi **Ordini/Cassa** (il grosso, con il nodo
fiscale), che è anche ciò che produce i numeri interessanti. **Statistiche
per ultimo**: aggrega ciò che gli altri moduli generano, quindi ha senso
solo quando quel dato esiste (D13). Sarà **pull, non push** — legge le
tabelle dei moduli *attraverso il core* al momento del report; niente
"layer di push" da instrumentare in anticipo. Se servirà pre-aggregare per
performance, si aggiunge allora.

### Nodi strutturali del kernel

- **Sotto-reparti** (cucina → griglia/friggitoria/primi): estensione del
  modello aree (tabella `sub_areas`, Opzione 1: l'area resta l'unità
  "pesante", il sotto-reparto è una suddivisione leggera). **Implementati**:
  il modulo Turni li consuma già (un turno può appartenere a un
  sotto-reparto; senza sotto-reparti tutto resta a livello area). Li
  useranno anche Ordini/Cassa (schermi comande) e Forniture.
- **Attore "cliente/avventore"**: oggi il sistema conosce solo
  volontari/organizzatori. Ordini online e Prenotazioni tavoli lo
  introducono. Decisione rimandata al giorno in cui si sviluppa il modulo
  **Ordini online** (il caso d'uso più adatto a definirlo).
