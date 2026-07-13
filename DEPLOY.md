# Deploy (VPS + Docker)

The cheapest production shape: one small VPS (e.g. Hetzner CX22, ~€5/mese)
running the web app, a queue worker and the scheduler from a single image,
plus PostgreSQL — all via `docker compose`. Sessions, cache and the queue live
in Postgres, so there is no Redis to run, and FrankenPHP provides automatic
HTTPS, so there is no separate reverse proxy.

## Prerequisites

- A VPS with Docker + the Compose plugin.
- Ports 80 and 443 open.
- (For HTTPS) a domain with an `A` record pointing at the VPS IP.

## First deploy

```bash
git clone <repo> sagra && cd sagra
cp .env.docker.example .env
```

Fill in `.env`, then generate the two secrets it needs:

```bash
# App key
docker compose run --rm app php artisan key:generate --show   # paste into APP_KEY

# Web push (VAPID) keys — copy both into VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY
docker compose run --rm app php artisan webpush:vapid
```

Set `APP_DOMAIN` to your domain (for automatic HTTPS) and `CADDY_EMAIL` to your
address. Then:

```bash
docker compose up -d --build
```

The `app` container runs the migrations on boot (`RUN_MIGRATIONS=true`). Check
it came up:

```bash
docker compose ps
curl -fsS https://your-domain.example/up   # health endpoint
```

## Using Neon (managed Postgres) instead of the local DB

1. Remove (or stop) the `db` service in `docker-compose.yml`.
2. In `.env`, point the database at Neon — either `DB_URL` with the Neon
   connection string (`…?sslmode=require`), or `DB_HOST` / `DB_USERNAME` /
   `DB_PASSWORD` with the Neon values. Keep `DB_CONNECTION=pgsql`.

## Updating

```bash
git pull
docker compose up -d --build
```

Old images pile up over time; `docker image prune -f` reclaims the space.

## Notes

- **Logs**: `docker compose logs -f app` (and `worker`, `scheduler`).
  Laravel logs to stderr, so they show up there.
- **Backups**: the local Postgres lives in the `pgdata` volume — schedule a
  `pg_dump` to off-box storage, or let Neon handle backups.
- **Scaling**: everything is DB-backed and stateless, so the web/worker
  containers can be replicated behind a load balancer when the time comes.
