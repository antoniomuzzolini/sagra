# 02 — Sintesi della revisione architetturale

> Questa sintesi parte da `01-architettura.md` (ricostruzione dal codice e
> divergenze con i documenti) e dagli ADR in `docs/review/adr/`. Ogni ADR
> confronta la decisione attuale con almeno due alternative, ne valuta i
> trade-off e chiude con una raccomandazione.
>
> **Metodo.** Ho riletto il codice per verificare le affermazioni di 01, invece
> di darle per buone. Ho anche verificato con un test temporaneo, eseguito
> fuori dal repository, un problema nuovo: l'appartenenza morbida si perde tra
> un'edizione e l'altra (ADR-007).
>
> **Cosa non ho verificato.** Non ho eseguito nulla su PostgreSQL, quindi i
> rischi di concorrenza sono ragionati, non riprodotti. Le osservazioni
> fiscali non sono consulenza.

## Indice degli ADR

| ADR | Tema | Raccomandazione |
|---|---|---|
| [001](adr/001-monolite-inertia-senza-api.md) | Monolite Inertia, niente API, regola API-first | **MANTENERE** Inertia · MODIFICARE la regola in "use-case-first" · rivedere D12 |
| [002](adr/002-logica-applicativa.md) | Dove vive la logica applicativa | **MODIFICARE** (Action selettive, eliminare il doppio percorso) |
| [003](adr/003-multi-tenancy.md) | Multi-tenancy | **MANTENERE** il modello · **MODIFICARE** l'isolamento (scope, poi RLS) |
| [004](adr/004-identita-e-accesso.md) | Identità e accesso | **MANTENERE** magic link e identità unica · **MODIFICARE** l'unicità e il login |
| [005](adr/005-autorizzazione.md) | Autorizzazione | **MODIFICARE** (Policy + oggetto `Access`, ruoli per edizione) |
| [006](adr/006-confine-core-moduli.md) | Confine core/moduli, Turni | **MANTENERE** l'attivazione · **MODIFICARE** il confine · **RIPROGETTARE** D21 sui Turni |
| [007](adr/007-aree-per-edizione.md) | Aree per edizione, evento corrente | **MODIFICARE** (reparto stabile di tenant) |
| [008](adr/008-cassa-e-comande.md) | Cassa e comande | **MODIFICARE** (idempotenza, numerazione, accesso per dispositivo); offline solo dopo una misura |
| [009](adr/009-notifiche.md) | Notifiche | **MANTENERE** l'infrastruttura · **MODIFICARE** la strategia dei canali |
| [010](adr/010-dati-personali-gdpr.md) | Dati personali | **MODIFICARE** (ciclo di vita unico, retention, export, minimizzazione) |
| [011](adr/011-infrastruttura-e-deploy.md) | Infrastruttura e deploy | **MANTENERE** la topologia · **MODIFICARE** il rilascio, lo storage e i backup |
| [012](adr/012-test-e-ci.md) | Test e CI | **MODIFICARE** (Postgres ovunque, CI bloccante) |
| [013](adr/013-frontend.md) | Frontend | **MANTENERE** · piccole modifiche (modalità postazione, props tipizzate) |

Nessun ADR raccomanda di riprogettare un pezzo di codice da zero. L'unico
RIPROGETTARE riguarda il **testo** di una decisione (D21 sui Turni), non il
codice.

---

## Cosa è fatto bene (e perché non lo toccherei)

- **Il flusso di accesso dei volontari** (magic link monouso con hash, kill
  switch, nessun dirottamento dei telefoni condivisi, link d'invito, recupero
  self-service). Nasce dagli utenti reali, cioè WhatsApp, telefoni di famiglia
  e nessuna password. È la parte più matura del progetto.
- **Identità unica con ruoli ortogonali** (D19). Elimina una classe intera di
  bug e rispecchia la realtà: la stessa persona è volontario e responsabile.
- **Date derivate, mai duplicate** (eventi dalle fasi, fase del turno dalla
  data). C'è una sola fonte di verità, e per questo le fasi non possono
  sovrapporsi.
- **Snapshot delle righe d'ordine e FK `nullOnDelete` sui riferimenti
  storici.** Modificare un prodotto non riscrive gli scontrini passati.
- **Invarianti nei modelli.** Valgono per ogni ingresso, non solo per l'HTTP.
- **Anonimizzazione al posto della cancellazione** e auto-cancellazione
  self-service.
