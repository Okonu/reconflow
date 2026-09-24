# ReconFlow

Daily sales reconciliation for Tupande. ReconFlow pulls sales, payments and ERP postings each morning and matches them with deterministic rules. It turns every discrepancy into an owned exception with an SLA, and routes corrections through maker-checker approval before posting them to the ERP with an idempotency key. Everything is recorded in a hash-chained audit trail. An optional AI assistant suggests causes and next steps; people decide.

> **Synthetic data only.** This system is a demonstration. No real customer data is used.

![Screenshot placeholder](docs/screenshot.png)

## Stack

Laravel 13 (PHP 8.3) with function-specific modules, PostgreSQL 16, Inertia + React 19 + TypeScript + Tailwind + shadcn/ui, FrankenPHP behind Caddy, Docker Compose. The AI assistant uses the official Anthropic PHP SDK (Claude).

## Run it (Docker, no configuration)

You only need **Docker** (Docker Desktop on macOS/Windows, or Docker Engine with the Compose plugin on Linux). Nothing else is required: no PHP, Node, database setup or `.env` file.

```bash
git clone https://github.com/Okonu/reconflow.git
cd reconflow
docker compose up -d --build
```

Then open **http://localhost:8080** and sign in with any account below. The password is **`ReconFlow2026`**.

What happens on the first start:

1. The image builds, which takes 3–6 minutes the first time and is cached afterwards.
2. PostgreSQL starts. The app generates its own secrets (application key, pseudonymisation salt, source-system token) into a Docker volume, runs the migrations, and creates the roles and demo users.
3. The page is usable as soon as `docker compose ps` shows `app` as **healthy**. A background worker then generates 14 days of synthetic data (3,000 sales a day) and reconciles every day. The dashboard shows "Preparing demo data…" and refreshes itself; this takes a few minutes.

Useful commands:

| Command | What it does |
|---|---|
| `docker compose ps` | Service status (`app` should be healthy) |
| `docker compose logs -f app worker` | Follow the logs |
| `docker compose down` | Stop (data is kept in Docker volumes) |
| `docker compose down -v` | Stop and delete all data; the next `up` starts fresh |

Docker Compose also reads a `.env` file in the project root if one exists (for example, from local development). Use `make up`, which ignores it, if you have one.

Optional settings: copy `deploy/.env.example` to `deploy/.env`, change what you need, and run `docker compose --env-file deploy/.env up -d` (or `make up`). Common changes:

| Setting | Default | When to change it |
|---|---|---|
| `HTTP_PORT` | `8080` | Port 8080 is already in use on your machine |
| `APP_URL` | `http://localhost:8080` | Serving on another host or port (used in notification links) |
| `ANTHROPIC_API_KEY` | empty | To use Claude for AI suggestions. Without a key, the AI panels use a clearly labelled offline rules stub, so every screen still works |
| `DEMO_PASSWORD` | `ReconFlow2026` | Any shared or public deployment |
| `SITE_ADDRESS`, `SESSION_SECURE_COOKIE` | `:80`, `false` | Real domain with HTTPS: set `SITE_ADDRESS=your.domain` and `SESSION_SECURE_COOKIE=true`; Caddy obtains the certificate |

The stack runs five containers: `app` (web), `worker` (queue: reconciliation, postings, AI, notifications), `scheduler` (daily 06:00 run, summaries, retention), `db` (PostgreSQL 16, internal network only), and `caddy` (reverse proxy on port 8080). See [docs/deployment.md](docs/deployment.md) for servers, HTTPS, backups and upgrades.

## Local development (without Docker)

Requires PHP 8.3, Composer, Node 22 and PostgreSQL 16.

```bash
cp .env.example .env && php artisan key:generate   # then set the DB_* values
make install
make migrate seed
make dev                                 # php artisan serve + queue worker + vite
```

## Demo logins

Password: `ReconFlow2026` (the value of `DEMO_PASSWORD`).

| Login | Seeded role | Can |
|---|---|---|
| `analyst@demo` | Recon Analyst | run reconciliations, upload data, work exceptions, propose adjustments, use AI |
| `manager@demo` | Finance Manager | everything an analyst can, plus approve (including above $1,000), sign off and reopen dates, unmasked exports |
| `auditor@demo` | Auditor | read-only; personal data masked; verify and export the audit log |
| `admin@demo` | Administrator | users, roles, settings, AI kill switch, demo reset |

Roles are templates made of permissions. Permissions can be changed and new roles created at runtime on the Roles page.

## Five-minute demo

1. **Analyst**: the dashboard shows 14 days of history: match rate ~96%, value at variance, open exceptions, and about 2h 45m saved today.
2. **Runs → Run now** for the latest closed date. It completes in seconds; open the run to see the DQ report with quarantined rows.
3. **Report**: filter to *Variance*, then **Export XLSX** (phones masked).
4. **Exceptions**: open an under-payment. See the sale, payment and posting side by side, the rule that fired, and the AI suggestion. Accept it and propose a write-off.
5. On **Approvals**, the approve button is disabled with "someone else must approve (segregation of duties)".
6. **Manager**: approve from the inbox. The adjustment is posted to the ERP as `ADJ-…` with its idempotency key.
7. A **timing** exception from the previous day shows *Resolved… by payment…* after the next run.
8. **Auditor**: phones are masked. **Audit log**: filter entity type `adjustment`, then **Verify integrity** (passes).
9. **Data uploads**: download the payments template, upload `samples/golden/payments_2026-09-22.xlsx`, see the preview flag 2 invalid rows with reasons, confirm, and run 2026-09-22. Counts match the answer key.
10. **Admin → AI oversight**: acceptance and override rates. Flip the kill switch; exceptions are still categorised by the rules.
11. **Manager → Sign-off**: clear the blockers, acknowledge carried exceptions, and sign off. The date is locked.

## Quality

```bash
make test      # Pest (unit, feature, architecture) + TypeScript check (needs the local dev setup)
make lint      # Pint + Larastan + ESLint
php artisan reconflow:ai-eval --stub       # AI eval baseline (use without --stub when a key is set)
```

The golden (65 items) and volume (2,531 items) answer keys in `samples/` are reproduced exactly. A performance test reconciles 50,000 sales in under 60 seconds.

## Documentation

| Doc | What's in it |
|---|---|
| [Architecture](docs/architecture.md) | Components, data flow, design decisions |
| [Reconciliation rules](docs/reconciliation-rules.md) | Every rule in plain English, with examples |
| [Data model](docs/data-model.md) | ER diagram and table purposes |
| [Controls matrix](docs/controls-matrix.md) | Risk → control → enforcement → evidence |
| [AI governance](docs/ai-governance.md) | Use register, model card, risk assessment, oversight, go-live gates |
| [Data protection](docs/data-protection.md) | Inventory, what leaves the system, masking, retention, DPIA-lite |
| [Runbook](docs/runbook.md) | Daily operation and incident procedures |
| [Deployment](docs/deployment.md) | Prerequisites, env vars, HTTPS, backups, upgrade and rollback |
| [API](docs/api.md) | Routes, JSON endpoints, mock source and ERP APIs |
| [Assumptions](docs/assumptions.md) | Business assumptions and scope notes |
| [Build notes](BUILD_NOTES.md) | Decision log, open questions with chosen defaults, limitations |

## Out of scope (production next steps)

Real source integrations, SSO/Azure AD, multi-currency/FX, multi-entity, high availability, formal retention policy sign-off, and penetration testing.
