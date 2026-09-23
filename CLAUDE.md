# CLAUDE.md

These rules MUST be followed for ALL code in this repository. Violations are NOT allowed.
`BUILD_PROMPT.md` defines WHAT to build; this file defines HOW it must be built.

## Precedence and when in doubt
1. The repo owner's (user's) instructions
2. The answer keys in `samples/`, for reconciliation rule behaviour
3. This file, for how code is built
4. `BUILD_PROMPT.md`, for scope and features

**When in doubt, ALWAYS ask the user before proceeding.** This covers ambiguous requirements, conflicts between these sources, missing information, and any choice that changes behaviour, data, controls or scope. Never resolve a conflict silently and never guess. State the options and your recommendation, then wait for the answer.

## What this app is

ReconFlow is Tupande's daily sales reconciliation automation. It ingests sales, payment (M-Pesa/bank) and ERP data, matches them with deterministic rules, routes exceptions through a maker-checker workflow with AI-suggested triage, posts approved corrections, and keeps a hash-chained audit trail.
Stack: Laravel 13 + PHP 8.3 + PostgreSQL 16 with nwidart/laravel-modules and spatie/laravel-permission (backend), Inertia.js + React + TypeScript + Vite + Tailwind + shadcn/ui, based on Laravel's React starter kit (frontend), Docker Compose + Caddy (deploy).

---

# FRONTEND RULES

## 1. COMPONENTS = UI ONLY
- No data fetching
- No business logic
- No data transformations (filter/reduce/map beyond simple display)
- No money calculations: amounts, variances, totals and match rates come from the server, already computed

Allowed:
- rendering
- event handlers
- calling hooks
- formatting for display only (currency, dates) via `resources/js/lib/format.ts`

## 2. PAGES AND PROPS
- Pages are Inertia pages: module pages in `Modules/<Name>/resources/js/Pages`, shared layouts and UI in `resources/js`
- All page data arrives as Inertia props from the controller. Props are shaped server-side by API Resource classes (or DTOs), never raw Eloquent models
- Pages compose components and hooks; they hold no business logic
- Loading more data, refreshing and polling use Inertia (partial reloads, deferred props, polling), not ad-hoc requests
- Prop types are declared in TypeScript (`resources/js/types`) to match the Resources

## 3. MUTATIONS AND REQUESTS
- Writes go through Inertia's `router` / `useForm` to controller routes; validation errors come back from Form Requests and are rendered as returned
- The few non-page endpoints (JSON status polling, file previews, downloads) go through one shared client, `resources/js/lib/http.ts`, which handles CSRF, errors and request IDs
- No `fetch`/`axios` calls inside components; reusable client behaviour lives in hooks (`resources/js/hooks`)

## 4. SERVER = BUSINESS LOGIC
- All core logic (matching, metrics, filtering, status roll-ups, SLA/ageing, permissions) MUST be server-side
- The frontend must NEVER replicate backend logic

## 5. ACCESS-CONTROL SAFETY (MANDATORY)
- Roles and permissions are enforced ONLY on the backend
- The frontend may hide buttons based on the shared `auth.permissions` prop or per-item `can` flags from Resources, for UX only, never as the control
- Never let the frontend decide approval eligibility (maker ≠ checker, thresholds). Always call the server and render its answer
- Never display unmasked personal data unless the server returned it unmasked
- Never parse or validate uploaded files in the browser. The upload preview (header check, row errors, counts) always comes from the server

## 6. NO DUPLICATION
- If logic appears twice → extract to a hook, lib or shared component

## 7. FILE SIZE LIMITS
- Components > 200 lines MUST be split
- No monolithic files

---

# BACKEND RULES

## 8. MODULES AND LAYERS
- Every business function is an nwidart module in `Modules/<Name>/` with its own `app/`, routes, migrations and tests: Users, Rbac, Audit, AI, Ingestion, Reconciliation, ExceptionManagement, Adjustments, Notifications, Dashboard, DataProtection. Minimal shared technical code used by several modules (Money cast, base controller, request ID, JSON logging, security headers, error envelope, health/metrics) lives in the root `app/` and holds no business logic. Modules talk to each other through Actions, Services, DTOs and Events, never through each other's controllers.
- **Controllers** (`Http/Controllers`): thin. A Form Request validates, a Policy authorizes, an Action or Service does the work, an API Resource shapes the response. No business logic, no queries.
- **Form Requests** (`Http/Requests`): all input validation. They hand data to the domain as DTOs.
- **API Resources** (`Http/Resources`): all response shaping, including personal-data masking.
- **Actions** (`Actions`): one state-changing use case per class. They own the DB transaction and record the audit event.
- **Services** (`Services`): domain logic shared across actions (matching engine, data quality, templates, AI client).
- **DTOs** (`DTOs`): readonly, typed data passed between layers.
- **Enums** (`Enums`): statuses, categories, states, permissions. No magic strings.
- **Policies** (`Policies`): all authorization. Permissions are defined first (enum), roles are composed of permissions in the database, users are assigned roles. Policies check permissions plus contextual rules (e.g. maker ≠ checker); never role names.
- **Models** (`Models`): Eloquent models, casts, relationships and query scopes. Queries live in models/scopes or dedicated query classes, not in controllers.
- **Traits and helpers** (`Traits`, `Support`): reusable behaviour; if logic appears twice, extract it.
- **Connectors** (`Modules/Ingestion/Connectors`): the only place that talks to source systems.
- Matching rules are pure classes: no DB, network, clock or randomness.

