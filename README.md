# Merchant Readiness Workspace

Merchant Readiness Workspace is a Laravel, Vue, and Inertia application for ecommerce returns-readiness assessment. It helps a merchant complete a guided intake, receive deterministic scoring and recommendations, and share a tokenized report before any sales conversation.

This is not a demonstration CRUD app or a static questionnaire. It models a real qualification workflow that could support sales engineering, customer success, implementation consulting, or partner-led discovery for ecommerce SaaS. The application combines anonymous merchant intake, resumable assessment drafts, optional website and CSV-assisted autofill, rule-based scoring, opportunity calculations, shareable reports, and an authenticated internal review workspace.

## Live Demo

- App: https://commerce-cartographer.onrender.com
- Start an anonymous assessment: https://commerce-cartographer.onrender.com/assessment
- Sample report: https://commerce-cartographer.onrender.com/sample-report
- Internal workspace: https://commerce-cartographer.onrender.com/dashboard
- Demo-only credentials: `admin@merchant-readiness.test` / `password`

The demo credentials work when demo data has been seeded in the target environment. Demo data includes three realistic submitted merchants across readiness tiers so the internal workspace, search/sort behavior, report view, and review workflow can be evaluated without submitting a new assessment.

## What This Project Demonstrates

This repository is intended to show senior-level engineering judgment across product discovery, architecture, implementation, testing, and deployment.

- Converted an ambiguous ecommerce returns opportunity into a working product workflow with public intake, internal review, and shareable output.
- Learned and applied Laravel 12, Vue 3, Inertia, Tailwind CSS, queues, migrations, seeders, factories, and Laravel authentication in a short delivery cycle.
- Modeled a multi-stage assessment domain with `Merchant`, `Assessment`, `AssessmentAnswer`, `Recommendation`, `Report`, import, evidence, benchmark, and opportunity persistence.
- Kept controllers thin where practical and moved workflow decisions into services such as `CreateAssessmentService`, `SaveAssessmentAnswersService`, `SubmitAssessmentService`, `ReadinessScoringService`, `RecommendationEngine`, `ReportBuilderService`, and import/website-scan services.
- Built deterministic scoring and recommendation logic that remains explainable, testable, and independent of optional AI behavior.
- Integrated optional LLM-assisted website extraction and executive insight generation without making scoring, recommendations, submission, or reports dependent on an external provider.
- Implemented authenticated internal routes, anonymous public assessment routes, throttled public mutation endpoints, tokenized report access, request validation, and SSRF-conscious website scanning.
- Designed safe and idempotent demo-data operations using real application services rather than hand-written database fixtures.
- Deployed a Dockerized Laravel application on Render with health checks, environment-based configuration, PostgreSQL support, startup migrations, stderr logging, and documented operational constraints.
- Documented architecture, product requirements, design direction, data ingestion, benchmarks, AI decisions, deployment, and developer setup in `docs/`.

## Delivery Approach

The project was built from the merchant workflow outward rather than by assembling disconnected layers.

1. Product and architecture documents defined the assessment intent, user roles, domain model, and non-goals: this is a readiness and qualification product, not a live returns platform.
2. The core domain was established first: merchants, assessments, answers, recommendations, reports, authentication, factories, seeders, and tests.
3. The public assessment lifecycle was implemented vertically: anonymous draft creation, resumable URLs, draft autosave, validation, submission, scoring, recommendations, report creation, and redirect to the report.
4. Deterministic scoring and rule-based recommendations were built before optional AI features, preserving explainability and regression-test coverage.
5. Opportunity estimates, peer comparisons, and report payloads were added as persisted submit-time artifacts so reports are stable after submission.
6. CSV/demo imports and website scanning were added behind provider/strategy seams, with manual answers taking precedence over inferred evidence.
7. Optional LLM features were added as enrichment layers with feature flags, structured output validation, evidence verification, caching, rate limiting, and graceful fallback.
8. Production behavior was validated through Docker, Render configuration, `/health`, PostgreSQL-compatible migrations, environment templates, and explicit demo-data commands.

