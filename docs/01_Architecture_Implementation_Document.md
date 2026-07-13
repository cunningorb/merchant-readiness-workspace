# Architecture Implementation Document

## Project
**Merchant Readiness Assessment**

## Updated Technical Stack

This project intentionally aligns with Loop's publicly advertised engineering stack.

- Laravel
- Vue.js
- Inertia.js
- MySQL (or PostgreSQL)
- Tailwind CSS
- Queue support
- REST API

Future integrations should be abstracted behind interfaces so Shopify, WooCommerce, BigCommerce, Magento, or CSV imports can be added without changing the core domain.

## Purpose

Build a production-quality Laravel + Vue application that helps ecommerce merchants assess the maturity of their returns process and identifies practical opportunities for improvement.

The MVP should function entirely without requiring Shopify app approval.

## Architecture

Domains

- Merchant
- Assessment
- Question Catalog
- Scoring
- Recommendations
- Reports

Core Models

- Merchant
- Assessment
- AssessmentAnswer
- Recommendation
- Report

Core Services

- CreateAssessmentService
- SubmitAssessmentService
- ReadinessScoringService
- RecommendationEngine
- ReportBuilderService

Contracts

- MerchantDataProvider (plus CatalogImporter, OrderReturnImporter, InventoryImporter,
  ImportNormalizer, ImportMetricCalculator — see `docs/data-ingestion.md`)
- AssessmentScorer
- RecommendationRule

## API

POST /api/assessments

POST /api/assessments/{id}/answers

POST /api/assessments/{id}/submit

GET /api/reports/{token}

Implemented (Milestone 11): a provider-agnostic import framework with a synthetic demo
provider and a custom CSV upload provider — see `docs/data-ingestion.md`.

Future

- Real Shopify export/API importer
- WooCommerce importer

## TDD Strategy

Write tests first for:

1. Assessment creation
2. Question validation
3. Score calculation
4. Recommendation generation
5. Report generation

## Deployment First

Milestone 0 is deployment.

Deploy immediately after Laravel bootstraps.

Acceptance:

- App deployed
- Database connected
- /health endpoint
- CI green
- README complete
