# CLAUDE.md

Gestionale modulare open source per sagre ed eventi gestiti da volontari,
con offerta SaaS (open core). Sviluppatore singolo. Rispondi in italiano.

## Contesto obbligatorio

Leggi @docs/dominio-e-decisioni.md prima di lavorare: contiene visione,
glossario delle entità, decisioni prese (D1–D12) e domande aperte.
È la fonte di verità — se il codice e il documento divergono, segnalalo.

## Stack

- Laravel + Inertia + Vue 3, monolite modulare
- PostgreSQL singolo, multi-tenancy a `tenant_id`
- Frontend PWA mobile-first (utenti: volontari da smartphone)
- Docker per deploy SaaS e per il self-hosting (stessa immagine)

## Regole architetturali (non negoziabili)

1. **Core vs moduli**: il core (persone, eventi, fasi, aree, turni, ruoli,
   notifiche) non sa nulla di cosa fanno i moduli. I moduli dipendono dal
   core, mai da altri moduli.
2. **Nessuna comunicazione modulo→modulo**: se due moduli devono
   coordinarsi, il dato o l'evento passa dal core.
3. **La fase di un turno è derivata dalla data**, mai persistita o
   dichiarata a mano.
4. **API-first**: ogni funzionalità del core è esposta via API, pensata
   per essere consumata in futuro da un servizio MCP/AI esterno (Python).
5. **Semplicità per l'utente finale**: niente flussi che richiedano
   password, manuali o più di pochi tap. In caso di dubbio tra eleganza
   tecnica e semplicità d'uso, vince la semplicità d'uso.

## Principi di sviluppo

- Privilegia soluzioni semplici e a basso costo operativo: un server, un
  database, niente servizi esterni se evitabili.
- Preferisci le funzionalità native di Laravel (notifiche, code, policy)
  a pacchetti terzi; pacchetti terzi solo se maturi e attivamente mantenuti.
- I dati dei volontari sono dati personali: minimizzazione e attenzione
  GDPR in ogni feature che li tocca.
- Tutto il codice e i commit in inglese; documentazione utente in italiano.

## Comandi

- Setup: `composer install && npm install`, poi `cp .env.example .env`,
  `php artisan key:generate`, `createdb sagra`, `php artisan migrate`
- Dev: `composer run dev` (server, queue, log e Vite in parallelo)
- Test: `php artisan test` (SQLite in memoria, vedi `phpunit.xml`)
- Lint/format PHP: `vendor/bin/pint`
- Lint/format frontend: `npm run lint` e `npm run format`

Ambiente locale: PHP e Composer via Laravel Herd, PostgreSQL via
Postgres.app (porta 5432, utente di sistema, senza password, db `sagra`).

## Stato del progetto

Scaffolding Laravel 12 + Inertia 2 + Vue 3 (starter kit ufficiale Vue)
inizializzato, collegato a PostgreSQL. MVP in corso di progettazione: il
core con gestione volontari e turni (vedi sezione 5 del documento di
dominio). Nessun modulo verticale e nessuna integrazione AI fino a MVP
completato.
