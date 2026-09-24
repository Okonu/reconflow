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
| 2026-09-24 | Exceptions only for roll-up Variance/Exception statuses (and prior-day variants); PENDING_TIMING is a soft exception; MATCHED_FUZZY goes to a "Confirm matches" review list (single and bulk confirm); rejecting one splits it into MISSING_PAYMENT + UNMATCHED_PAYMENT exceptions (audited); unreviewed fuzzy matches block sign-off | Owner | |
| 2026-09-24 | Exceptions have a stable key (date + transaction_id/payment ids + status family); a re-run re-links same-key exceptions, auto-resolves vanished ones ("Resolved by re-run vN") unless an adjustment is in flight ("Needs review"), closes and links on a family change, and creates new ones; signed-off dates can't be re-run until a Finance Manager reopens them with a reason | Owner | |
| 2026-09-24 | Severity by value at risk: Low < $50 (SLA 5 days), Medium $50–$499.99 (72 h), High $500–$1,999.99 (24 h, blocks sign-off), Critical ≥ $2,000 (8 h, blocks sign-off, notifies Finance Manager); at least Medium for DUPLICATE_PAYMENT, POSTING_MISMATCH and DUPLICATE_POSTING; PENDING_TIMING always Low with no SLA until escalation; calendar hours; configurable | Owner | |
| 2026-09-24 | Approved adjustments post balanced journals to `/api/mock/erp/journals` with an idempotency key (same key → original response); config-driven accounts (write-off Dr 6150/Cr 1100; refund Dr 1100/Cr 2150; missing posting Dr 1100/Cr 4000; unmatched payment Dr 1100/Cr 2190 suspense; duplicate posting → reversal); failure simulation toggle → POSTING_FAILED with Retry; posted journals feed the postings connector | Owner | Chart of accounts to be confirmed by Finance |
| 2026-09-24 | From Phase 4, edge cases are decided with a default and logged under "Open questions" instead of pausing the build | Owner | |
| 2026-09-24 | Carried PENDING_TIMING sales match next-day payments with R1/R2/R3 (fuzzy flagged needs confirmation, one-to-one with current-day sales); lookback items auto-match on exact reference/split only; same-phone, within-tolerance unmatched payments are offered as possible matches, and an Analyst's confirmation creates an audited manual match resolving the item as "Paid late (D+n), manually matched" | Owner | Phase 3 checkpoint answer |
| 2026-09-24 | Ingestion and reconciliation are separate: the daily job pulls then reconciles; Run now reconciles loaded data unless "Refresh from sources first" is ticked; one active batch per source per date; the job skips sources whose active batch is manual ("manual upload in effect"); refreshing over an upload needs an explicit keep/replace choice; a pull only creates a new batch if the checksum changed; runs store the exact batch versions used; no active batch means BLOCKED_DATA (except PROVISIONAL runs) | Owner | |
| 2026-09-24 | Batch versions are immutable, complete snapshots; Append copies the active rows plus the new rows into a new version (`parent_batch_id`, mode PULL / UPLOAD_REPLACE / UPLOAD_APPEND, `manual`, `rows_added`); an append onto a pull sets manual | Owner | Replaces Phase 2's second-active-batch Append |
| 2026-09-24 | PENDING_TIMING gets one carry-forward, then escalates to MISSING_PAYMENT ("no payment by close of D+1 window"); late payments match open MISSING_PAYMENT items from the last 7 days by exact reference | Owner | |
| 2026-09-24 | Prior-day matches are reported in a separate section (MATCHED_PRIOR_DAY, roll-up "Match (prior day)", tags "Paid next day" / "Paid late (D+n)"), excluded from the day's metrics, with separate prior-day KPIs; D's results are never rewritten; every payment appears exactly once | Owner | |
| 2026-09-24 | Several referencing payments whose total is off by more than the tolerance are one VARIANCE (`R2+R4`, flag split) | Owner | |
| 2026-09-24 | Boundaries: tolerance, fuzzy window, duplicate window and timing cut-off are inclusive; the extract window is start-inclusive, end-exclusive | Owner | |
| 2026-09-24 | 2026-09-22 is seeded with **generated** data like every other day; the answer key is reproduced by uploading all three golden files with Replace | Owner | |
| 2026-09-24 | Extra quarantine rules: sales **business_date must equal the batch date**; payment timestamp must fall **inside the extract window** (D 00:00 → D+1 06:00 EAT) | Owner | Neither case occurs in the samples |
| 2026-09-24 | Amounts with **more than 2 decimals are quarantined** ("Amount has more than 2 decimal places"), never rounded; values within 1e-9 of a 2-dp number are treated as that number (Excel float noise); trailing zeros are fine | Owner | |
| 2026-09-24 | Duplicate keys: (1) exact duplicate row in a file → keep first, quarantine the copy ("Duplicate row: exact copy of row N"); (2) same transaction_id/journal_id with different content → quarantine **all** rows sharing it ("Conflicting records share <key>"); (3) key already loaded for the date on Append → preview says "already exists for this date: use Replace" and it is not imported; (4) two different POSTED journal_ids for one transaction_id → new status **DUPLICATE_POSTING** (roll-up Exception), REVERSED lines excluded. Payment duplicates stay R5 DUPLICATE_PAYMENT exactly as the answer keys define | Owner | |
| 2026-09-24 | **Payment ownership:** a payment belongs to its own timestamp date. The D run may match payments from D+1 00:00–06:00 against D sales; a match claims the payment for D. Unmatched grace-window payments are **not** reported as UNMATCHED_PAYMENT on D. The D+1 run excludes payments claimed by the latest D run version and can match D's open PENDING_TIMING sales (auto-resolving them). Re-running D after D+1 exists marks D+1 **STALE** (needs re-run) | Owner | Verified compatible: all 27 grace-window payments in both answer keys are MATCHED |
| 2026-09-24 | **Run now** defaults to the latest **closed** business date (window closed at D+1 06:00 EAT; same logic as the scheduled job). Any date can be chosen; an open date's run is **PROVISIONAL** (banner, timing exceptions expected, cannot be signed off) | Owner | |