AI-assisted development was used as an acceleration and review tool, but the architecture keeps product decisions, validation, tests, and production behavior under application control. Core business outcomes are determined by PHP services and persisted domain state, not by model output.

## Architecture

The application has two primary user surfaces:

- Public merchant workflow: landing page, anonymous assessment, assisted autofill, submission, report-ready email, and tokenized report.
- Authenticated internal workflow: dashboard list, search/sort, submitted-assessment review, and report-style detail page for internal users.

Conceptual flow:

```text
Public assessment
    -> anonymous draft persistence
    -> draft answer autosave
    -> optional website scan / CSV or demo import evidence
    -> submission transaction
    -> deterministic scoring
    -> recommendation generation
    -> opportunity and benchmark persistence
    -> tokenized public report
    -> optional AI executive insight on report view
    -> authenticated internal review
```

Key responsibilities:

- `AssessmentQuestionCatalog` defines the questionnaire sections and validates allowed question keys and answer options.
- `CreateAssessmentService` creates anonymous draft assessments and associated merchants.
- `SaveAssessmentAnswersService` upserts draft answers and syncs merchant identity fields.
- `SubmitAssessmentService` validates required answers, runs scoring, recommendations, opportunity calculations, benchmark comparisons, status transitions, and report creation inside a database transaction.
- `ReadinessScoringService` applies question-specific scorers from `config/scoring.php`, computes weighted section scores, and assigns readiness tiers.
- `RecommendationEngine` applies registered `RecommendationRule` implementations and sorts drafts by priority.
- `OpportunityCalculationService` and `OpportunityRankingService` estimate and rank practical opportunities such as retained revenue, manual work savings, and support contact reduction.
- `ReportBuilderService` builds the public/internal report payload, including hero opportunity, supporting metrics, calculation explanations, action plan, peer comparisons, talking points, and optional AI insight.
- `ImportCoordinator` orchestrates provider-agnostic imports through create, attach, process, cancel, and finalize phases.
- `WebsiteCrawler`, `RulesWebsiteExtractionStrategy`, `LlmWebsiteExtractionStrategy`, and `HybridWebsiteExtractionStrategy` power assisted website extraction.
- `RecommendationInsightService` produces one optional AI-generated explanation for the already-selected top recommendation.

Core HTTP surfaces:

- Public web: `/`, `/assessment`, `/assessment/{assessment}`, `/reports/{report:token}`, `/sample-report`, `/privacy`, `/terms`, `/health`
- Authenticated web: `/dashboard`, `/dashboard/assessments/{assessment}`, `/profile`
- Public API: `POST /api/assessments`, `POST /api/assessments/{assessment}/answers`, `POST /api/assessments/{assessment}/website-scan`, `POST /api/assessments/{assessment}/submit`, import endpoints, report JSON, and report contact notification

Deeper architecture documentation:

- [Architecture implementation document](docs/01_Architecture_Implementation_Document.md)
- [Architecture diagrams](docs/architecture-diagrams.md)
- [Product requirements](docs/04_Product_Requirements_Document.md)
- [Design approach](docs/02_Design_Approach.md)
- [Data ingestion](docs/data-ingestion.md)
- [Peer benchmarking](docs/benchmarks.md)

## Production Capability

The current deployment is a cost-conscious public demonstration environment, but the application uses production-aware Laravel patterns and has a clear scaling path.

Already implemented:

