# Routes and APIs

This document lists the pages, actions and endpoints of ReconFlow.

The server makes the pages through Inertia. A page route returns an Inertia response to the browser, and JSON to an `X-Inertia` request. A form post goes back to the page with a message. Some endpoints return JSON or files. The pages call them through the shared HTTP client. All routes, except the system and mock APIs, need a signed-in session. A policy checks the permission in the table for each action.

**Conventions**
- CSRF: a session cookie and the `X-XSRF-TOKEN` header. Inertia and `resources/js/lib/http.ts` send them.
- Errors (JSON): `{"error": {"code": "forbidden|not_found|conflict|validation_failed|…", "message": "…", "request_id": "…"}}`. Validation errors use the Laravel format `{"message", "errors": {field: [..]}}`.
- Each response has an `X-Request-ID` header. You can send your own ID to connect your request to the logs.
- Rate limits: login attempts are limited. Report exports: 20 each minute. Audit and unmasked exports: 10 each minute. AI triage: 20 each minute. Summaries: 10 each minute. Batch triage: 5 each minute. Unmask: 30 each minute.

## Pages (Inertia)

| Method | Path | Page | Permission |
|---|---|---|---|
| GET | `/` | Dashboard | `dashboard.view` (others see a welcome page) |
| GET | `/login` | Login | guest |
| GET | `/runs` | Run history, Run now | `runs.view` |
| GET | `/runs/{run}` | Run detail, DQ per source, narrative | `runs.view` |
| GET | `/reports/reconciliation` | Reconciliation report (`date, section, roll_up, status, search, page`) | `results.view` |
| GET | `/exceptions` | Exception queue (`state, severity, category, owner, overdue, business_date, search`) | `exceptions.view` |
| GET | `/exceptions/{exception}` | Exception detail | `exceptions.view` |
| GET | `/matches` | Fuzzy matches to confirm (`date`) | `results.view` |
| GET | `/approvals` | Approvals inbox | `adjustments.view` |
| GET | `/signoff/{date}` | Sign-off | `exceptions.view` |
| GET | `/uploads`, `/uploads/{staging}` | Data uploads, preview | `uploads.view` |
| GET | `/batches`, `/batches/{batch}` | Source batches and DQ report | `batches.view` |
| GET | `/audit` | Audit log (`action, actor, entity_type, entity_id, from, to`) | `audit.view` |
| GET | `/ai` | AI oversight | `ai.oversee` |
| GET | `/settings` | Settings (sections you can view) | `config.view` / `ai.oversee` |
| GET | `/users` | Users | `users.view` |
| GET | `/access/roles` | Roles | `roles.view` |

## Actions (form posts, redirect back)

| Method | Path | Body | Permission |
|---|---|---|---|
| POST | `/login` · `/logout` | `email, password` | guest · signed in |
| POST | `/runs` | `business_date, refresh?, replace_manual?` | `runs.trigger` |
| POST | `/uploads` | multipart `source, business_date, file` | `uploads.create` |
| POST | `/uploads/{staging}/confirm` · `/cancel` | `mode: replace\|append` | `uploads.create` |
| POST | `/demo/reset` | | `demo.reset` |
| POST | `/exceptions/assign` | `exception_ids[], owner_id?` | `exceptions.assign` |
| POST | `/exceptions/{e}/review` · `/resolve` · `/comments` | `reason` / `comment` | `exceptions.work` |
| POST | `/exceptions/{e}/adjustments` | `type, amount, reason` | `adjustments.propose` |
| POST | `/adjustments/{a}/approve` · `/reject` | `comment` (required to reject) | `adjustments.approve` (+ `approve_high_value` above threshold; never the proposer) |
| POST | `/adjustments/{a}/retry` | | `adjustments.approve` |
| POST | `/erp/simulate-failure` | | `erp.manage` |
| POST | `/matches/confirm` | `result_ids[]` | `matches.confirm` |
| POST | `/matches/{result}/reject` | `reason` | `matches.confirm` |
| POST | `/results/{result}/manual-match` | `payment_result_id, reason` | `matches.confirm` |
| POST | `/signoff/{date}` | `comment` (required when exceptions are carried) | `runs.signoff` |
| POST | `/signoff/{date}/reopen` | `reason` | `runs.reopen` |
| POST | `/exceptions/{e}/ai-triage` | | `ai.use` |
| POST | `/ai/triage-batch` | `business_date` | `ai.use` |
| POST | `/ai/suggestions/{s}/decision` | `decision: accept\|override, action?, reason` (required to override) | `ai.use` |
| POST | `/ai/kill-switch` | | `ai.manage` |
| PUT | `/settings/{section}` | section fields + `comment` | section's manage permission |
| POST · PATCH | `/users` · `/users/{user}` | `name, email, password, region, role_ids[]` · `name, region, is_active` | `users.manage` |
| PUT | `/access/users/{user}/roles` | `role_ids[]` | `roles.manage` |
| POST · PATCH · DELETE | `/access/roles[/{role}]` | `code, label, description, permissions[]` | `roles.manage` |

