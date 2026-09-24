# Build Prompt — Sales Reconciliation Automation ("ReconFlow")

> Work phase by phase; stop at each checkpoint, show what was built, and wait for review before continuing.
>
> **Place `CLAUDE.md` at the repo root before starting.** It defines the mandatory engineering rules (layering, financial controls, AI/data-protection rules, verification checklist). Follow it for every phase; if this prompt and `CLAUDE.md` conflict, `CLAUDE.md` wins, so flag the conflict.

---

## 0. Your role and the goal

You are a senior full-stack engineer building a **production-ready daily sales reconciliation automation** for a growing organisation (Tupande) that processes high volumes of daily cash sales across several operational systems.

Today the reconciliation:
- runs once a day after operational data becomes available
- takes ~3 hours: people manually collect data from multiple systems, reconcile in spreadsheets and local scripts, manually investigate and categorise exceptions, then chase follow-up, approvals and postings before sign-off.

Build an application that replaces this with: **automated ingestion → deterministic matching → exception workflow with maker-checker approvals → controlled posting of corrections → dashboard, reporting and an audit trail.**

The audience for the demo is **senior business leaders**, so the UI must be clean, calm and self-explanatory. The codebase will also be reviewed by engineering leadership, so it must show strong engineering practice: tests, typed code, migrations, CI, containerisation, structured logging, configuration via environment.

**Time budget is tight (~10 focused hours).** Prefer simple, solid and finished over broad and half-done. Don't gold-plate. If a feature threatens the timeline, stub it cleanly behind an interface and note it in `BUILD_NOTES.md`.

---

## 1. Non-negotiable design principles

1. **Matching is deterministic and rule-based.** The same inputs always give the same results. No LLM is involved in matching, amounts or status decisions.
2. **AI is assistive only.** Claude suggests an exception category, probable root cause and recommended next action, with a confidence score and rationale. A human must accept or override the suggestion. Every suggestion and the human's decision are stored. If the AI call fails or is disabled, the system works fully without it (rules-only categorisation).
3. **Segregation of duties.** The user who proposes an adjustment can never approve it. Enforce this server-side and test it.
4. **Append-only audit log.** Every state change (ingestion, run, match override, exception transition, AI suggestion, approval, posting, sign-off, login) writes an immutable audit event. Hash-chain the events (each event stores `prev_hash` and `hash`) and add an endpoint that verifies the chain.
5. **Idempotent, re-runnable runs.** A run for a given business date can be re-executed safely. A new run version supersedes the old one and keeps the history. Postings to the ERP use idempotency keys, so the same correction is never posted twice.
6. **Data quality before matching.** Validate every source batch (schema, required fields, duplicates, row counts, freshness). Quarantine bad rows with reasons instead of silently dropping them. Owner decisions 2026-09-24: sales business_date must equal the batch date; payment timestamps must fall inside the extract window; amounts with more than 2 decimals are quarantined, never rounded (values within 1e-9 of a 2-dp number count as that number); an exact duplicate row keeps the first and quarantines the copy; rows sharing a sales transaction_id or journal_id with different content are all quarantined; keys already loaded for the date on Append are refused with "already exists for this date: use Replace".
7. **Everything configurable via env/DB config**, not hard-coded: tolerances, date windows, schedule, approval thresholds, AI on/off, AI model.

---

## 1A. AI governance and data protection (mandatory, build these in)

The app handles personal data (customer phone numbers, payer details) and financial records, and it sends data to an external AI provider. Treat Kenya's **Data Protection Act 2019** as the governing law (lawful purpose, data minimisation, security safeguards, restrictions on cross-border transfer). Align AI controls with the **NIST AI Risk Management Framework** and **ISO/IEC 42001** principles. Don't claim certification; reference them as the design basis.

### AI governance
1. **Human in the loop, always.** AI output is a suggestion, never an action. No state change, adjustment, posting or sign-off can be triggered by AI output. Enforce this in code: the AI module has no write access to workflow tables other than `ai_suggestions`.
2. **Data minimisation to the model.** Before any AI call, a `redact()` step pseudonymises personal data:
   - phone numbers → salted hash token (e.g. `CUST_7f3a`)
   - names removed
   - agent IDs tokenised.

   Send only the fields the task needs (amounts, timestamps, statuses, rule fired, channel, region). A unit test must assert that no raw phone number or name ever appears in an outbound AI payload.