## 9. FINANCIAL CONTROLS (MANDATORY)
- Money is `decimal(14,2)` in Postgres and `Brick\Math\BigDecimal` in PHP (via `App\Casts\MoneyCast`). Never `float`, never PHP float arithmetic on money.
- Every state change records an audit event through the Audit module's `AuditLogger`. Nothing writes to `audit_events` directly.
- Maker-checker and approval thresholds are enforced in the Adjustments policy and action, and covered by tests.
- ERP postings always carry an idempotency key.
- Matching is deterministic: the same input gives the same output. No randomness, no AI in matching.

## 10. AI AND DATA PROTECTION (MANDATORY)
- All AI calls go through the AI module's `ClaudeClient`, and every payload passes through `Redactor::redact()` first.
- The AI module can write only to `ai_suggestions`. It never changes workflow state.
- No personal data (phone numbers, names) in logs, notifications or default exports.
- No secrets in code. Everything comes from env through `config/*.php`; `env()` is only called inside config files.

## 11. UPLOADS AND SAMPLE DATA
- Upload templates are generated from the same schema classes the importer validates against. Never hand-maintain a second copy of the columns.
- Uploads go to staging first. Nothing reaches live tables until the user confirms.
- `samples/` is read-only reference data. Never edit the golden or volume files or their answer keys to make a test pass: fix the code, or raise the discrepancy.

## 12. TESTS AND CODE QUALITY
- Tests are written in Pest. Every matching rule, workflow transition, policy and control has a test.
- The engine must reproduce `samples/golden/expected_results_2026-09-22.xlsx` and `samples/volume/expected_results_2026-09-22_volume.xlsx` exactly. A rule change that alters expected results needs an explicit, reviewed update to the answer key and an entry in the decision log.
- Every PHP file declares `strict_types=1`. Code passes Pint and Larastan.
- No comments or docstrings in code. Names carry the intent; explanations belong in `docs/` and `BUILD_NOTES.md`.
- `make test` must pass before any task is considered done.

---

# PROCESS

When implementing or refactoring ANY feature:

STEP 1: ANALYZE
- Read the relevant files
- Identify logic in the wrong layer
- Identify duplication

STEP 2: PLAN
- Decide what goes to controllers, form requests, policies, actions, services, DTOs, resources and models (backend) and pages, hooks, components (frontend)

STEP 3: IMPLEMENT
- Create or update the layers in order: backend domain (enums, DTOs, models, services, actions, policies) → HTTP (requests, controllers, resources, routes) → page props and types → hook → component

STEP 4: REFACTOR
- Move logic out of components and controllers
- Simplify the UI layer

STEP 5: VERIFY
- Check all rules are followed
- Run `make test` and the frontend type check/build
- Ensure there are no regressions and the layout is responsive

Claude MUST NOT skip steps.

Claude MUST use tools for:
- Reading existing files before editing
- Searching for duplicate logic
- Understanding the current architecture

DO NOT:
- blindly rewrite files
- assume structure without reading

ALWAYS:
- inspect → then modify

Before completing ANY task, Claude MUST confirm:

[ ] No business logic or money calculations in components
[ ] No data fetching in components; page data arrives as Inertia props shaped by Resources
[ ] Mutations via Inertia router/useForm; the few JSON calls via the shared http client
[ ] Controllers are thin; validation in Form Requests, authorization in Policies, business logic in Actions/Services
[ ] Money uses BigDecimal/decimal(14,2); no floats
[ ] State changes are audited; maker-checker enforced server-side
[ ] AI payloads redacted; no personal data in logs
[ ] Uploads parsed server-side, staged before import; templates generated from schemas
[ ] No duplicated logic
[ ] No comments in code; strict types; Pint and Larastan pass
[ ] Tests pass; layout is responsive; no console errors
[ ] Every doubt or conflict was raised with the user, none resolved silently

# 🚨 If any rule is violated, the implementation is INVALID.

## Commands

```bash
make up        # docker compose up (app + worker + scheduler + postgres + caddy)
make dev       # composer dev: php artisan serve + queue worker + vite dev server
make test      # backend tests (Pest) + frontend type check
make seed      # generate 14 days of synthetic data and run reconciliations
make lint      # pint + larastan + eslint
```
