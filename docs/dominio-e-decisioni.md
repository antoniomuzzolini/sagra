# Gestionale modulare per sagre ed eventi con volontari

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

### Piano D19 — implementazione (sessione dedicata)

Refactor corposo, da fare a contesto pieno. Traccia di massima:

1. **Modello dati**: `people` diventa la tabella identità unica → aggiungere `password` (nullable), `email` già presente, e ruoli. Ricondurre `users` (organizzatori) a `people` con ruolo organizzatore, oppure retire di `users`. Migrazione dati semplice (siamo in test).
2. **Ruoli/permessi**: ruolo a livello persona (organizzatore, responsabile-scoped-area, volontario); `person_roles` già modella lo scoping per area — estenderlo/riusarlo.
3. **Auth**: una guardia sola. Login con password per chi ce l'ha; magic link per chi non ce l'ha. Rivedere `bootstrap/app.php` (redirect ospiti), `config/auth.php` (unificare guard `web`/`volunteer`), controller Auth.
4. **Invito responsabile**: endpoint che crea l'account (email obbligatoria) e genera un **link di set-password**, condivisibile via email / WhatsApp / copia (dialog come per il magic link).
5. **Viste**: i responsabili usano le **pagine scoped** già estratte (Panoramica/Calendario/Persone/gestione area), non più `/me`. Chiunque (anche organizzatore/responsabile) può avere "i miei turni" e iscriversi.
6. **Iscrizione ai turni universale**: `shift_signups` riferisce l'identità unica → ogni ruolo può iscriversi.
7. **Test**: coprire login-con-password, magic-link-per-volontari, invito responsabile, iscrizione ai turni per ogni ruolo, scoping responsabile.

Ripartenza: *"continua sul progetto sagra, branch main: implementa D19 (unificazione identità)"*.

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
- Il responsabile definisce aree e turni con fabbisogno persone.
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
- Nome del progetto.

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