3. **Transparency.** Every AI suggestion is visibly labelled "AI-generated suggestion". It shows the model name, confidence and rationale. Users can always see what the rules said vs. what the AI suggested.
4. **Traceability.** Log per call: prompt template version, model ID, redacted input hash, output, latency, token usage, and the human decision (accepted / overridden + override reason, which is required). Store prompt templates in the repo, versioned (`Modules/AI/resources/prompts/v1_triage.md`); changing a prompt is a code change that goes through review and CI.
5. **Quality monitoring.** Track and show on an admin "AI oversight" panel: suggestion acceptance rate, override rate by category, average confidence, failure/timeout rate. Include a small labelled evaluation set (`Modules/AI/tests/fixtures/ai_eval/`, ~20 cases) and a script that scores the current prompt against it. It runs manually or in CI when a key is present, and is skipped otherwise.
6. **Kill switch and graceful degradation.** Admin can turn AI off instantly (audited); the system falls back to rules-only categorisation with no loss of function.
7. **Accountability.** The settings screen records a named AI owner (role) and the date of the last AI review. Document the AI use case in an **AI use register** entry (purpose, data used, risk level, controls, owner, review cadence).
8. **Provider controls.** API key only via env/secrets, never logged. Document that the provider's commercial API terms (data use, retention) and cross-border transfer must be confirmed and approved by the Data Protection Officer before production use. This is an explicit go-live gate in the docs.

### Data protection and data sharing
1. **Data inventory and classification:** tag every field as Public / Internal / Confidential / Personal. Personal data = customer phone, payer phone, names.
2. **Least privilege:** RBAC per role (section 5). **Auditor/Viewer sees personal data masked** (e.g. `07•• ••• 123`); unmasking requires the Analyst/Manager role, and every unmask action is audited.
3. **Data sharing is explicit:**
   - Exports (CSV/XLSX) mask personal data by default. An unmasked export needs the Manager role and a stated reason, and is audited.
   - Notifications (Slack/email) contain aggregates only, never personal data or row-level details.
4. **No personal data in logs.** A Monolog processor masks phone numbers and tokens. Test it.
5. **Security:** HTTPS only (Caddy), secure headers, CORS locked to the app origin, login rate limiting, Argon2id password hashing, Laravel session authentication with CSRF protection and a short idle timeout, and secrets only in env. The database is not exposed publicly; backups are encrypted. Note encryption at rest as a hosting requirement.
6. **Retention** (owner decision, 2026-09-23; replaces the original 13-month / 90-day defaults). Every period is env-configurable, and every retention job is itself audited:
   - **Transaction data** (sales, payments, postings, including phone numbers): kept for the taxable period, default **7 years**. After that, records are **anonymised** (personal fields replaced), not deleted.
   - **Data-subject erasure requests:** an Admin action that **always anonymises** the customer's personal data across all records (no soft-delete). It requires a reason and is audited.
   - **AI payload logs:** default **12 months**, then deleted; aggregate metrics are kept.
   - **Audit log:** default **7 years**, then **automatically archived**. A scheduled job writes expired events to a gzipped JSONL file on a local volume (`AUDIT_ARCHIVE_DIR`), appends a checkpoint event (file name, file SHA-256, last archived hash), then removes the archived events, so the hash chain stays verifiable from the checkpoint. Audit events never contain raw personal data, so erasure never touches the audit log.
7. **Synthetic data only.** The demo uses generated data. State clearly in the UI footer and README that no real customer data is used.

---

## 2. Tech stack (use exactly this)

- **Backend:** PHP 8.3, Laravel 13, PostgreSQL 16, domain modules with `nwidart/laravel-modules`, Eloquent + migrations, Laravel session auth, `spatie/laravel-permission` (permissions → roles → users) with Laravel Policies, `brick/math` for money, PhpSpreadsheet for xlsx, Laravel scheduler and queues (database driver), the official `anthropic-ai/sdk` PHP SDK, Monolog JSON logs, Argon2id password hashing, Pest for tests, Larastan (PHPStan) for types, Pint for style. Code is organised into Form Requests, Actions, Services, DTOs, Enums, Policies, API Resources, Models, Traits and helpers.
- **Frontend:** Inertia.js v3 + React 19 + TypeScript 5 + Vite, based on Laravel's React starter kit (React 19 replaces the brief's React 18; TypeScript stays on 5.x until typescript-eslint supports 7): Tailwind CSS, shadcn/ui components, Recharts, lucide-react icons. Pages live in each module's `resources/js/Pages`; page props are shaped by API Resource classes. Built by Vite into `public/build` and served by Laravel: **one app image.** (Owner decision 2026-09-23: Inertia replaces the separate SPA + JSON API.)
- **AI model:** read `ANTHROPIC_MODEL` from env (default `claude-sonnet-5`). Use tool-use / structured JSON output validated against a schema. Timeout 20s, 2 retries, then fall back.
- **Infra:** multi-stage Dockerfile (node build → composer install → FrankenPHP runtime), `docker-compose.yml` (app, queue worker and scheduler from the same image, postgres, caddy reverse proxy with automatic HTTPS), GitHub Actions CI (Pint, Larastan, Pest, frontend type-check/build, build image, push to GHCR), plus an optional deploy job over SSH that runs `docker compose pull && docker compose up -d` on a Linux server.