- Dockerized deployment through `Dockerfile` and `render.yaml`.
- PostgreSQL production support via `DB_CONNECTION=pgsql` and `DATABASE_URL` on Render/Neon.
- SQLite-first local setup for fast onboarding and test execution.
- Database migrations for users, merchants, assessments, answers, reports, imports, jobs, cache, sessions, benchmarks, website scans, evidence, and AI provenance.
- `/health` endpoint configured as Render's health check path.
- Environment-based configuration for app, database, session, cache, queue, mail, Resend, and optional LLM features.
- Production startup migrations with `php artisan migrate --force`.
- Database-backed sessions and cache in the Render blueprint.
- Laravel authentication for internal workspace access, with an `internal` middleware restricting `/dashboard` to `admin` and `internal` roles.
- Tokenized public reports using 40-character random report tokens and route model binding on `reports/{report:token}`.
- Request validation for assessment answers, imports, CSV uploads, and website scans.
- Rate limiting on public assessment creation, answer saving, website scan, submit, contact, and import endpoints.
- Queue-compatible import processing through Laravel jobs implementing `ShouldQueue`.
- Idempotent CSV import detection through content fingerprints.
- Uploaded CSV files capped at 5MB and deleted from local storage after terminal import states while retaining audit rows.
- Safe demo-data reset that only deletes merchants marked `is_demo`.
- Structured operational logging to stderr in production and sanitized warnings for optional AI failures.
- Automated PHP and Vue test coverage for domain logic, APIs, imports, reports, AI fallback behavior, and frontend interactions.
- Graceful fallbacks when optional LLM services are disabled, unavailable, rate-limited, or return invalid output.

Intentionally simplified for the demo deployment:

- Render uses a single free web service and Laravel's built-in server from the Docker `CMD`, which is acceptable for the demo but not the preferred high-throughput PHP serving model.
- `QUEUE_CONNECTION=sync` is set in `render.yaml` so imports and scans complete without provisioning a separate paid worker service.
- Uploaded CSVs use local disk storage and are deleted after processing; this is enough for small demo uploads but not durable shared storage for multi-instance deployments.
- Observability is limited to health checks and application logs. There is no centralized tracing, metrics dashboard, or alerting stack in this repository.
- Public assessment mutation routes are anonymous and rate-limited. They use assessment ULIDs/resume URLs but do not implement a separate edit-token/session ownership guard.
- Peer benchmarks are an illustrative configured data set, not measured industry averages. The report surfaces this provenance rather than overstating the data.

Scaling path under higher load:

- Run PHP behind a production web server such as Nginx plus PHP-FPM instead of `php artisan serve`.
- Switch queues from `sync` to `database` or Redis and run dedicated queue workers for imports, report email, and future long-running scans.
- Use managed PostgreSQL with connection pooling appropriate for request traffic and unpooled connections for migrations when needed.
- Move uploads to object storage such as S3-compatible storage and keep local disk ephemeral.
- Add Redis or managed cache for queue throughput, rate limiting, and cache-heavy report/AI paths.
- Add centralized logs, metrics, uptime checks, alerting, and error tracking.
- Add deployment promotion between environments and secret management through the deployment platform.
- Add stronger anonymous draft ownership, organization-level tenancy, and more granular authorization if the workflow expands beyond public lead intake.

## Responsible AI Integration

AI is optional enrichment in this application. It does not decide readiness scores, recommendation selection, opportunity ranking, or report access.

There are two optional AI-assisted paths:

- Website scan extraction: `LlmWebsiteExtractionStrategy` can help extract returns-policy facts from public website text. The default strategy is deterministic rules only.
- Executive Perspective: `RecommendationInsightService` can generate a concise explanation for the top already-ranked recommendation on report view.

Design constraints implemented in code:

