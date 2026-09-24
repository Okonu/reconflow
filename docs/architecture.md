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

## Application structure

I set this structure before the build started. The coding agent had to follow it for all code.

### Repository layout

```text
reconflow/
├── app/                      Shared technical kernel. It contains no business logic
│   ├── Casts/                MoneyCast (BigDecimal, no floats)
│   ├── Contracts/            Interfaces that modules use to communicate
│   ├── Http/                 Base controller, middleware (request ID, security headers)
│   ├── Policies/             Shared policies (settings)
│   └── Support/              Money, business calendar, versioned settings, exports
├── Modules/                  One module for each business function
│   ├── Users/  Rbac/  Audit/  DataProtection/
│   ├── Ingestion/  Reconciliation/  ExceptionManagement/  Adjustments/
│   └── AI/  Notifications/  Dashboard/
├── resources/js/             Shared frontend: layouts, UI components, hooks, lib, types
├── tests/                    Architecture tests and shared tests
├── samples/                  Templates, golden and volume data, answer keys (read-only)
├── deploy/                   Caddyfile, entrypoint, example settings
└── docs/                     This documentation
```

### Inside each module

Each module has the same layout. For example, `Modules/Adjustments/`:

```text
Modules/Adjustments/
├── app/
│   ├── Http/
│   │   ├── Controllers/      Thin: validate, authorise, call an Action, return a Resource
│   │   ├── Requests/         Form Requests: all input validation. They give DTOs to the domain
│   │   └── Resources/        API Resources: all response shapes, including masking
│   ├── Actions/              One state change for each class. Owns the transaction and the audit event
│   ├── Services/             Domain logic that two or more Actions use
│   ├── DTOs/                 Read-only typed data between the layers
│   ├── Enums/                Statuses, states, categories and permissions. No magic strings
│   ├── Policies/             All authorisation: permissions and rules such as maker ≠ checker
│   ├── Models/               Eloquent models, casts, relations and query scopes
│   ├── Contracts/  Jobs/  Support/
├── resources/js/
│   ├── Pages/                Inertia pages: compose components and hooks only
│   ├── components/           UI only: render and handle events
│   └── types.ts              Prop types that agree with the Resources
├── routes/  database/  config/
└── tests/                    Pest tests for this module
```

### Request flow through the layers

```mermaid
flowchart LR
    Page[Inertia page<br/>React] -->|router / useForm| Route
    Route --> Controller
    Controller --> Request[Form Request<br/>validates, makes DTO]
    Controller --> Policy[Policy<br/>checks permission]
    Controller --> Action[Action<br/>transaction + audit]
    Action --> Service[Service<br/>domain logic]
    Service --> Model[Model / scope]
    Action --> Audit[AuditLogger]
    Controller --> Resource[API Resource<br/>shapes and masks]
    Resource -->|props| Page
```

### Coding principles

**Backend**

- Controllers are thin. A Form Request validates. A Policy authorises. An Action or a Service does the work. An API Resource shapes the response.
- Each state change is one Action. The Action owns the database transaction and records the audit event through `AuditLogger`.
- The matching rules are pure classes. They use no database, network, clock or random values. Thus the same input always gives the same output.
- Money is `decimal(14,2)` in PostgreSQL and `BigDecimal` in PHP. Code never uses floating-point numbers for money.
- Policies check permissions, never role names.
- Modules communicate through Actions, Services, DTOs, Events and Contracts. A module never calls the controller of a different module.
- Only `Modules/Ingestion/Connectors` communicates with source systems.
- All AI calls go through `ClaudeClient`. The `Redactor` processes each payload first.

**Frontend**

- Components show data and handle events only. They contain no business logic, no data fetching and no money calculations.
- The server calculates all amounts, variances, totals and rates. The frontend only formats them for display (`resources/js/lib/format.ts`).
- All page data comes as Inertia props from Resources. Writes go through the Inertia `router` or `useForm`. The few JSON calls go through one client, `resources/js/lib/http.ts`.
- The frontend can hide a button with the `can` flags from the server. The server always enforces the permission.
- A component has a maximum of 200 lines. If a component is longer, split it.

**Code style**

- Each PHP file declares `strict_types=1`.
- Names give the intent. The code contains no comments and no docblocks. Explanations go in `docs/`.
- Statuses, states and permissions are enums, not strings.
- Pint sets the PHP style. Larastan checks the types. ESLint and the TypeScript compiler check the frontend.
- If logic occurs two times, move it to a shared Service, hook, library function or component.

### How the build enforces the structure

| Rule | Check |
|---|---|
| Each class declares strict types | Architecture test |
| `env()` is only in configuration files | Architecture test |
| Controllers do not query the database | Architecture test |
| No comments or docblocks in PHP | Architecture test |
| No authorisation by role name | Architecture test |
| Each server-rendered page has a page component | Architecture test |
| Each route needs its permission | Permission contract test |
| PHP style and types | Pint and Larastan in CI |
| Frontend types and style | TypeScript compiler and ESLint in CI |

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