## Open questions (defaults chosen, awaiting owner review)

| # | Question | Default implemented |
|---|---|---|
| 1 | Who counts as a preparer for auto-assignment? | Active users with `exceptions.work` but without `runs.signoff`; same region first, then the least loaded, ties by user id. If there are none, the exception stays unassigned |
| 2 | How is a POSTING_MISMATCH corrected? | `correct_posting` reverses the original ERP journal and reposts at the expected amount (two journals, one idempotency key) |
| 3 | A re-run frees an item the ledger had resolved. Does its closed exception reopen? | No. Only a new exception result opens a new exception, linked to its predecessor |
| 4 | What does "Carried from D" mean in the queue? | Open exceptions whose business date is before the latest closed business date |
| 5 | Can uploads be confirmed for a signed-off date? | No: signed-off dates block both re-runs and upload confirmation until reopened |
| 6 | Chart of accounts for journals | 1100 receivables, 6150 bad-debt write-off, 2150 refunds payable, 2190 unidentified receipts suspense, 4000 sales cash. Needs Finance confirmation (docs/assumptions.md) |
| 7 | Which posting lines count in the posting check? | Only revenue-account lines (prefix `4000`). `ADJ-` journals are summed into the posted amount but never count as duplicate postings |
| 8 | An adjustment is in flight and a re-run clears the exception | The exception stays open, flagged "needs review"; the adjustment is not cancelled automatically |
| 9 | Critical-exception alerts | One aggregated notification per run (count and value at risk) to holders of `notifications.critical_alerts`, not one per exception |
| 10 | New permissions added by an upgrade | Granted automatically to the matching default template roles the first time they appear; roles edited at runtime keep their edits for existing permissions |
| 11 | Slack and email content | Aggregates only (counts, amounts, dates, links). No phone numbers or customer identifiers leave the system |
| 12 | Daily summary time | 07:00 Africa/Nairobi (`NOTIFY_DAILY_SUMMARY_TIME`), after the 06:00 reconciliation |
| 13 | Default AI model | `claude-opus-5` (`ANTHROPIC_MODEL`), adaptive thinking at effort `medium` (`AI_EFFORT`), JSON-schema structured output, server-side refusal fallbacks on (`AI_REFUSAL_FALLBACKS`) |
| 14 | What does the model see? | Triage sees one exception's records, with customers, payers and agents replaced by salted pseudonym tokens and a final PII scrub. Summaries see aggregates only. The exact redacted input is stored with each suggestion for audit |
| 15 | What does accepting a suggestion do? | It records agreement on the timeline and in the audit log only. It never proposes an adjustment or changes state; the analyst still acts through the normal maker-checker flow |
| 16 | Kill switch scope | Global and immediate for everyone, stored in the DB, and audited. Holders of `ai.manage` can toggle it (Finance Manager, Administrator by default) |
| 17 | Demo without an API key | The panels show "No Anthropic API key is configured". `AI_DRIVER=stub` swaps in a clearly labelled rule-based stub (model name `stub-rules`) for offline demos and eval baselines |
| 18 | Where settings live | A shared, versioned `setting_versions` store in root `app/`. Each module contributes a settings section (rules, severity/SLA, approval threshold, AI). Matching rules keep their own `recon_rule_configs` versions. Every save needs a reason and is audited by the owning module |
| 19 | Who changes settings | `config.view` / `config.manage` (Administrator by default) for rules, severity/SLA and approvals; `ai.oversee` / `ai.manage` for the AI section |
| 20 | "Time saved vs manual baseline" | Auto-matched sale items × 3.4 s each (`DASHBOARD_MANUAL_SECONDS_PER_ITEM`), about 2h 45m on a 3,000-sale day. The baseline needs confirming with Finance |
| 21 | Report "Status" column | Shows the roll-up (Match / Variance / Exception…), with the detailed status and rule beside it. The detailed status reflects later human decisions (for example a rejected fuzzy match shows MISSING_PAYMENT, "was MATCHED_FUZZY") |
| 22 | Unmasking | Masked by default everywhere. Holders of `pii.unmask` (Analyst, Manager) can reveal one record's phone numbers with a stated purpose; each reveal is audited. Unmasked report exports need `results.export_unmasked` (Manager) and a reason |
| 23 | AI eval set location | `Modules/AI/resources/evals/triage_v1.json` (20 cases) instead of `tests/fixtures`, because the Docker image strips test folders and the eval must be runnable in production. CI runs it only when the `ANTHROPIC_API_KEY` secret is set; tests always use `AI_DRIVER=stub` |
| 24 | AI decisions and the exception timeline | The AI module writes only to `ai_suggestions` (governance rule). Decisions appear in the AI panel, on the oversight page and in the audit log, not on the exception timeline |
| 25 | Self-approval message | The segregation-of-duties check runs before the permission check, so a proposer always sees "someone else must approve", and the approve button is shown disabled with that reason |
| 26 | Transaction retention | `reconflow:anonymise-expired` (daily 02:30) replaces customer and payer phones older than `RETENTION_TRANSACTIONS_YEARS` with `ANONYMISED` in records, quarantine rows and mock source copies, and clears old staged uploads. Implemented through a `PersonalDataStore` contract that Ingestion provides |
| 27 | Erasure interface | API only: `POST /privacy/erasures` (`privacy.erase`, Administrator) with phone, reason and request reference. It anonymises every copy and audits a pseudonym token of the subject, never the number |
| 28 | CORS | The app is same-origin. `config/cors.php` allows no cross-origin callers unless `CORS_ALLOWED_ORIGINS` is set, and then only for `api/*` GET/POST without credentials |
| 29 | Batch triage | "Suggest for all" on the exception queue (for a filtered business date) queues suggestions for up to 200 open exceptions without one (`AI_BATCH_LIMIT`). The job stops as soon as the kill switch is flipped. This uses sequential calls, not the Message Batches API, so results appear within minutes |