- `LLM_ENABLED=false` disables LLM calls. `WEBSITE_EXTRACTION_STRATEGY=rules` is the default.
- Only Groq is implemented today, behind the provider-neutral `App\Services\Llm\LlmClient` contract and `GroqLlmClient`.
- Website extraction sends only cleaned visible text from public merchant website pages fetched by `WebsiteCrawler` and reduced by `WebsiteTextExtractor`.
- Website extraction does not receive questionnaire answers, contact email, CSV contents, or raw assessment data.
- `SafePublicHttpUrl` and `SafePublicHttpUrlRule` reject non-http(s), localhost, private, reserved, and unresolved hosts before fetching.
- LLM website output must match a structured schema and each non-null field must include a source URL and verbatim evidence quote found in the text sent to the model.
- Unverified AI claims are discarded, not downgraded into accepted evidence.
- Hybrid extraction runs rules first and only asks the LLM for missing or low-confidence fields.
- Manual answers are never overwritten by imported or website-derived suggestions.
- Equal-confidence rule/LLM conflicts require manual confirmation and are not auto-applied.
- Provider failures, timeouts, invalid JSON, invalid schema output, and LLM rate-limit exhaustion degrade to rules-only behavior.
- Executive insight uses a PII-conscious `MerchantContextBuilder`, excludes contact email/contact name, validates required output fields through `RecommendationInsight`, caches valid output, and returns `null` on failure.
- Submit and contact endpoints intentionally skip AI insight generation so submission and sales-contact notifications stay fast.

Relevant files:

- [LLM extraction docs](docs/llm-extraction.md)
- [ADR 0008: Hybrid rules + LLM website extraction](docs/decisions/0008-hybrid-rules-llm-extraction.md)
- [ADR 0009: AI recommendation insight](docs/decisions/0009-ai-recommendation-insight.md)
- `app/Services/WebsiteScan/*`
- `app/Services/Ai/*`
- `app/Services/Llm/*`
- `config/llm.php`
- `config/ai.php`

## Domain And Business Logic

The readiness score is deterministic and explainable.

- The question catalog covers Business, Catalog, Return Policy, Exchanges, Manual Operations, and Platform.
- Scored sections are weighted in `config/scoring.php`: return policy 30, manual operations 30, exchanges 20, platform 20.
- Readiness tiers are Foundational, Developing, Established, and Advanced.
- Question-level scoring is implemented through `QuestionScorer` classes under `app/Services/Scoring/Questions`.
- Recommendations are generated by `RecommendationRule` implementations under `app/Services/Recommendations/Rules`.
- Opportunities are calculated with explicit assumptions in `config/assessment.php`, persisted on submit, and shown with calculation explanations in the report.
- Peer comparisons are persisted at submit time and labeled with their benchmark provenance.
- Report presentation is separate from domain decisions. Vue report components render payloads produced by `ReportBuilderService` rather than recomputing business rules client-side.

Representative protected behavior:

- Required-answer validation before submit.
- Optional unanswered questions do not unfairly penalize section averages.
- Recommendations are selected from actual answers and sorted by priority/opportunity ranking.
- Reports include explanation data and do not expose unpublished reports.
- AI insight absence never breaks report rendering.

## Security And Data Protection

Implemented protections include:

- Authenticated internal workspace routes using Laravel authentication and `EnsureInternalUser` middleware.
- Disabled public registration in the current app behavior, with tests protecting that route behavior.
- Public reports accessed by non-guessable tokens rather than incremental report IDs.
- Submitted assessments reject further answer edits.
- Request validation through form requests for answers, imports, CSV files, and website scans.
- Public mutation endpoints use Laravel throttling.
- CSRF protection and password hashing are provided by Laravel's web/auth stack.
- Eloquent models use explicit `fillable` attributes for mass-assignment boundaries.
- Website scan URL validation rejects SSRF-prone targets before fetch.
- CSV upload type and size validation restricts the import surface.
- Uploaded blobs are deleted after imports complete, fail, or are cancelled.
- Demo reset deletes only `is_demo` merchants and leaves real prospect submissions untouched.
- API keys and production secrets are environment variables, not repository files.
- LLM warnings are sanitized and do not log prompts, page content, API keys, or raw response bodies.

This repository does not claim formal compliance certification. It follows security-conscious application patterns appropriate for a public demo and identifies where stronger ownership, tenancy, and operational controls would be added for a broader production rollout.

