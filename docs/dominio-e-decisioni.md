# Fieste — gestionale modulare per sagre ed eventi con volontari

> Documento di dominio e decisioni. Versione 0.1 — giugno 2026.
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
| D2 | Architettura a due piani: core + moduli verticali | I turni e le persone sono trasversali per natura; i verticali restano isolati |
| D3 | I moduli non comunicano tra loro, solo con il core | Mantiene l'isolamento e rende i moduli abilitabili singolarmente |
| D4 | MVP = core con gestione volontari e turni | Minor rischio tecnico/normativo, tocca tutti i volontari, valida l'idea |
| D5 | AI/MCP rimandati a dopo l'MVP, ma API dei moduli progettate per essere esposte come tool MCP | L'AI ragiona bene solo su un dominio dati solido |
| D6 | Accesso volontari senza password (link magico, es. via WhatsApp) | Abbattere la barriera d'ingresso per utenti poco tecnologici |
| D7 | Documentazione in Markdown nel repo, referenziata da CLAUDE.md | Permette il riuso del contesto tra claude.ai e Claude Code |
| D8 | Stack: Laravel + Inertia + Vue 3, monolite modulare | Sviluppatore solo con anni di esperienza Laravel/Vue; ecosistema ricco per SaaS (auth magic link, notifiche, code, Cashier, Filament) |
| D9 | PostgreSQL singolo, tenancy a `tenant_id` | Semplicità operativa; isolamento per schema/database è complessità prematura |
| D10 | Frontend PWA mobile-first; notifiche base via push web | I volontari usano lo smartphone; WhatsApp Business API rimandata per costi e vincoli |
| D11 | Deploy: VPS economico + Docker; stessa immagine per SaaS e self-hosting | Costi minimi; l'immagine Docker è anche il prodotto open source installabile |
| D12 | Futuro livello AI/MCP come servizio Python separato che consuma l'API del core | Usa ogni linguaggio dove rende; valida i confini dell'architettura |
| D13 | Per l'MVP il core usa la struttura Laravel standard (`app/Models`, `app/Enums`...); niente cartella `Modules/` né convenzioni di confine finché non esiste il primo modulo verticale | Evita architettura speculativa; il confine core/moduli si disegna sul primo caso reale |
| D14 | Schema dati del core come da `docs/schema-core.md`: `tenant_id` su ogni tabella, disponibilità+assegnazione in un'unica `shift_signups` con stato, volontari in `people` distinti dagli `users` con password | Vedi motivazioni nel documento di schema |
| D15 | L'anno di un'edizione è **derivato** dalle date delle fasi, mai dichiarato; le viste di gestione sono filtrate per anno (default: l'anno più vicino a oggi); niente entità "manifestazione" che raggruppa le edizioni finché un caso d'uso concreto non la richiede | Stesso principio della fase derivata dalla data; la replica delle edizioni (post-MVP) avverrà per duplicazione di un evento esistente |
| D16 | Auto-registrazione volontari via **link d'invito** del tenant (condiviso una volta: gruppo WhatsApp, QR), rigenerabile; basta il nome, contatto facoltativo da completare poi (abilita promemoria e recupero accesso self-service) | Elimina il collo di bottiglia "il gestore crea ogni persona e invia ogni link" senza reintrodurre password (conferma D6) |
| D17 | Link magici personali **monouso** con scadenza breve (7 giorni); la permanenza è data dalla sessione remember lunga (~1 anno); rigenerare il link revoca link e sessioni remember (kill switch); su un dispositivo già collegato a un'altra persona il link **chiede** ("continua come X / entra come Y"), mai blocca o scambia in silenzio | Il link viaggia su WhatsApp e può essere inoltrato; il telefono condiviso in famiglia è un caso legittimo e frequente |
| D18 | Gerarchia organizzatore → responsabili d'area → volontari con **delega facoltativa**: l'organizzatore può tutto, il responsabile gestisce turni e volontari del proprio reparto (via link magico, senza account); appartenenza dei volontari ai reparti **morbida e derivata dalla storia delle iscrizioni** (niente da dichiarare o amministrare: prima i turni dei reparti dove hai già lavorato, i buchi altrui restano visibili); sovrapposizioni di turno permesse ma **segnalate** (al volontario e al responsabile che conferma) | Rispecchia l'organizzazione reale delle sagre; la divisione rigida sprecherebbe la flessibilità dei volontari, che è la risorsa principale |
| D19 | **Identità unica con ruoli ortogonali** (rivede D14 e D18). Una sola entità persona, non più lo split `users`/`people`: password **facoltativa**, ruoli separati dall'identità (organizzatore / responsabile d'area / volontario). **Chiunque può iscriversi ai turni**, a prescindere dal ruolo. Volontari semplici: accesso solo con **link magico** (D6/D17). Responsabili e organizzatori: **account con password** (email + password). Il responsabile **non entra più via link magico** (supera la delega senza account di D18). Invito account (organizzatore → responsabile) con **link a scelta del canale**: email, WhatsApp o copia-incolla (riusa il pattern di condivisione dei link già presente) | Lo split a due tabelle era la radice dell'attrito (responsabile "a metà" tra i due mondi, bug cross-guard). Un'unica identità con password opzionale rispecchia la realtà — è la stessa persona con ruoli diversi — e semplifica auth, sessioni e viste |
| D20 | **Un guscio, contesto "evento corrente", turni divisi per responsabilità.** Navigazione unica (sidebar) uguale per organizzatore e responsabile; le voci cambiano solo lo *scope*. Un **selettore di evento corrente** in alto (tenuto in sessione, default = edizione più vicina a oggi per D15; per i responsabili mostra solo gli eventi in cui hanno un ruolo; nascosto/passivo con un solo evento). Voci **evento-scoped**: Panoramica, Calendario, Gestione turni, Prenotazione turni. Voci **cross-evento** (separatore, in basso): Volontari, Eventi (solo organizzatore, dove si definiscono eventi → aree → responsabili). **Split dei turni per responsabilità** (Opzione 1): *Gestione turni* (org/responsabile, scoped alle aree gestite) crea/configura turni, vede le disponibilità e **assegna/modera**; *Prenotazione turni* (**chiunque, identica per ruolo**) dà disponibilità ("ci sono"), recap dei propri impegni, sostituzioni | Separa due compiti mentali diversi ("allestire il tabellone" vs "prenotarsi per lavorare"); l'assegnazione è una **decisione di gestione**, coerente con la distinzione Disponibilità/Assegnazione del glossario; tiene *Prenotazione* universale e semplice (D19/D5), senza rami per ruolo; l'evento corrente rende esplicito il filtro per edizione già previsto (D15) |
| D21 | **I turni sono un modulo, non una capacità speciale del core.** Due piani netti: (1) **kernel** sempre attivo — identità persone, eventi, fasi, **aree + sotto-reparti**, ruoli, notifiche, API, layer di **aggregazione/metriche**; (2) **moduli peer, attivabili per evento e isolati** (turni, ordini/cassa, forniture, statistiche…), che parlano **solo col core** (D3), tutti spegnibili allo stesso modo. Nessun modulo *operativo* ha bisogno di sapere "chi è di turno" (comande instrada a un sotto-reparto/schermo, la cassa al dispositivo/area), quindi i turni **non sono un substrato condiviso**: sono un modulo come gli altri. L'unico caso cross-cutting è **statistiche/contabilità**, che legge il layer di aggregazione del core su cui ogni modulo spinge i propri numeri (modulo→core, **mai** modulo→modulo). Per l'MVP i turni sono l'unico modulo e restano sempre attivi. *(Sostituisce la prima stesura di D21, che li trattava come "capacità orizzontale del core": sovradimensionata, perché nessuno li consuma.)* | Semplicità e simmetria: se nessun modulo consuma i turni, farne una capacità core speciale era architettura per un bisogno inesistente. La linea vera è **kernel** ("chi/dove/quando" + servizi condivisi) vs **moduli** ("cosa ci fai"), spegnibili tutti uguali — coerente con D13 (il confine si disegna sul caso reale) |

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
  Login role-aware: organizzatore → dashboard, altri → `/me`.
