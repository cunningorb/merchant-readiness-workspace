# Merchant Readiness Workspace Codex Guide

## Mission

Implement a Laravel + Vue + Inertia + Tailwind application for merchant returns-readiness assessment. The app should guide merchants through questions, calculate transparent readiness scores, generate actionable recommendations, and create shareable reports for sales/customer-success follow-up.

## Always Read First

- `README.md`
- `docs/01_Architecture_Implementation_Document.md`
- `docs/02_Design_Approach.md`
- `docs/03_Codex_Execution_Plan.md`
- `docs/04_Product_Requirements_Document.md`
- `docs/ui-ux-design/Merchant Readiness Assessment.html`

Architecture decisions must stay aligned with those files.

## Stack Constraints

- Backend: Laravel
- Frontend: Vue.js through Inertia.js
- Styling: Tailwind CSS
- Database: MySQL by default, PostgreSQL only when deployment requires it
- Tests: Laravel/PHP tests for domain behavior and feature flows
- APIs: REST endpoints for assessment/report flows
- Async: Laravel queues where async processing is justified

Do not replace this stack with another framework or architecture.

## Milestone Discipline

Work in this order and stop after each milestone for verification:

1. Bootstrap, deploy, `/health`, database connection, CI.
2. Authentication, models, factories, seeders, relationships, tests.
3. Question catalog, public wizard, validation, draft saving, tests.
4. Scoring engine, recommendation engine, rule system, tests.
5. Results dashboard, charts, recommendation cards, responsive UI.
6. Public report, secure tokens, printable report, optional PDF.
7. Internal workspace, prospect list, review, search, filtering, talking points.
8. Demo data, reset command, accessibility, loading states, README, diagrams.

Milestone 0 is deployment-first. Do not begin feature development until deployment is verified.

## Domain Model

Expected domains:

- Merchant
- Assessment
- Question Catalog
- Scoring
- Recommendations
- Reports

Expected models:

- `Merchant`
- `Assessment`
- `AssessmentAnswer`
- `Recommendation`
- `Report`

Expected services:

- `CreateAssessmentService`
- `SubmitAssessmentService`
- `ReadinessScoringService`
- `RecommendationEngine`
- `ReportBuilderService`

Expected contracts:

- `MerchantDataSource`
- `AssessmentScorer`
- `RecommendationRule`

Controllers should coordinate HTTP only. Validation belongs in request objects or validators. Business rules belong in services, contracts, rule classes, or domain code.

## Required MVP Routes

- `POST /api/assessments`
- `POST /api/assessments/{id}/answers`
- `POST /api/assessments/{id}/submit`
- `GET /api/reports/{token}`
- `GET /health`

Keep future Shopify, WooCommerce, BigCommerce, Magento, CSV, CRM, and AI work behind contracts. Do not make the core domain depend on vendor APIs.

## Product Requirements

- Anonymous assessment completion must work.
- Server-side scoring and recommendation generation are required.
- Shareable reports must use secure, non-guessable tokens.
- Internal users must be able to review assessments.
- Recommendations must be explainable and actionable.
- The MVP must not process live returns or require Shopify approval.

## Design Requirements

Use this flow:

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

Results should use the value-proposition report format: readiness score, hero opportunity, supporting metrics, top opportunities, recommended actions, and clear calculation explanations. Do not re-expose the legacy diagnostic score-breakdown/capability-mapping report as an end-user surface.

Use clean SaaS dashboard UI with cards, score rings, progress bars, responsive layouts, and trust-building copy. Preserve the blue-forward reference direction and Geist-style typography from the reference UI where practical.

## Testing Requirements

Write tests first or alongside implementation for:

- Assessment creation
- Question validation
- Draft saving
- Score calculation
- Recommendation generation
- Report generation
- Tokenized public report access

Before marking work complete, run relevant tests and report any tests that could not be run.

## Implementation Rules

- Keep changes minimal and aligned with Laravel conventions.
- Prefer explicit service and contract boundaries over speculative abstractions.
- Do not add integrations before the MVP needs them.
- Do not commit secrets or environment-specific credentials.
- Update docs when setup, architecture, routes, deployment, or milestone status changes.

## MCP Expectations

Use project MCP servers for current framework documentation, filesystem/codebase awareness, browser verification, and structured reasoning. App-specific Laravel/database MCP servers should only be enabled after the Laravel app, `.env`, and database are present.
