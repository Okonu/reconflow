# Reconciliation rules

This document is for Finance users. Each business day, ReconFlow compares three lists:

1. **Sales:** what the agents sold, and what the customer must pay (from the sales system).
2. **Payments:** the money that Tupande received by mobile money, bank or cash (from the payment systems).
3. **ERP postings:** the revenue that the ledger records (account `4000-SALES-CASH`).

Each sale gets one status. Each payment that has no sale also gets one status. The rules apply in a fixed order. Each result records the rule that decided it (`rule_id`). Thus you can explain each line in the report. All times are Nairobi time (EAT).

## Settings that change the matching

An administrator changes these values on the Settings page. Each change makes a new version. Each run records the version that it used.

| Setting | Default | Meaning |
|---|---|---|
| Amount tolerance | $0.50 (or 0.5%, if that is larger) | A difference up to this value is a match |
| Fuzzy window | 24 hours | The maximum time between a sale and a payment that has no usable reference |
| Timing cut-off | 22:00 | An unpaid sale at or after this time gets one day more |
| Duplicate window | 5 minutes | Two payments with the same reference and amount in this time are duplicates |
| Payment window | D 00:00 to D+1 06:00 | A payment early on D+1 can still pay a sale from D |
| Late-payment lookback | 7 days | A later payment can clear a missing payment up to this age |

## The rules, in order

### R5: Duplicate payments (first rule)
The same receipt occurs two times. Or, two payments have the same reference and amount within 5 minutes. The first payment counts. The copy becomes **DUPLICATE_PAYMENT**, and it cannot pay a sale.

*Example: the payment system sends M-Pesa receipt SL0LMI8X76 two times. The second copy is a duplicate payment. The customer can need a refund.*

### Manual matches
An analyst can confirm that a late payment belongs to an older sale (refer to "Possible late payments"). The system applies this pair before the automatic rules. The tag is "Paid late (D+n), manually matched".

### R1: Exact reference
The payment reference is the same as the transaction ID of the sale.

*Example: sale TUP-S-000041 for $32.00. The payment reference is TUP-S-000041. The result is a match.*

### R2: Split payment
Two or more payments have the same reference. If their total is the expected amount (within the tolerance), the sale is **MATCHED_SPLIT**. If the difference is more than the tolerance, the sale is a **VARIANCE** (rule `R2+R4`).

*Example: a $50.00 sale, paid as $30.00 and $20.00. The result is a split match.*

### R3: Fuzzy match (a person must confirm it)
These conditions must all be true:

- The payment has no usable reference.
- The payment comes from the phone of the customer.
- The amount is within the tolerance.
- The payment arrived within 24 hours of the sale.

If only one sale agrees with the conditions, the item is **MATCHED_FUZZY**. The report shows it as a match with a flag. A person must confirm it on the **Fuzzy matches** page before sign-off. If the person rejects it, it becomes a missing payment and an unmatched payment. If two sales agree equally, the system matches neither. The items become exceptions.

*Example: a $28.00 bank deposit with the reference "ACC 7781" comes from the same phone as a $28.00 sale on the same day. The result is a fuzzy match. The page shows the confidence.*

### R4: Amount check
For each matched sale, the system compares the amount received with the expected amount.

- The difference is $0.00: **MATCHED**.
- The difference is within the tolerance but not zero: **MATCHED_TOLERANCE**.
- The difference is more than the tolerance: **VARIANCE**. The page shows the difference in dollars and as a percentage.

*Example: expected $32.00, received $30.00. The variance is −$2.00 (the customer paid too little).*

A VARIANCE comes before the ERP check. If the customer paid too little, the report shows that first. This agrees with the answer keys.

### R6: ERP posting check
For each sale that matched within the tolerance, the system finds the revenue lines for its transaction ID. ReconFlow correcting journals (`ADJ-…`) are part of the total.

- No line: **MISSING_POSTING**.
- The total is not the expected amount: **POSTING_MISMATCH**.
- Two different posted journals for the same sale: **DUPLICATE_POSTING**. The system does not count reversed lines.

*Example: the sale and the payment agree at $60.00. The ERP shows $66.00. The result is a posting mismatch. The correction is a "correct posting" adjustment.*

### R7: Items that remain
- A sale with no payment is **MISSING_PAYMENT**. If the sale occurred at or after the timing cut-off (22:00), it is **PENDING_TIMING**. This is a soft exception with no due date yet.
- A payment with no sale is **UNMATCHED_PAYMENT**. In the brief, this is "Expected: Missing". If a payment arrived after midnight and no sale of day D used it, it stays for the next day. The report for D does not show it.

## Across days

- **Timing items get one day more.** If a payment arrives the next day, the sale becomes **MATCHED_PRIOR_DAY** ("Paid next day"). The timing exception closes automatically. If no payment arrives, the item becomes **MISSING_PAYMENT** with a due date.
- **Late payments.** The system compares each new payment with the open missing payments of the last 7 days, by exact reference. If they agree, the old item becomes **MATCHED_PRIOR_DAY** ("Paid late (D+n)").
- **Possible late payments.** For a missing payment, an analyst can ask for later unmatched payments from the same phone, within the tolerance. If the analyst confirms one, the system makes an audited manual match.
- **The report shows prior-day items in a separate section.** They do not change the report of the earlier day. They do not change the match rate of the current day. The system counts each payment one time only.
- **A run for a date that has a run.** The new run is a new version. If you run D again after D+1 has a run, D+1 becomes "stale". You must then run D+1 again.

## Statuses

| Status | The report shows | The usual next step |
|---|---|---|
| MATCHED / MATCHED_TOLERANCE / MATCHED_SPLIT | Match | No step |
| MATCHED_FUZZY | Match (with a flag) | Confirm or reject it on the Fuzzy matches page |
| MATCHED_PRIOR_DAY | Match (prior day) | No step |
| VARIANCE | Variance | Speak to the customer. Write off a small shortfall or refund an overpayment |
| POSTING_MISMATCH | Variance | Correct the ERP posting (reverse and post again) |
| MISSING_PAYMENT | Exception | Speak to the customer, or match a late payment manually |
| PENDING_TIMING | Exception (soft) | Wait one day. The item clears or escalates automatically |
| UNMATCHED_PAYMENT | Exception | Find the sale, or move the payment to suspense |
| MISSING_POSTING | Exception | Post the missing sale to the ERP |
| DUPLICATE_PAYMENT | Exception | Refund the duplicate |
| DUPLICATE_POSTING | Exception | Reverse the duplicate journal |

## Severity and due dates of exceptions

The value at risk sets the severity (Settings → Exception severity and SLAs):

| Severity | Value at risk | Due in | Effect |
|---|---|---|---|
| Low | Less than $50 | 5 days | |
| Medium | $50 to less than $500 | 72 hours | Duplicate payments, posting mismatches and duplicate postings are Medium or higher |
| High | $500 to less than $2,000 | 24 hours | Stops sign-off |
| Critical | $2,000 or more | 8 hours | Stops sign-off. Sends an alert to the Finance Managers |

Timing items have no due date until they escalate.