- **Invito account**: `POST people/{person}/account-invite` genera un link
  di set-password (broker `people`), condivisibile via email/WhatsApp/copia
  come il magic link. L'email è obbligatoria (chiave dell'account).
- **`assigned_by`** ora riferisce `people` (non più `users`).
- **Iscrizione universale**: le rotte di iscrizione sono sotto `auth`, quindi
  ogni ruolo (organizzatore incluso) può iscriversi ai turni.
- **Roster**: la pagina Persone esclude gli organizzatori (tengono l'account
  ma non sono nel roster che amministrano); riga roster con `hasAccount`.

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
senza alcun modulo associato (es. pulizie: solo turni).

**Turno** — Fascia oraria su una specifica area, con un fabbisogno di
persone (es. "cucina, sabato 18–22, servono 6 persone"). I turni
appartengono al core e si agganciano sempre a un'area.

**Persona** — Anagrafica di un volontario o organizzatore. Una persona può
avere ruoli diversi (vedi Ruolo) e disponibilità dichiarate sui turni.

**Disponibilità** — Dichiarazione di una persona di poter coprire un turno.
Distinta dall'**Assegnazione**, che è la conferma (automatica o del
responsabile) che quella persona copre quel turno.

**Ruolo / Permesso** — Cosa può fare una persona: organizzatore evento,
responsabile d'area, volontario semplice. I permessi possono essere
limitati a una o più aree.

