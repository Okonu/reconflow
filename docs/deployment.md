# Deployment

ReconFlow ships as one Docker image (FrankenPHP running Laravel, with the built frontend inside). `deploy/docker-compose.yml` runs it as five services:

| Service | Role |
|---|---|
| `app` | Web (FrankenPHP on :8000). On start it caches config, routes and views, runs migrations and seeders, and bootstraps demo data when `DEMO_AUTO_SEED=true` and the DB is empty |
| `worker` | `queue:work`: reconciliation runs, ERP postings, notifications, AI batch triage |
| `scheduler` | `schedule:work`: daily pull + reconcile (06:00 EAT), daily summary (07:00), retention jobs, upload purge, audit archive |
| `db` | PostgreSQL 16 on an **internal** network (not reachable from outside) |
| `caddy` | TLS termination (automatic Let's Encrypt for `DOMAIN`), HSTS, compression; blocks `/metrics` from the internet |

## Server prerequisites

- Linux host with Docker Engine 24+ and the Compose plugin (or docker-compose 1.29+)
- 2 vCPU, 4 GB RAM, 20 GB disk for the demo; size the disk for Postgres and backups in production
- DNS `A` record for `DOMAIN` pointing at the host; ports 80 and 443 open
- Outbound HTTPS to `api.anthropic.com` (only if AI is enabled) and to the Slack webhook (if set)

## Environment variables (`deploy/.env`)

| Variable | Required | Notes |
|---|---|---|
| `DOMAIN` | yes | Public hostname; Caddy obtains the certificate |
| `APP_IMAGE` | yes | For example `ghcr.io/okonu/reconflow:latest` (CI pushes on `main`) |
| `APP_KEY` | yes | `docker run --rm $APP_IMAGE php artisan key:generate --show` |
| `POSTGRES_PASSWORD` | yes | Strong random value |
| `PII_HASH_SALT` | yes | Random 32+ chars; keep it stable (it links pseudonym tokens) and secret |
| `DEMO_PASSWORD` | demo | Password for the seeded demo users; set `SEED_DEMO_USERS=false` in production |
| `SOURCE_SYSTEMS_TOKEN` | yes | Bearer token for the mock source and ERP APIs |
| `DEMO_AUTO_SEED`, `DEMO_DAYS`, `DEMO_SALES_PER_DAY` | demo | First-boot synthetic data (14 days × 3,000 sales) |
| `SESSION_LIFETIME` | no | Minutes (default 30) |
| `TRUSTED_PROXIES` | no | Docker network range so client IPs and HTTPS are detected behind Caddy |
| `RETENTION_TRANSACTIONS_YEARS`, `RETENTION_AI_LOGS_MONTHS`, `RETENTION_AUDIT_YEARS` | no | 7 / 12 / 7 |
| `ANTHROPIC_API_KEY` | no | Enables the AI assistant; leave empty to run rules-only |
| `ANTHROPIC_MODEL`, `AI_EFFORT`, `AI_ENABLED`, `AI_DRIVER`, `AI_REFUSAL_FALLBACKS` | no | Defaults `claude-opus-5`, `medium`, `true`, `claude`, `true`; `AI_DRIVER=stub` gives an offline, clearly labelled rules stub |
| `NOTIFY_MAIL_ENABLED`, `NOTIFY_DAILY_SUMMARY_TIME`, `SLACK_WEBHOOK_URL` | no | Email and Slack delivery for alerts and the daily summary (aggregates only) |
| `MAIL_*` | if email | Standard Laravel mail settings |

Secrets live only in `deploy/.env` (git-ignored) or your secrets manager. They are never logged.

## First deploy

```bash
git clone git@github.com:Okonu/reconflow.git && cd reconflow
cp deploy/.env.example deploy/.env      # fill in the variables above
docker compose -f deploy/docker-compose.yml --env-file deploy/.env pull   # or: make up (builds locally)
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d
docker compose -f deploy/docker-compose.yml --env-file deploy/.env ps     # app should be "healthy"
curl -fsS https://$DOMAIN/health
```

`make up` does the same with a local build. On docker-compose 1.29, run `make down` before `make up` after changing an image (a known ContainerConfig bug in that version).

## HTTPS

Caddy requests and renews certificates automatically for `DOMAIN` (`deploy/Caddyfile`). For a local demo, `DOMAIN=localhost` uses Caddy's internal CA. The app trusts `X-Forwarded-*` only from `TRUSTED_PROXIES`.

## Backups

Nightly logical backup from the host (cron), kept for 30 days:

```cron
15 1 * * * cd /opt/reconflow && docker compose -f deploy/docker-compose.yml --env-file deploy/.env exec -T db pg_dump -U reconflow -Fc reconflow > /var/backups/reconflow/reconflow_$(date +\%F).dump && find /var/backups/reconflow -name '*.dump' -mtime +30 -delete
```

Also back up the `audit_archive` volume (gzipped JSONL audit archives and checkpoints) and copy both off the host. Test a restore monthly (see runbook).

## Upgrade

```bash
cd /opt/reconflow && git pull
docker compose -f deploy/docker-compose.yml --env-file deploy/.env pull
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d   # app runs migrations on start
```

Take a `pg_dump` first. New permissions introduced by an upgrade are granted automatically to the matching default roles; custom roles keep their existing permissions.

## Rollback

1. Re-deploy the previous image tag: `APP_IMAGE=ghcr.io/okonu/reconflow:<previous-sha> docker compose … up -d`.
2. If the failed release ran a migration that the old code can't read, restore the pre-upgrade dump (runbook → Restore). Migrations are additive by convention, so this is rarely needed.

## CI/CD

GitHub Actions (`.github/workflows/ci.yml`) runs Pint, Larastan, ESLint, TypeScript, the Vite build, the Pest suite with coverage, the 50k-sale performance test, and the AI eval (only when an `ANTHROPIC_API_KEY` secret exists). On `main` it pushes `ghcr.io/<owner>/reconflow:{latest,sha}`, and optionally deploys over SSH when the `DEPLOY_*` secrets are configured.
