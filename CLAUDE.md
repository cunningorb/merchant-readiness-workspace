# Merchant Readiness Workspace Agent Guide

## Project Intent

Build a production-quality Laravel + Vue + Inertia application that helps ecommerce merchants evaluate the maturity of their returns operations through a guided assessment, transparent scoring, actionable recommendations, and shareable reports.

The product must deliver value before requesting a demo or sales conversation. Do not turn the MVP into a live returns platform or require Shopify App Store approval.

## Source Of Truth

Read these before changing architecture, product flow, or UI direction:

- `README.md`
- `docs/01_Architecture_Implementation_Document.md`
- `docs/02_Design_Approach.md`
- `docs/03_Codex_Execution_Plan.md`
- `docs/04_Product_Requirements_Document.md`
- `docs/ui-ux-design/Merchant Readiness Assessment.html`

If implementation details conflict, follow the architecture document first, then the PRD, then the execution plan, then the design approach.

## Required Stack

- Laravel for backend, routing, validation, persistence, jobs, and tests.
- Vue.js for interactive UI.
- Inertia.js for Laravel/Vue page delivery.
- Tailwind CSS for styling.
- MySQL for the default database unless the deployment target requires PostgreSQL.
- Queues for async work where useful, especially reports or future imports.
- REST API endpoints for assessment and report flows.

Do not introduce a different primary stack, SPA framework, UI kit, ORM, or backend framework without explicit approval.

## Delivery Order

Follow the documented milestone sequence:

1. Bootstrap Laravel, deploy, add `/health`, connect database, make CI green, then stop for manual verification.
2. Add authentication, core models, factories, seeders, relationships, and tests.
3. Build the assessment question catalog and public wizard.
4. Build scoring and recommendation engines.
5. Build results dashboard.
6. Build public tokenized report.
7. Build internal workspace.
8. Add demo data, polish, accessibility, loading states, README updates, and diagrams.

Deployment must be proven before feature development continues.

## Architecture Guardrails

Use these domains:

- Merchant
- Assessment
- Question Catalog
- Scoring
- Recommendations
- Reports

Use these core models unless there is a documented reason to change them:

- `Merchant`
- `Assessment`
- `AssessmentAnswer`
- `Recommendation`
- `Report`

Use service classes for workflow logic:

- `CreateAssessmentService`
- `SubmitAssessmentService`
- `ReadinessScoringService`
- `RecommendationEngine`
- `ReportBuilderService`

Use contracts for future integration seams:

- `MerchantDataSource`
- `AssessmentScorer`
- `RecommendationRule`

Keep controllers thin. Put validation in Laravel form requests when appropriate. Put business rules in services, rules, actions, or domain classes rather than Vue components or controllers.

## API Guardrails

Implement these MVP endpoints unless routing changes are explicitly approved:

- `POST /api/assessments`
- `POST /api/assessments/{id}/answers`
- `POST /api/assessments/{id}/submit`
- `GET /api/reports/{token}`
- `GET /health`

Future integrations must go behind `MerchantDataSource` implementations. Do not couple the domain directly to Shopify, WooCommerce, BigCommerce, Magento, CSV imports, or AI providers.

## Product Guardrails

- Anonymous merchants must be able to complete the assessment.
- Scores and recommendations must be generated server-side.
- Recommendations must be transparent, explainable, and rule-based for the MVP.
- Reports must be shareable through secure, non-guessable tokens.
- Internal users must be able to review completed assessments.
- Scoring is heuristic, not predictive; avoid language that implies guaranteed financial outcomes.

## UX And Design Guardrails

Follow the intended flow:

1. Landing page
2. Assessment wizard
3. Score generation
4. Recommendations
5. Shareable report

Assessment sections:

- Business
- Catalog
- Return Policy
- Exchanges
- Manual Operations
- Platform

Results must show the value-proposition report format:

- Readiness score
- Hero opportunity
- Supporting metrics
- Top opportunities
- Recommended actions
- Clear calculation explanations

Do not re-expose the legacy diagnostic score-breakdown/capability-mapping report as an end-user surface.

Use a clean SaaS dashboard style with cards, score rings, progress bars, strong spacing, responsive layouts, and high trust. The reference UI uses a blue-forward visual direction and Geist-style typography; preserve that direction unless deliberately improving it.

Do not create generic AI-looking landing pages. Prioritize clarity, trust, merchant value, and transparent scoring over decorative sections.

## Testing Guardrails

Use TDD for core behavior. Write or update tests for:

- Assessment creation
- Question validation
- Draft answer saving
- Score calculation
- Recommendation generation
- Report generation
- Secure report token access

Run the narrowest useful test first, then the full relevant test suite before declaring a milestone complete.

## Code Quality Guardrails

- Prefer small, cohesive changes.
- Preserve Laravel conventions unless the docs require otherwise.
- Keep Vue components focused on presentation and interaction state.
- Keep domain decisions in PHP services/contracts.
- Do not add future integrations before the MVP requires them; create interfaces and seams only.
- Do not store secrets in the repository.
- Update documentation when behavior, setup, deployment, or architecture changes.

## MCP Usage

Use configured MCP servers for framework documentation, codebase context, browser/UI checks, and structured reasoning when available. If a Laravel app has not been bootstrapped yet, do not assume Laravel Boost, database, or app-introspection MCP servers can run.