---

## 3. Domain model

### 3.1 Source systems (simulated)

Real integrations aren't available, so build **three mock source APIs** inside the app under `/mock/...` (clearly labelled "simulated source systems"). Put **connector classes** behind a `SourceConnector` interface, so a real integration can replace a mock later without touching matching code. Also support **manual file upload (Excel .xlsx or CSV)** for each source, with downloadable templates and a validation preview before import (section 3.5).

| Source | Represents | Key fields |
|---|---|---|
| `sales` | Sales / order system (sales made by field agents and shops) | `transaction_id`, `business_date`, `timestamp`, `agent_id`, `customer_phone`, `region`, `product_sku`, `expected_amount`, `currency`, `payment_reference` |
| `payments` | Payments received (mobile money + bank) | `payment_id` (provider receipt no.), `timestamp`, `channel` (`MOBILE_MONEY`/`BANK`), `payer_phone`, `amount`, `currency`, `reference` (usually the transaction_id but sometimes missing or mistyped) |
| `postings` | ERP / general-ledger postings | `journal_id`, `posting_date`, `transaction_id`, `account`, `amount`, `currency`, `status` |

Currency: USD (single-currency; note multi-currency as out of scope).

### 3.2 Database tables (minimum)

`users`, `roles`, `source_batches` (one per source per business date per ingestion: checksum, row counts, DQ summary), `sales_records`, `payment_records`, `posting_records`, `quarantined_rows`, `upload_staging` (upload id, source, business_date, filename, checksum, uploaded_by, parsed rows JSON, row-level errors, expires_at, state: STAGED/CONFIRMED/CANCELLED/EXPIRED), `recon_runs` (business_date, version, status, started/finished, triggered_by, rule_config snapshot, summary metrics, superseded_by), `recon_results` (one row per reconciled item: keys from each source, expected, actual, posted, variance, status, rule_id, match_confidence), `exceptions` (linked to result; category, severity, owner, due_at, state), `exception_events` (comments, transitions), `ai_suggestions` (inputs hash, model, output JSON, latency, accepted/overridden, by whom), `adjustments` (proposed correction: type, amount, reason, proposed_by, approved_by, state, idempotency_key), `erp_postings_out` (what we posted back), `run_signoffs`, `audit_events` (hash-chained), `config` (versioned rule config).

### 3.3 Reconciliation statuses

Detailed status → roll-up shown to business users, mirroring the brief's report (`Match` / `Variance` / `Exception`):

