# Process map

This document compares the daily sales reconciliation today with the process in ReconFlow.

## Current process (manual)

The brief describes this process. It occurs one time each day, after the transaction data is available. It takes approximately 3 hours.

```mermaid
flowchart TD
    A[Export sales from the sales system] --> D[Copy the data into spreadsheets]
    B[Export payments from M-Pesa and bank] --> D
    C[Export postings from the ERP] --> D
    D --> E[Run local scripts and spreadsheet formulas]
    E --> F[Find differences by eye]
    F --> G[Categorise each exception by hand]
    G --> H[Send emails and messages to get answers]
    H --> I[Get approval for corrections by email]
    I --> J[Post corrections in the ERP by hand]
    J --> K[Send a summary to managers]
```

| Problem in the brief | Where it occurs |
|---|---|
| Excessive manual effort | Steps A to G: exports, copies, formulas and checks by eye |
| Process in many tools | Three systems, spreadsheets, scripts, email and chat |
| Late identification of exceptions | The work starts late and takes 3 hours |
| Limited visibility of status | The status is in files and messages, not in one place |
| Many manual interventions | Every exception and approval needs a person to chase it |
| Inconsistent audit trail | Emails and spreadsheet versions are the only record |
| Difficult to scale | The effort increases with each new transaction |

## Future process (ReconFlow)

```mermaid
flowchart TD
    S[06:00 Scheduler] --> P[Pull sales, payments and postings through connectors]
    U[Upload of an Excel or CSV file, if a source is late] --> V[Preview and confirm]
    V --> Q
    P --> Q[Check the data. Put bad rows in quarantine]
    Q --> R[Match with rules R1 to R7]
    R --> M[Matches: no work]
    R --> FZ[Fuzzy matches: a person confirms]
    R --> X[Exceptions: owner, severity and due date]
    X --> AI[Optional AI suggestion. A person accepts or overrides it]
    AI --> ADJ[Analyst proposes an adjustment]
    X --> ADJ
    ADJ --> APP[A different person approves it]
    APP --> ERP[Journal posts to the ERP one time only]
    ERP --> SO[Finance Manager signs off the date]
    R --> DB[Dashboard and 07:00 summary]
    SO --> AU[All steps are in the audit trail]
```

## Step comparison

| Step | Today | ReconFlow | Who |
|---|---|---|---|
| Get the data | Manual exports from three systems | Automatic pull at 06:00, or an upload with a preview | System |
| Check the data | Not done in a consistent way | Checks on columns, types, currency, decimals, dates and duplicates. Bad rows go into quarantine with a reason | System |
| Match | Formulas and scripts on one computer | Seven rules in a fixed order. Each result shows the rule that decided it | System |
| Find exceptions | Checks by eye | Each problem becomes an exception with an owner, a severity and a due date | System |
| Investigate | Emails and messages | All records side by side on one page, with an optional AI suggestion | Analyst |
| Approve a correction | Email | Maker-checker in the system. More than $1,000 needs a Finance Manager | Analyst and manager |
| Post to the ERP | Manual entry | A balanced journal with an idempotency key | System |
| Report | A summary by email | A live dashboard, a report with exports, and a daily summary | System |
| Close the day | No formal step | Sign-off locks the date | Finance Manager |
| Audit | Emails and files | A hash-chained audit trail that an auditor can check | System |

## Effort

| Activity | Today (minutes) | ReconFlow (minutes) |
|---|---|---|
| Get, copy and check data | 60 | 0 |
| Match and find differences | 60 | 0 |
| Investigate and categorise | 40 | 15 |
| Approve and post corrections | 20 | 5 |
| **Total** | **180** | **20** |

These values are estimates. We will measure the real baseline during discovery and the parallel run.