- **Infrastruttura minima e coerente:** una VPS, un'immagine, Postgres per
  tutto, FrankenPHP. Redis o un orchestratore oggi sarebbero costo senza
  beneficio.
- **Inertia senza API.** Non è un ripiego: per uno sviluppatore solo
  eliminare un contratto vale più di un'API senza consumatori.
- **Polling per il KDS.** È più robusto dei websocket per questo contesto.
- **Test di feature scritti in termini di dominio.** Sono la ragione per cui
  tutto ciò che segue si può fare in modo incrementale.
- **I commenti spiegano il perché** e citano le decisioni. Chi legge il codice
  ricostruisce l'intento (01 ci è riuscito quasi sempre senza i documenti).

---

## Le 5 decisioni a più alto impatto

Il criterio è **gravità × probabilità × costo del ritardo**: quanto costa non
decidere adesso.

### 1. Cassa: affidabilità e accesso per dispositivo (ADR-008)

- **Perché è la prima.** È l'unico componente che gestisce soldi, lavora in
  picchi concorrenti e gira su reti incerte.
- **I problemi:**
  - la numerazione `max+1` fallisce con due casse simultanee;
  - un ordine ritentato dopo un timeout viene duplicato;
  - `paid` è modificabile da chiunque sia staff, senza traccia;
  - il volontario di turno alla cassa non può aprirla. L'unico ripiego è
    lasciare sul tablet la sessione di un responsabile, che apre anche i dati
    personali dei volontari.
- **Scadenza:** il prossimo evento con più casse.
- **Costo:** 2-4 giorni per l'irrobustimento (idempotenza, numerazione,
  token del dispositivo, tracciabilità).

### 2. Stato e rilascio in produzione (ADR-011)

- **I problemi:**
  - gli allegati (fatture) si cancellano a ogni rebuild del container;
  - non ci sono backup automatici;
  - il deploy automatico porta in produzione qualunque push su `main`, anche
    con i test rossi, costruendo l'immagine sulla VPS, potenzialmente nel
    mezzo del servizio del sabato sera.
- **Perché è la seconda:** è **perdita di dati** e **disponibilità** proprio
  nei giorni che contano.
- **Costo:** il volume per gli allegati richiede minuti; il resto 1-2 giorni.

### 3. Isolamento tra tenant e autorizzazione (ADR-003 + ADR-005)

- **Il problema:** l'isolamento tra associazioni e le regole "gestisci
  quest'area?" sono ricostruiti a mano in ogni controller. Oggi tengono per
  disciplina, con qualche crepa:
  - un responsabile qualunque può modificare gli ordini di qualsiasi
    edizione;
  - un ruolo passato dà accesso staff per sempre.
- **Costo del ritardo:** cresce con ogni modulo, e va risolto **prima** di
  aprire la registrazione SaaS a molti tenant.
- **Costo:** 4-5 giorni, che salgono a circa 1 settimana con la RLS.

### 4. Il reparto come identità stabile tra le edizioni (ADR-007)

- **Problema verificato.** Il veterano della Cucina 2025 non viene avvisato
  dei turni della Cucina 2026 e non la vede tra le sue aree. L'appartenenza
  morbida (D18) funziona solo dentro un'edizione, cioè fallisce proprio nel
  caso d'uso per cui è nata.
- **La causa è di modello:** esiste l'area dell'edizione, non il reparto
  dell'associazione.
- **Costo del ritardo:** cresce con ogni modulo che referenzia `area_id`
  (oggi sette tabelle).
- **Cosa sblocca:** confronti tra edizioni (Statistiche), catalogo e setup
  guidato (`AreaFamily`), una replica completa.
- **Costo:** 2-3 giorni.

### 5. Ambito dell'identità e login (ADR-004)

- **Il problema:** non è deciso se un'identità appartiene all'associazione o
  alla persona fisica, e il codice risponde in tre modi diversi:
  - schema: per tenant;
  - registrazione: globale;
  - login: ambiguo.
- **Effetti concreti:**
  - login non deterministico per chi ha un account in due associazioni;
  - un **500** quando si reinserisce un telefono cancellato;
  - telefoni salvati come digitati;
  - il recupero accesso via email funziona anche da kill switch involontario.
