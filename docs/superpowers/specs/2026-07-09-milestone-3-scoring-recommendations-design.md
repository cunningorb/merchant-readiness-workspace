# Milestone 3: Scoring Engine, Recommendation Engine, Rule System

Date: 2026-07-09

## Context

Milestone 2 (assessment question catalog + public wizard + draft saving) is merged and CI-green. Per `docs/03_Codex_Execution_Plan.md`, Milestone 3 covers the scoring engine, recommendation engine, rule system, and tests, with a STOP gate to "review recommendation quality."

None of `docs/01_Architecture_Implementation_Document.md`, `docs/02_Design_Approach.md`, `docs/03_Codex_Execution_Plan.md`, or `docs/04_Product_Requirements_Document.md` specify concrete scoring mechanics (formula, weights, scale) or a recommendation rule schema. `docs/01` names the `AssessmentScorer` and `RecommendationRule` contracts and the `ReadinessScoringService`/`RecommendationEngine` services, but only as a bullet list with no further detail. This design fills that gap.

## Scope

In scope:
- Readiness scoring model (per-question point mapping, section weighting, capability tiers, top opportunities).
- Recommendation rule contract, engine, and an initial rule catalog.
- A `POST /api/assessments/{assessment}/submit` endpoint and `SubmitAssessmentService`.
- A migration adding computed score fields to `assessments`.
- A minimal, unstyled "Submit assessment" affordance in the existing wizard, sufficient to manually verify recommendation quality.
- Test coverage for scoring, rules, and the submit endpoint.

Out of scope (deferred to Milestone 4 - Results dashboard):
- Any styled/dashboard presentation of the score, charts, or recommendation cards.
- Public shareable report (Milestone 5).

## Scoring model

### Which questions score

Only questions with a genuine maturity ordering feed the score. `business.*` and `catalog.*` questions, and `platform.ecommerce_platform`, are excluded entirely from scoring — they remain in the wizard unchanged, used only for merchant profile data and recommendation personalization text. There is no zero-weight placeholder for them; they simply aren't part of the scoring model.

### Per-question point mapping (0-100 per question)

| Question | Mapping |
|---|---|
| `return_policy.window_days` | 14 days or less=0, 15-30 days=33, 31-60 days=67, More than 60 days=100 |
| `return_policy.policy_clarity` | Not documented=0, Basic FAQ=33, Detailed policy page=67, Contextual by product/order=100 |
| `manual_operations.weekly_hours` | 50+=0, 21-50=33, 5-20=67, Under 5=100 (fewer hours = more mature) |
| `manual_operations.common_bottlenecks` (multiselect, optional, 5 options) | `100 - (selected_count / 5) * 100` (more bottlenecks reported = lower score; empty selection = 100) |
| `exchanges.offered` (boolean) | Yes=100, No=0 |
| `exchanges.incentives` (multiselect, optional, 4 options) | `(selected_count / 4) * 100` (more incentives offered = higher score; empty selection = 0) |
| `platform.return_tools` | Email/spreadsheets=0, Helpdesk workflow=33, Returns app=67, Custom automation=100 |

### Section scores and weights

Section score = average of its scorable questions' points.

| Section | Scored question(s) | Weight |
|---|---|---|
| Return Policy | window_days, policy_clarity | 30% |
| Manual Operations | weekly_hours, common_bottlenecks | 30% |
| Exchanges | offered, incentives | 20% |
| Platform | return_tools | 20% |

Overall score = weighted sum of the 4 section scores, rounded to an integer 0-100.

### Capability tiers

Applied to the overall score and each of the 4 section scores:

- 0-39: Foundational
- 40-64: Developing
- 65-84: Established
- 85-100: Advanced

This is the "Capability Mapping" result requirement from CLAUDE.md/PRD — each scored section gets a tier label alongside its numeric score.

### Top opportunities

The 4 scored sections ranked ascending by score (lowest first). Exposed in the submit response so a future results view can foreground the weakest areas first. No separate selection logic beyond the sort.

## Recommendation rule system

### Contracts

```php
interface AssessmentScorer
{
    public function score(Assessment $assessment): ScoreBreakdown;
}

interface RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool;
    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft;
}
```

