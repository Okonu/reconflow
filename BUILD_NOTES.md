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
| 2026-09-24 | 2026-09-22 is seeded with **generated** data like every other day; the answer key is reproduced by uploading all three golden files with Replace | Owner | |
| 2026-09-24 | Extra quarantine rules: sales **business_date must equal the batch date**; payment timestamp must fall **inside the extract window** (D 00:00 → D+1 06:00 EAT) | Owner | Neither case occurs in the samples |
| 2026-09-24 | Amounts with **more than 2 decimals are quarantined** ("Amount has more than 2 decimal places"), never rounded; values within 1e-9 of a 2-dp number are treated as that number (Excel float noise); trailing zeros are fine | Owner | |
| 2026-09-24 | Duplicate keys: (1) exact duplicate row in a file → keep first, quarantine the copy ("Duplicate row: exact copy of row N"); (2) same transaction_id/journal_id with different content → quarantine **all** rows sharing it ("Conflicting records share <key>"); (3) key already loaded for the date on Append → preview says "already exists for this date: use Replace" and it is not imported; (4) two different POSTED journal_ids for one transaction_id → new status **DUPLICATE_POSTING** (roll-up Exception), REVERSED lines excluded. Payment duplicates stay R5 DUPLICATE_PAYMENT exactly as the answer keys define | Owner | |
| 2026-09-24 | **Payment ownership:** a payment belongs to its own timestamp date. The D run may match payments from D+1 00:00–06:00 against D sales; a match claims the payment for D. Unmatched grace-window payments are **not** reported as UNMATCHED_PAYMENT on D. The D+1 run excludes payments claimed by the latest D run version and can match D's open PENDING_TIMING sales (auto-resolving them). Re-running D after D+1 exists marks D+1 **STALE** (needs re-run) | Owner | Verified compatible: all 27 grace-window payments in both answer keys are MATCHED |
| 2026-09-24 | **Run now** defaults to the latest **closed** business date (window closed at D+1 06:00 EAT; same logic as the scheduled job). Any date can be chosen; an open date's run is **PROVISIONAL** (banner, timing exceptions expected, cannot be signed off) | Owner | |

## Phase 2: Data

### What exists
- **Ingestion module.** One schema class per source (`SalesSchema`, `PaymentsSchema`, `PostingsSchema`) is the single definition of columns, requirements, formats, dropdown values and rule text. The importer validates against it, and the template builder generates the xlsx templates from it (Data sheet with the reference header style, frozen header, number formats and data validation; Instructions sheet with the column table and example row).
- **Validation and quarantine.** Answer-key reasons use the exact wording. Owner rules: business date must match the batch; payment timestamp must fall inside D 00:00 → D+1 06:00 EAT; amounts with more than 2 decimals are quarantined (Excel float noise within 1e-9 is tolerated); exact duplicate rows keep the first; rows sharing a key with different content are all quarantined; Append refuses keys already loaded. Duplicate payment receipts are never quarantined (the engine flags them). Invalid rows are stored in `quarantined_rows` with their raw values and reasons; nothing is dropped.
- **Upload flow.** Upload guard (extension allow-list, content sniffing, macro detection by inspecting the xlsx package for `vbaProject.bin` / macro content types, 10 MB, 50,000 rows) → streaming parse (openspout for xlsx, only the `Data` sheet; native CSV) → header check (missing/unexpected/repeated columns block the import) → row validation → `upload_staging`. The preview shows counts, per-row status and reasons, an invalid-only filter, a duplicate-file warning (same checksum already imported for the date) and how many rows would be refused on Append. Confirm requires Replace or Append when the date already has data. Replace creates a new batch version and supersedes the old one, which stays for audit. Staged uploads expire after 24 h (hourly job). Every stage, confirm, cancel, expiry and supersede is audited.
- **Simulated source systems.** `/api/mock/{sales|payments|postings}?date=` (bearer token, labelled `simulated`). The payments extract follows the window rule. Two connectors behind `SourceConnector`: `MockSourceApiConnector` (HTTP, used in Docker by the worker) and `LocalMockSourceConnector` (in-process, used by the CLI and tests). Repeated pulls create new batch versions.
- **Synthetic data generator.** Deterministic (`Mt19937` seeded) and in exact template format. Right-skewed small-ticket amounts from a product catalogue. Anomalies at configurable rates: under/over-payments, rounding differences, blank/truncated/garbled references, split payments, duplicate receipts and same-reference repeats, missing payments (including after the 22:00 cut-off), next-morning late payments for after-cut-off sales, unknown payments, missing postings, posting mismatches, and each malformed-row type. `php artisan reconflow:seed --days --sales-per-day --seed --end [--no-ingest]`, and `--export=<date> --out=<dir>` writes template-format xlsx files that upload cleanly.
- **Demo operations.** On first boot with an empty database the entrypoint queues 14 days × 3,000 sales ending on the latest closed business date (about 30 s in the worker). The Admin-only reset (type RESET) truncates operational tables through a `ResetsDemoData` contract that each module implements, keeps users, roles and the audit log, audits the reset, and queues fresh data. The sample test pack is a zip of `samples/golden` and `samples/templates`.
- **Pages.** Data uploads (template downloads, sample pack, upload form, history, reset), upload preview, source batches and batch data-quality report. JSON is available on the batch endpoints for API clients.

### Verification
- Uploading the golden (xlsx and CSV) and volume files through the real HTTP upload flow gives exactly the answer keys' Quarantine sheets (source, record key, reason) and the expected valid counts (59/61/56 golden; 2,500/2,531/2,476 volume). CSV and xlsx produce identical parsed results.
- 192 Pest tests pass; Larastan 0 errors; Pint, ESLint, tsc and the Vite build are clean.
- In Docker the worker seeds through the HTTP connector; the mock API refuses requests without the token.

### Simplifications and known limitations
- Masking in previews and quarantine views is always on in this phase; the audited "reveal" action for `pii.unmask` holders arrives with the reconciliation report (Phase 3/6).
- Pages are functional but unpolished; Phase 6 does the visual pass.
- The upload suite is the slowest part of the tests (about 2 minutes total), mostly the 2,500-row volume file.

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