## Phase 7: Docs and hardening

### What exists
- **Docs:** `docs/architecture.md`, `reconciliation-rules.md`, `data-model.md`, `controls-matrix.md`, `ai-governance.md` (use register, model card, risk assessment, oversight design, go-live gates), `data-protection.md` (inventory, egress diagram, masking, retention, DPIA-lite), `runbook.md`, `deployment.md` and `api.md`. The README has the demo script and a docs index.
- **Retention and erasure:** implemented (open questions 26 and 27).
- **Security pass:**
  - no secrets tracked (`.env` and `deploy/.env` are git-ignored; only examples are committed);
  - restrictive CORS;
  - login throttling;
  - Argon2id hashing;
  - CSP with nonces and security headers, plus HSTS at Caddy;
  - secure, HTTP-only, same-site session cookies in production;
  - `/metrics` blocked at the edge;
  - DB on an internal network;
  - rate limits on exports, AI, unmask and batch triage;
  - formula-safe exports.
- **CI:** an AI eval step runs when the `ANTHROPIC_API_KEY` secret is present; tests force `AI_DRIVER=stub`.

### Deferred until all phases are done (owner instruction: tests after the phases)
- Run the full suite, Larastan and the slow performance test; fix failures.
- Write the missing tests:
  - AI governance: no raw phone in an outbound payload, kill switch, override reason, schema rejection;
  - notifications;
  - settings;
  - report export masking and escaping;
  - unmask;
  - erasure and retention;
  - dashboard;
  - permission contract rows for every new route.