`ScoreBreakdown` and `RecommendationDraft` are plain value objects (not Eloquent models):
- `ScoreBreakdown`: overall score, overall tier, and a per-section map of `{score, tier}`.
- `RecommendationDraft`: `title`, `description`, `category`, `priority`, `expected_impact` — matching the existing `Recommendation` model's fillable columns.

`ReadinessScoringService implements AssessmentScorer`.

### RecommendationEngine

Holds a registered list of `RecommendationRule` instances (bound via `config/recommendations.php`, listing rule class names). `generate(Assessment $assessment, ScoreBreakdown $scores): Collection<RecommendationDraft>` runs `applies()` on each registered rule, collects drafts from the ones that trigger, sorted by priority (high, medium, low). `SubmitAssessmentService` persists each draft as a `Recommendation` row (`assessment_id` + the draft's fields).

Rules trigger on a specific weak answer, not a score threshold, so each is independently explainable in plain language (matches the "recommendations must be transparent, explainable, and rule-based" product guardrail).

### Initial rule catalog

| Rule | Trigger | Category | Priority |
|---|---|---|---|
| `ShortReturnWindowRule` | `window_days` = "14 days or less" | return_policy | high |
| `UndocumentedPolicyRule` | `policy_clarity` in [Not documented, Basic FAQ] | return_policy | medium |
| `NoExchangesOfferedRule` | `offered` = false | exchanges | high |
| `NoExchangeIncentivesRule` | `offered` = true and `incentives` empty | exchanges | low |
| `HighManualHoursRule` | `weekly_hours` in [21-50, 50+] | manual_operations | high |
| `ReturnBottlenecksRule` | `common_bottlenecks` count >= 2 | manual_operations | medium |
| `ManualReturnToolingRule` | `return_tools` = "Email/spreadsheets" | platform | high |
| `BasicReturnToolingRule` | `return_tools` = "Helpdesk workflow" | platform | medium |

A merchant only triggers the subset matching their actual answers; there is no cap on how many recommendations they can receive.

## Submission endpoint and persistence

### Migration

Adds three nullable columns to `assessments`: `overall_score` (unsigned tinyint), `overall_tier` (string), `section_scores` (json, keyed by section: `{score, tier}`). Computed once at submit time and persisted — answers are frozen after submission, so there's no need to recompute on every later read (dashboard in Milestone 4, report in Milestone 5).

### SubmitAssessmentService

1. If `status === 'submitted'` already, reject with `409 Conflict`.
2. If any required question (catalog-wide) has no saved answer, reject by throwing `ValidationException::withMessages([...])`, which Laravel renders as `422` with an `errors` object — the same shape the wizard's answers endpoint already produces, so the existing frontend error-handling pattern (`error.response?.data?.errors`) applies unchanged.
3. Compute `ScoreBreakdown` via `ReadinessScoringService`.
4. Compute recommendation drafts via `RecommendationEngine`, persist as `Recommendation` rows.
5. Persist `overall_score`, `overall_tier`, `section_scores`, `status = 'submitted'`, `submitted_at = now()` on the assessment.

### Route and controller

`POST /api/assessments/{assessment}/submit` -> `AssessmentController@submit`. Returns JSON: assessment id/status, overall score/tier, per-section scores/tiers (ranked ascending for "top opportunities"), and the recommendation list.

### Wizard change (minimal)

On the last wizard section, after a successful `saveSection()`, show a "Submit assessment" button that POSTs to `/submit`. On success, render a plain, unstyled block below the form with the overall score/tier, the 4 section scores/tiers, and the recommendation list (title, description, category, priority) — enough to manually verify recommendation quality at the Milestone 3 STOP gate. No dashboard styling; that is Milestone 4's job. On a 422, reuse the existing inline error display. On a 409 (already submitted), show a simple "this assessment has already been submitted" status message.

## Testing plan

- `ReadinessScoringServiceTest` (unit): point mapping and weighting math against known answer sets; edge cases for empty multiselects; overall rounding.
- One test per recommendation rule, plus a `RecommendationEngineTest` covering aggregation and priority sorting.
- `AssessmentSubmissionTest` (feature): happy path (correct score + expected recommendations persisted to DB); incomplete assessment rejected with 422 and correct missing-field errors; already-submitted assessment rejected with 409.