- **Costo del ritardo:** il passaggio a un modello "account globale +
  membership" diventa più costoso con ogni utente registrato. La correzione
  minima (email globale per chi ha la password, indici parziali) costa ore e
  non chiude nessuna strada.

### Decisioni di governo a costo quasi zero

Queste tre non sono tra le cinque perché non richiedono codice, ma vanno
**scritte**, perché oggi documenti e codice si contraddicono:

- **API-first** (ADR-001): abbandonata o riformulata? La propongo come
  "use-case-first".
- **D21 e i Turni** (ADR-006): modulo di base o davvero spegnibile?
- **Retention dei dati** (ADR-010): quanti anni?

---

## Refactoring incrementale o riscrittura?

**Valutazione onesta: refactoring incrementale ovunque. Nessuna parte giustifica
una riscrittura.**

Il codebase è piccolo (circa 6.300 righe PHP in `app/` e `routes/`, circa
6.700 di Vue/TS escluso `components/ui`) e ha 276 test di feature. I problemi
trovati sono di due tipi, e nessuno dei due si risolve riscrivendo:

1. **Meccanismi trasversali mancanti**, non struttura sbagliata: scope di
   tenancy, Policy, contratto dei moduli, accesso per dispositivo. Si
   aggiungono sopra il codice esistente e i test esistenti dicono se qualcosa
   si è rotto.
2. **Un punto di modellazione** (reparto stabile tra le edizioni). Si
   risolve con una colonna, una tabella e un backfill, non con un nuovo
   modello.

Una riscrittura costerebbe mesi di prodotto fermo per uno sviluppatore solo. E
ripartirebbe da zero proprio sulle cose fatte meglio: flusso d'accesso, date
derivate, snapshot, test.

Valutazione per parte:

| Parte | Verdetto | Motivo |
|---|---|---|
| Modello dati del core (persone, eventi, fasi, turni, iscrizioni) | Mantenere, con aggiunte | Buono; manca solo il reparto stabile (ADR-007) e alcuni vincoli DB (ADR-002/012) |
| Accesso volontari | Mantenere | La parte migliore |
| Login con password / account | Modificare in modo mirato | Regola di unicità e login (ADR-004) |
| Autorizzazione | **Refactoring sistematico** (il più esteso) | Da sparsa a Policy + `Access`; da fare con una matrice di test scritta prima |
| Controller di Turni e iscrizioni | Sottrarre, poi estrarre | Eliminare il doppio percorso, poi Action per i casi d'uso con effetti |
| Modulo Forniture | Mantenere | Già verticale; serve solo il volume per gli allegati e la retention separata |
| Modulo Ordini/Cassa, lato server | Modificare | Idempotenza, numerazione, pagamenti come fatti (ADR-008) |
| Cassa, lato client | **L'unico candidato a una "nuova" parte** | Solo *se* la misura della connettività giustifica l'offline: una nuova pagina operativa offline-first, non una riscrittura del resto |
| Notifiche | Modificare | Una classe base e il ripiego manuale; l'infrastruttura resta |
| Infrastruttura | Mantenere la topologia, modificare il rilascio | ADR-011 |
| Frontend | Mantenere | Pulizia e modalità postazione; Tailwind 4 quando capita |

### Ordine suggerito

> **Superato da [`03-roadmap.md`](03-roadmap.md).** Le verifiche fatte dopo
> hanno mostrato che questo ordine era sbagliato in quattro punti e che le
> stime erano ottimistiche (vedi 03-roadmap §1). Lo lascio com'era, come
> traccia del ragionamento.

L'ordine segue le dipendenze tra ADR, non solo l'impatto.

0. **Subito (ore, nessuna decisione da prendere):**
   - volume per `storage/app`;
   - backup automatici;
   - fermare il deploy automatico non verificato;
   - indici unici parziali (risolve il 500);
   - autorizzazione di `OrderController::update`;
   - `DomainException` → 422.
1. **Rete di sicurezza** (ADR-012): Postgres nei test, CI bloccante, PHP
   allineato. Tutto il resto si appoggia qui.
2. **Cassa** (ADR-008, opzione A). Va **prima del punto 3** se c'è un evento
   con più casse in calendario.
3. **Isolamento e autorizzazione** (ADR-003 B, ADR-005), con la matrice di
   test scritta per prima.