| Detailed status | Roll-up | Meaning |
|---|---|---|
| `MATCHED` | Match | Sale, payment and posting agree within tolerance |
| `MATCHED_TOLERANCE` | Match | Agree within configured rounding tolerance (e.g. ≤ $0.50) |
| `MATCHED_SPLIT` | Match | Multiple payments sum to the expected amount |
| `MATCHED_FUZZY` | Match (flagged) | Payment had a missing/incorrect reference; matched on phone + amount + time window; needs confirmation |
| `VARIANCE` | Variance | Payment amount ≠ expected beyond tolerance (under/over payment) |
| `POSTING_MISMATCH` | Variance | Posted amount ≠ matched amount |
| `MISSING_PAYMENT` | Exception | Sale with no payment |
| `PENDING_TIMING` | Exception (soft) | Sale late in the day with no payment yet; carried forward and auto-resolved if the payment arrives in the next run's window |
| `UNMATCHED_PAYMENT` | Exception | Payment with no corresponding sale (the brief's "Expected: Missing") |
| `MISSING_POSTING` | Exception | Sale and payment match but nothing is posted in the ERP |
| `DUPLICATE_PAYMENT` | Exception | Same receipt twice, or same reference + amount within 5 minutes |
| `MATCHED_PRIOR_DAY` | Match (prior day) | A carried PENDING_TIMING sale or an open MISSING_PAYMENT from the last 7 days cleared by a payment on this date (exact reference, split, or a confirmed manual match tagged "Paid late (D+n), manually matched"); reported in a separate section, excluded from the day's metrics. A carried sale matched by R3 is MATCHED_FUZZY in that section, flagged as needing confirmation. Owner decisions 2026-09-24 |
| `DUPLICATE_POSTING` | Exception | Two different POSTED journal lines for the same transaction_id (REVERSED lines excluded). Owner decision 2026-09-24 |

### 3.4 Matching rules (apply in this order; each result records `rule_id`)

- **R1 Exact key:** join sales ↔ payments on `transaction_id == reference`.
- **R2 Split:** for sales not matched by R1, group payments by reference; if the sum equals expected within tolerance → `MATCHED_SPLIT`.
- **R3 Fuzzy:** for remaining sales, match unreferenced payments on `customer_phone == payer_phone`, amount within tolerance, and timestamp within ±N hours (default 24) → `MATCHED_FUZZY` with a confidence score. One-to-one only; ties go to exceptions.
- **R4 Amount check:** for matched pairs, |expected − actual| ≤ tolerance → match, else `VARIANCE` (store variance amount and %).
- **R5 Duplicates:** detect duplicate payments before matching; the extra copy → `DUPLICATE_PAYMENT`.
- **R6 Posting check:** for matched pairs, look up the ERP posting by `transaction_id`: absent → `MISSING_POSTING`; amount differs → `POSTING_MISMATCH`.
- **R7 Leftovers:** unmatched sales → `MISSING_PAYMENT`, or `PENDING_TIMING` if the sale timestamp is within the configured cut-off window; unmatched payments → `UNMATCHED_PAYMENT`.

Implement each rule as a **pure class** in `Modules/Reconciliation/app/Rules/` operating on indexed in-memory collections (no DB, clock or randomness), with its own unit tests. The engine composes them and must handle **50,000 sales/day in under 60 seconds.**

### 3.5 Manual upload: templates, preview, confirm

Finance users and the interview panel must be able to test the system by uploading their own files. **Reference files are provided in `samples/`**: copy the folder into the repo root unchanged.

```
samples/
  templates/   sales_upload_template.xlsx, payments_upload_template.xlsx, erp_postings_upload_template.xlsx
  golden/      sales_ / payments_ / erp_postings_2026-09-22.xlsx (+ csv/), expected_results_2026-09-22.xlsx
  volume/      ~2,500-sale day for the same date + expected_results_..._volume.xlsx
```

- **Templates.**
  - Each template has a `Data` sheet (exact headers, formats, dropdown validation) and an `Instructions` sheet (field rules and an example row).
  - The app serves them at `GET /api/uploads/templates/{source}.xlsx`, **generated at runtime from the same schema classes the importer validates against**, so templates and validation can never drift apart.
  - Use the provided files as the layout reference (sheet names, header order, header styling, data validation, instructions content).
- **Upload flow:**
  1. Choose source (sales / payments / postings) and business date.
  2. Upload `.xlsx` or `.csv` (max 10 MB / 50,000 rows).
  3. The server parses the file into a **staging area**. Nothing reaches the live tables yet.
  4. **Preview screen** shows:
     - a header check (missing, extra or renamed columns block the import, with a clear message)
     - counts of rows read / valid / invalid
     - a table of rows with a per-row status chip and error reasons, with a filter to show invalid rows only
     - a warning if the same file (by checksum) was already imported for that date.
  5. **Confirm import** creates a `source_batch`, loads valid rows and quarantines invalid rows with reasons. **Cancel** discards the staged upload.
  6. After import, offer **"Run reconciliation for this date"**.
- **Replace vs. append:**
  - Importing a source for a date that already has data asks the user to choose **Replace** or **Append**.
  - Replace creates a new batch version and keeps the old one for audit.
  - Re-running reconciliation afterwards creates a new run version (section 1, principle 5).
- **Where parsing happens:** in the backend only, with openspout (streaming) for .xlsx and PHP's native CSV reader for .csv; PhpSpreadsheet only generates the templates. **Only the `Data` sheet is read.** Reject `.xlsm` and other macro-enabled or unknown types, and anything whose content type doesn't match its extension. Staged uploads expire after 24h (scheduled purge).
- **Safety:**
  - Every CSV/XLSX export escapes values starting with `=`, `+`, `-` or `@` to prevent formula injection.
  - Uploads, confirmations, cancellations and replacements are audited.
  - Only Analyst+ can upload; Auditor can view the upload history.
- **Sample data download:**
  - A "Download sample test pack" button (a zip of `samples/golden` + `samples/templates`) on the uploads page, so the panel can test end-to-end.
  - An Admin-only **"Reset demo data"** action truncates operational tables and re-seeds (audited, with a confirmation prompt).

---

## 4. Exception workflow

- Each non-match result creates an **exception** with:
  - a rule-based default **category**: `Timing difference`, `Customer under/over-payment`, `Missing/incorrect reference`, `Duplicate payment`, `ERP posting failure`, `Unknown payment`, `Data quality`
  - a **severity** by value bands (configurable)
  - an **owner** (auto-assigned by region/round-robin among Preparers)
  - an **SLA due time** (e.g. 24h high, 48h medium).
- **States:** `OPEN → IN_REVIEW → (RESOLVED_NO_ACTION | ADJUSTMENT_PROPOSED) → PENDING_APPROVAL → (APPROVED → POSTED → RESOLVED) | REJECTED → IN_REVIEW`. `PENDING_TIMING` auto-resolves when a later run matches it.
- **AI triage (async, per exception, batched):** send the exception's source records, the rule that fired, and a small history of similar resolved exceptions. Return JSON: `{category, probable_root_cause, recommended_action, confidence (0–1), rationale}`. Show it in a clearly labelled "AI suggestion" panel with Accept / Override buttons. Never auto-apply.
- **Adjustments** (e.g. write-off of under-payment, reallocate payment to sale, reverse duplicate, re-post to ERP):
  - A Preparer proposes and an Approver approves; the proposer can never approve.
  - Amounts above a threshold (default $1,000) need the **Finance Manager** role.
  - On approval, post to the mock ERP endpoint with an idempotency key and record the response.
- **Run sign-off:** an Approver can sign off a business date only when there are no open High-severity exceptions (others can be acknowledged and carried forward with a comment). A signed-off run is locked.

---

## 5. Roles and demo users (seeded)

| Role | Can do | Demo login |
|---|---|---|
| Recon Analyst (Preparer) | Trigger runs, upload files, work exceptions, propose adjustments | `analyst@demo` |
| Finance Manager (Approver) | Everything the Analyst can do, plus approve/reject adjustments and sign off runs | `manager@demo` |
| Auditor / Viewer | Read-only everything, including the audit log and exports | `auditor@demo` |
| Admin | Users, config (tolerances, thresholds, schedule, AI toggle) | `admin@demo` |

Passwords come from env (`DEMO_PASSWORD`). Access control is **permissions first** (owner decision): permissions are defined by each module, roles are composed of permissions in the database and can be created or changed at runtime, users are assigned roles, and every action is authorised by a Laravel Policy that checks permissions, never role names. The four roles above are seeded templates only. The Administrator role is a protected system role that always keeps user and role management, and no change may leave zero active administrators. The UI hides actions the user can't take.

---

## 6. Scheduling and operations

- A Laravel scheduled command runs daily at a configured time (default 06:00 Africa/Nairobi): pull all three sources for the previous business date, run DQ checks, run reconciliation, run AI triage, send the summary. There is also a **"Run now"** button (Analyst+).
- If a source is late or empty, don't run silently: mark the run `BLOCKED_DATA`, alert, and retry at a configured interval.
- **Run now** defaults to the latest closed business date (its payment window closed at D+1 06:00 EAT), the same logic as the scheduled job. Any date can be chosen; a run for a date whose window is still open is `PROVISIONAL` (banner, timing exceptions expected, cannot be signed off). Re-running a date after the next date has been run marks the next date's run `STALE` (needs re-run). Owner decisions 2026-09-24.
- **Daily summary notification:**
  - Metrics are computed deterministically; the LLM only phrases a 3–4 sentence narrative from those numbers, and a template is used if AI is off.
  - Deliver to an in-app notifications panel, and optionally via Slack webhook or SMTP if env vars are set.
- **Observability:**
  - JSON logs (Monolog) with request/run IDs
  - `/health` (liveness) and `/ready` (DB check)
  - `/metrics` (Prometheus format: run duration, match rate, exception counts, AI latency/failures)
  - a failed run raises an alert through the same notification channel.

---

## 7. Frontend pages

Clean, professional, light theme, one accent colour, generous whitespace. Number formatting: `$1,500.00`. Every page has an empty state and a loading state.

1. **Login.**
2. **Dashboard (home):**
   - KPI tiles: match rate %, value reconciled, value at variance, open exceptions, overdue exceptions, "time saved vs manual baseline"
   - 14-day trend chart (match rate and exception count)
   - exceptions by category (bar)
   - exception ageing buckets
   - latest run card: status, duration, data-quality summary, sign-off state.
3. **Runs:**
   - history table with version, status, duration and counts; "Run now"
   - run detail showing per-source ingestion and DQ report (rows in, rows quarantined and why).
4. **Reconciliation report:**
   - the brief's table (Transaction ID | Expected amount | Actual amount | Posted amount | Status), with detailed-status chip, filters, search, pagination
   - **export to CSV and XLSX.**
5. **Exceptions queue:** filter by category, severity, owner, state, overdue; bulk assign.
6. **Exception detail:**
   - side-by-side source records (sale / payment(s) / posting)
   - the rule that fired and why
   - AI suggestion panel (accept/override)
   - comment thread and timeline
   - action buttons by role and state
   - adjustment form.
7. **Approvals inbox (Approver):** pending adjustments with maker, amount, reason; approve/reject with comment.
8. **Audit log:** filterable table and export; a "Verify integrity" button that calls the hash-chain check and shows the result.
9. **Settings (Admin):** tolerances, fuzzy window, timing cut-off, severity bands, approval threshold, schedule, AI on/off, model name. Changes are versioned and audited.
10. **AI oversight (Admin/Manager):** acceptance/override rates, confidence, failures, AI owner and last review date, kill switch.
11. **Data uploads (Analyst+):** template download buttons per source; the upload form (source, business date, file); **preview** (header check, valid/invalid counts, row table with per-row errors and an invalid-only filter, duplicate-file warning); Confirm / Cancel; Replace vs. Append prompt; upload history with who/when/rows/outcome; "Download sample test pack"; Admin-only "Reset demo data".

---

## 8. Synthetic data generator

CLI: `php artisan reconflow:seed --days=14 --sales-per-day=3000 --seed=42`. It generates realistic data for all three sources and writes it into the mock source stores, with configurable anomaly injection rates:
- ~2% under/over-payments
- ~1.5% missing or mistyped references (fuzzy candidates)
- ~1% split payments
- ~0.5% duplicates
- ~1% missing payments (some near cut-off → timing)
- ~0.7% unknown payments
- ~1% missing ERP postings
- ~0.3% posting amount mismatches
- ~0.5% malformed rows (bad dates, negative amounts, missing IDs) for DQ quarantine.

Amount distribution: realistic small-ticket retail, roughly $5–$2,500, right-skewed.

**Golden and volume datasets are provided; don't regenerate them.**
- `samples/golden/` holds 59 valid sales, 61 valid payments (including 2 duplicates to be detected) and 56 valid postings for 2026-09-22, plus 6 malformed rows. Together they cover every rule R1–R7, every status, a fuzzy-match tie, a late-night payment and each quarantine reason.
- `expected_results_2026-09-22.xlsx` is the answer key: 65 reconciliation items with expected status and rule, plus 6 quarantine rows. Its "Rules reference" sheet defines the rules, including **precedence: VARIANCE is reported before posting checks**.
- The golden regression test must reproduce the answer key **exactly**: same status for every transaction/payment and the same quarantine count. The same applies to `samples/volume/`, which has 2,531 items.
- Parameters the answer keys assume (make these the config defaults):
  - tolerance $0.50, inclusive
  - fuzzy window ±24h
  - timing cut-off 22:00 on the business date
  - duplicate window 5 minutes
  - payments extract window: business date 00:00 to next day 06:00 EAT
  - payment ownership (owner decision 2026-09-24): a payment belongs to its own timestamp date. The D run may match payments from D+1 00:00–06:00 against D sales, and a match claims the payment for D. Unmatched grace-window payments are not reported as UNMATCHED_PAYMENT on D. The D+1 run excludes payments claimed by the latest D run version and can match D's open PENDING_TIMING sales. Re-running D after D+1 exists marks D+1 STALE
  - fuzzy matching is one-to-one: any tie leaves both sales as MISSING_PAYMENT and the payment as UNMATCHED_PAYMENT.

The generator's output must use **exactly the template schemas**, and it must be able to export any seeded day to xlsx in template format (`php artisan reconflow:seed --export=2026-09-22 --out=./export/`). That way seeded data, uploads and templates share one format.

On first boot with an empty DB, auto-seed 14 days and run reconciliations, so the demo opens on a populated dashboard.

---

## 9. Testing (must pass in CI)

- Unit tests per matching rule (R1–R7), including boundary cases on tolerance and time window.
- Golden-dataset regression test.
- Data-quality validator tests (each quarantine reason).
- Upload tests:
  - template round-trip (download template → fill → upload → preview is all valid)
  - header mismatch is blocked
  - invalid rows are shown with reasons, then quarantined on confirm
  - duplicate-file warning
  - CSV and XLSX give identical results
  - `.xlsm` is rejected
  - staged data is invisible to reconciliation until confirmed
  - replace creates a new batch version
  - formula-injection escaping on export
  - importing the golden files via the upload API and running reconciliation reproduces the answer key.
- Workflow state-machine tests, including **maker ≠ checker** and the approval threshold.
- Idempotency tests: re-running a date creates a new version and no duplicate postings; re-approving doesn't double-post.
- Audit hash-chain tests (tamper detection).
- HTTP tests for RBAC: a permission contract lists every authenticated route with the permission it needs; each route is denied to a user holding every other permission and allowed to a user holding only that one, and a coverage test fails if a route is missing from the contract.
- Architecture tests (Pest `arch`): strict types everywhere, `env()` only in config files, no DB facade in controllers, no comments in code, no role-name authorisation, every rendered Inertia page resolves to a file.
- AI client tests with the SDK mocked: valid JSON, invalid JSON, timeout → fallback.
- Governance tests: outbound AI payload contains no raw personal data; AI module cannot change workflow state; overrides require a reason; kill switch works; Auditor sees masked data; unmasked export blocked for Analyst; logs contain no phone numbers.
- Performance test (Pest group `slow`): 50k sales reconcile in under 60s.
- Frontend: minimal (TypeScript type check + Vite build) is acceptable.

Generate `docs/test-report.md` from the latest run (counts, coverage %).

---

## 10. Repository layout

The Laravel application sits at the repository root (owner decision 2026-09-24).

```
reconflow/
  app/                          # minimal shared technical code only, no business logic:
                                #   Casts/MoneyCast, Support/Money, Contracts (AuditActor, PermissionEnum, RoleAssignable,
                                #   MetricsCollector), Support/Authorization (PermissionRegistry, DefaultRole,
                                #   AuthorizesPermissions), Support/Modules/ModuleProvider, Exceptions (DomainException,
                                #   ErrorRenderer), Http/Middleware (request ID, security headers + CSP nonce, Inertia),
                                #   Http/Controllers/System (health, readiness, metrics)
  Modules/                      # nwidart modules, one per business function
    Users/                      # accounts, session login/logout, user administration        [Phase 1: built]
    Rbac/                       # permission catalogue sync, roles, role assignment, lock-out guard   [Phase 1: built]
    Audit/                      # hash-chained logger, verifier, archive + checkpoint        [Phase 1: built]
    DataProtection/             # classification, masking, pseudonymiser, PII log processor  [Phase 1: built; retention/erasure later]
    Ingestion/                  # connectors (mock + upload), staging, data quality, templates, mock source APIs, synthetic data   [Phase 2: built]
    Reconciliation/             # rules R1–R7, engine, runs, results, report exports, rule settings   [Phase 3: built]
    ExceptionManagement/        # exception workflow, assignment, SLA, run sign-off
    Adjustments/                # maker-checker approvals, ERP posting
    AI/                         # ClaudeClient, Redactor, prompts, suggestions, oversight, kill switch
    Notifications/              # in-app, Slack, email, daily summary
    Dashboard/                  # KPIs, trends, ageing
    <Module>/
      app/                      # Actions/ Console/ DTOs/ Enums/ Http/{Controllers,Middleware,Requests,Resources}/
                                # Metrics/ Models/ Policies/ Providers/ Services/ Support/ Traits/
      config/config.php         # module settings (the only place a module calls env())
      database/{migrations,seeders,factories}/
      resources/js/Pages/       # the module's Inertia pages, rendered as '<Module>/<Page>'
      routes/web.php            # session-authenticated routes (routes/api.php only for machine APIs)
      tests/{Unit,Feature}/
  resources/js/                 # Inertia entry, page resolver, shared layouts, shadcn/ui components, hooks, lib, types
  resources/css/app.css         # Tailwind v4 theme (light theme, one teal accent)
  config/ bootstrap/ routes/ database/ public/ storage/ stubs/ tests/   # standard Laravel layout
  composer.json  package.json  phpunit.xml  phpstan.neon  pint.json  vite.config.ts  tsconfig.json  eslint.config.js
  deploy/                       # docker-compose.yml, Caddyfile, docker-entrypoint.sh, .env.example
  .github/workflows/ci.yml
  Dockerfile  Makefile
  samples/                      # provided: templates/, golden/, volume/ (read-only, unchanged)
  README.md  BUILD_NOTES.md  BUILD_PROMPT.md  CLAUDE.md  docs/
```

### Module conventions (as built in Phase 1)
- Each module owns a permission enum implementing `App\Contracts\PermissionEnum`, registers it with `PermissionRegistry` in its service provider, and declares which seeded roles get each permission by default. Modules register lock-out-protected permissions with `PermissionRegistry::protect()`.
- Each module registers its policies with `Gate::policy()`. Form Requests call the policy in `authorize()`, so authorization runs before validation. Controllers without a Form Request call `$this->authorize()`.
- Each module defines its audit actions as an enum and records them through `Modules\Audit\Services\AuditLogger`.
- Module providers extend `App\Support\Modules\ModuleProvider`, which skips Blade view and translation registration.
- Modules contribute Prometheus gauges by tagging `App\Contracts\MetricsCollector` implementations.
- Allowed module dependencies: Users → Rbac → Audit → DataProtection → `app/`. Rbac reaches users only through the configured auth model and `App\Contracts\RoleAssignable`.

## 11. Documentation to produce (concise, business-readable where noted)

- `README.md`: what it is, a screenshot placeholder, quick start (`make up`), demo logins, how to run tests.
- `docs/architecture.md`: Mermaid component diagram and data-flow diagram, key design decisions.
- `docs/reconciliation-rules.md`: each rule in plain English with an example (business-readable).
- `docs/data-model.md`: ER diagram (Mermaid) and table purposes.
- `docs/controls-matrix.md`: risk → control → how it's enforced → evidence (e.g. SoD, audit chain, DQ quarantine, idempotent posting, RBAC, AI human-in-the-loop).
- `docs/ai-governance.md`: AI use register entry, model card (purpose, inputs, outputs, limitations, known failure modes), risk assessment (risk → control → evidence), human-oversight design, monitoring metrics, go-live gates (DPO approval of provider terms and cross-border transfer).
- `docs/data-protection.md`: data inventory and classification table, data-flow diagram showing exactly what leaves the system (only redacted fields to the AI provider; aggregates to notifications), masking rules, retention schedule, a DPIA-lite (Data Protection Impact Assessment) summary.
- `docs/runbook.md`: daily operation, source late/missing, run failed, rerun a date, AI down, rotate secrets, restore DB backup.
- `docs/deployment.md`: server prerequisites, env vars, `docker compose` deploy, HTTPS via Caddy, backups (`pg_dump` cron), upgrade and rollback.
- `docs/api.md`: summary of the routes (Inertia pages, JSON/download endpoints) and the mock source-system and ERP APIs.
- `BUILD_NOTES.md`: keep this updated as you work. For each significant piece: key technical choices, anything stubbed or simplified, known limitations.

---

## 12. Out of scope (document as production next steps, don't build)

Real source integrations; SSO/Azure AD; multi-currency/FX; multi-entity; high availability / horizontal scaling; formal retention policy sign-off (the retention *mechanisms* in §1A.6 are in scope); penetration testing.

---

## 13. Build phases and checkpoints

Stop after each phase, summarise what exists, list assumptions made, and wait for review.

1. **Foundation** ✅ *(built 2026-09-24)*: Laravel app with Users, Rbac, Audit and DataProtection modules; shared kernel in `app/`; migrations; session auth + permissions-first RBAC with policies; data classification, masking, pseudonymisation and PII-safe logging; hash-chained audit logger, verifier and automatic archive with checkpoints; health/readiness/metrics; Inertia shell with login; multi-stage Docker image (FrankenPHP), compose (app, worker, scheduler, postgres, caddy); CI (Pint, Larastan, ESLint, tsc, Vite build, Pest, GHCR push, optional SSH deploy). 85 Pest tests passing. *Checkpoint: `make up` boots; login works; CI green.*
2. **Data** ✅ *(built 2026-09-24)*: synthetic generator (template-format export), golden/volume fixtures wired into tests, mock source APIs, connectors, **upload → staging → preview → confirm** API, template download endpoint, DQ validation + quarantine, reset demo data. *Checkpoint: 14 days seeded; DQ report visible via API.*
3. **Engine** ✅ *(built 2026-09-24)*: rules R1–R7, engine, run versioning, scheduler, run-now endpoint, all rule/golden/idempotency tests. *Checkpoint: golden test passes; 50k perf test passes.*
4. **Workflow:** exceptions, state machine, assignment/SLA, adjustments, approvals with SoD and thresholds, mock ERP posting with idempotency, run sign-off, notifications. *Checkpoint: full API flow scripted in a test.*
5. **AI assist:** Claude triage client with schema validation, batching, fallback, storage of suggestions and human decisions; narrative summary. Include the `redact()` layer, override-reason capture, kill switch, AI oversight panel and eval set. *Checkpoint: works with a key and without one; governance tests pass.*
6. **Frontend:** all pages in section 7, wired to the API, polished. *Checkpoint: click-through demo of the full story.*
7. **Docs and hardening:** all docs in section 11, test report, final README, `.env.example`, security pass (no secrets in repo, CORS, rate-limit login, secure headers).

---

## 14. Demo script the finished app must support (≈5 minutes)

1. Log in as **Analyst**. The dashboard shows 14 days of history: match rate ~96%, value at variance, open exceptions, and "~2h 45m saved today vs manual baseline".
2. Click **Run now** for the latest closed business date and watch it complete in seconds. Open the run's DQ report showing quarantined bad rows.
3. Open the **Reconciliation report**: filter to Variance, then export XLSX.
4. Open an **under-payment exception**: see the source records side by side, the rule that fired, and the AI suggestion. Accept the suggestion and propose a write-off adjustment.
5. Try to approve your own adjustment: **blocked** (segregation of duties).
6. Log in as **Manager**, approve from the inbox, and see it posted to the ERP with an idempotency key.
7. Show a **timing** exception auto-resolved by the next run.
8. Log in as **Auditor**: phone numbers are masked. Open the audit log, filter to the adjustment, click **Verify integrity** (passes).
8a. Open **Data uploads**: download the payments template, upload `samples/golden/payments_2026-09-22.xlsx`, show the preview flagging 2 invalid rows with reasons, confirm, and run reconciliation for that date. Counts match the answer key.
8b. As **Admin**, open **AI oversight**: acceptance/override rates, then flip the kill switch and show exceptions still categorised by rules.
9. The manager signs off the day and the run is locked.