- Generate `docs/test-report.md` from the run.
- Verify the Docker deployment end to end (`make down && make up`, first-boot seed, demo script click-through).

## Phase 6: Pages and demo flow

### What exists
- **Dashboard (`Modules/Dashboard`, home):** KPI tiles (match rate, value reconciled, value at variance, open, overdue, time saved), a 14-day match-rate and exception trend, open exceptions by category, ageing buckets, and a latest-run card (DQ per source, sign-off state, pending fuzzy matches and approvals).
- **Reconciliation report:** the brief's table with roll-up chips, section/status/detailed-status filters, search and pagination. CSV/XLSX export is masked, formula-safe and audited; unmasked export needs a reason.
- **Settings:** versioned, audited sections for matching rules, severity/SLA bands, approval threshold, and AI (on/off, model, effort, accountable owner, last review date).
- **Audit log:** filters, payload and hash details, CSV export (audited), and Verify integrity.
- **Users:** create, edit, deactivate and assign roles. **Roles:** create and edit with grouped permissions, and delete unused roles.
- **AI oversight additions:** average confidence, failure rate, override rate by category, accountable owner and last review date.
- **Data protection:** audited per-record unmask on exception detail.
- **Navigation:** moved to a second header row.

### Deferred until all phases are done
- Tests for the dashboard, report export (masking, formula escaping, audit), settings (validation, versioning, audit), unmask, audit export, and the users/roles UI flows. Also permission contract entries for all new routes.

## Phase 5: AI assist

### What exists
- **`Modules/AI`:** an `LlmClient` contract with `ClaudeClient` (official `anthropic-ai/sdk`, beta messages with `fallbacks: 'default'`, adaptive thinking, JSON-schema output, typed error handling, cached system prompt) and `StubLlmClient`.
- **Redaction:** `Redactor` pseudonymises personal fields, then scrubs the whole payload.
- **Versioned prompts:** `resources/prompts/v1_triage.md` and `v1_run_summary.md`. Each suggestion stores the version and SHA-256 of its prompt.
- **Triage:** suggests a likely cause (enum), a next action (enum), an explanation, evidence and a confidence. People accept it, or override it with a reason and an optional alternative action. Both are logged on the exception timeline and in the audit log.
- **Run narrative:** a summary built from aggregates only, shown on the run detail page.
- **Oversight page (`/ai`):** kill switch, usage and acceptance/override rates, latency, tokens, fallback use, the override list with reasons, evaluation runs and recent requests.
- **Eval set:** `resources/evals/triage_v1.json` has 12 labelled cases. `php artisan reconflow:ai-eval [--stub]` scores action and cause accuracy and stores the run.
- **Retention:** `reconflow:ai-prune` deletes AI logs older than `RETENTION_AI_LOGS_MONTHS` (12) daily and audits the deletion.

### Deferred until all phases are done
- Tests for the AI module (stub-driven), the eval baseline run, and permission contract entries for `ai.*` routes.

## Phase 4: Workflow

### What exists
- **ExceptionManagement:** exceptions keyed by identity and status family, which survive re-runs (relink, auto-resolve, reclassify, escalate timing items). Severity and SLA come from the value at risk; owners are auto-assigned; state transitions are guarded. The module also has comments and a timeline, bulk assign, sign-off with blockers and acknowledgement, a date lock, and reopening by a Finance Manager.
- **Adjustments:** write-off, refund, post-missing, correct-posting, reverse-duplicate and suspense adjustments. Maker-checker is enforced by policy, as is the $1,000 Finance Manager threshold. Posting to the mock ERP is balanced and idempotent; a failed posting goes to POSTING_FAILED and can be retried. There is an ERP-failure simulation toggle.
- **Fuzzy review:** a list to confirm or reject fuzzy matches. Rejecting one splits it into missing-payment and unmatched-payment exceptions, and the rejection is remembered on re-runs.
- **Notifications:** in-app bell, plus optional email (`NOTIFY_MAIL_ENABLED`) and Slack (`SLACK_WEBHOOK_URL`). It covers run failed/blocked alerts, aggregated critical-exception alerts and a scheduled daily summary.
- **Pages:** exception queue, exception detail (records side by side, rule explanation, timeline, work actions, adjustment panel, possible late payments), approvals inbox, fuzzy matches and sign-off. Module panels plug into exception detail through `resources/js/lib/contributions.ts`.

