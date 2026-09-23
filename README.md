# ReconFlow

Daily sales reconciliation automation for Tupande: automated ingestion, deterministic matching, exception workflow with maker-checker approvals, controlled posting of corrections, and a hash-chained audit trail.

> **Synthetic data only.** This system is a demonstration. No real customer data is used.

![Screenshot placeholder](docs/screenshot.png)

## Stack

Laravel 13 (PHP 8.3) with domain modules (nwidart), PostgreSQL 16, Inertia + React + TypeScript + Tailwind + shadcn/ui, FrankenPHP behind Caddy, Docker Compose.

## Quick start (Docker)

```bash
cp deploy/.env.example deploy/.env      # fill in APP_KEY, POSTGRES_PASSWORD, PII_HASH_SALT, DEMO_PASSWORD
make up                                  # app + worker + scheduler + postgres + caddy
```

Open `https://localhost` (or the `HTTPS_PORT` you set).

## Local development

```bash
cp .env.example .env && php artisan key:generate
make install
make migrate seed
make dev                                 # php artisan serve + queue + vite
```

## Demo logins

Password: the value of `DEMO_PASSWORD`.

| Login | Seeded role |
|---|---|
| `analyst@demo` | Recon Analyst |
| `manager@demo` | Finance Manager |
| `auditor@demo` | Auditor |
| `admin@demo` | Administrator |

Roles are templates. Permissions can be changed and new roles created at runtime.

## Quality

```bash
make test    # Pest + TypeScript check
make lint    # Pint + Larastan + ESLint
```

See `BUILD_NOTES.md` for decisions and limitations, and `CLAUDE.md` for the engineering rules.
