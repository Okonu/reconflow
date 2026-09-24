# Assumptions and scope limits

This document records the assumptions when we make them. Each item gives the person who decided it and where the system enforces it. The [Decision log](decision-log.md) gives the assumptions register with the effect of each assumption and how we will confirm it.

## Scope of the reconciliation

- **Cash sales only.** Credit sales are out of scope. The system treats two or more payments for one sale as instalments of a cash sale. If their total and the expected amount differ by more than the tolerance, the sale is a VARIANCE. The rule is `R2+R4`, with the flag `split` and the default category "Customer under/over-payment (instalments)". For production, sales need a `payment_type` field. Then credit instalments can go to receivables and not to the variance list. *(Owner, 2026-09-24.)*
- **One currency (USD).** Rows in other currencies go into quarantine. Currency conversion is out of scope.
- **Postings with no sale.** An ERP line whose transaction ID agrees with no sale for the date is not a reconciliation item. The answer keys give no status for it. A production version must show these lines in the data-quality report.

## Limits for matching (owner, 2026-09-24)

| Limit | Rule |
|---|---|
| Amount tolerance | Includes the limit: \|actual − expected\| ≤ $0.50 |
| Fuzzy time window | Includes the limit: \|Δt\| ≤ 24 h |
| Duplicate window | Includes the limit: Δt ≤ 5 min |
| Timing cut-off | Includes the limit: an unpaid sale at t ≥ 22:00:00 is PENDING_TIMING |
| Payment extract window | Includes the start and excludes the end: D 00:00:00 ≤ t < D+1 06:00:00 |
| Posting check | Exact: posted ≠ expected is POSTING_MISMATCH (answer key, "Rules reference" sheet) |

All times are Africa/Nairobi.

## Prior-day items (owner, 2026-09-24)

- A payment belongs to the date of its timestamp. The run for D can use payments from D+1 00:00 to 06:00. The run for D+1 gets the payments that the run for D did not use. The report for D never shows them as UNMATCHED_PAYMENT.
- A PENDING_TIMING sale goes forward one day (`timing_carry_days`, default 1). If the next run finds no payment, the item escalates to MISSING_PAYMENT. The reason is "no payment by close of D+1 window".
- The system compares new payments with open MISSING_PAYMENT items of the last 7 days, by exact reference (`late_payment_lookback_days`).
- Prior-day matches go into a separate section, "Prior-day items cleared". Their status is MATCHED_PRIOR_DAY, or VARIANCE, MISSING_POSTING or POSTING_MISMATCH when those checks fail. The tag is "Paid next day" or "Paid late (D+n)". They are not part of the match rate and totals of the day. Separate prior-day figures count them. The results of D never change. The current state of the item is in `recon_item_states`.
- **Carried PENDING_TIMING sales.** The system compares them with the payments of D+1 by R1, R2 and R3. Fuzzy matching has the same conditions:
  - the same phone;
  - an amount within the tolerance;
  - \|Δt\| ≤ 24 h from the sale;
  - one sale for one payment;
  - no match for a tie.

  It uses one pass with the sales of the current day. Thus a payment that agrees with both is a tie. The result is MATCHED_FUZZY in the prior-day section, with a flag for confirmation. *(Owner, 2026-09-24.)*
- **Escalated items and missing payments in the lookback.** The system matches them automatically only by exact reference or by R2 split. It shows other possible matches: unmatched later payments from the same phone, with an amount within the tolerance, in the lookback. An analyst (`matches.confirm`) can confirm one, with a reason. This makes an audited manual match (who, when and why). The item becomes "Paid late (D+n), manually matched". The system keeps the match separately and applies it again at each new run. *(Owner, 2026-09-24.)*

## Business assumptions

- The manual process takes approximately 3 hours each day. There are approximately 300 reconciliation days each year.
- The chart of accounts for the journals is 1100 receivables, 2150 refunds payable, 2190 unidentified receipts (suspense), 4000 sales (cash) and 6150 bad-debt write-off. Finance must confirm these accounts.
- The "time saved" figure on the dashboard uses 3.4 seconds of manual work for each matched sale. Finance must confirm this baseline.
- All data in the demo is synthetic.
