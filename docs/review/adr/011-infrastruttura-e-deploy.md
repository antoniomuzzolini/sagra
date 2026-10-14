# ADR-011 — Infrastruttura e deploy

- **Raccomandazione:** MANTENERE la forma: un'immagine, FrankenPHP, Postgres
  per tutto, una VPS, Compose. MODIFICARE il ciclo di rilascio e la gestione
  dello stato: storage persistente, backup automatici, immagine costruita in
  CI, nessun deploy automatico non verificato, finestre di rilascio.
- **Dipendenze:** ADR-010 (backup e retention), ADR-012 (CI come cancello del
  deploy), ADR-003 (ruolo DB per la RLS).

## Contesto e decisione attuale

- **Un'immagine per web, worker e scheduler** (D11), su FrankenPHP con HTTPS
  automatico (`Dockerfile`). Compose avvia `app`, `worker`, `scheduler`, `db` e
  due profili Cloudflare Tunnel (`docker-compose.yml`).
- **Postgres per tutto:** dati, sessioni, cache e coda (`.env.docker.example`,
  `SESSION_DRIVER`, `QUEUE_CONNECTION` e `CACHE_STORE` = `database`).
- **Migrazioni all'avvio** del solo container web (`docker/entrypoint.sh:12-14`).
- **Deploy automatico** (`scripts/deploy.sh`, `DEPLOY.md:116-146`): un cron
  ogni 5 minuti fa `git pull` di `main` e `docker compose up -d --build`
  **sulla macchina di produzione**. Non guarda l'esito della CI.
- **Allegati** su `storage/app/private` **dentro il container**, senza volume.
  Ogni rebuild li cancella (01-architettura §5.7). `DEPLOY.md:154` dice ancora
  "everything is DB-backed and stateless".
- **Backup:** solo un suggerimento ("schedule a `pg_dump`", `DEPLOY.md:152`).
- **Versioni:** runtime PHP 8.3 (`Dockerfile:33`), CI su 8.4, `composer.lock`
  che richiede PHP ≥ 8.4.1. Funziona perché il platform check è disattivato
  (`Dockerfile:19-20`). L'ho riverificato anche in questa sessione: con PHP
  8.3 l'autoload si ferma sul platform check finché non lo si disattiva.

## Cosa è fatto bene

La topologia è giusta per il problema.

- **Postgres per tutto.** A questi volumi (decine di richieste al secondo nel
  picco di una sagra, centinaia di job al giorno) Redis non darebbe nulla di
  misurabile e sarebbe un servizio in più da tenere vivo, aggiornare e
  includere nei backup. La coda su database di Laravel su Postgres usa
  `FOR UPDATE SKIP LOCKED`, quindi più worker non si pestano i piedi.
- **FrankenPHP** elimina nginx, php-fpm e supervisor: meno parti mobili.
- **Una sola immagine** per tutti i ruoli e per il self-hosting: un solo
  artefatto da testare.
- **Le migrazioni solo nel container web** evitano le corse tra container.

## Analisi dai principi

