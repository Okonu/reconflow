# ReconFlow documentation

This folder contains the documentation for ReconFlow, the sales reconciliation automation for Tupande.

## Writing standard

We wrote these documents in ASD-STE100 Simplified Technical English (STE). We use these rules:

- One topic for each sentence. A procedure sentence has a maximum of 20 words. A descriptive sentence has a maximum of 25 words.
- Active voice. Instructions start with a verb in the imperative ("Open the run.").
- One instruction for each step. Warnings and cautions come before the step that they apply to.
- The same word always has the same meaning. The glossary at the end of this page gives the terms.
- Technical names do not change. These include code, commands, file names, statuses, permissions and the labels on the screen.

## Documents

| Document | Use it to |
|---|---|
| [Process map](process-map.md) | Compare the manual process today with the automated process |
| [Business case](business-case.md) | See the value, the costs, the rollout plan and the measures of success |
| [Decision log](decision-log.md) | See each important decision, the options, the reason and the use of AI tools |
| [Technology choices](technology-choices.md) | See why we selected each technology |
| [Architecture](architecture.md) | See the application structure, the layers, the coding principles, the parts of the system and the daily data flow |
| [Reconciliation rules](reconciliation-rules.md) | See how the system matches sales, payments and ERP postings |
| [Assumptions](assumptions.md) | See what we assumed and the scope limits |
| [Data model](data-model.md) | See the tables and the relations between them |
| [Controls matrix](controls-matrix.md) | See each risk, its control and the evidence for an auditor |
| [Risk register](risk-register.md) | See the delivery and operation risks and their owners |
| [AI governance](ai-governance.md) | See how the AI assistant is controlled |
| [Data protection](data-protection.md) | See how the system protects personal data |
| [Deployment](deployment.md) | Install, upgrade, back up and roll back the system |
| [Runbook](runbook.md) | Operate the system each day and recover from incidents |
| [Routes and APIs](api.md) | See the pages, actions and API endpoints |
| [Test report](test-report.md) | See the latest test results |

The `samples/` folder contains the upload templates, the test data and the two answer keys. `BUILD_NOTES.md` is the engineering log that we kept during the build. It is a record and is not written in STE.

## Glossary

| Term | Meaning |
|---|---|
| Business date (D) | The day that the sales occurred. The next day is D+1 |
| Sale | A record from the sales system: what the customer must pay |
| Payment | A record from the payment systems: money that Tupande received (M-Pesa, bank or cash) |
| ERP posting | A line in the ERP ledger that records the sale as revenue |
| Batch | One complete, unchangeable copy of the data from one source for one business date |
| Run | One reconciliation of one business date. Each run has a version number |
| Result | The status that a run gives to one sale, or to one payment that has no sale |
| Exception | A result that a person must examine. It has an owner, a severity and a due date |
| Adjustment | A correction that a person proposes. A different person must approve it before it goes to the ERP |
| Maker | The person who proposes an adjustment |
| Checker | The person who approves or rejects an adjustment. The checker cannot be the maker |
| Sign-off | The approval of a business date by a Finance Manager. After sign-off, the date is locked |
| Tolerance | The maximum difference between two amounts that the system accepts as a match |
| Quarantine | The area for rows that fail the data checks. The system does not use these rows |
| Pseudonym token | A code that replaces a phone number or agent ID. The code does not show the real value |
