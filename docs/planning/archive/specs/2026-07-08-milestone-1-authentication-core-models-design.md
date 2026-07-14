# Milestone 1: Authentication, Core Models, Factories, Seeders, Relationships

Date: 2026-07-08
Status: Approved

## Purpose

Establish the domain schema and internal-staff authentication needed before assessment
question catalog, scoring, and reporting work can begin (Milestones 2+). This milestone
does not touch the assessment wizard UI, scoring, recommendations logic, or the public
report API — those are later milestones per `docs/03_Codex_Execution_Plan.md`.

Source of truth, in priority order: `docs/01_Architecture_Implementation_Document.md`,
`docs/04_Product_Requirements_Document.md`, `docs/03_Codex_Execution_Plan.md`,
`docs/02_Design_Approach.md`.

## Data model

### Merchant (`merchants`, bigint PK)

| Column | Type | Notes |
|---|---|---|
| company_name | string | required |
| contact_name | string | nullable |
| contact_email | string | nullable |
| website | string | nullable |

`hasMany` Assessment.

**Guardrail:** `contact_email` (and any other Merchant PII) must never be exposed by a
public report serialization. There is no report API yet in this milestone, but whoever
builds `ReportBuilderService` / the report API resource (Milestone 5) must exclude it.

### Assessment (`assessments`, **ULID** PK)

| Column | Type | Notes |
|---|---|---|
| merchant_id | FK -> merchants | cascade delete |
| status | string enum | `draft` \| `submitted` \| `scored` \| `archived`, default `draft` |
| started_at | timestamp | |
| submitted_at | timestamp | nullable |

Uses a ULID as the primary/route-binding key instead of an auto-increment integer,
because assessments are created and mutated anonymously via public API
(`POST /api/assessments`, `POST /api/assessments/{id}/answers`) and a sequential ID
would let one merchant enumerate or tamper with another's in-progress assessment.

The `scored` and `archived` states are defined now even though nothing transitions an
assessment into them yet (scoring lands in Milestone 3), so the lifecycle enum doesn't
need a breaking migration later. No `total_score` column yet — that arrives with the
scoring engine.

`belongsTo` Merchant, `hasMany` AssessmentAnswer, `hasMany` Recommendation, `hasOne` Report.

### AssessmentAnswer (`assessment_answers`, bigint PK)

| Column | Type | Notes |
|---|---|---|
| assessment_id | FK -> assessments (ulid) | cascade delete |
| question_key | string | placeholder identifier until the Question Catalog model exists (Milestone 2) |
| section | string enum | `business` \| `catalog` \| `policy` \| `exchanges` \| `operations` \| `platform` |
| value | json | flexible enough for text/numeric/boolean/multi-select answers without a schema change |

Unique index on `(assessment_id, question_key)` so re-saving a draft answer updates in
place instead of duplicating.

`section` stores machine keys, not display labels — display labels belong to the
Question Catalog when it's built.

`belongsTo` Assessment.

### Recommendation (`recommendations`, bigint PK)

| Column | Type | Notes |
|---|---|---|
| assessment_id | FK -> assessments (ulid) | cascade delete |
| title | string | |
| description | text | |
| category | string | |
| priority | string enum | `high` \| `medium` \| `low` |
| expected_impact | string | nullable — surfaced in the results UI |

`belongsTo` Assessment.

### Report (`reports`, bigint PK)

| Column | Type | Notes |
|---|---|---|
| assessment_id | FK -> assessments (ulid) | cascade delete, **unique** (one report per assessment) |
| token | string | unique, randomly generated, independent of the assessment's ULID |
| summary | text | nullable — placeholder for a future generated executive summary |
| published_at | timestamp | nullable |

The report `token` is deliberately distinct from the assessment's ULID: the assessment
ID is a working identifier for the anonymous wizard session, while the report token is
the durable, shareable link and should be able to change independently later (e.g.
rotation) without affecting the assessment.

`belongsTo` Assessment.

### Cascade behavior

Deleting a Merchant cascades to its Assessments; deleting an Assessment cascades to its
AssessmentAnswers, Recommendations, and Report. No soft deletes for MVP — can be
revisited later if there's a real need (e.g. audit/undo requirements).

## Authentication & authorization

- Install **Laravel Breeze** with the Vue/Inertia stack (`breeze:install vue`). This
  scaffolds session-based login/logout/password-reset pages matching the project's
  existing Vue+Inertia stack, plus its own test coverage, rather than hand-rolling
  auth controllers Laravel already solves well.
- Add a `role` column (`string`, default `staff`) to `users` via a small follow-up
  migration. No permission/authorization logic is built on top of it yet — it exists
  as a schema seam so Milestone 6 (internal workspace) doesn't need another migration
  just to add roles.
- **Disable Breeze's public self-registration route and page.** This is an internal
  tool for sales engineers and customer success — staff accounts are provisioned
  (seeded), not self-served. Public/anonymous users interact only with the (future)
  assessment wizard and report, never through an account.

## Factories, seeders

- Factories for all five domain models: `MerchantFactory`, `AssessmentFactory`,
  `AssessmentAnswerFactory`, `RecommendationFactory`, `ReportFactory`.
- `role` states (`admin()` / `staff()`) added to the existing `UserFactory`.
- `DatabaseSeeder`:
  - Seeds one admin `User` for local login.
  - Seeds one minimal `Merchant` with one draft `Assessment` (via factories) purely to
    speed up local smoke testing. This is intentionally minimal — realistic,
    multi-merchant demo scenarios with varied recommendations are Milestone 7's job
    and are not built here.

## Testing strategy (TDD)

Write tests first per project guardrails. Priority areas per this milestone's scope:

**Unit — relationships** (both directions, one test per relationship):
- Merchant `hasMany` Assessment / Assessment `belongsTo` Merchant
- Assessment `hasMany` AssessmentAnswer / AssessmentAnswer `belongsTo` Assessment
- Assessment `hasMany` Recommendation / Recommendation `belongsTo` Assessment
- Assessment `hasOne` Report / Report `belongsTo` Assessment

**Feature — assessment ownership / data isolation**:
- Two assessments' answers never cross (querying one assessment's answers never
  returns another's).
- Deleting an Assessment cascades and removes its AssessmentAnswers, Recommendations,
  and Report.
- Assessment resolves via ULID in route-model binding, not integer ID.

**Feature — authentication**:
- Breeze's generated login/logout/password-reset tests are kept as-is.
- Registration route is disabled (404 or unavailable).
- An unauthenticated user is redirected away from the protected `/dashboard` route.

**Feature — public access**:
- `GET /` (public Welcome page) is reachable without authentication — a smoke test
  that public routes aren't accidentally gated behind auth middleware, ahead of the
  real assessment wizard landing in Milestone 2.

## Explicitly out of scope for this milestone

- Assessment wizard UI (Milestone 2).
- Question Catalog model (Milestone 2).
- Scoring engine, `total_score` column (Milestone 3).
- Recommendation generation logic / `RecommendationEngine` (Milestone 3).
- Report API endpoint, report serialization/PII stripping implementation (Milestone 5).
- Internal workspace UI, role-based permission enforcement (Milestone 6).
- Realistic multi-merchant demo data (Milestone 7).
