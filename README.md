# ReconFlow

Daily sales reconciliation for Tupande. ReconFlow pulls sales, payments and ERP postings each morning and matches them with deterministic rules. It turns every discrepancy into an owned exception with an SLA, and routes corrections through maker-checker approval before posting them to the ERP with an idempotency key. Everything is recorded in a hash-chained audit trail. An optional AI assistant suggests causes and next steps; people decide.

> **Synthetic data only.** This system is a demonstration. No real customer data is used.

![Screenshot placeholder](docs/screenshot.png)

## Stack

Laravel 13 (PHP 8.3) with function-specific modules, PostgreSQL 16, Inertia + React 19 + TypeScript + Tailwind + shadcn/ui, FrankenPHP behind Caddy, Docker Compose. The AI assistant uses the official Anthropic PHP SDK (Claude).

## Quick start (Docker)

```bash
cp deploy/.env.example deploy/.env      # set APP_KEY, POSTGRES_PASSWORD, PII_HASH_SALT, DEMO_PASSWORD, SOURCE_SYSTEMS_TOKEN
make up                                  # app + worker + scheduler + postgres + caddy
```

Open `https://localhost` (or your `HTTPS_PORT`). On first boot the app seeds 14 days of synthetic data (3,000 sales a day) and reconciles them, so the dashboard opens populated.

AI is optional. Set `ANTHROPIC_API_KEY` to enable it, or `AI_DRIVER=stub` for an offline, clearly labelled rules stub. Without either, everything works rules-only.

## Local development

```bash
cp .env.example .env && php artisan key:generate
make install
make migrate seed
make dev                                 # php artisan serve + queue worker + vite
```

## Demo logins

Password: the value of `DEMO_PASSWORD`.

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
make test      # Pest (unit, feature, architecture) + TypeScript check
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