Una sagra ha **giorni di picco prevedibili**, ed è proprio lì che il servizio
non può fermarsi. Il ciclo di rilascio attuale ottimizza la velocità ("un push
va live in 5 minuti"), mentre il requisito è la **prevedibilità**.

- **Un push su `main` va in produzione anche con i test rossi.** Il cron non
  sa nulla della CI.
- **La build gira sulla VPS da 5 €**, con un picco di CPU di qualche minuto,
  magari il sabato sera mentre le casse vendono.
- **La migrazione all'avvio non ha un ritorno indietro.** Una migrazione che
  rompe significa un'immagine che non parte.
- **Lo stato fuori dal database non è gestito.** Gli allegati si perdono, e
  nessun backup copre i file.

## Alternative

| | A. Status quo + volume per `storage/app` | B. Stessa topologia, rilascio irrobustito | C. PaaS gestito (Laravel Cloud, Fly.io, Render…) | D. Orchestratore (Kubernetes, Nomad) |
|---|---|---|---|---|
| Idea | Si chiude la perdita di dati, il resto resta com'è | La CI costruisce e pubblica l'immagine con tag (GHCR) solo con i test verdi; la VPS fa `pull` di un tag; backup automatici di DB e file; storage S3-compatibile UE per gli allegati (opzionale); finestre di rilascio | La piattaforma gestisce build, deploy e database | — |
| Complessità | Minima | Bassa: un workflow, uno script di backup | Bassa in esercizio, dipendenza dal fornitore | Sproporzionata |
| Costo | ~5 €/mese | ~5 €/mese + qualche € di storage per i backup | 20-50+ €/mese, che cresce con i servizi | — |
| Affidabilità | Deploy non verificati; build in produzione | Rilascio solo di immagini testate; rollback = tag precedente | Alta | — |
| Self-hosting | Invariato | Invariato: chi fa self-hosting usa la stessa immagine pubblicata, senza build locale | Non applicabile, va mantenuto un percorso separato | — |
| Costo di migrazione | Minuti | 1-2 giorni | Giorni, e cambia D11 | — |

## Raccomandazione

**MODIFICARE con B.** Per prima cosa, oggi stesso, la parte di A: il volume
per `storage/app`.

1. **Volume** per `storage/app/private`, oppure un disco S3-compatibile in UE.
   Correggere `DEPLOY.md:154`.
2. **Backup automatici** come servizio Compose:
   - `pg_dump` notturno più copia dei file verso uno storage esterno (per
     esempio restic su un object storage UE);
   - retention dichiarata (ADR-010);
   - **una prova di restore documentata**. Un backup mai ripristinato non è
     un backup.
3. **Immagine costruita in CI** dopo i test e pubblicata su un registry con
   tag; la VPS fa `pull` di un tag. Così la build esce dalla VPS e il
   self-hosting non deve più compilare nulla.
4. **Niente deploy automatico da `main` senza CI verde.** Bastano un deploy
   manuale ("promuovi il tag") o un deploy automatico solo dopo i test. In più,
   una **finestra di blocco** durante i giorni di servizio: per un tenant solo,
   con il calendario; in SaaS multi-tenant, per esempio "niente deploy
   venerdì-domenica da maggio a settembre".
5. **Migrazioni compatibili all'indietro** (prima si aggiunge, poi si toglie):
   la versione N-1 dell'app deve funzionare con lo schema N. Il rollback
   diventa così cambiare tag.
6. **Allineare PHP:** runtime 8.4 (consigliato, è quello della CI), oppure
   `config.platform.php = 8.3` in `composer.json`. Poi riattivare il platform
   check.

**MANTENERE** Postgres per tutto, FrankenPHP, Compose e la singola VPS. Non
passare a C finché il costo del tuo tempo in operatività non supera la
differenza di prezzo. Il self-hosting resta possibile: è un argomento dell'open
core (garanzia e adozione, D1).

Sul self-hosting domestico (Cloudflare Tunnel, `deploy.ps1`): se serve a te
per sviluppo o demo, va bene, ma andrebbe tolto dal percorso principale di
`DEPLOY.md`, perché D1 dice che i clienti non si auto-ospitano. Se invece il
servizio vero gira su un PC di casa, è un rischio di disponibilità proprio nei
giorni di servizio. È una domanda aperta.

## Rischi e dipendenze

- **Un registry privato richiede credenziali sulla VPS.** Un registry pubblico
  rende l'immagine scaricabile da chiunque: coerente con l'open source, ma da
  decidere.
- **La finestra di blocco ritarda le correzioni urgenti.** Serve una procedura
  "hotfix" esplicita, con deploy manuale e un test di fumo.
- La RLS (ADR-003) richiede due ruoli DB, uno per le migrazioni e uno per
  l'app: va riflesso nell'`entrypoint` e in `.env.docker.example`.
