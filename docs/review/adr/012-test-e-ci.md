# ADR-012 — Strategia di test e CI

- **Raccomandazione:** MODIFICARE. Test backend su PostgreSQL (in CI e in
  locale), CI che fallisce davvero su lint e formattazione, Vitest in CI, PHP
  allineato al runtime, test architetturali e di autorizzazione. MANTENERE lo
  stile dei test di feature, che è buono.
- **Dipendenze:** prerequisito per ADR-003 (RLS), ADR-005 (matrice di
  autorizzazione), ADR-008 (concorrenza), ADR-011 (CI come cancello del
  deploy). ADR-002 (vincoli DB) ne trae beneficio.

## Contesto e decisione attuale

- **276 test PHPUnit di feature** su SQLite in memoria (`phpunit.xml`). Le
  migrazioni hanno rami `if sqlite`
  (`2026_07_22_000001_unify_identity_into_people.php:67,96`).
- **Produzione solo su PostgreSQL.** Vale per il SaaS e anche per il
  self-hosting, che usa la stessa immagine e il servizio `db` di Compose.
- **`lint.yml`** esegue Pint e Prettier in **modalità scrittura**, con lo step
  di commit commentato: non fallisce mai sulla formattazione.
- **`tests.yml`** non esegue **Vitest** (5 file `*.test.ts`) e usa PHP 8.4
  contro l'8.3 del runtime.
- **Il deploy non dipende dalla CI** (ADR-011).

## Cosa è fatto bene

I test sono **di feature, sul comportamento, scritti in termini di dominio**:
`OverlapAndMembershipTest`, `D19IdentityTest`, `EventReplicationTest`. Sono la
rete di sicurezza che rende possibile tutto il refactoring incrementale
proposto negli altri ADR. Il rapporto test/codice è alto per un progetto di uno
sviluppatore solo.

## Analisi dai principi

Un test vale quanto la sua **somiglianza con la produzione nei punti in cui
il comportamento può differire**. Per questo progetto quei punti sono proprio
quelli che stanno diventando importanti:

- **concorrenza e isolamento transazionale** (numerazione ordini, ADR-008):
  SQLite serializza le scritture e non può riprodurre il problema;
- **vincoli che SQLite non ha**: `EXCLUDE USING gist` per le fasi
  (`docs/schema-core.md:83-86`) e Row Level Security (ADR-003);
- **tipi**: `timestamptz` e fusi orari in `whereDate`, JSON, confronto
  `ILIKE`;
- **migrazioni:** i rami `if sqlite` fanno sì che la migrazione testata non
  sia quella che gira in produzione.

Il vantaggio di SQLite (nessun servizio) oggi non vale più molto: hai
Postgres.app in locale, e la CI può avviare un container Postgres in pochi
secondi.

## Alternative

| | A. Status quo | B. Postgres in CI, SQLite in locale | C. Postgres ovunque |
|---|---|---|---|
| Fedeltà | Bassa nei punti critici | Alta in CI; in locale un test può passare e poi fallire in CI | Alta |
| Complessità | Nessuna | Due configurazioni, i rami `if sqlite` restano | Una configurazione; i rami `if sqlite` si tolgono |
| Velocità | La più alta | CI un po' più lenta | Con `RefreshDatabase` (transazioni) la differenza su 276 test è dell'ordine dei secondi, non dei minuti: da misurare |
| Contributori | Nessun prerequisito | Nessun prerequisito in locale | Serve Postgres: `docker compose up db` lo fornisce già |
| Abilita | — | Test di concorrenza e vincoli in CI | Lo stesso, e vincoli DB senza rami condizionali |
| Costo di migrazione | — | Mezza giornata | 1 giorno |

## Raccomandazione

**MODIFICARE con C**, cioè Postgres ovunque, più le correzioni alla CI.

1. **Postgres** come database di test (`phpunit.xml`; un service container in
   `tests.yml`). Togliere i rami `if sqlite` dalle migrazioni.
2. **Lint come cancello:**
   - `vendor/bin/pint --test`;
   - `npm run format:check`;
   - `npm run lint` senza `--fix`;
   - `vue-tsc --noEmit`, il controllo dei tipi del frontend, oggi assente.
3. **`npm run test` (Vitest)** in CI.
4. **PHP della CI uguale al runtime** (ADR-011), più un **test di fumo
   dell'immagine Docker**: build, avvio con un Postgres effimero, `GET /up`,
   migrazioni.
5. **Test di autorizzazione a matrice** prima del refactor di ADR-005:
   ruolo × azione × area propria/altrui × evento corrente/altro.
6. **Test architetturale** del confine dei moduli (ADR-006): un test PHPUnit
   che fallisce se un file del core importa un namespace di modulo.
7. **Test di concorrenza** per la numerazione degli ordini (ADR-008), con due
   connessioni.
8. **Togliere `tests/Pest.php`**, che resta dallo starter kit, oppure adottare
   Pest. Nessuna delle due scelte è importante, ma oggi il file inganna.

## Rischi e dipendenze

- **Velocità della suite:** se con Postgres la suite diventa lenta,
  `LazilyRefreshDatabase` e l'esecuzione in parallelo (`--parallel`, un
  database per processo) la riportano a livelli accettabili.
- Rendere la CI bloccante farà fallire i primi push per formattazione. Conviene
  un commit iniziale `pint` + `prettier --write` su tutto il codice, separato
  dal resto.
- È **il primo passo** di tutti gli altri refactoring: senza questo, ADR-003
  (RLS), ADR-008 (concorrenza) e i vincoli DB di ADR-002 non si possono
  verificare.
