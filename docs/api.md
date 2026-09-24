# Routes and APIs

The UI is server-rendered through Inertia: page routes return an Inertia response for the browser and JSON for `X-Inertia` requests. Form posts redirect back with a flash message. A few endpoints return JSON or files and are called with `fetch` from the pages. Every route except the system and mock APIs needs a signed-in session, and every action is authorised by a policy on the permission shown.

**Conventions**
- CSRF: session cookie plus the `X-XSRF-TOKEN` header (handled by Inertia and `resources/js/lib/http.ts`).
- Errors (JSON): `{"error": {"code": "forbidden|not_found|conflict|validation_failed|…", "message": "…", "request_id": "…"}}`. Validation errors use Laravel's `{"message", "errors": {field: [..]}}`.
- Every response carries `X-Request-ID` (send your own to correlate).
- Rate limits: login is throttled; exports 20/min (report) and 10/min (audit, unmasked); AI triage 20/min, summaries 10/min, batch triage 5/min; unmask 30/min.

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
| `GET /ready` | Readiness: database reachable (`503` otherwise) |
| `GET /metrics` | Prometheus metrics (runs, match rate, exceptions by severity/state, ingestion). Blocked by Caddy; scrape from the internal network |

## Mock source systems

The stand-ins for the real sales, payments and ERP systems require `Authorization: Bearer $SOURCE_SYSTEMS_TOKEN`.

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

Rows use the upload template columns. `window` applies to payments only (D 00:00 to D+1 06:00, end-exclusive).

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

- Lines must balance (Σ debit = Σ credit). `reverse_journal_id` reverses an existing journal (used for correct-posting and duplicate-posting fixes).
- Response `200`: `{"journal_id": "ADJ-2026-000001", "status": "POSTED", "posting_date", "reversal_of", "posted_at", "simulated": true, "replayed": false}`.
- A repeated `idempotency_key` returns the original response with `"replayed": true` and posts nothing.
- `422 invalid_journal` for unbalanced lines or an unknown journal to reverse; `503 erp_unavailable` while the failure simulation is on.
- Posted revenue lines are appended to the mock postings source, so the next pull and re-run see the correction.
