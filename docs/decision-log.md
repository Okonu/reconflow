# Decision log

This log records the important decisions for ReconFlow. Each entry gives the options, the decision, the reason and the trade-off.

Each entry also has an **AI-assisted tooling** field. It gives the direction or decision of the author first. Then it gives what the AI tools made, and what the author changed or rejected.

**Status key:** Accepted = in the build. Deferred = a step for production.

## AI-assisted tooling: summary

The author (Ian Okonu) led this work. He defined the problem, set the engineering standards, gave the direction for each part and made every decision. He used AI tools to do the drafting and the coding under his direction. He examined the results and changed or rejected them when necessary.

| Item | Entry |
|---|---|
| Tools | Claude (claude.ai) for analysis and specification. Claude Code for the code, the tests, the documents and the deployment |
| What the author did | Defined the problem and the scope from the brief. Directed and approved the build prompt (`BUILD_PROMPT.md`). Supplied the engineering rules from an earlier project as the base for `CLAUDE.md`, and added rules such as "when in doubt, ask the owner". Set the order of authority. Selected the stack. Decided each rule and each edge case. Approved or rejected each proposal. Selected his server for the deployment and directed it |
| What the AI tools did under his direction | Claude analysed the brief, compared the approaches and drafted the build prompt, the rules file, the sample data, the answer keys and the edge-case proposals. Claude Code wrote all the application code, the tests and these documents, and it found and repaired defects |
| Governing documents | The author spent much of the work on the documents that govern the AI tools. These are the build prompt (`BUILD_PROMPT.md`), the engineering rules (`CLAUDE.md`), the order of authority and more than 30 rule decisions. These documents are the policies, guardrails and instructions that the coding agent must obey. Claude drafted the text to the direction of the author. The author set the content and the limits, examined each version and changed it. |
| Deployment | The author selected an isolated demo environment for the deployment. The AI tool deployed the system only there. Refer to D-19 |
| How the author controlled the output | Every change goes through the rules in `CLAUDE.md`, the two answer keys, 376 automated tests, Pint and Larastan. The coding agent must ask the author when it is in doubt. The author decided every conflict and edge case that the agent raised |

## Order of authority

When two sources do not agree, this order applies:

1. The instructions of the repository owner.
2. The answer keys in `samples/`, for the behaviour of the reconciliation rules.
3. `CLAUDE.md`, for how we build the code.
4. `BUILD_PROMPT.md`, for the scope and the features.

A person must decide each conflict. The build does not solve a conflict silently.

---

### D-01 · Solution approach
- **Options:** (a) better spreadsheets and scripts; (b) screen-automation bots (RPA); (c) a low-code workflow tool; (d) buy a reconciliation product; (e) a code-first application.
- **Decision:** A code-first application: data pipeline, rules engine and workflow application.
- **Reason:** Financial controls need logic that we can test and audit. They also need segregation of duties and an audit trail. RPA copies the manual process and stops when a screen changes. Low-code tools make complex matching and testing difficult. To buy a product is a valid option. The operating model records it as a "buy, reuse or build" check.
- **Trade-off:** The application needs engineers to maintain it. Standards, documents and a shared platform decrease this cost.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author accepted the recommendation. He kept "buy" as a check in the operating model, not as a rejected option. _What the AI tools made:_ Claude compared six approaches (spreadsheets, RPA, low-code, buy, AI matching, code-first) and recommended code-first.

### D-02 · How the system matches transactions
- **Options:** deterministic rules; machine-learning matching; matching by a large language model.
- **Decision:** Rules in a fixed order: R5 duplicates, manual matches, R1 exact reference, R2 split, R3 fuzzy, R4 amount, R6 posting, R7 leftovers. Each result records the rule that decided it.
- **Reason:** The same input always gives the same result. Finance and auditors can see why each result occurred. We can test each rule.
- **Trade-off:** A new type of mismatch needs a new rule. Rule settings have versions and each change is audited.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author made the answer keys the authority for rule behaviour. When the prompt and the answer keys did not agree on R6, the author selected the answer keys. _What the AI tools made:_ Claude proposed the rule set R1 to R7. Claude also made the golden and volume datasets and the answer keys, and checked them with an independent reference matcher.

### D-03 · Where the system uses AI
- **Options:** no AI; AI decides; AI suggests and a person decides.
- **Decision:** The AI suggests a likely cause and a next action for an exception. A person must accept or override the suggestion. An override needs a reason. The AI module can write only to the `ai_suggestions` table.
- **Reason:** Investigation is the most manual step. The AI makes it faster, but people stay accountable for financial decisions.
- **Trade-off:** Cost and a dependency on a provider. A kill switch, a rules-only fallback and oversight metrics decrease this risk.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author questioned the AI principle in the prompt, and Claude removed the AI layer. After Claude explained its purpose, the author decided to keep it, as an assistant only. _What the AI tools made:_ Claude put AI exception triage in the build prompt.

