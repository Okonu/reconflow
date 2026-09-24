# Assumptions and scope notes

Recorded as they are made. Each item says who decided it and where it is enforced.

## Reconciliation scope

- **Cash sales only.** Credit sales are out of scope. Several payments referencing one sale are treated as instalments of a cash sale: when their total differs from the expected amount by more than the tolerance, the sale is a VARIANCE (rule `R2+R4`, flag `split`, default category "Customer under/over-payment (instalments)"). Production needs a `payment_type` on sales so that credit instalments are routed to receivables rather than reported as variances. *(Owner, 2026-09-24.)*
- **Single currency (USD).** Rows in other currencies are quarantined. Multi-currency/FX is out of scope.
- **Postings without a sale** (an ERP line whose transaction_id matches no sale for the date) are not reported as a reconciliation item. The answer keys define no status for them. A production version should surface them in the data-quality report.

## Matching boundaries (owner, 2026-09-24)

| Boundary | Rule |
|---|---|
| Amount tolerance | inclusive: \|actual − expected\| ≤ $0.50 |
| Fuzzy time window | inclusive: \|Δt\| ≤ 24 h |
| Duplicate window | inclusive: Δt ≤ 5 min |
| Timing cut-off | inclusive: an unpaid sale at t ≥ 22:00:00 is PENDING_TIMING |
| Payments extract window | start-inclusive, end-exclusive: D 00:00:00 ≤ t < D+1 06:00:00 |
| Posting check | exact: posted ≠ expected is POSTING_MISMATCH (answer key "Rules reference") |

All times are Africa/Nairobi.

## Prior-day items (owner, 2026-09-24)

- A payment belongs to its own timestamp date. The D run may claim D+1 00:00–06:00 payments; unclaimed ones are left for D+1 and never reported as UNMATCHED_PAYMENT on D.
- A PENDING_TIMING sale gets one carry-forward (`timing_carry_days`, default 1). If the next run finds no payment, the item is escalated to MISSING_PAYMENT with the reason "no payment by close of D+1 window".
- New payments are matched by exact reference against open MISSING_PAYMENT items from the last 7 days (`late_payment_lookback_days`).
- Prior-day matches appear in a separate "Prior-day items cleared" section as MATCHED_PRIOR_DAY (or VARIANCE / MISSING_POSTING / POSTING_MISMATCH when those checks fail), tagged "Paid next day" or "Paid late (D+n)". They are excluded from that day's match rate and totals and counted in separate prior-day KPIs. D's results are never rewritten; the item's current state lives in `recon_item_states`.
- **Prior-day matching uses exact references (R1/R2 including split totals), not fuzzy matching.** This follows the owner's instruction for late payments; carried PENDING_TIMING sales use the same method. *(Pending owner confirmation at the Phase 3 checkpoint.)*
