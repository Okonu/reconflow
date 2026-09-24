# Deployment

ReconFlow is one Docker image. The image contains FrankenPHP, the Laravel application and the built frontend. `docker-compose.yml` (in the repository root) runs the image as five services. The system needs no configuration. On the first start, it makes the secrets in the `app_secrets` volume. Each setting has a default.

| Service | Function |
|---|---|
| `app` | The web process (FrankenPHP on port 8000). At start, it caches the configuration, routes and views, runs the migrations and seeders, and loads the demo data if `DEMO_AUTO_SEED=true` and the database is empty |
| `worker` | `queue:work`: reconciliation runs, ERP postings, notifications and AI batch triage |
| `scheduler` | `schedule:work`: the daily pull and reconciliation (06:00 EAT), the daily summary (07:00), retention jobs, the upload clean-up and the audit archive |
| `db` | PostgreSQL 16 on an **internal** network. It is not available from outside |
| `caddy` | The reverse proxy on `HTTP_PORT` (default 8080). If `SITE_ADDRESS` is a domain, Caddy gets a TLS certificate automatically (Let's Encrypt). It blocks `/metrics` from the internet |

## Server requirements

- A Linux server with Docker Engine 24 or later and the Compose plugin (or docker-compose 1.29 or later).
- For the demo: 2 vCPU, 4 GB RAM and 20 GB disk. For production, calculate the disk size for PostgreSQL and the backups.
- For HTTPS with a domain only: a DNS `A` record for the server, and ports 80 and 443 free.
- Outbound HTTPS to `api.anthropic.com` (only if the AI is on) and to the Slack webhook (if set).

## Settings (optional `deploy/.env` file)

All variables are optional. If a secret is empty, the system makes it on the first start and keeps it in the `app_secrets` volume. Give the file with `docker compose --env-file deploy/.env …`, or use `make up`, which finds the file automatically.

| Variable | Necessary | Note |
|---|---|---|
| `HTTP_PORT` / `HTTPS_PORT` | No | The published ports (8080 / 8443) |
| `APP_URL` | No | The public URL in notification links (default `http://localhost:8080`) |
| `SITE_ADDRESS` | No | `:80` (plain HTTP, the default) or a domain name for automatic HTTPS. With a domain, also set `SESSION_SECURE_COOKIE=true` |
| `APP_IMAGE` | No | The image tag to build or run (default `reconflow:local`) |
| `APP_KEY` | No | The system makes it on the first start if it is empty |
| `POSTGRES_PASSWORD` | No | Default `reconflow`. Only the internal Docker network can connect to the database. For production, set a strong value **before the first start** |
| `PII_HASH_SALT` | No | The system makes it on the first start if it is empty. Keep it the same (it links the pseudonym tokens) and keep it secret |
| `DEMO_PASSWORD` | No | The password of the demo users (default `ReconFlow2026`). Change it for each shared deployment. In production, set `SEED_DEMO_USERS=false` |
| `SOURCE_SYSTEMS_TOKEN` | No | The bearer token for the mock source and ERP APIs. The system makes it if it is empty |
| `DEMO_AUTO_SEED`, `DEMO_DAYS`, `DEMO_SALES_PER_DAY` | Demo | Synthetic data on the first start (14 days × 3,000 sales) |
| `SESSION_LIFETIME` | No | Minutes (default 30) |
| `TRUSTED_PROXIES` | No | The Docker network range. The application then detects the client IP address and HTTPS behind Caddy |
| `RETENTION_TRANSACTIONS_YEARS`, `RETENTION_AI_LOGS_MONTHS`, `RETENTION_AUDIT_YEARS` | No | 7 / 12 / 7 |
| `ANTHROPIC_API_KEY` | No | Turns on the Claude assistant. If it is empty, the system uses the rules only |
| `ANTHROPIC_MODEL`, `AI_EFFORT`, `AI_ENABLED`, `AI_DRIVER`, `AI_REFUSAL_FALLBACKS` | No | Defaults: `claude-opus-5`, `medium`, `true`, automatic (`claude` with a key, otherwise the labelled offline `stub`), `true` |
| `NOTIFY_MAIL_ENABLED`, `NOTIFY_DAILY_SUMMARY_TIME`, `SLACK_WEBHOOK_URL` | No | Email and Slack for alerts and the daily summary (totals only) |
| `MAIL_*` | For email | The standard Laravel mail settings |

Secrets are only in `deploy/.env` (Git ignores this file), in the `app_secrets` volume or in your secret store. The logs never contain them.

## First deployment

1. Get the code:

   ```bash
   git clone https://github.com/Okonu/reconflow.git /opt/reconflow && cd /opt/reconflow
   ```

2. Optional: copy the settings file and change the values (ports, `APP_URL`, `DEMO_PASSWORD`, `POSTGRES_PASSWORD`, AI key):

   ```bash
   cp deploy/.env.example deploy/.env
   ```

3. Build and start the services:

   ```bash
   docker compose --env-file deploy/.env up -d --build
   ```

4. Make sure that the `app` service is "healthy":

   ```bash
   docker compose ps
   curl -fsS http://localhost:${HTTP_PORT:-8080}/health
   ```

With docker-compose 1.29, use `docker-compose`. After you build a new image, run `down` before `up`. This prevents a known ContainerConfig error in that version.

### Example: a shared server with no domain

On the demo server (`212.56.45.149`), Apache uses ports 80 and 443. Thus ReconFlow uses its own port:

```bash
# deploy/.env
HTTP_PORT=8090
HTTPS_PORT=8453
APP_URL=http://212.56.45.149:8090
```

It serves plain HTTP on that port. For HTTPS, point a domain to the server and set `SITE_ADDRESS` (Caddy then needs ports 80 and 443). Or add a reverse-proxy virtual host in the existing web server.

**Note:** The demo server has little free memory and no swap. Build the image on a different computer and copy it to the server:

```bash
docker build -t reconflow:local .
docker save reconflow:local | gzip | ssh root@<server> 'gunzip | docker load'
ssh root@<server> 'cd /opt/reconflow && docker compose --env-file deploy/.env up -d --no-build'
```

## HTTPS

If `SITE_ADDRESS=your.domain`, Caddy gets and renews the certificates automatically (`deploy/Caddyfile`). The HSTS header then applies. The default `:80` serves plain HTTP for local and demo use. The application trusts `X-Forwarded-*` headers only from `TRUSTED_PROXIES`.

## Backups

Make a database backup each night from the server (cron). Keep each backup for 30 days:

```cron
15 1 * * * cd /opt/reconflow && docker compose --env-file deploy/.env exec -T db pg_dump -U reconflow -Fc reconflow > /var/backups/reconflow/reconflow_$(date +\%F).dump && find /var/backups/reconflow -name '*.dump' -mtime +30 -delete
```

Also back up the `audit_archive` volume (the audit archives and checkpoints). Copy both backups off the server. Test a restore each month (refer to the runbook).

## Upgrade

1. Make a database backup with `pg_dump`.
2. Get the new code and start the services. The `app` service runs the migrations at start.

   ```bash
   cd /opt/reconflow && git pull
   docker compose --env-file deploy/.env up -d --build
   ```

The system gives new permissions to the matching default roles automatically. Custom roles keep their permissions.

## Rollback

1. Get the previous release (`git checkout <previous-sha>`) and run `docker compose --env-file deploy/.env up -d --build`. Or run an earlier CI image with `APP_IMAGE=ghcr.io/okonu/reconflow:<sha>`.
2. If the failed release ran a migration that the old code cannot read, restore the backup from before the upgrade (runbook → Restore a database backup). Migrations only add structure, so this is not usually necessary.

## CI/CD

GitHub Actions (`.github/workflows/ci.yml`) runs these checks on each change:

- Pint, Larastan, ESLint and TypeScript.
- The Vite build.
- The Pest tests with coverage.
- The performance test with 50,000 sales.
- The AI evaluation (only if an `ANTHROPIC_API_KEY` secret exists).
 On `main`, it publishes `ghcr.io/<owner>/reconflow:{latest,sha}`. If the `DEPLOY_*` secrets exist, it can also deploy over SSH.

## Path to scale

The single-server deployment is correct for one daily reconciliation. If the volume or the uptime needs increase, use these steps:

1. Move PostgreSQL to a managed database service with automatic backups.
2. Run the same image on a managed container service with two or more web instances.
3. Keep one scheduler instance, and add workers when the queue gets long.