### D-04 · Personal data and the AI provider
- **Options:** send full records; send nothing; send only the necessary fields, with personal data replaced by tokens.
- **Decision:** The `Redactor` replaces phone numbers and agent IDs with salted pseudonym tokens before each AI call. The system sends only the fields that the task needs. It keeps a copy of what it sent. A test makes sure that no phone number goes to the provider.
- **Reason:** Data minimisation, as the Kenya Data Protection Act 2019 requires. It also decreases the risk of the transfer of data out of Kenya.
- **Trade-off:** The AI has less context. Amounts, times and rule results give the AI sufficient information.
- **Status:** Accepted. Before go-live, the Data Protection Officer (DPO) must approve the terms of the provider and the transfer of data out of Kenya.
- **AI-assisted tooling:** _Author's direction and decision:_ The author made AI governance and data protection a requirement for the full design, not only for the AI feature. _What the AI tools made:_ Claude wrote the AI governance and data protection section of the build prompt. Claude Code wrote the `Redactor` and the tests.

### D-05 · Technology stack
- **Options:** Python with FastAPI and React; Laravel with Inertia and React; Node with NestJS and React; a low-code platform.
- **Decision:** Laravel 13 (PHP 8.3) with one module for each business function, PostgreSQL 16, and Inertia with React and TypeScript. Refer to [Technology choices](technology-choices.md).
- **Reason:** Laravel gives authentication, policies, queues, a scheduler, validation and Excel import and export. Most of the application is workflow and controls, so these parts save time. The owner knows the framework well. The matching engine is pure PHP code, and it reconciles 50,000 sales in less than 10 seconds.
- **Trade-off:** We first planned Python and FastAPI. The change on 2026-09-23 made us rewrite the build prompt and the rules file.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author rejected this advice and selected Laravel. Claude then converted the build prompt and the rules file to Laravel. _What the AI tools made:_ Claude recommended Python and FastAPI. When the author asked about Laravel, Claude advised him to keep FastAPI.

### D-06 · Hosting and deployment
- **Options:** Kubernetes; a managed cloud container service; Docker Compose on one server.
- **Decision:** One application image, a worker, a scheduler, PostgreSQL and a Caddy reverse proxy. Docker Compose runs them on one Linux server. The stack starts with one command and needs no configuration. GitHub Actions tests the code and builds the image.
- **Reason:** The size is correct for one reconciliation each day. It is cheap, easy to operate and easy to hand over.
- **Trade-off:** There is no high availability. The deployment document gives the path to a managed container service.
- **Status:** Accepted. High availability is deferred.
- **AI-assisted tooling:** _Author's direction and decision:_ The author selected his own server. He required a stack that starts with no configuration, for the panel. Claude Code changed the setup to meet this requirement. It deployed the system, to the direction of the author, in the isolated demo environment of D-19. _What the AI tools made:_ Claude proposed Docker Compose and a managed host. Claude Code built the Compose stack and the CI pipeline.

### D-07 · Segregation of duties
- **Decision:** The server enforces maker-checker. A person cannot approve an adjustment that they proposed. An adjustment of more than $1,000 needs a Finance Manager. Automated tests prove these controls.
- **Reason:** This is the main financial control in the brief ("manual follow-up, approval, and posting").
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author's engineering rules require that the server, not the browser, enforces all access and approval controls. _What the AI tools made:_ Claude specified maker-checker and the $1,000 threshold. Claude Code wrote the policy and the tests.

### D-08 · Audit trail
- **Options:** application logs only; an audit table; an append-only audit table with a hash chain.
- **Decision:** An append-only audit table with a hash chain. Each event keeps the hash of the previous event. A database trigger stops updates and deletes. A "Verify integrity" button calculates the chain again.
- **Reason:** A change to the audit trail becomes visible. Auditors can do the check themselves.
- **Trade-off:** A small cost for each write. At this volume, the cost is not important.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author set the retention of the audit log (7 years, then an archive with a checkpoint). _What the AI tools made:_ Claude specified the hash chain. Claude Code wrote the audit module, the trigger and the verifier.

