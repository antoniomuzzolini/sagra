# ADR-001 — Monolite server-driven con Inertia, senza API

- **Raccomandazione:** MANTENERE il monolite Inertia. MODIFICARE la regola
  "API-first", sostituendola con una regola "use-case-first". Rivedere D12
  (servizio MCP in Python).
- **Dipendenze:** ADR-002 (strato applicativo), ADR-006 (confine moduli),
  ADR-008 (eventuale primo endpoint JSON per la cassa).

## Contesto e decisione attuale

- Laravel 12 e Inertia 2 con Vue 3. Ogni controller restituisce
  `Inertia::render(...)` oppure `back()`. Non esistono `routes/api.php`, Sanctum
  o risorse JSON (`bootstrap/app.php:15-19` registra solo `web`).
- Il `CLAUDE.md` originale (`docs/review/CLAUDE.original.md:30-31`) poneva come
  regola **non negoziabile** che "ogni funzionalità del core è esposta via API",
  pensata per un futuro servizio MCP/AI in Python (D5, D12). La regola non è mai
  stata applicata, e il `CLAUDE.md` ridotto non la riporta più.
- Di fatto la decisione in vigore è quindi "Inertia, niente API". Nessun
  documento però la registra come decisione.

## Analisi dai principi

Per uno sviluppatore singolo, il costo dominante è il **numero di contratti da
tenere coerenti**. Inertia ne elimina uno, quello tra frontend e backend: le
props sono il contratto e vivono nello stesso commit. È il motivo per cui la
scelta è giusta, non un ripiego.

La regola API-first si basava su una premessa debole. Un agente AI che usa tool
MCP non ha bisogno di un CRUD REST. Ha bisogno di **casi d'uso con intenzione**,
per esempio "metti Mario sul turno di sabato in cucina" oppure "chi manca
domenica sera?". Un'API REST costruita in anticipo avrebbe esposto le tabelle,
non le intenzioni, e il servizio Python avrebbe dovuto ricomporre la logica.
Oggi quella logica sta nei controller ed è in parte duplicata (§5.1 di
01-architettura). Per un futuro MCP, quindi, la preparazione giusta non è
un'API. È **un punto unico per ogni caso d'uso** (ADR-002).

Lo stesso principio di D13, cioè "niente architettura speculativa, il confine
si disegna sul caso reale", vale anche per l'API: senza un consumatore reale,
API-first è speculativa.

## Alternative

| | A. Status quo (Inertia, regola implicita) | B. Inertia + strato use-case, adattatori quando serve | C. API-first: JSON + SPA | D. Servizio MCP Python separato (D12) | E. MCP in-process su B |
|---|---|---|---|---|---|
| Complessità | Minima | Bassa: una classe per caso d'uso | Alta: auth token, DTO, versioning, stato client | Alta: secondo runtime, auth tra servizi, deploy doppio | Bassa-media, sopra B |
| Manutenibilità | Buona oggi; la logica duplicata cresce con ogni nuovo ingresso | Buona: un caso d'uso, N ingressi | Due contratti da tenere allineati | Due linguaggi e due codebase per un solo sviluppatore | Buona: i tool MCP chiamano le stesse Action |
| Performance | Ottima (un round-trip, props mirate) | Identica | Più round-trip, ma cache client | Latenza aggiuntiva per ogni tool | Identica |
| Costo di migrazione | — | Incrementale, per caso d'uso (vedi ADR-002) | Riscrittura del frontend (circa 6.700 righe Vue) | Prima va costruita l'API, poi il servizio | Basso, una volta fatto B |

Note sulle alternative:

- **C** non porta nulla agli utenti di oggi. Andrebbe considerata solo con
  un'app nativa o con integrazioni di terzi.
- **E** esiste come possibilità concreta: l'ecosistema Laravel ha un supporto
  MCP ufficiale (`laravel/mcp`). Prima di adottarlo va verificato quanto è
  maturo (versione, stabilità delle API, manutenzione), coerentemente con il
  principio "pacchetti terzi solo se maturi".

## Raccomandazione

**MANTENERE** Inertia come unico canale dell'interfaccia.

**MODIFICARE** la regola 4 e registrarla come nuova decisione, che superi
esplicitamente D5 (nella parte API) e D12:

> *Use-case-first.* Ogni mutazione di dominio passa da un'unica classe di caso
> d'uso, chiamata dal controller Inertia. Un adattatore (API JSON, tool MCP,
> comando) si aggiunge **solo quando esiste un consumatore reale**, e chiama gli
> stessi casi d'uso.

**Rivedere D12.** Un secondo linguaggio per un solo sviluppatore è la più
costosa delle decisioni originali, e ha meno valore oggi: i client AI parlano
MCP, e il server MCP può vivere nel monolite (E). La motivazione "valida i
confini dell'architettura" si ottiene anche con test architetturali (ADR-006).

**Correzione minore.** Le props condivise oggi serializzano l'intero modello
`Person` (`HandleInertiaRequests.php:49`). Con Inertia le props *sono* il
contratto: vanno esposti campi espliciti, non modelli interi (vedi anche
ADR-010).

## Rischi e dipendenze

- **Rischio di B:** trasformare ogni lettura in una "query class" per
  simmetria. Lo strato use-case serve alle mutazioni con effetti collaterali o
  con più ingressi. Le letture per una pagina possono restare nei controller.
- **Rischio di non decidere:** la regola resta "non negoziabile" in un
  documento e ignorata nel codice. Chi legge i documenti (tu tra un anno, un
  contributore, un agente) riceve indicazioni contraddittorie.
- ADR-008 potrebbe richiedere il **primo endpoint JSON** (sincronizzazione
  della cassa offline). Sarebbe un adattatore sopra il caso d'uso
  `RegisterOrder`, coerente con questa raccomandazione.
