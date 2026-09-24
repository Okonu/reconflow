# Runbook

This runbook tells operators how to operate ReconFlow and how to recover from incidents.

The commands use the installation at `/opt/reconflow`. Set this alias first:

```bash
alias dc='docker compose --project-directory /opt/reconflow --env-file /opt/reconflow/deploy/.env'
```

**Health checks:**

- `GET /health`: the process operates.
- `GET /ready`: the application can connect to the database.
- `GET /metrics`: Prometheus figures. Caddy blocks this path from the internet. Read it from the internal network.

Each response and each log line has an `X-Request-ID`. Give this ID when you report a problem.

## Daily operation

| Time (EAT) | Event | Who checks |
|---|---|---|
| 06:00 | The scheduler pulls the sales, payments and postings of the previous day and reconciles them. If a source is missing, it tries again every 30 minutes, up to 12 times | Automatic |
| 07:00 | The daily summary goes to the bell (and to Slack or email, if set) | Finance Manager |
| Morning | Analysts work on the **Exceptions** queue (critical and overdue items first), confirm **Fuzzy matches** and propose adjustments | Analysts |
| During the day | Managers approve adjustments on the **Approvals** page | Finance Manager |
| End of day | The manager signs off the date on the **Sign-off** page when there are no blockers. Carried exceptions need a comment | Finance Manager |

The system is healthy when:

- The latest run card on the dashboard shows the status *completed*.
- The match rate is approximately 96%.
- There are no failed postings.
- The queue worker operates (`dc ps`).

## A source is late or missing

**Symptoms:** the run shows **Blocked: no data for payments** (or sales, or postings). You get a "Reconciliation blocked" notification.

1. Wait for the automatic retry. Examine the source system, or the mock API at `/api/mock/{source}`.
2. If the source cannot send data, go to **Data uploads**. You need `uploads.create`.
3. Download the template.
4. Upload the file.
5. Examine the preview: the invalid rows and their reasons.
6. Confirm the upload with *Replace* or *Append*.
7. Go to **Runs → Run now** and select the date.
8. Select *Refresh from sources first* only if you want to pull the data again.

A manual upload stays active until a person selects to replace it.

If you run an earlier date again, the next date becomes **stale**. Run the next date again.

## A run failed

**Symptoms:** the run status is *failed*. You get a "Reconciliation failed" alert.

1. Open the run. Write down the error and the request ID.
2. Find the details in the logs: `dc logs app worker | grep <request-id>`.
3. Examine the usual causes:
   - The database is not available. Examine `/ready` and `dc ps db`.
   - Bad data passed the checks. Examine the data-quality report on the batch page.
4. Repair the cause.
5. Select **Run now**.

Each attempt is a new version. No data is lost.

To start a run from the command line: `dc exec app php artisan recon:run YYYY-MM-DD [--refresh]`.

## Run a date again

- Select **Runs → Run now** with the date, or use the command above.
- The new version replaces the old version. The system relinks, resolves or reclassifies the exceptions. Comments and adjustments stay with the exceptions.

**CAUTION:** You cannot run a signed-off date again. First, a Finance Manager must select **Reopen** on the Sign-off page and give a reason. The audit trail records this action.

## An ERP posting failed

**Symptoms:** the adjustment and the exception show *Posting failed*. The dashboard counts it.

1. Make sure that the ERP is available. In the demo, examine the **Simulate ERP failure** switch on the Approvals page (Administrator).
2. Select **Retry posting** on the adjustment.

The retry uses the same idempotency key. If the first posting was successful, the ERP does not post it again.

## The AI assistant is not available or gives bad output

- The AI panels show "AI suggestion unavailable: …" with the reason (no key, rate limit, provider error). All work continues with the rules only.
- To stop the AI immediately, select **AI oversight → Kill switch** (or Settings → AI assistant → off). The audit trail records this action.
- Examine the failure rate and the latest errors on the **AI oversight** page. The status of the provider is at status.anthropic.com.
- After a change to the model or the prompt, run `dc exec app php artisan reconflow:ai-eval`. Compare the result with the previous evaluation.

## Audit integrity alert

**WARNING:** If **Verify integrity** fails, treat it as a security incident.

1. Make a snapshot of the database.
2. Limit the access to the system.
3. Find the event ID in the error message. The chain breaks at this event.
4. Compare the event with the latest archive or backup to find the change.

The system checks archived segments from their checkpoint.

## Change a secret

| Secret | Procedure | Effect |
|---|---|---|
| `ANTHROPIC_API_KEY`, `SLACK_WEBHOOK_URL`, `SOURCE_SYSTEMS_TOKEN` | Change `deploy/.env`. Then run `dc up -d app worker scheduler` | None |
| `POSTGRES_PASSWORD` | Run `dc exec db psql -U reconflow -c "alter user reconflow password '…'"`. Change `.env`. Restart the app, the worker and the scheduler | A short reconnection |
| `APP_KEY` | Make a new key. Set `APP_PREVIOUS_KEYS=<old>` so that sessions and encrypted values continue to operate. Restart. Remove the old key after the session lifetime | Users can have to sign in again |
| `PII_HASH_SALT` | **Do not change it if you can avoid it.** A change changes all pseudonym tokens. Old AI inputs then do not link. If the salt leaks, change it and record the date in the DPIA | Tokens change |
| User passwords | On the Users page, deactivate the user or reset the password. Deactivation ends the sessions | |

## Restore a database backup

**CAUTION:** This procedure deletes the current database. Make sure that you have the correct backup file.

```bash
dc stop app worker scheduler
dc exec -T db dropdb -U reconflow reconflow
dc exec -T db createdb -U reconflow reconflow
cat /var/backups/reconflow/reconflow_YYYY-MM-DD.dump | dc exec -T db pg_restore -U reconflow -d reconflow --no-owner
dc start app worker scheduler
```

Then do these checks:

1. Make sure that `/ready` is green.
2. Make sure that **Audit log → Verify integrity** passes.
3. Make sure that the dashboard shows the expected latest date.
4. Run again each business date that the system processed after the backup.

## Reset the demo

An administrator selects **Data uploads → Reset demo data**. The system clears the operation tables, makes 14 days of synthetic data and reconciles them. Users, roles, settings and the audit log do not change.

## Useful commands

```bash
dc exec app php artisan reconflow:seed --days=14 --sales-per-day=3000 --seed=42
dc exec app php artisan recon:run 2026-09-22
dc exec app php artisan reconflow:daily-summary --date=2026-09-22
dc exec app php artisan reconflow:ai-eval [--stub]
dc exec app php artisan reconflow:anonymise-expired
dc exec app php artisan audit:archive
dc exec app php artisan queue:failed           # show failed jobs
dc exec app php artisan queue:retry all
```