### D-09 · Runs again and ERP postings that occur two times
- **Decision:** Each run has a version for its business date. A new run replaces the previous version but does not delete it. Each adjustment has one idempotency key. If the ERP gets the same key again, it returns the first journal and posts nothing.
- **Reason:** Late data and failures are usual. A new run must never post a correction two times.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author decided how a new run keeps exceptions, comments and adjustments. _What the AI tools made:_ Claude specified run versions and idempotency keys. Claude Code wrote them.

### D-10 · Bad source data
- **Options:** stop the full run; remove bad rows silently; put bad rows in quarantine with a reason.
- **Decision:** The system checks each batch (columns, types, currency, decimal places, dates, duplicates). It puts each bad row in quarantine with a reason. If a source is missing, the run status is `BLOCKED_DATA` and the system sends an alert.
- **Reason:** One bad row must not stop the day. No data must disappear silently.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author decided that amounts with more than 2 decimal places go into quarantine and are never rounded. He also decided the rules for duplicate IDs. _What the AI tools made:_ Claude specified the data checks. Claude Code wrote them and proposed more checks.

### D-11 · Source systems for the demo
- **Decision:** Three simulated source APIs (sales, payments, ERP) behind a connector interface. Excel and CSV uploads are the second path. All demo data is synthetic.
- **Reason:** We do not have access to the real systems. Real connectors can replace the simulated connectors without a change to the matching engine.
- **Status:** Accepted. Real connectors are deferred.
- **AI-assisted tooling:** _Author's direction and decision:_ The author accepted the design. _What the AI tools made:_ Claude specified the connector interface and the simulated source APIs. Claude Code wrote them.

### D-12 · Uploads, templates and staging
- **Decision:** The system makes the upload templates from the same schema classes that the importer uses. An upload goes to a staging area first. The user sees a preview with the header check, the counts and the row errors. Data goes into the live tables only when the user confirms.
- **Reason:** The templates and the importer cannot become different. Users can test the system with their own files. No bad file can change the live data.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author asked for seed data, templates that users can download, and a preview of each upload. These were not in the first prompt. _What the AI tools made:_ Claude designed the upload flow and made the Excel templates. Claude Code wrote the staging, the preview and the template builder.

### D-13 · Timing differences
- **Decision:** An unpaid sale at or after 22:00 becomes a soft `PENDING_TIMING` exception. It goes forward one day. If a payment arrives, the item closes automatically. If no payment arrives, the item becomes `MISSING_PAYMENT`.
- **Reason:** Mobile money and bank payments often arrive after midnight. Hard exceptions for these payments would fill the queue.
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author decided the escalation: one day of carry-forward, then `MISSING_PAYMENT`. _What the AI tools made:_ Claude proposed the timing rule and the carry-forward.

### D-14 · Tolerances and settings
- **Decision:** The default amount tolerance is $0.50. The fuzzy window is 24 hours. The duplicate window is 5 minutes. Severity bands and the approval threshold are also settings. An administrator can change them. Each change has a version, an author and a reason.
- **Reason:** Finance owns these values, not engineering. Each change must leave a record.
- **Status:** Accepted. Finance must confirm the values.
- **AI-assisted tooling:** _Author's direction and decision:_ The author accepted the values. Finance must confirm them. _What the AI tools made:_ Claude proposed the default values.

### D-15 · Edge-case rules (decided by the owner on 2026-09-23 and 2026-09-24)
- **Decision:** The owner decided these rules. [Reconciliation rules](reconciliation-rules.md) and [Assumptions](assumptions.md) give the full text.
  - All limits include the edge value. The payment window is the exception: it includes its start and excludes its end.
  - The system does not round an amount with more than 2 decimal places. It puts the row in quarantine.
  - Rows that share a sales or journal ID but have different content go into quarantine. None of them goes into matching.
  - "Run now" uses the latest closed business date. A run for an open date is provisional and cannot be signed off.
  - A payment belongs to the date of its timestamp. The system counts each payment one time only.
  - A new run keeps the exceptions and their comments, owners and adjustments.
  - Fuzzy matches go to a confirmation list. Unconfirmed fuzzy matches stop sign-off.
  - An approved adjustment posts a balanced double-entry journal to the ERP.
- **Reason:** The answer keys do not cover these cases. A person must decide them, because each case changes a financial result.
- **Status:** Accepted. None of these rules changes the two answer keys.
- **AI-assisted tooling:** _Author's direction and decision:_ The author decided each answer and sent it to the coding agent. When time was short, he told the agent to record new edge cases with a default, and not to stop. _What the AI tools made:_ Claude Code raised each edge case as a question. Claude proposed an answer for each question.

