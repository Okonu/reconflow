# Deployment

ReconFlow ships as one Docker image (FrankenPHP running Laravel, with the built frontend inside). `docker-compose.yml` (repository root) runs it as five services. It works with no configuration: secrets are generated on first boot into the `app_secrets` volume, and every setting has a default.

| Service | Role |
|---|---|
| `app` | Web (FrankenPHP on :8000). On start it caches config, routes and views, runs migrations and seeders, and bootstraps demo data when `DEMO_AUTO_SEED=true` and the DB is empty |
| `worker` | `queue:work`: reconciliation runs, ERP postings, notifications, AI batch triage |
| `scheduler` | `schedule:work`: daily pull + reconcile (06:00 EAT), daily summary (07:00), retention jobs, upload purge, audit archive |
| `db` | PostgreSQL 16 on an **internal** network (not reachable from outside) |
| `caddy` | Reverse proxy on `HTTP_PORT` (default 8080). With `SITE_ADDRESS` set to a domain it terminates TLS automatically (Let's Encrypt); it blocks `/metrics` from the internet |

## Server prerequisites

- Linux host with Docker Engine 24+ and the Compose plugin (or docker-compose 1.29+)
- 2 vCPU, 4 GB RAM, 20 GB disk for the demo; size the disk for Postgres and backups in production
- Only for HTTPS with a domain: a DNS `A` record pointing at the host and ports 80/443 free
- Outbound HTTPS to `api.anthropic.com` (only if AI is enabled) and to the Slack webhook (if set)

## Settings (optional `deploy/.env`)

All variables are optional; blank secrets are generated on first boot and kept in the `app_secrets` volume. Pass the file with `docker compose --env-file deploy/.env …` (or `make up`, which picks it up automatically).

| Variable | Required | Notes |
|---|---|---|
| `HTTP_PORT` / `HTTPS_PORT` | no | Published ports (8080 / 8443) |
| `APP_URL` | no | Public URL used in notification links (default `http://localhost:8080`) |
| `SITE_ADDRESS` | no | `:80` (plain HTTP, default) or a domain name for automatic HTTPS; then also set `SESSION_SECURE_COOKIE=true` |
| `APP_IMAGE` | no | Image tag to build/run (default `reconflow:local`) |
| `APP_KEY` | no | Generated on first boot if blank |
| `POSTGRES_PASSWORD` | no | Default `reconflow`; the database is only reachable on the internal Docker network. Set a strong value in production **before the first start** |
| `PII_HASH_SALT` | no | Generated on first boot if blank; keep it stable (it links pseudonym tokens) and secret |
| `DEMO_PASSWORD` | no | Password for the seeded demo users (default `ReconFlow2026`); change it for any shared deployment and set `SEED_DEMO_USERS=false` in production |
| `SOURCE_SYSTEMS_TOKEN` | no | Bearer token for the mock source and ERP APIs; generated if blank |
| `DEMO_AUTO_SEED`, `DEMO_DAYS`, `DEMO_SALES_PER_DAY` | demo | First-boot synthetic data (14 days × 3,000 sales) |
| `SESSION_LIFETIME` | no | Minutes (default 30) |
| `TRUSTED_PROXIES` | no | Docker network range so client IPs and HTTPS are detected behind Caddy |
| `RETENTION_TRANSACTIONS_YEARS`, `RETENTION_AI_LOGS_MONTHS`, `RETENTION_AUDIT_YEARS` | no | 7 / 12 / 7 |
| `ANTHROPIC_API_KEY` | no | Enables the AI assistant; leave empty to run rules-only |
| `ANTHROPIC_MODEL`, `AI_EFFORT`, `AI_ENABLED`, `AI_DRIVER`, `AI_REFUSAL_FALLBACKS` | no | Defaults `claude-opus-5`, `medium`, `true`, automatic (`claude` when a key is set, otherwise the clearly labelled offline `stub`), `true` |
| `NOTIFY_MAIL_ENABLED`, `NOTIFY_DAILY_SUMMARY_TIME`, `SLACK_WEBHOOK_URL` | no | Email and Slack delivery for alerts and the daily summary (aggregates only) |
| `MAIL_*` | if email | Standard Laravel mail settings |

Secrets live only in `deploy/.env` (git-ignored), the `app_secrets` volume, or your secrets manager. They are never logged.

## First deploy

```bash
git clone https://github.com/Okonu/reconflow.git /opt/reconflow && cd /opt/reconflow
cp deploy/.env.example deploy/.env      # optional: ports, APP_URL, DEMO_PASSWORD, POSTGRES_PASSWORD, AI key
docker compose --env-file deploy/.env up -d --build
docker compose ps                        # app should be "healthy"
curl -fsS http://localhost:${HTTP_PORT:-8080}/health
```

On docker-compose 1.29 use `docker-compose` and run `down` before `up` after rebuilding an image (a known ContainerConfig bug in that version).

### Example: shared server without a domain

The demo server (`212.56.45.149`) already runs Apache on ports 80 and 443, so ReconFlow is published on its own port and left alone otherwise:

```bash
# deploy/.env
HTTP_PORT=8090
HTTPS_PORT=8453
APP_URL=http://212.56.45.149:8090
DEMO_PASSWORD=<shared with the panel>
```

It serves plain HTTP on that port. For HTTPS, point a domain at the server and set `SITE_ADDRESS` (Caddy then needs ports 80/443), or add a reverse-proxy vhost in the existing web server.

## HTTPS

With `SITE_ADDRESS=your.domain`, Caddy requests and renews certificates automatically (`deploy/Caddyfile`) and the HSTS header takes effect. The default `:80` serves plain HTTP for local and demo use. The app trusts `X-Forwarded-*` only from `TRUSTED_PROXIES`.

## Backups

Nightly logical backup from the host (cron), kept for 30 days:

```cron
15 1 * * * cd /opt/reconflow && docker compose --env-file deploy/.env exec -T db pg_dump -U reconflow -Fc reconflow > /var/backups/reconflow/reconflow_$(date +\%F).dump && find /var/backups/reconflow -name '*.dump' -mtime +30 -delete
```

Also back up the `audit_archive` volume (gzipped JSONL audit archives and checkpoints) and copy both off the host. Test a restore monthly (see runbook).

## Upgrade

```bash
cd /opt/reconflow && git pull
docker compose --env-file deploy/.env up -d --build   # app runs migrations on start
```

Take a `pg_dump` first. New permissions introduced by an upgrade are granted automatically to the matching default roles; custom roles keep their existing permissions.

## Rollback

1. Check out the previous release (`git checkout <previous-sha>`) and run `docker compose --env-file deploy/.env up -d --build`, or run a previous CI image with `APP_IMAGE=ghcr.io/okonu/reconflow:<sha>`.
2. If the failed release ran a migration that the old code can't read, restore the pre-upgrade dump (runbook → Restore). Migrations are additive by convention, so this is rarely needed.

## CI/CD

GitHub Actions (`.github/workflows/ci.yml`) runs Pint, Larastan, ESLint, TypeScript, the Vite build, the Pest suite with coverage, the 50k-sale performance test, and the AI eval (only when an `ANTHROPIC_API_KEY` secret exists). On `main` it pushes `ghcr.io/<owner>/reconflow:{latest,sha}`, and optionally deploys over SSH when the `DEPLOY_*` secrets are configured.
