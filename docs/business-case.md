# Business case

This document gives the value of ReconFlow, its costs, the rollout plan and the measures of success. The values are estimates. We will measure the real values during the parallel run.

## Value

| Value | Today | With ReconFlow | Basis |
|---|---|---|---|
| Daily effort | Approximately 3 hours | Approximately 20 minutes of review | Refer to the [Process map](process-map.md) |
| Staff time released | | Approximately 800 hours each year | 160 minutes each day × 300 reconciliation days |
| Time to find exceptions | Next day | Same morning (the run starts at 06:00) | Scheduled run |
| Control | Approval by email, no audit trail | Maker-checker, threshold, sign-off and a hash-chained audit trail | [Controls matrix](controls-matrix.md) |
| Scale | Effort increases with volume | 50,000 sales reconcile in less than 10 seconds | Performance test |

Other value that we did not put into numbers:

- Tupande finds cash shortfalls, duplicate payments and posting errors while it can still recover the money.
- Auditors can check the full history without help from the team.
- The connectors and the controls are reusable for the next automations.

## Costs

| Cost | Estimate | Note |
|---|---|---|
| Hosting | One Linux server with 2 vCPU and 4 GB RAM | The demo uses approximately 400 MB of memory on a shared server |
| AI usage | Small | The AI is optional. It has rate limits and a cap on each batch. The dashboard shows the token use |
| Licences | None | All the software is open source, except the AI API |
| Support | Part of one engineer, with Finance super-users | Refer to the operating model in the memo |

We will give a full cost estimate after the parallel run, when we know the real volumes.

## Rollout plan

| Phase | Days | Work | Exit criteria |
|---|---|---|---|
| 1. Connect | 0 to 15 | Connect the real sales, M-Pesa, bank and ERP systems. Confirm the chart of accounts and the tolerances with Finance | The system gets data from all three sources |
| 2. Parallel run | 15 to 30 | Operate ReconFlow and the manual process together. Compare the results each day | The results agree, or each difference has a reason |
| 3. Cut-over | 31 to 45 | Stop the manual process. Train the analysts and managers | Finance approves the cut-over |
| 4. Hypercare | 46 to 90 | Daily check-ins. Fix issues. Measure the benefits at 30 and 90 days | The benefits report goes to the Steering Committee |

Before go-live, these conditions must be true:

1. The DPO approves the AI provider terms and the transfer of data out of Kenya ([AI governance](ai-governance.md)).
2. Finance names a process owner and confirms the chart of accounts.
3. The system uses HTTPS with a domain.
4. A penetration test finds no high-risk issues.

## Measures of success

| Measure | Target after 12 months |
|---|---|
| Daily reconciliation effort | 20 minutes or less |
| Time to find exceptions | Same morning |
| Critical exceptions resolved within their due date | 95% or more |
| Adjustments posted without a failure | 99% or more |
| Audit chain check | Passes every month |

## Roadmap after go-live

| Item | Reason |
|---|---|
| Intraday runs for early warning | Find duplicate payments and payments with no sale within minutes. The daily close stays the control |
| M-Pesa push notifications | Match payments when they arrive |
| Single sign-on (SSO) | One login for staff |
| Credit sales | Send credit instalments to receivables, not to reconciliation |
| High availability | Needed only if the business needs the system all day |
