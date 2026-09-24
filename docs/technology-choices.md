# Technology choices

This document gives the reason for each technology in ReconFlow. We used four criteria:

1. **Control:** the technology helps us test, audit and secure financial logic.
2. **Speed of delivery:** a small team can build and change the system quickly.
3. **Cost:** the licence and operation costs are low.
4. **Handover:** Tupande engineers can find people who know the technology.

## Application

| Area | Choice | Reason | Alternatives that we did not select |
|---|---|---|---|
| Backend framework | Laravel 13 on PHP 8.3 | Authentication, policies, queues, a scheduler, validation and migrations are part of the framework. Most of the system is workflow and controls, so these parts save much time. | FastAPI (Python): good for data work, but we must add authentication, queues and workflow ourselves. NestJS: the same gap. |
| Module structure | `nwidart/laravel-modules`, one module for each business function | Each business function (Ingestion, Reconciliation, Adjustments and others) has its own code, routes, migrations and tests. A team can change one module and not break the others. | Microservices: too much operation work for one daily process. |
| Access control | `spatie/laravel-permission`, with policies | We define permissions in code. Roles are data that an administrator can change. Each action goes through a policy that checks a permission, never a role name. | Role checks in the code: they break when the organisation changes. |
| Money | `brick/math` BigDecimal, and `numeric(14,2)` in the database | Money never uses floating-point numbers. The matching engine uses integer cents. Results are exact. | PHP floats: they cause rounding errors. |
| Excel and CSV | PhpSpreadsheet | The system reads uploads and writes templates and exports on the server. The browser never reads a file. | Parsing in the browser: the server cannot control the result. |
| Frontend | Inertia with React 19, TypeScript, Tailwind and shadcn/ui (Laravel React starter kit) | One codebase and one authentication model. The server shapes all page data. There is no separate API to maintain. | A separate single-page application with a JSON API: more code for the same result. |
| Database | PostgreSQL 16 | One store for data, workflow and audit. It has partial unique indexes, advisory locks, JSON columns and triggers. We use all of them for controls. | MySQL: fewer of these features. A document database: weak for financial transactions. |
| AI assistant | Anthropic Claude through the official PHP SDK | Structured JSON output that the server validates, and good reasoning over records. The system sends only redacted data. A rules-only stub works when there is no API key. | No AI: a valid option, but investigation stays slow. Other providers: possible, because a client interface isolates the provider. |

## Quality

| Area | Choice | Reason |
|---|---|---|
| Tests | Pest 4 (on PHPUnit 12) | Readable tests. The answer keys run as tests. Architecture tests make sure that the code follows the rules (strict types, no role-name checks, `env()` only in configuration files). |
| Static analysis | Larastan (PHPStan for Laravel) and Pint | Larastan finds type errors before run time. Pint keeps one code style. |
| Frontend checks | TypeScript compiler and ESLint | The prop types of each page agree with the server resources. |

## Operation

| Area | Choice | Reason |
|---|---|---|
| Runtime | FrankenPHP in one Docker image | One image contains the web server, PHP and the built frontend. The same image runs the web process, the worker and the scheduler. |
| Orchestration | Docker Compose | One command starts the full system with no configuration. This is the correct size for one daily process. |
| Reverse proxy | Caddy | Automatic HTTPS when a domain is set. It blocks the metrics endpoint from the internet. |
| CI/CD | GitHub Actions | Each change runs the linters, the type checks, the build, the tests, the performance test and (with a key) the AI evaluation. The pipeline publishes the image to the GitHub Container Registry. |
| Monitoring | `/health`, `/ready` and a Prometheus `/metrics` endpoint, with JSON logs and a request ID | Standard tools can monitor the system. The request ID connects a user report to the log lines. |

## AI-assisted engineering tools

The brief asks for a short reason for the AI-assisted engineering tools that we used to build the system.

| Tool | What we used it for | Reason |
|---|---|---|
| Claude (claude.ai) | Analysis of the brief, comparison of approaches, the build prompt, the rules file, the sample data and answer keys, and proposals for edge cases | Good reasoning over long documents. I could examine and change each proposal before the build used it |
| Claude Code | The application code, the tests, the documents, the Docker setup and the deployment, under my direction and review | It works in the repository and runs the tests, the linters and the build itself. It follows `CLAUDE.md` and asks me when it is in doubt |