## JSON and download endpoints

| Method | Path | Returns | Permission |
|---|---|---|---|
| GET | `/results/{result}/possible-matches` | `{data: [{payment_result_id, payment_id, paid_at, amount, reference, days_late}]}` | `results.view` |
| GET · POST | `/runs/{run}/ai-summary` | Latest narrative / generate one (`201`, or `503 ai_unavailable`) | `runs.view` · `ai.use` |
| GET | `/notifications` | `{unread, items: [...]}` (own notifications only) | signed in |
| POST | `/notifications/{id}/read` · `/notifications/read-all` | `{unread}` | signed in |
| POST | `/audit/verify` | `{data: {ok, events_checked, head_hash, broken_at_id, reason}}` | `audit.verify` |
| GET | `/audit/export` | CSV (same filters as the page) | `audit.export` |
| GET | `/reports/reconciliation/export` | CSV/XLSX, masked (`format=csv\|xlsx` + page filters) | `results.export` |
| POST | `/reports/reconciliation/export-unmasked` | CSV/XLSX, unmasked; body adds `reason` (≥10 chars) | `results.export_unmasked` |
| POST | `/pii/unmask` | `{values: {customer_phone}}` for `dataset: sales\|payments, record_id, purpose` | `pii.unmask` |
| POST | `/privacy/erasures` | `{records_anonymised: {...}, total}` for `phone, reason, request_reference` | `privacy.erase` |
| GET | `/uploads/templates/{source}.xlsx`, `/uploads/sample-pack.zip` | Files | `uploads.view` |

## System

| Path | Purpose |
|---|---|
| `GET /health` | Liveness: `{"status":"ok"}` |
| `GET /ready` | Readiness: the database is available (otherwise `503`) |
| `GET /metrics` | Prometheus figures (runs, match rate, exceptions for each severity and state, ingestion). Caddy blocks this path. Read it from the internal network |

## Mock source systems

These APIs simulate the real sales, payment and ERP systems. They need the header `Authorization: Bearer $SOURCE_SYSTEMS_TOKEN`.

### `GET /api/mock/{sales|payments|postings}?date=YYYY-MM-DD`

```json
{
  "system": "simulated-payments", "simulated": true, "source": "payments",
  "business_date": "2026-09-22",
  "window": {"from": "2026-09-22T00:00:00+03:00", "to": "2026-09-23T06:00:00+03:00"},
  "extracted_at": "2026-09-23T06:00:02+03:00",
  "rows": [{"payment_id": "SL0LMI8X76", "timestamp": "2026-09-22 10:31:00", "channel": "MOBILE_MONEY", "payer_phone": "2547…", "amount": "32.00", "currency": "USD", "reference": "TUP-S-000041"}]
}
```

The rows have the same columns as the upload templates. `window` applies only to payments (D 00:00 to D+1 06:00, the end is not included).

### `POST /api/mock/erp/journals`

```json
{
  "idempotency_key": "6f1c…-uuid",
  "posting_date": "2026-09-22",
  "reference": "ReconFlow exception #123 (TUP-S-000041)",
  "reverse_journal_id": null,
  "lines": [
    {"account": "6150-BAD-DEBT-WRITE-OFF", "debit": "2.00", "credit": "0.00", "transaction_id": null},
    {"account": "1100-CUSTOMER-RECEIVABLES", "debit": "0.00", "credit": "2.00", "transaction_id": "TUP-S-000041"}
  ]
}
```

- The lines must balance (Σ debit = Σ credit). `reverse_journal_id` reverses an existing journal. The correct-posting and duplicate-posting corrections use it.
- Response `200`: `{"journal_id": "ADJ-2026-000001", "status": "POSTED", "posting_date", "reversal_of", "posted_at", "simulated": true, "replayed": false}`.
- If the `idempotency_key` occurs again, the ERP returns the first response with `"replayed": true`. It posts nothing.
- `422 invalid_journal`: the lines do not balance, or the journal to reverse does not exist. `503 erp_unavailable`: the failure simulation is on.
- The ERP adds posted revenue lines to the mock postings source. Thus the next pull and run see the correction.
