# Build notes

Running log of technical choices, simplifications and known limitations. Newest phase first.

## Decision log

| Date | Decision | Decided by | Why |
|---|---|---|---|
| 2026-09-23 | Stack is **Laravel 13 / PHP 8.3** with PostgreSQL 16 | Owner | Owner's stack |
| 2026-09-23 | Frontend is **Inertia + React** (Laravel React starter kit), not a separate SPA + JSON API | Owner (on recommendation) | One codebase and auth model, less plumbing; page props shaped by API Resources |
| 2026-09-23 | **nwidart/laravel-modules**, one module per business function: Users, Rbac, Audit, AI, Ingestion, Reconciliation, ExceptionManagement, Adjustments, Notifications, Dashboard, DataProtection | Owner | Modular monolith; modules are business capabilities, not layers |
| 2026-09-23 | Minimal shared technical code lives in the root `app/` (Money cast, base controller, middleware, error envelope, health/metrics, authorization contracts) | Owner | Avoid a "core" module; keep the Laravel top-level layout |
| 2026-09-23 | **Permissions first → roles composed of permissions in the DB → users assigned roles; every action authorised by a Policy** | Owner | Access must be reconfigurable at runtime; never gate on role names |
| 2026-09-23 | Password hashing is **Argon2id** | Owner ("use the best") | OWASP first recommendation |
| 2026-09-23 | Transaction data retained for the taxable period, env-configurable, **default 7 years**, then anonymised (not deleted) | Owner | Tax record-keeping; Data Protection Act storage limitation |
| 2026-09-23 | Data-subject erasure **always anonymises** (no soft delete) | Owner | Simplicity and irreversibility |
| 2026-09-23 | AI payload logs retained **12 months** (env-configurable) | Owner | Replaces the brief's 90 days |
| 2026-09-23 | Audit log retained 7 years, then **automatically archived** to gzipped JSONL on a local volume behind a checkpoint event | Owner | Keeps the hash chain verifiable after old events leave the database |
| 2026-09-24 | The Laravel application sits at the **repository root** (no `backend/` folder) | Owner | Standard Laravel project layout |
| 2026-09-23 | Local development and tests use the owner's local PostgreSQL 16 (`reconflow`, `reconflow_test`) | Owner | |
| 2026-09-23 | No comments or docblocks in code | Owner | Names carry intent; explanations live in docs |
| 2026-09-23 | R6 posting check compares the posted amount with the **expected** amount | Answer key (`Rules reference` sheet) ranks above the brief | The brief said "matched amount"; the golden data gives the same result either way |

## Phase 1: Foundation

### What exists
- **Laravel 13 app** with nwidart modules `Users`, `Rbac`, `Audit`, `DataProtection`, plus the shared kernel in `app/`.
- **Authorization model.** Each module owns a permission enum implementing `App\Contracts\PermissionEnum` and registers it with `App\Support\Authorization\PermissionRegistry`. Each permission declares which seeded roles get it by default. `Rbac` syncs the registry into spatie's `permissions` table and seeds the default roles (`recon_analyst`, `finance_manager`, `auditor`, `admin`) as templates only; roles and their permissions are editable at runtime. Policies use the `AuthorizesPermissions` trait and check permission codes, never role names (an architecture test enforces this).
- **Lock-out guard.** The Administrator role is a protected system role. Permissions registered as protected (`roles.manage`, `users.manage`) can't be removed from it, and no change may take the number of active users holding them to zero.
- **Authentication.** Laravel session auth via Inertia login page; Argon2id; case-insensitive email; generic failure message; timing-equalised checks for unknown users; rate limiting per email + IP (5 per 5 minutes, configurable); session regenerated on login; inactive users are signed out on their next request. Login, failure, throttling and logout are all audited.
- **Hash-chained audit log** (`Modules/Audit`). `AuditLogger` serialises writes with a transaction-scoped Postgres advisory lock, scrubs personal data from payloads, and chains `sha256(prev_hash + canonical JSON)`. A database trigger rejects UPDATE/TRUNCATE and rejects DELETE unless the archive job flags its transaction. `ChainVerifier` detects edits, deletions, re-ordering and an unanchored start. `AuditArchiver` writes expired events to gzipped JSONL, appends a checkpoint event (file name, SHA-256, last archived hash) **before** deleting, so verification then anchors on the checkpoint. Archive files can be verified independently. `audit:archive` is scheduled daily.
- **Data protection.** Field inventory and classification for every source column (a test checks it against the provided templates); phone masking (`07•• ••• 456`); salted HMAC pseudonymisation; a Monolog processor that masks phone numbers, bearer tokens, JWTs, API keys and sensitive keys, tapped onto every writing log channel.
- **Shared kernel.** `MoneyCast` / `Money` on `brick/math` BigDecimal (floats rejected); request-ID middleware (Laravel Context, so the ID appears in every log line); security headers with a per-request CSP nonce (Ziggy's inline script carries it; no `unsafe-inline` for scripts); JSON error envelope for JSON clients; `/health`, `/ready`, `/metrics` (Prometheus, collectors contributed by modules through a container tag).
- **Frontend shell.** Inertia + React 19 + TypeScript + Tailwind v4 + shadcn/ui primitives; page resolver maps `Module/Page` to `Modules/<Module>/resources/js/Pages/<Page>.tsx`; shared `http.ts` client (CSRF, request IDs, error envelope); `format.ts`; `usePermissions`. Phase 1 pages: Login, Home placeholder, and bare Users, Roles and Audit lists (styling comes in Phase 6).
- **Infrastructure.** Multi-stage Dockerfile (composer → node/Vite → FrankenPHP runtime, non-root). Compose runs app, queue worker and scheduler from one image, plus Postgres (internal network, not published) and Caddy (automatic HTTPS, HSTS, `/metrics` blocked externally). Makefile. GitHub Actions: Pint, Larastan, ESLint, tsc, Vite build, Pest with coverage, then image push to GHCR, then an optional SSH deploy gated by `vars.DEPLOY_ENABLED`.

### Tests (85 passing)
Permission contract (every authenticated route is denied without its permission and allowed with only that permission; a coverage test fails if a new route isn't in the contract), role management and lock-out guard, authentication and rate limiting, audit chain tamper cases and archive/checkpoint, log masking, masking/pseudonymisation, money, system endpoints/headers/CSP, and architecture rules (strict types, no `env()` outside config, no DB facade in controllers, no comments, no role-name authorisation, every rendered Inertia page resolves to a file).

### Simplifications and known limitations
- `/metrics` currently exposes gauges computed at scrape time (audit event count). Request counters/latency histograms need shared storage (Redis/APCu); deferred until there's a reason to add Redis.
- The hash chain proves internal consistency. Detecting a complete rewrite of the chain requires anchoring the head hash externally; the plan is to include it in the daily summary notification (Phase 4).
- Login rate limiting uses the cache store (database in production), so it holds across app instances.
- Inertia's built-in page-existence check can't resolve module page names, so it's disabled and replaced by our own architecture test.
- TypeScript is pinned to 5.x because `typescript-eslint` doesn't support TypeScript 7 yet.
- Local `docker-compose` is v1.29, which has a known bug when *recreating* containers on newer Docker engines (`KeyError: 'ContainerConfig'`). Use `make down && make up`, or Compose v2.
