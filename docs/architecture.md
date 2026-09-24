# Architecture

ReconFlow is one Laravel 13 application. The application has one module for each business function (`nwidart/laravel-modules`). A small shared kernel is in the root `app/` folder. PostgreSQL 16 keeps all the data. A queue worker and a scheduler do the background work. The same application serves the user interface (Inertia and React). There is no separate single-page application and no API gateway.

## Components

```mermaid
flowchart LR
    subgraph Browser
        UI[Inertia + React pages]
    end

    subgraph App["Laravel app (FrankenPHP)"]
        direction TB
        Users & Rbac & DataProtection & Audit
        Ingestion --> Reconciliation --> ExceptionManagement
        ExceptionManagement --> Adjustments
        ExceptionManagement --> AI
        Reconciliation --> Notifications
        ExceptionManagement --> Notifications
        Dashboard
        Kernel["app/ shared kernel<br/>Money, BusinessCalendar, contracts,<br/>PermissionRegistry, VersionedSettings,<br/>error envelope, security headers"]
    end

    Worker[Queue worker] --- App
    Scheduler[Scheduler] --- App
    DB[(PostgreSQL 16)]
    App --- DB
    Worker --- DB

    Sources["Mock source APIs<br/>/api/mock/{sales,payments,postings}"] --> Ingestion
    Adjustments --> ERP["Mock ERP<br/>POST /api/mock/erp/journals"]
    AI --> Claude["Anthropic API<br/>(redacted input only)"]
    Notifications --> Slack["Slack / email<br/>(aggregates only)"]
    UI <--> App
    Caddy[Caddy reverse proxy] --> App
```

| Module | Responsibility |
|---|---|
| Users | Accounts, login and logout, Argon2id password hashes, login limits, deactivation |
| Rbac | The permission catalogue, roles made of permissions, role assignment |
| Audit | The append-only audit table with a hash chain, the verifier, and the archive with a checkpoint |
| DataProtection | The field inventory and classification, masking, pseudonym tokens, a log filter for personal data, and audited unmasking |
| Ingestion | Source connectors, the mock source data, the synthetic data generator, uploads (staging, preview, confirm), data checks and quarantine, batch versions, and the mock ERP |
| Reconciliation | The matching engine (rules R1 to R7), runs and run versions, item states, fuzzy review, manual matches, the report and exports, and rule settings |
| ExceptionManagement | Exceptions that continue across new runs, severity and due dates, assignment, the state machine, comments, sign-off and the date lock |
| Adjustments | Correcting journals, maker-checker approval, the value threshold, ERP posting with an idempotency key, and retry |
| AI | The Claude client, redaction, prompts with versions, triage and daily summaries, human accept or override, the kill switch, oversight and the evaluation set |
| Notifications | The in-app bell, optional Slack and email, run alerts, critical-exception alerts and the daily summary |
| Dashboard | Key figures, trends, and charts for categories and age |

The modules communicate through events and contracts. They do not call the controllers of other modules.

- **Events:** `RunFinished`, `ExceptionsSynced`, `ItemStateChanged`, `FuzzyMatchRejected`, `DemoDataSeeded`.
- **Contracts** (in `app/Contracts`): `BusinessDateLock`, `ExceptionDetailContributor`, `SettingsSection`, `ResetsDemoData`, `PermissionEnum`, `AuditActor`.

For example, the Adjustments and AI modules add panels to the exception page. The ExceptionManagement module does not know about these modules.

## Daily data flow

```mermaid
sequenceDiagram
    autonumber
    participant S as Scheduler (06:00 EAT)
    participant I as Ingestion
    participant R as Reconciliation
    participant E as ExceptionManagement
    participant N as Notifications
    participant H as Analyst / Manager
    participant A as Adjustments
    participant ERP as Mock ERP

    S->>I: pull sales, payments, postings for D
    I->>I: check data, quarantine bad rows, keep a new batch version only if the data changed
    S->>R: reconcile D (one lock for each date)
    R->>R: load active batches and carried items, apply R5, manual matches, R1, R2, R3, R4/R6, R7
    R->>R: write the run version, results and item states; replace the previous version
    R-->>E: RunFinished
    E->>E: open, relink, reclassify or close exceptions by their stable key
    E-->>N: ExceptionsSynced (critical alerts)
    H->>E: examine, comment, ask the AI for a suggestion (optional)
    H->>A: propose an adjustment (maker)
    H->>A: approve (checker is not the maker; threshold)
    A->>ERP: POST journal with an idempotency key
    ERP-->>A: journal ID (or failure: POSTING_FAILED, retry)
    A-->>E: exception resolved
    H->>E: sign off D; the date is locked
```

## Design decisions

- **Deterministic engine with integer cents.** The engine uses integer cents and Unix seconds. It does no input or output. Money is a `brick/math` BigDecimal at the edges. `MoneyCast` rejects floating-point numbers. The same input always gives the same output. The engine reproduces both answer keys exactly (65 golden items and 2,531 volume items).
- **Ingestion is separate from reconciliation.** A batch is a copy of the data that does not change. There is one active batch for each source and date. Each run records the batch versions that it used. Thus we can explain each result from the past.
- **Runs have versions.** A new run replaces the previous version but does not delete it. Each exception has a stable key (identity and status family). Thus comments, owners and adjustments stay with the exception after a new run.
- **Permissions first.** The code defines the permissions. Roles are data made of permissions. A policy checks a permission for each action. No code checks a role name.
- **Maker-checker in the policy.** `AdjustmentPolicy` enforces the segregation of duties and the value threshold. The pages, the HTTP routes and the jobs all use the same policy.
- **Posting one time only.** Each adjustment keeps one idempotency key. If the ERP gets the same key again, it returns the first journal. A retry uses the same key.
- **Audit trail that shows changes.** Each event keeps `sha256(prev_hash + canonical JSON)`. A database trigger stops updates and deletes, except by the archiver. "Verify integrity" calculates the chain again.
- **The AI is an advisor only.** The AI module can write only to `ai_suggestions`. It gets records with pseudonym tokens. It keeps the redacted input and its hash. A person must accept or override each suggestion. An override needs a reason.
- **Settings have versions.** Each change to the rule, severity, approval or AI settings has a version, an author and a reason. The audit trail records it.
- **Operation data.** Each log line and each error has a request ID. `/health`, `/ready` and the Prometheus endpoint `/metrics` give the status of runs, exceptions and the queue. The logs contain no phone numbers and no secrets.
