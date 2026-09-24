# Runbook

Commands assume the host checkout at `/opt/reconflow`, with this alias:

```bash
alias dc='docker compose --project-directory /opt/reconflow --env-file /opt/reconflow/deploy/.env'
```

Health: `GET /health` (process up) and `GET /ready` (database reachable). Metrics: `GET /metrics` (Prometheus; blocked at Caddy, scrape inside the network). Every response and log line carries an `X-Request-ID`; quote it when reporting a problem.

## Daily operation

| Time (EAT) | What happens | Who checks |
|---|---|---|
| 06:00 | Scheduler pulls sales, payments and postings for yesterday and reconciles. It retries every 30 min (up to 12×) if a source is missing | Automatic |
| 07:00 | Daily summary to the bell (and Slack/email if configured) | Finance Manager |
| Morning | Analysts work the **Exceptions** queue (critical and overdue first), confirm **Fuzzy matches**, propose adjustments | Analysts |
| Through the day | Managers approve adjustments in **Approvals** | Finance Manager |
| End of day | Manager signs off the date on **Sign-off** once blockers are clear; carried exceptions need an acknowledgement | Finance Manager |

Healthy signs: run status *completed* on the dashboard's latest run card, match rate around 96%, no *posting failed* count, and the queue worker running (`dc ps`).

## A source is late or missing

Symptoms: run shows **Blocked: no data for payments** (or sales/postings); "Reconciliation blocked" notification.

1. The run retries automatically. Check the source system, or the mock API at `/api/mock/{source}`.
2. If the source cannot deliver, a user with `uploads.create` downloads the template on **Data uploads**, uploads the file, checks the preview (invalid rows and reasons), and confirms (*Replace* or *Append*).
3. Go to **Runs → Run now** for the date. Tick *Refresh from sources first* only if you want to pull again. A manual upload stays in effect until someone explicitly chooses to replace it.
4. If an earlier date is re-run later, the next date is marked **stale**; re-run it too.

## A run failed

Symptoms: status *failed*, "Reconciliation failed" alert.

1. Open the run and note the error and request ID. Find the details in the logs: `dc logs app worker | grep <request-id>`.
2. Common causes: DB unavailable (check `/ready`, `dc ps db`) or bad data that got past validation (check the batch page's DQ report).
3. Fix the cause, then **Run now**. Runs are idempotent; each attempt is a new version and nothing is lost.
4. From the CLI: `dc exec app php artisan recon:run YYYY-MM-DD [--refresh]`.

## Re-run a date

- **Runs → Run now** with the date, or the CLI command above. The new version supersedes the old one; exceptions are relinked, auto-resolved or reclassified, and comments and adjustments stay attached.
- If the date is **signed off**, a Finance Manager must first **Reopen** it on the Sign-off page with a reason (audited). Re-runs and upload confirmations are blocked until then.

## An ERP posting failed

Symptoms: adjustment and exception in *Posting failed*; the dashboard counts it.

1. Check that the ERP is up. In the demo, check the **Simulate ERP failure** toggle on Approvals (Administrator).
2. Press **Retry posting** on the adjustment. The same idempotency key is reused, so a posting that actually succeeded is never duplicated.

## AI assistant is down or misbehaving

- The AI panels show "AI suggestion unavailable: …" with the reason (no key, rate limited, provider error). All work continues rules-only.
- To stop AI use immediately: **AI oversight → Kill switch** (or Settings → AI assistant → off). This is audited.
- Check the failure rate and recent errors on **AI oversight**. Provider status: status.anthropic.com.
- After changing the model or prompt, run `dc exec app php artisan reconflow:ai-eval` and compare with the previous eval run.

## Audit integrity alert

If **Verify integrity** fails, the audit chain is broken at the reported event ID. Treat this as a security incident: preserve a DB snapshot, restrict access, and compare the event with the latest archive or backup to identify the change. Archived segments are verified from their checkpoint.

## Rotate secrets

| Secret | How | Impact |
|---|---|---|
| `ANTHROPIC_API_KEY`, `SLACK_WEBHOOK_URL`, `SOURCE_SYSTEMS_TOKEN` | Update `deploy/.env`, then `dc up -d app worker scheduler` | None |
| `POSTGRES_PASSWORD` | `dc exec db psql -U reconflow -c "alter user reconflow password '…'"`, update `.env`, restart app/worker/scheduler | Brief reconnect |
| `APP_KEY` | Generate a new key, set `APP_PREVIOUS_KEYS=<old>` so sessions and encrypted values keep decrypting, restart, and remove the old key after the session lifetime | Users may need to sign in again |
| `PII_HASH_SALT` | **Avoid.** Changing it changes every pseudonym token; historical AI inputs will no longer link. If compromised, rotate and record the date in the DPIA | Tokens change |
| User passwords | Users → deactivate or reset; sessions end on deactivation | |

## Restore a DB backup

```bash
dc stop app worker scheduler
dc exec -T db dropdb -U reconflow reconflow
dc exec -T db createdb -U reconflow reconflow
cat /var/backups/reconflow/reconflow_YYYY-MM-DD.dump | dc exec -T db pg_restore -U reconflow -d reconflow --no-owner
dc start app worker scheduler
```

Then: `/ready` is green, **Audit log → Verify integrity** passes, and the dashboard shows the expected latest date. Re-run any business dates processed after the backup was taken.

## Reset the demo

Administrators: **Data uploads → Reset demo data**. This clears operational tables, reseeds 14 days of synthetic data and reconciles them. Users, roles, settings and the audit log are kept.

## Useful commands

```bash
dc exec app php artisan reconflow:seed --days=14 --sales-per-day=3000 --seed=42
dc exec app php artisan recon:run 2026-09-22
dc exec app php artisan reconflow:daily-summary --date=2026-09-22
dc exec app php artisan reconflow:ai-eval [--stub]
dc exec app php artisan reconflow:anonymise-expired
dc exec app php artisan audit:archive
dc exec app php artisan queue:failed           # inspect failed jobs
dc exec app php artisan queue:retry all
```
