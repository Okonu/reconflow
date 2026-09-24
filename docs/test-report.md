# Test report

This report shows the results of the latest Pest run. `php scripts/test-report.php` made it on 2026-09-24 11:18 UTC.

| Metric | Value |
|---|---|
| Tests | 376 |
| Passed | 376 |
| Failed | 0 |
| Skipped | 0 |
| Assertions | 1665 |
| Duration | 61.5 s |
| Coverage | Not measured in this run (no coverage driver). CI measures it with `--coverage` |
| Performance (group `slow`) | 50,000 sales reconciled in 7.8 s (budget 60 s), match rate 95.59% |

## By suite

| Suite | Tests | Failed | Skipped | Time |
|---|---|---|---|---|
| AI (Feature) | 9 | 0 | 0 | 3.9 s |
| Adjustments (Feature) | 7 | 0 | 0 | 3.2 s |
| Audit (Feature) | 18 | 0 | 0 | 1.1 s |
| Dashboard (Feature) | 2 | 0 | 0 | 0.5 s |
| DataProtection (Feature) | 8 | 0 | 0 | 0.9 s |
| DataProtection (Unit) | 15 | 0 | 0 | 0.8 s |
| ExceptionManagement (Feature) | 6 | 0 | 0 | 2.3 s |
| Ingestion (Feature) | 46 | 0 | 0 | 13.5 s |
| Ingestion (Unit) | 34 | 0 | 0 | 1.5 s |
| Notifications (Feature) | 4 | 0 | 0 | 1.0 s |
| Rbac (Feature) | 126 | 0 | 0 | 11.7 s |
| Reconciliation (Feature) | 25 | 0 | 0 | 14.5 s |
| Reconciliation (Unit) | 41 | 0 | 0 | 1.8 s |
| Shared (Feature) | 8 | 0 | 0 | 1.3 s |
| Shared (Unit) | 15 | 0 | 0 | 2.5 s |
| Users (Feature) | 12 | 0 | 0 | 0.9 s |

## What the tests prove

- **Answer keys:** the engine reproduces the golden file (65 items) and the volume file (2,531 items) exactly. This includes the status and the rule of each item, and the quarantine rows.
- **Rules:** each rule from R1 to R7 has unit tests on the engine. The tests include the limits (tolerance, fuzzy window, duplicate window, cut-off, payment window), ties, splits, prior-day matches, escalation and manual matches.
- **Workflow:** one test does the full flow. It covers the golden import, exceptions, maker-checker and the block on self-approval, ERP posting one time only, the value threshold, ERP failure and retry, fuzzy confirm and reject, sign-off blockers, the date lock, reopen, and the relink at a new run.
- **Controls:** the permission contract checks each route. A user without the permission gets a refusal. A user with only that permission gets access. Architecture tests check strict types, no role-name checks, `env()` only in configuration files, no comments, and the page files. The audit chain finds changes. The archive keeps the chain possible to check.
- **AI governance:** no phone number goes to the model. The system keeps the prompt and input hashes. An override needs a reason. The AI never writes to workflow tables. The system rejects output that does not agree with the schema. The kill switch stops all calls. The summary uses totals only. The evaluation set operates offline.
- **Data protection:** masked pages and exports, safe cells in exports, audited unmask and unmasked export, erasure by anonymisation, and anonymisation at the end of retention.