## Testing Strategy

The test suite covers both domain logic and user-facing workflows.

Run PHP tests:

```bash
php artisan test
```

Run Vue/Vitest tests:

```bash
npm test
```

Useful targeted examples:

```bash
php artisan test tests/Feature/WebsiteScanHybridTest.php
php artisan test tests/Feature/RecommendationInsightReportTest.php
php artisan test tests/Unit/Services/ReadinessScoringServiceTest.php
npm test -- resources/js/Pages/Assessment/__tests__/Wizard.test.js
```

Coverage areas include:

- Feature tests for public assessment creation, draft resume, answer validation, submission, report access, workspace access, imports, website scans, contact notifications, and public routes.
- Unit tests for scoring, recommendation rules, opportunity calculations, report payload building, benchmark resolution, import coordination, CSV/demo importers, website extraction strategies, AI context/insight generation, and LLM client behavior.
- Auth tests for login, logout, password reset/update, email verification, profile updates, and disabled registration.
- Demo-data tests proving idempotent seed behavior, self-healing partial seeds, and `demo:reset` safety.
- Frontend tests for the assessment wizard, report components, calculation modal, opportunity hero, peer perspective, badges, and workspace report page.
- AI fallback tests proving disabled providers, malformed output, timeouts, invalid JSON, rate limits, and missing keys do not break core application behavior.

The current full local verification run produced 620 passing PHP tests and 93 passing JavaScript tests. Treat those counts as a snapshot, not a contract.

## Demo Data Design

Demo data is designed to be safe and representative rather than magical.

- `DatabaseSeeder` creates a demo admin user, benchmark data, and three demo merchants.
- `DemoMerchantsSeeder` creates demo assessments through `CreateAssessmentService`, `SaveAssessmentAnswersService`, and `SubmitAssessmentService`, so scores, recommendations, opportunities, benchmarks, and reports are generated through the same path as real submissions.
- The three seeded merchants represent different readiness profiles: Foundational, Established, and Advanced-style operations.
- `php artisan demo:reset` deletes and regenerates only merchants where `is_demo=true`.
- Re-running seeders is idempotent or self-healing and does not duplicate demo merchants.
- Production startup runs migrations only. Demo rows are created only when an operator explicitly seeds or runs `demo:reset`.

Commands:

```bash
php artisan db:seed
php artisan demo:reset
```

## Technology Choices

- Laravel 12: conventions, routing, validation, authentication, queues, migrations, mail, tests, and service-container wiring.
- Vue 3 + Inertia: interactive frontend without maintaining a separate SPA API application for every page.
- Tailwind CSS: fast, consistent UI composition across the assessment, report, and workspace surfaces.
- PostgreSQL: production persistence on Render/Neon.
- SQLite: low-friction local development and fast test execution.
- Laravel queues: import jobs and a clear async scaling path.
- Docker: reproducible deployment artifact for Render.
- Render: inexpensive public demonstration environment.
- Resend: report-ready and contact-request email delivery.
- Groq: optional LLM provider behind an application-owned abstraction.

## Local Development

Complete setup details are in [developer resources](docs/developer-resources.md). The quick path is:

```bash
composer run setup
composer run dev
php artisan test
```

`composer run setup` installs PHP and Node dependencies, copies `.env.example` to `.env` if needed, generates an app key, runs migrations, and builds frontend assets.

Manual setup:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Run only the Laravel server:

```bash
php artisan serve
```

Run the server, queue listener, log viewer, and Vite dev server together:

```bash
composer run dev
```

Seed demo data:

```bash
php artisan db:seed
```

## Deployment And Operations

Render deploys the app through Docker.

Deployment artifacts:

- `Dockerfile`
- `render.yaml`
- `/health`
- [Render deployment guide](docs/render-deployment.md)

Render settings when creating the service manually:

- Language/runtime: Docker
- Root directory: blank
- Dockerfile path: `./Dockerfile`
- Instance type: Free for the public demo
- Health check path: `/health`

Operational behavior:

- The Docker build installs PHP dependencies, builds frontend assets, copies compiled assets, and optimizes Composer autoloading.
- Container startup clears config cache, runs `php artisan migrate --force`, and starts Laravel on the provided port.
- `render.yaml` sets `LOG_CHANNEL=stderr`, `DB_CONNECTION=pgsql`, database-backed sessions/cache, and `QUEUE_CONNECTION=sync` for the single-service demo.
- Secrets such as `APP_KEY`, `DATABASE_URL`, `RESEND_KEY`, `GROQ_API_KEY`, and `GROQ_MODEL` are dashboard-managed with `sync: false` where appropriate.
- Neon should use an unpooled connection string for startup migrations.
- Demo data is not created on boot. Seed or reset it explicitly through an operator action.
- Report contact emails use Resend when configured; failures are logged and the contact endpoint still returns a non-fatal queued status.

The architecture is not tied exclusively to Render. A larger deployment would keep the Laravel/Vue/Inertia application structure and change the serving, worker, storage, cache, observability, and promotion topology.

## Repository Map

```text
app/
  Contracts/                  Scoring, recommendation, import, website, and AI seams
  Console/Commands/           Operational commands such as demo:reset
  Http/Controllers/           Thin HTTP entry points
  Http/Requests/              Request validation
  Http/Middleware/            Internal-user authorization
  Jobs/                       Queue-compatible import jobs
  Mail/                       Report-ready and contact-request mailables
  Models/                     Domain persistence
  Services/                   Assessment, scoring, recommendations, reports, imports, AI, LLM, benchmarks, opportunities

config/                       Scoring, assessment, AI, LLM, queue, mail, database, and Laravel config
database/
  migrations/                 Schema for app, domain, imports, benchmarks, jobs, cache, sessions
  seeders/                    Admin user, benchmarks, demo merchants
docs/                         Architecture, product, design, deployment, data ingestion, benchmarks, ADRs
resources/js/                 Vue + Inertia pages, layouts, and report components
routes/                       Public, authenticated, and API routes
tests/                        PHP feature/unit tests and Vue/Vitest component/page tests
Dockerfile                    Production demo container
render.yaml                   Render blueprint
```

## Project Status

Completed and implemented in the repository:

- Laravel, Vue, Inertia, Tailwind application foundation.
- Docker/Render deployment configuration and `/health` endpoint.
- Authentication and internal workspace authorization.
- Core domain models, relationships, migrations, factories, and seeders.
- Public assessment question catalog and anonymous wizard.
- Resumable drafts, partial autosave, website scan, CSV upload, and demo-import assisted fill.
- Deterministic scoring and rule-based recommendation engines.
- Opportunity calculations, action plan, calculation explanations, and peer comparisons.
- Public tokenized report and internal workspace review surface.
- Report-ready and contact-request email flows.
- Optional hybrid rules/LLM website extraction.
- Optional AI executive insight for top recommendation explanation.
- Safe demo-data seeding and `demo:reset` command.
- Documentation for architecture, data ingestion, benchmarks, AI decisions, deployment, and local development.
- Automated backend and frontend test coverage.

Logical next steps beyond the current product scope:

- Dedicated queue workers and async website-scan/report workflows.
- Production PHP serving stack with Nginx/PHP-FPM.
- Object storage for uploaded files.
- Centralized logging, metrics, tracing, alerting, and error tracking.
- Stronger anonymous draft ownership/edit tokens.
- Organization-level tenancy and expanded authorization.
- Configurable question/scoring catalogs.
- Real ecommerce integrations such as Shopify, WooCommerce, BigCommerce, or Magento behind existing provider seams.
- Measured benchmark datasets and analytics.
- Additional LLM providers behind `LlmClient`.
- Runtime feature flags for AI and integration behavior.
