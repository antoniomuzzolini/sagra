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

## Self-hosting at home (Cloudflare Tunnel)

Runs on any always-on machine — a mini-PC, a Raspberry Pi, or a Windows 11 PC
with Docker Desktop (WSL2). Home connections rarely have a public IP (CGNAT),
so instead of port-forwarding, a **Cloudflare Tunnel** gives a public HTTPS URL
with nothing opened on the router.

1. In the Cloudflare Zero Trust dashboard, create a **Tunnel** and copy its
   token. Add a **public hostname** (your domain) pointing at `http://app:80`.
2. In `.env`, set `CLOUDFLARE_TUNNEL_TOKEN=<token>`, `TRUSTED_PROXIES=*`,
   `APP_URL=https://your-domain`, and leave `APP_DOMAIN` empty (the app serves
   plain HTTP; the tunnel provides HTTPS).
3. Start everything, tunnel included:

   ```bash
   docker compose --profile tunnel up -d --build
   ```

Reachability aside, the setup is identical to a VPS. Keep in mind a home box
depends on your power and internet staying up — for the days of the event a
VPS is more dependable.

## Updating

```bash
git pull
docker compose up -d --build
```

Old images pile up over time; `docker image prune -f` reclaims the space.

## Auto-deploy on push

`scripts/deploy.ps1` (Windows) and `scripts/deploy.sh` (Linux/macOS) pull
`origin/main` and, only if there are new commits, rebuild and restart the
containers. Run them on a schedule for hands-off redeploys (poll interval =
how quickly a push goes live).

If you self-host with the tunnel, add `COMPOSE_PROFILES=tunnel` to `.env` so the
rebuild keeps the tunnel running.

**Windows (Task Scheduler), every 5 minutes:**

```powershell
schtasks /Create /SC MINUTE /MO 5 /TN "Sagra Auto Deploy" `
  /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\path\to\sagra\scripts\deploy.ps1" /F
```

**Linux (cron), every 5 minutes:**

```bash
*/5 * * * * /path/to/sagra/scripts/deploy.sh >> /var/log/sagra-deploy.log 2>&1
```

Notes:
- The machine needs read access to the repo (a private repo needs a stored
  credential — Git Credential Manager on Windows remembers it after the first
  `git clone`/login, or use an SSH deploy key).
- Keep the checkout clean; the script uses `git pull --ff-only`, so only `.env`
  (git-ignored) should differ locally.
- A rebuild spikes CPU for a couple of minutes while assets/deps compile —
  harmless on a PC, slow on a Raspberry Pi.

## Notes

- **Logs**: `docker compose logs -f app` (and `worker`, `scheduler`).
  Laravel logs to stderr, so they show up there.
- **Backups**: the local Postgres lives in the `pgdata` volume — schedule a
  `pg_dump` to off-box storage, or let Neon handle backups.
- **Scaling**: everything is DB-backed and stateless, so the web/worker
  containers can be replicated behind a load balancer when the time comes.