**Modulo** — Pacchetto funzionale verticale, abilitabile per evento.
Risponde alla domanda "*cosa* sai fare". Esempi: cucina/comande, bar,
magazzino, prenotazioni tavoli, pubblicità/sponsor, contabilità.

**Binding modulo→area** — Per i soli moduli che operano su aree (es.
comande → cucina, bar), la configurazione di quali aree il modulo copre. I
moduli trasversali (pubblicità, contabilità) operano a livello evento e
non hanno binding.

**Notifica** — Messaggio del sistema verso una persona (promemoria turno,
richiesta sostituzione, turno scoperto). Servizio del core, usato anche
dai moduli.

### Relazioni in sintesi

```
Evento 1—N Fase           (tipizzate: preparazione / fruizione / smaltimento)
Evento 1—N Area
Area   1—N Turno          (la fase del turno è derivata dalla data)
Turno  N—M Persona        (via Disponibilità / Assegnazione)
Evento N—M Modulo         (moduli abilitati)
Modulo N—M Area           (binding, solo per moduli ad area)
Persona N—M Ruolo         (eventualmente scoped per area)
```

## 4. Architettura: core e moduli

**Core (sempre attivo):** anagrafica persone, eventi e aree, turni
(disponibilità, assegnazioni, sostituzioni, copertura), ruoli e permessi,
notifiche, API (in futuro esposta come tool MCP).

Regola fondante: **il core non sa cosa si fa nelle aree**. Sa solo che
esistono, che hanno turni e persone assegnate.

**Moduli verticali (abilitabili):** ognuno parla solo con il core, mai con
altri moduli. Candidati, in ordine indicativo di priorità futura:
comande/cucina e bar, magazzino e acquisti, prenotazioni tavoli,
contabilità e rendicontazione (attenzione: fiscalità italiana — 
corrispettivi, enti del terzo settore), pubblicità/sponsor, comunicazione.

### Flusso di configurazione evento

1. Crea l'evento (nome, date).
2. Definisci le aree → da qui nasce la griglia dei turni.
3. (Opzionale) Abilita i moduli; quelli ad area chiedono il binding.

Un'associazione minimale si ferma al passo 2 e ha già un gestore turni
completo.

## 5. MVP — Modulo volontari e turni (= il core)

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

## 6. Domande aperte

- Modello di prezzo SaaS (per evento? per associazione? freemium?).
- Privacy/GDPR: l'anagrafica volontari è dato personale; titolarità del
  trattamento in capo all'associazione, da gestire nel design.
- Conservazione delle edizioni passate: disponibilità e assegnazioni
  restano consultabili per anno (D15), ma sono dati personali — serve una
  politica di retention (es. dopo N anni anonimizzare le iscrizioni
  conservando i numeri aggregati).
- Canali notifiche oltre il push web (e-mail di servizio? Telegram?
  WhatsApp Business API più avanti?).

**Chiuse:**
- Nome del progetto: **Fieste** (impostato come `APP_NAME`, da cui derivano
  brand in-app, `<title>` e manifest PWA).

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

## 8. Mappa dei moduli (post-MVP)

Pianificazione, non ancora impegno. Serve a scegliere l'ordine di
costruzione e a tenere pulito il confine core/moduli (D2, D3, D21).

### Kernel (sempre attivo, mai un modulo)

Identità persone, eventi, fasi, **aree e sotto-reparti**, ruoli/permessi,
notifiche, API, e un **layer di aggregazione/metriche** su cui i moduli
spingono i propri numeri per il reporting. Tutto ciò che *ogni*
installazione ha e che più moduli riferiscono.

### Moduli (peer, attivabili per evento, isolati — solo core, mai tra loro)

| Modulo | Responsabilità | Dipendenze dal core | Rischi / note |
|--------|----------------|---------------------|----------------|
| **Turni** | Programmazione e prenotazione turni (l'MVP) | persone, aree, notifiche | Fatto. È il primo modulo, sempre attivo per ora |
| **Ordini / Cassa** | POS (tablet/telefono), pagamenti, **comande** verso la cucina con schermi per sotto-reparto (KDS). Comande e cassa sono due facce dello stesso "ordine" → **un modulo solo**, non due che si coordinano | aree + **sotto-reparti**, layer metriche | ⚠️ **Fiscalità italiana** (corrispettivi, scontrino elettronico, ETS/terzo settore): la parte più regolamentata. **Slice A (fatto)**: listino per evento (prodotti, prezzo, area/sotto-reparto), POS con carrello e totale, contante + "segna pagato", numero ordine progressivo per evento, righe con snapshot del prodotto. Listino = organizzatore, cassa = staff. **Slice B**: comande/KDS (voci ordine agli schermi cucina, stati). **Poi**: pagamenti elettronici (SumUp/Satispay/Nexi) e scontrino |
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
