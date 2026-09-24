# Reconciliation rules

This is for finance users. Every business day, ReconFlow compares three lists:

1. **Sales**: what agents sold, and what the customer should pay (from the sales system).
2. **Payments**: money received by mobile money, bank or cash (from the payments system).
3. **ERP postings**: what was recorded as revenue in the ledger (account `4000-SALES-CASH`).

Each sale, and each payment that belongs to no sale, gets exactly one status. The rules run in a fixed order, and the result records which rule decided it (`rule_id`), so any line in the report can be explained. All times are Nairobi time (EAT).

## Settings that affect matching

Admins change these on the Settings page. Every change creates a new version, and each run records the version it used.

| Setting | Default | Meaning |
|---|---|---|
| Amount tolerance | $0.50 (or 0.5% if larger) | Differences up to this count as a match |
| Fuzzy window | 24 hours | Maximum time between a sale and a payment without a usable reference |
| Timing cut-off | 22:00 | Unpaid sales at or after this time get a day's grace |
| Duplicate window | 5 minutes | Same reference and amount within this window is a duplicate |
| Payment window | D 00:00 to D+1 06:00 | Early-morning payments on D+1 can still settle D's sales |
| Late-payment lookback | 7 days | Later payments can clear open missing payments from this far back |

## The rules, in order

### R5: Duplicate payments (runs first)
The same receipt appears twice, or two payments carry the same reference and amount within 5 minutes. The first counts; the copy becomes **DUPLICATE_PAYMENT** so it cannot also settle a sale.
*Example: M-Pesa receipt SL0LMI8X76 is sent twice by the payments system → the second copy is a duplicate payment, and the customer may need a refund.*

### Manual matches
When an analyst has confirmed that a late payment belongs to an older sale (see "Possible late payments" below), that pairing is applied before the automatic rules and tagged "Paid late (D+n), manually matched".

### R1: Exact reference
The payment's reference equals the sale's transaction ID.
*Example: sale TUP-S-000041 for $32.00; payment reference TUP-S-000041 → matched.*

### R2: Split payment
Several payments carry the same reference. If together they add up to the expected amount (within tolerance), the sale is **MATCHED_SPLIT**.
*Example: $50.00 sale paid as $30.00 + $20.00 → matched split.* If the total is off by more than the tolerance, it is a **VARIANCE** (`R2+R4`).

### R3: Fuzzy match (needs a person to confirm)
The payment has no usable reference, but it comes from the customer's own phone, the amount is within tolerance, and it arrived within 24 hours of the sale. If exactly one sale fits, the item is **MATCHED_FUZZY**. It is shown as a match but flagged; someone must confirm it on the **Fuzzy matches** page before the day can be signed off. Rejecting it splits it into a missing payment and an unmatched payment. If two sales fit equally well, nothing is matched (a tie goes to exceptions).
*Example: a $28.00 bank deposit with reference "ACC 7781" from the same phone as a $28.00 sale earlier that day → fuzzy match, confidence shown.*

### R4: Amount check
For every matched sale: is the amount received within tolerance of the amount expected?
- Within $0.00 → **MATCHED**; within tolerance but not exact → **MATCHED_TOLERANCE**.
- Beyond tolerance → **VARIANCE**, with the difference in dollars and percent.

*Example: expected $32.00, received $30.00 → variance −$2.00 (under-paid).*

VARIANCE takes precedence: if the customer under-paid, that is reported before any ERP question (answer-key rule).

### R6: ERP posting check
For every sale matched within tolerance, ReconFlow looks up the revenue lines posted for its transaction ID. ReconFlow's own correcting journals (`ADJ-…`) count towards the total.
- Nothing posted → **MISSING_POSTING**.
- Total posted ≠ expected → **POSTING_MISMATCH**.
- Two different posted journals for the same sale → **DUPLICATE_POSTING** (reversed lines are ignored).

*Example: sale and payment agree at $60.00, ERP shows $66.00 → posting mismatch; the fix is a correct-posting adjustment.*

### R7: Leftovers
- A sale with no payment → **MISSING_PAYMENT**, unless it happened at or after the timing cut-off (22:00), in which case it is **PENDING_TIMING**, a soft exception with no SLA yet.
- A payment with no sale → **UNMATCHED_PAYMENT** (the brief's "Expected: Missing"). Payments received after midnight that no D sale claimed are left for the next day and not reported on D.

## Across days

- **Timing items get one day's grace.** If the next day's payments include the customer's payment, the sale is cleared as **MATCHED_PRIOR_DAY** ("Paid next day") and the timing exception closes automatically. If not, it escalates to **MISSING_PAYMENT** with an SLA.
- **Late payments.** New payments are checked by exact reference against open missing payments from the last 7 days. A hit clears the old item as **MATCHED_PRIOR_DAY**, "Paid late (D+n)".
- **Possible late payments.** For a missing payment, analysts can ask for unmatched later payments from the same phone within tolerance. Confirming one creates an audited manual match.
- **Prior-day clearances are reported separately.** They do not change the earlier day's report or today's match rate. Every payment is counted exactly once.
- **Re-running a date** creates a new version. If D is re-run after D+1 exists, D+1 is marked stale until it is re-run too.

## Statuses at a glance

| Status | Report shows | What usually happens next |
|---|---|---|
| MATCHED / MATCHED_TOLERANCE / MATCHED_SPLIT | Match | Nothing |
| MATCHED_FUZZY | Match (flagged) | Confirm or reject on the Fuzzy matches page |
| MATCHED_PRIOR_DAY | Match (prior day) | Nothing |
| VARIANCE | Variance | Contact the customer; write off a small shortfall or refund an overpayment |
| POSTING_MISMATCH | Variance | Correct the ERP posting (reverse and repost) |
| MISSING_PAYMENT | Exception | Chase the customer, or match a late payment manually |
| PENDING_TIMING | Exception (soft) | Wait one day; it clears or escalates automatically |
| UNMATCHED_PAYMENT | Exception | Find the sale, or move to suspense |
| MISSING_POSTING | Exception | Post the missing sale to the ERP |
| DUPLICATE_PAYMENT | Exception | Refund the duplicate |
| DUPLICATE_POSTING | Exception | Reverse the duplicate journal |

## Exception severity and SLA

Severity comes from the value at risk (Settings → Exception severity and SLAs):

| Severity | Value at risk | SLA | Effect |
|---|---|---|---|
| Low | under $50 | 5 days | |
| Medium | $50 to under $500 | 72 hours | Duplicate payments, posting mismatches and duplicate postings are at least Medium |
| High | $500 to under $2,000 | 24 hours | Blocks sign-off |
| Critical | $2,000 or more | 8 hours | Blocks sign-off; notifies Finance Managers |

Timing items have no SLA until they escalate.