4. **Contratto dei moduli e reparti** (ADR-006 + ADR-007). Vanno insieme
   perché la replica passa da entrambi.
5. **Identità, notifiche, dati personali** (ADR-004, ADR-009, ADR-010). Sono
   indipendenti fra loro e si possono fare in qualsiasi ordine.
6. **Logica applicativa e frontend** (ADR-002, ADR-013): continui, un caso
   d'uso o una pagina alla volta, quando li si tocca per altri motivi.

Ordine di grandezza complessivo: **6-8 settimane** di lavoro, distribuibili
tra una feature e l'altra. Per ogni passo esiste una versione minima che
costa ore.

---

## Domande aperte che devi risolvere tu

Sono raggruppate per ADR. Le prime cinque bloccano scelte strutturali.

1. **Open core: cosa sarà chiuso?** Moduli a pagamento distribuiti a parte,
   solo l'hosting, oppure funzioni come i canali di notifica a pagamento? La
   risposta decide se il confine dei moduli resta una convenzione (ADR-006 B)
   o diventa un sistema di plugin (C).
2. **Chi usa la cassa e la cucina?** Confermi l'accesso per dispositivo
   (tablet abbinato a una cassa o a uno schermo) al posto del "solo staff"?
   E vuoi la numerazione per cassa (`A-042`), che cambia ciò che vede il
   cliente al bancone? (ADR-008)
3. **Regime fiscale dei clienti tipici** (associazioni in 398/91, ETS, Pro
   Loco). Serve memorizzazione o invio telematico dei corrispettivi, oppure
   basta una ricevuta? Va chiesto a un commercialista prima di progettare la
   parte fiscale. (ADR-008) *Aggiornamento: dal 2026 le Pro Loco non sono
   più nel regime 398/91; vedi [`04-fiscalita-cassa.md`](04-fiscalita-cassa.md).*
4. **Connettività reale alle feste.** Hai dati, o puoi misurarla al prossimo
   evento? Decide se la cassa offline vale 2-3 settimane di lavoro. (ADR-008)
5. **Turni: modulo di base o davvero spegnibile?** Propongo il primo e la
   riscrittura di D21. (ADR-006)
6. **API-first:** la abbandoni formalmente? E il servizio MCP in Python
   (D12): lo confermi, oppure valuti un MCP dentro Laravel? (ADR-001)
7. **Identità tra associazioni:** hai già casi di responsabili in più
   associazioni? Se no, va bene "email globale per chi ha la password" (D)
   per ora? (ADR-004)
8. **Responsabili e password:** D19 la impone, il codice no. Quale delle due
   è la regola? Io propendo per ammettere il magic link, insieme a una minore
   esposizione dei dati personali. (ADR-004, ADR-010)
9. **Il ruolo di responsabile scade con l'edizione?** E un responsabile deve
   poter consultare le edizioni passate in sola lettura? (ADR-005)
10. **Reparti:** le associazioni riusano davvero gli stessi reparti ogni
    anno? Come vuoi trattare la divisione di un reparto (Cucina → Cucina +
    Griglia)? (ADR-007)
11. **Retention:** dopo quanti anni o edizioni si anonimizzano le iscrizioni?
    E che cosa conta come "attività" di una persona? (ADR-010)
12. **Ruoli GDPR del SaaS:** hai un accordo ex art. 28 con le associazioni,
    un'informativa per i volontari e un registro dei trattamenti? (ADR-010)
13. **Canali a pagamento** (WhatsApp Business, SMS): sono accettabili come
    funzione a pagamento, dopo aver misurato la portata del push? (ADR-009)
14. **Self-hosting domestico:** serve a te per sviluppo e demo, o il servizio
    vero gira su un PC di casa? E il registry dell'immagine sarà pubblico?
    (ADR-011)
15. **Finestre di rilascio:** accetti di bloccare i deploy nei fine settimana
    della stagione delle sagre, con una procedura di hotfix? (ADR-011)
16. **Mercato:** solo Italia, e quindi `Europe/Rome`, normalizzazione dei
    telefoni `+39` e niente i18n, oppure no? (ADR-004, ADR-013)

Restano aperte anche le domande di 01-architettura non coperte qui: il
destino delle rotte organizzatore duplicate (risposta proposta in ADR-002:
rimuoverle) e il comportamento di `declined` (ri-proposte sì o no).
