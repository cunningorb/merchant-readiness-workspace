# Product Requirements Document (PRD)

# Merchant Readiness Assessment

## Vision
Provide a self-service assessment that helps ecommerce merchants understand the maturity of their returns operation while generating qualified, informed leads.

## Problem Statement
Prospective merchants often don't know whether their returns process is a bottleneck or which improvements will have the greatest business impact. Sales teams spend valuable discovery time collecting information that could be gathered beforehand.

## Goals
- Deliver value before requesting a demo.
- Generate actionable recommendations.
- Produce a shareable readiness report.
- Enable future integrations without redesign.

## Non-Goals
- Replace a returns platform.
- Process live returns.
- Require Shopify App Store approval.

## Users
### Merchant
Wants to identify weaknesses and opportunities.

### Sales Engineer
Reviews completed assessments and tailors demos.

### Customer Success
Uses historical assessments to guide onboarding.

## MVP Scope
- Public assessment wizard
- Rule-based scoring
- Recommendations
- Public report
- Internal dashboard
- Demo data

## Future Scope
- Shopify development-store connector
- CSV imports
- WooCommerce/BigCommerce connectors
- AI recommendations
- CRM integration

## Success Metrics
- Assessment completion rate
- Report downloads
- Demo request conversion
- Time saved during discovery

## User Stories
- As a merchant, I want to understand where my returns process can improve.
- As a sales engineer, I want prospect context before a demo.
- As a customer success manager, I want assessment history.

## Acceptance Criteria
- Assessment can be completed anonymously.
- Server generates scores and recommendations.
- Report is shareable via secure token.
- Internal users can review assessments.

## Risks
- Scoring is heuristic, not predictive.
- Recommendations should remain transparent.
- Integrations intentionally deferred from MVP.

## Technical Summary
Laravel + Vue + Inertia, TDD, deployment-first, modular services and contracts for future integrations.
