# Risk register

This register contains the risks for the delivery and the operation of ReconFlow. The [Controls matrix](controls-matrix.md) gives the financial controls in the system. The [AI governance](ai-governance.md) document gives the AI risks.

**Scale:** L = low, M = medium, H = high.

| # | Risk | Likelihood | Impact | Response | Owner |
|---|---|---|---|---|---|
| R1 | The real source data is different from the synthetic data (formats, references, cut-off times) | H | M | Connect the real sources first. Change the checks and settings during the parallel run | Automation Engineering hub |
| R2 | Finance does not accept the rules or the tolerances | M | H | Show the rules and the answer keys to Finance. Finance approves the settings before cut-over | Finance process owner |
| R3 | The chart of accounts in the journals is not correct | M | H | Finance confirms the accounts before the first real posting. The accounts are settings | Finance process owner |
| R4 | Access to the real systems is late | M | H | Uploads are a second path for each source. Ask the Steering Committee for access | Steering Committee |
| R5 | The DPO does not approve the AI provider | M | L | The system works fully with the AI off. Do not turn on the AI until the DPO approves it | DPO |
| R6 | Users trust the AI too much | M | M | A person always decides. Monitor the override rate. Do a quarterly review | Finance Manager |
| R7 | The server fails | L | M | Daily backups off the server. Restore procedure in the runbook. A run for any date can occur again | Automation Engineering hub |
| R8 | A secret leaks (API key, database password) | L | H | Secrets are only in environment files and a Docker volume. The logs remove secrets. Rotate the secret with the runbook procedure | Automation Engineering hub |
| R9 | One engineer has all the knowledge | M | M | The documents, the tests and the module structure let other engineers take over | Automation Engineering hub |
| R10 | The volume increases quickly | L | M | The performance test covers 50,000 sales each day. The path to a managed container service is in the deployment document | Automation Engineering hub |
| R11 | Staff keep the manual process after cut-over | M | M | Train the users. The Finance process owner stops the manual process after approval | Finance process owner |
| R12 | The demo server uses HTTP, not HTTPS | H | L | Use only synthetic data on the demo. Production needs a domain and HTTPS | Automation Engineering hub |
