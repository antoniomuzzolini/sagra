# ADR-003 — Multi-tenancy: database condiviso, `tenant_id`, controlli a mano

- **Raccomandazione:** MANTENERE il modello (un database, `tenant_id`).
  MODIFICARE il meccanismo di isolamento: scope automatico che fallisce in modo
  chiuso; Row Level Security come rinforzo successivo.
- **Dipendenze:** ADR-004 (identità), ADR-005 (autorizzazione), ADR-012
  (Postgres nei test, prerequisito per RLS).

## Contesto e decisione attuale

- D9: un solo PostgreSQL, tenancy a `tenant_id`. Lo schema
  (`docs/schema-core.md:9-12`) denormalizza `tenant_id` su ogni tabella
  "perché semplifica i **global scope** di tenancy e rende impossibile un join
  cross-tenant per errore".
- Nel codice i global scope **non esistono**. L'isolamento dipende da tre
  elementi scritti a mano:
  - `authorizeTenant()` nella classe base (`Controller.php:13-16`), che
    restituisce 404 per una risorsa di un altro tenant;
  - controlli inline come `abort_unless($x->tenant_id === $person->tenant_id,
    404)` in `KitchenController`, `TillController` e
    `Volunteer/SignupController`;
  - `where('tenant_id', …)` nelle query di elenco.
- Il route model binding non è vincolato al tenant. Le regole `exists` filtrano
  per `event_id` o `area_id` (per esempio `ProductController.php:57`), quindi
  sono corrette solo perché l'evento corrente è già del tenant giusto.

## Analisi dai principi

L'isolamento tra associazioni è **la** proprietà di sicurezza di un SaaS
multi-tenant. Un meccanismo che dipende dal "ricordarsi di aggiungere il
`where`" **fallisce in modo aperto**: chi dimentica il filtro non riceve un
errore, mostra i dati di un altro tenant. Oggi, nelle letture fatte, non ho
trovato fughe cross-tenant, e questo è merito della disciplina. Ma la
disciplina non scala con il numero di controller e moduli. Lo schema ha già
pagato il costo della denormalizzazione senza incassare la garanzia per cui
l'aveva pagato.

**Cosa è giusto e va mantenuto:**

- il database condiviso: corretto per il costo e per la scala (centinaia di
  associazioni, non milioni di righe per tenant);
- il 404 invece del 403 sulle risorse di altri tenant: non rivela che
  esistono;
- `tenant_id` denormalizzato: rende possibile uno scope senza join e la RLS.

## Alternative

| | A. Status quo (manuale) | B. Global scope + `TenantContext` | C. Postgres Row Level Security | D. Schema o database per tenant |
|---|---|---|---|---|
| Garanzia | Dipende dalla disciplina | Applicativa: Eloquent filtra sempre, ma `DB::table` e il SQL grezzo restano esclusi | Nel database: vale anche per il SQL grezzo e per i bug | Fisica |
| Complessità | Nessuna | Bassa: un trait, un middleware, uno scope | Media: policy per tabella, ruolo DB senza `BYPASSRLS`, `SET app.tenant_id` per transazione, attenzione a worker e pool | Alta: migrazioni ripetute N volte, operazioni cross-tenant (recupero, amministrazione) difficili |
| Manutenibilità | Peggiora con ogni modulo | Buona; le eccezioni (console, recupero, join) sono esplicite | Buona, ma il comportamento "invisibile" sorprende nel debug | Pesante per uno sviluppatore solo |
| Performance | = | = (la condizione è già indicizzabile) | Overhead trascurabile | Connessioni e cataloghi per tenant |
| Costo di migrazione | — | 1-2 giorni, più la revisione dei punti senza contesto | 2-4 giorni dopo B e dopo i test su Postgres (ADR-012) | Settimane; rivede D9 senza un motivo |
| Plus | — | Riempie anche `tenant_id` in `creating` | Difesa in profondità | Export e cancellazione per tenant banali |

### Dettagli di B da non sbagliare

- Lo scope **non deve leggere `auth()->user()`**. `Person` è il modello
  autenticato: se fosse soggetto a uno scope che risolve l'utente, la
  risoluzione diventerebbe ricorsiva. Serve un `TenantContext` (singleton)
  impostato da un middleware dopo `auth`. Per `Person` basta che il provider di
  autenticazione carichi per id, che è già univoco.
- **Contesto assente in HTTP = errore, non "nessun filtro".** Il default
  silenzioso "senza tenant, tutto" riprodurrebbe il fallimento aperto.
- Le eccezioni legittime vanno marcate in modo esplicito, con
  `withoutTenantScope()` o un helper:
  - console e job: `SendShiftReminders` itera su tutti i tenant;
  - recupero accesso (`RecoveryController`, cross-tenant per scelta);
  - link d'invito (`JoinController`) e magic link, che sono ospiti e
    risolvono il tenant dal token.
- I job in coda devono **ripristinare il contesto** dal payload.

## Raccomandazione

**MODIFICARE: B ora, C dopo.**

- B chiude la classe di errori più probabile, cioè una query nuova senza
  filtro, con un costo modesto.
- C diventa ragionevole quando i test girano su Postgres (ADR-012). È la
  garanzia che lo schema prometteva. La renderei obbligatoria prima di aprire
  la registrazione self-service a molti tenant.
- D9 resta corretta: **non** passare a D. L'argomento che potrebbe far
  cambiare idea è l'export e la cancellazione per tenant (titolarità
  dell'associazione, ADR-010), ma si ottengono bene anche con `tenant_id`.

`authorizeTenant` resta come difesa leggibile nei controller finché non
arrivano le Policy (ADR-005). Poi diventa ridondante e si può togliere.

## Rischi e dipendenze

- **Rischio di B:** risultati vuoti inattesi in job e comandi. Va mitigato con
  test dedicati per ogni eccezione elencata sopra.
- **Rischio di C:** migrazioni e manutenzione eseguite con un ruolo che bypassa
  la RLS, contro l'app con un ruolo che la subisce. Servono due credenziali e
  va documentato in `DEPLOY.md` (ADR-011). Anche il self-hosting deve
  funzionare.
- ADR-004: se l'identità diventa globale (account sopra le membership), il
  `TenantContext` si risolve dalla membership attiva, non dalla persona.
  Conviene decidere ADR-004 prima di scrivere il middleware.
