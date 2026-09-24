# Test report

Generated 2026-09-24 07:45 UTC by `php scripts/test-report.php` from the latest Pest run.

| Metric | Value |
|---|---|
| Tests | 373 |
| Passed | 373 |
| Failed | 0 |
| Skipped | 0 |
| Assertions | 1635 |
| Duration | 352.6 s |
| Coverage | not measured in this run (no coverage driver); CI measures it with `--coverage` |
| Performance (group `slow`) | 50,000 sales reconciled in 16.1 s (budget 60 s), match rate 95.59% |

## By suite

| Suite | Tests | Failed | Skipped | Time |
|---|---|---|---|---|
| AI (Feature) | 9 | 0 | 0 | 8.6 s |
| Adjustments (Feature) | 7 | 0 | 0 | 7.4 s |
| Audit (Feature) | 18 | 0 | 0 | 2.3 s |
| Dashboard (Feature) | 2 | 0 | 0 | 1.1 s |
| DataProtection (Feature) | 8 | 0 | 0 | 2.0 s |
| DataProtection (Unit) | 15 | 0 | 0 | 1.5 s |
| ExceptionManagement (Feature) | 6 | 0 | 0 | 5.2 s |
| Ingestion (Feature) | 43 | 0 | 0 | 225.2 s |
| Ingestion (Unit) | 34 | 0 | 0 | 3.1 s |
| Notifications (Feature) | 4 | 0 | 0 | 2.3 s |
| Rbac (Feature) | 126 | 0 | 0 | 49.7 s |
| Reconciliation (Feature) | 25 | 0 | 0 | 32.6 s |
| Reconciliation (Unit) | 41 | 0 | 0 | 3.7 s |
| Shared (Feature) | 8 | 0 | 0 | 2.8 s |
| Shared (Unit) | 15 | 0 | 0 | 3.4 s |
| Users (Feature) | 12 | 0 | 0 | 1.8 s |

## What the suite proves

- **Answer keys**: the golden file (65 items) and the volume file (2,531 items) are reproduced exactly: status and rule for every item, plus quarantine rows.
- **Rules**: each rule R1–R7 has unit tests on the pure engine, including boundaries (tolerance, fuzzy window, duplicate window, cut-off, payment window), ties, splits, prior-day matching, escalation and manual matches.
- **Workflow**: an end-to-end scripted flow covers the golden import, exceptions, maker-checker with the SoD block, idempotent ERP posting and replay, the high-value threshold, ERP failure and retry, fuzzy confirm and reject, sign-off blockers, the date lock, reopen, and re-run relinking.
- **Controls**: the permission contract checks every authenticated route (denied without its permission, allowed with only it). Architecture tests cover strict types, no role-name checks, `env()` only in config, no comments, and page resolution. The audit chain detects tampering; the archive keeps it verifiable.
- **AI governance**: no raw phone number is ever sent to the model; prompt and input hashes are stored; an override needs a reason; the AI never writes workflow tables; schema violations are rejected; the kill switch stops all calls; the narrative is built from aggregates only; the eval set runs offline.
- **Data protection**: masked views and exports, formula-safe cells, audited unmask and unmasked export, erasure by anonymisation, and retention anonymisation.
