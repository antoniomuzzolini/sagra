# CLAUDE.md (temporaneo — revisione architetturale)

> Versione ridotta per una revisione indipendente. L'originale è in
> docs/review/CLAUDE.original.md.

**Fieste**: gestionale open source per sagre, feste paesane ed eventi
gestiti da volontari, con offerta SaaS (open core). Sviluppatore singolo.
Rispondi in italiano.

Utenti finali: volontari con competenze tecnologiche molto variabili,
prevalentemente da smartphone. I dati dei volontari sono dati personali
(GDPR).

## Stack

- PHP 8.2+, Laravel 12, Inertia 2, Vue 3 + TypeScript, Tailwind 3
- PostgreSQL (test su SQLite in memoria)
- Docker: un'immagine usata per il deploy SaaS e per il self-hosting
  (vedi `DEPLOY.md`)

## Convenzioni

- Codice e commit in inglese; documentazione utente in italiano.
- Formattazione: Pint (PHP), ESLint + Prettier (frontend). La CI
  (`.github/workflows/`) esegue lint e test.

## Comandi

- Setup: `composer install && npm install`, `cp .env.example .env`,
  `php artisan key:generate`, `createdb sagra`, `php artisan migrate`
- Dev: `composer run dev` (server, queue, log e Vite in parallelo)
- Build frontend: `npm run build`
- Test backend: `php artisan test` (SQLite in memoria, vedi `phpunit.xml`)
- Test frontend: `npm run test` (Vitest + Vue Test Utils, jsdom)
- Lint/format PHP: `vendor/bin/pint`
- Lint/format frontend: `npm run lint`, `npm run format`
  (`npm run format:check` in CI)

Ambiente locale dello sviluppatore: PHP/Composer via Laravel Herd,
PostgreSQL via Postgres.app (porta 5432, utente di sistema, db `sagra`).

## Stato

Sono implementati: gestione volontari e turni, accesso con link magico e
password, Forniture, Ordini/Cassa (POS e comande) e moduli attivabili per
evento.