### Deferred until all phases are done (owner instruction)
- Test run: `Modules/ExceptionManagement/tests` and `Modules/Adjustments/tests` are written. Three known failures remain: the SoD message assertion (the analyst lacks `adjustments.approve`, so the permission message wins) and two scenarios that reference transaction IDs not present in the seeded late-payment data.
- Permission contract entries for the new routes.
- The Larastan run after the typed-relation fixes.

## Phase 3: Engine

### What exists
- **Reconciliation module.** A pure engine (`Engine/ReconciliationEngine`) composes rule classes that do no I/O, read no clock and use no randomness. Amounts are integer cents and times are Unix seconds. The order is R5 duplicates → R1/R2 current-day references → R1/R2 prior-day items → R3 fuzzy (strict one-to-one; a sale or payment with more than one candidate leaves everyone involved unmatched, rule `R3 tie → R7`) → R4/R6 (VARIANCE takes precedence over posting checks; posting mismatch is an exact comparison; DUPLICATE_POSTING when two different POSTED journals exist) → R7 leftovers (timing window, grace-window exclusion, escalations).
- **Runs.** `QueueRun` creates a versioned run with a snapshot of the rule config, flagged PROVISIONAL when the date's payment window is still open. `ExecuteRun` optionally refreshes from source systems (respecting manual uploads), blocks with BLOCKED_DATA when a source has no data, reconciles under a per-date advisory lock, writes results, supersedes the previous version, records the exact batch versions used, marks the next date STALE when an earlier date is re-run, and audits every outcome. Failures are recorded as FAILED and re-thrown.
- **Prior-day items.** Carried pending sales are matched with R1/R2/R3 (fuzzy results flagged as needing confirmation); lookback items by exact reference or split only. `PossibleMatchFinder` suggests same-phone, within-tolerance unmatched later payments, and `ConfirmManualMatch` records an audited manual match (`recon_manual_matches`) that the engine re-applies on every re-run (rule `MANUAL`). `ItemStateLedger` lists open prior items (carried PENDING_TIMING within `timing_carry_days`; open MISSING_PAYMENT and escalated items within `late_payment_lookback_days`), records resolutions and escalations with audit events, and releases decisions made by superseded runs so that re-running a date recomputes cleanly. Payments claimed by the previous date's latest run are excluded, so every payment appears exactly once.
- **Rule settings** are versioned in `recon_rule_configs` (defaults from the answer keys); each change is audited and each run stores the version it used.
- **Scheduling.** `recon:daily` runs at the configured time (06:00 Africa/Nairobi): pull, then reconcile the latest closed date, retrying BLOCKED_DATA every 30 minutes up to 12 attempts. Run now queues an `ExecuteRunJob`; the run page polls until the run finishes. Demo seeding reconciles every seeded date in order.
- **Pages.** Runs history with Run now (latest closed date preselected, source readiness, refresh with keep/replace for manual uploads, provisional warning); run detail with status, provisional/stale/blocked banners, metrics (match rate, value reconciled, value at variance, exceptions, prior-day cleared, escalations) and the batches used.

### Verification
- **The golden and volume answer keys are reproduced exactly:** every item's transaction, payments, status and rule ID (65 and 2,531 items), from files imported through the upload API.
- 34 rule unit tests including every boundary (±$0.50, ±24 h, 22:00:00, 5 minutes, window end), ties, instalment variances, posting checks and prior-day matching.
- Lifecycle tests: versioning and idempotent re-runs, batch references, BLOCKED_DATA, PROVISIONAL, STALE, carry-forward clearing, escalation and recomputation on re-run, manual-upload refresh policy, the exactly-once payment invariant across consecutive days, demo seeding and versioned settings.
- Performance: 50,000 sales reconcile in about 7.4 s (budget 60 s); the test runs in CI.
- 250 Pest tests pass; Larastan 0 errors; Pint, ESLint, tsc and the Vite build are clean. In Docker, first boot seeds and reconciles 14 days (about 0.7 s per day, match rate 95–96%).

### Simplifications and known limitations
- BLOCKED_DATA retries each create a new run version, so the history shows every attempt.
- Postings with no sale are not reported (no status defined); see `docs/assumptions.md`.
- The full reconciliation report page with filters and exports is part of Phase 6 (the data and roll-ups exist now).
- Sign-off locking arrives with the workflow in Phase 4.

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