### D-16 · Testing strategy
- **Decision:** Unit tests for each rule. Two answer keys as a regression gate: a golden set (65 items) and a volume set (2,531 items). Tests for the workflow, segregation of duties, idempotency, the audit chain, permissions on each route, AI governance and data protection. A performance test with 50,000 sales.
- **Reason:** Finance can read the answer keys and confirm that the rules do what Finance expects.
- **Status:** Accepted. 376 tests pass. Refer to the [Test report](test-report.md).
- **AI-assisted tooling:** _Author's direction and decision:_ The author accepted the test strategy. He made the answer keys the authority, above the build prompt. _What the AI tools made:_ Claude Code wrote the tests. The tests found defects, for example a method name that stopped the report pages and a template download that used too much memory. Claude Code repaired them.

### D-17 · Scope limits
- **Decision:** Cash sales only. One currency (USD, as in the sample in the brief). One legal entity. One daily run and a "Run now" button. SSO, multiple currencies, intraday runs and high availability are deferred.
- **Reason:** A complete and controlled core is better than a wide and incomplete system. The design lets us add these items later.
- **Status:** Deferred items are in the [Business case](business-case.md) roadmap.
- **AI-assisted tooling:** _Author's direction and decision:_ The author accepted them, including the credit-sales limit. _What the AI tools made:_ Claude proposed the scope limits.

### D-18 · Scope for the submission day (2026-09-24)
- **Decision:** On the last day, the build had five priorities, in this order:
  1. The demo path from end to end.
  2. The deployment.
  3. The upload preview.
  4. The AI assistant.
  5. The other features.
- **Reason:** The panel must be able to use a working and deployed system. A complete path is more valuable than many incomplete features.
- **Status:** Accepted. All items in the scope are complete.
- **AI-assisted tooling:** _Author's direction and decision:_ The author accepted the order. _What the AI tools made:_ Claude proposed the priority order for the last day.

### D-19 · Governance of the AI tools during the build
- **Options:** let the AI tools work without limits; do not use AI tools; use AI tools inside documented guardrails, with a person who decides.
- **Decision:** The AI tools worked only inside written guardrails:
  - `CLAUDE.md` gives the engineering rules. Each rule is mandatory. The coding agent must ask the author when it is in doubt, and it must not solve a conflict silently.
  - `BUILD_PROMPT.md` gives the scope and the phases.
  - The answer keys in `samples/` are read-only. The agent must not change them to make a test pass.
  - The author decided each conflict and each edge case that the agent raised.
- **Deployment:** the author selected an isolated demo environment. The AI tool deployed only to this environment. The environment has these properties:
  - ReconFlow has its own containers, Docker networks and volumes on the demo server. The database is on an internal network with no access from outside.
  - Only one port (8090) is open. The other services on the server did not change.
  - The data is synthetic only. There is no connection to Tupande systems or to real customer data.
- **Production rule:** an AI tool must not deploy to production. For production, a person approves each release at the Tier 1 release gate of the operating model. The CI pipeline then deploys it.
- **Reason:** AI tools make delivery fast. Written guardrails and human decisions keep the quality and the control. The same approach is part of the operating model: "AI-assisted engineering is encouraged but always reviewed and recorded."
- **Status:** Accepted
- **AI-assisted tooling:** _Author's direction and decision:_ The author set the guardrails, the order of authority and the isolated environment. He decided that the agent must ask when it is in doubt. _What the AI tools made:_ Claude drafted `CLAUDE.md` from the rules of an earlier project of the author. Claude Code obeyed the guardrails and raised conflicts for the author to decide.

---

## Assumptions register

| # | Assumption | Effect if it is wrong | How we will confirm it |
|---|---|---|---|
| A1 | There are three sources: the sales system, the payment systems (M-Pesa and bank) and the ERP | We must add a connector | Discovery with Finance |
| A2 | The payment reference usually contains the transaction ID of the sale | More fuzzy matches and exceptions | A sample of real payment data |
| A3 | The data for day D is complete at 06:00 on D+1 | We must change the schedule | Check the cut-off times of the source systems |
| A4 | One currency (USD) | We must add currency conversion | Finance |
| A5 | There are approximately 300 reconciliation days each year | The estimate of hours saved changes | The Finance calendar |
| A6 | The tolerances ($0.50 and 24 hours) are acceptable | We must change the settings | Finance approval during the parallel run |
| A7 | A Finance Manager approves adjustments of more than $1,000 | We must change the approval matrix | Finance policy |
| A8 | The manual effort is approximately 3 hours each day | The value changes | Measure the baseline during discovery |
| A9 | The chart of accounts in the journals (1100, 2150, 2190, 4000, 6150) is correct | We must change the account settings | Finance |
