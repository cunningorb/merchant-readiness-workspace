# 0004. Opportunity-First Report Refactor

## Status

Accepted

## Context

The shareable report previously led with the readiness score. Merchant feedback and the
product intent (deliver value before a sales conversation) called for leading with a
concrete, quantified business opportunity instead — e.g. "you may retain $X-$Y in revenue
per year" — while keeping the score as supporting context rather than removing it.

This meant introducing dollar- and hours-denominated estimates derived from a merchant's
banded questionnaire answers. Those answers are qualitative bands (e.g. "1,000-10,000"
orders/month), not exact figures, and several inputs the formulas need (average order
value, return rate, exchange-conversion lift, automation share, policy-confusion share)
are never collected at all. Any estimate is therefore necessarily a heuristic built on
top of configured assumptions, not a measured fact. The design had to make that explicit
rather than presenting a single misleadingly precise number, and had to avoid implying a
guaranteed financial outcome (per the product's scoring guardrails).

## Decision

**Persist at submit time, never recalculate on read.** `OpportunityCalculationService`
and `OpportunityRankingService` run once, inside the same DB transaction as scoring and
recommendation generation, in `SubmitAssessmentService::submit()`. Results are written to
the `assessment_opportunities` table (model `AssessmentOpportunity`) with the formula
version that produced them (`config('assessment.opportunities.formula_version')`,
currently `"1.0"`). `ReportBuilderService::buildPayload()` and the report/workspace
controllers only ever read the persisted rows (`$assessment->opportunities`) — there is
no recomputation path on the read side. This keeps a merchant's report stable even if
config assumptions or formulas change later, and makes "what did we tell this merchant"
auditable from stored data alone.

**All quantitative inputs are banded; formulas consume configured midpoints and
assumptions, and both are labeled in stored evidence.** The questionnaire only offers
banded selects (e.g. `business.monthly_order_volume`: "Under 1,000" / "1,000-10,000" /
"10,001-50,000" / "50,000+"; `manual_operations.weekly_hours` similarly banded). Formulas
map the reported band to a representative midpoint via
`order_volume_band_midpoints` / `weekly_manual_hours_band_midpoints` in
`config/assessment.php`. Everything else the formulas need but the assessment doesn't
collect — `assumed_average_order_value`, `base_return_rate` /
`fit_sensitive_return_rate`, `eligible_refund_share`, `exchange_conversion_lift`,
`automation_share`, `policy_confusion_share`, `clarity_reduction` — is a configured
assumption, also in `config/assessment.php`. Each calculation records every value it used
in `assumptions` (tagged `source: 'merchant_answer'` or `source: 'configured_assumption'`)
and in `evidence` (`inputs`, `sourceAnswerKeys`, and a `why` array of plain-language
explanation lines) on the `AssessmentOpportunity` row. `ReportBuilderService` surfaces
only the `configured_assumption`-tagged entries in `calculationExplanations`, and the
report's "See calculation" modal (`CalculationModal.vue`) renders inputs as "Your answer"
and assumptions as "Configured assumption" so a merchant can see exactly what was theirs
and what was assumed on their behalf.

**Confidence is capped at `medium` in this milestone.** `ConfidenceLevel` has `High` /
`Medium` / `Low` cases, but no calculation path in `OpportunityCalculationService`
currently assigns `High` — it's reserved for a future milestone with imported store data
(e.g. actual order/return history) as a real input source. The assignment rule is
per-formula input provenance: retained-revenue and support-contact-reduction opportunities
get `Medium` confidence only when every answer they depend on
(`catalog.fit_sensitive_categories` and `exchanges.offered` for the former;
`business.monthly_order_volume` and `return_policy.policy_clarity` for the latter) was
actually answered, and drop to `Low` otherwise. Manual-work-savings is always `Medium`
because its only input, `manual_operations.weekly_hours`, is required for the opportunity
to be calculated at all (see next point).

**Insufficient data means skip, never fabricate.** Each `calculate*` method in
`OpportunityCalculationService` returns `null` — and is filtered out of the result — the
moment a required band lookup misses (e.g. `bandValue()` returns `null` for an unanswered
or unrecognized band). No opportunity is invented from a default value. A report can
therefore end up with zero, one, two, or three persisted opportunities. When an
assessment has none (including legacy, pre-milestone assessments with no
`assessment_opportunities` rows at all), `ReportBuilderService::heroOpportunityFallback()`
renders a non-monetary hero (`kind: 'fallback'`) that references the recommendation count
instead of a dollar or hours figure, so the report never shows a broken or zeroed-out
estimate.

**Retained revenue is never called profit; all copy is hedged.** The retained-revenue
opportunity's title ("Retained revenue from exchange conversion") and summary explicitly
say "revenue," not profit or savings, and both the hero and the calculation modal carry a
standing disclaimer — "Estimated range based on your answers and clearly labeled
assumptions — not a promise of results" (`OpportunityHero.vue`) and "This is a heuristic
estimate ... It is not a prediction or a promise of results" (`CalculationModal.vue`).
This matches the existing scoring-guardrail language ("heuristic, not predictive; avoid
language that implies guaranteed financial outcomes").

**Backwards-compatible payload: legacy keys unchanged, new keys additive.**
`ReportBuilderService::buildPayload()` still returns the pre-existing `merchant`,
`assessment` (`overall_score`, `overall_tier`, `section_scores`, `ranked_sections`),
`recommendations`, and `published_at` keys unchanged. It adds `heroOpportunity`,
`supportingMetrics` (up to three metrics, falling back to the readiness score if fewer
than three opportunities exist), `topRecommendations` / `remainingRecommendations` (the
recommendation list re-ranked by `OpportunityRankingService::rankRecommendations()` and
split at three items), `calculationExplanations` (keyed by opportunity type), and
`actionPlan` (`this_week` / `plan_next` title lists derived from ranked, high-priority
recommendations). Nothing consuming the old keys needs to change.

**Frontend leads with the opportunity, demotes the score, and caps initial
recommendations at three.** The Vue report page renders `OpportunityHero.vue` first;
the readiness score moves into `SupportingMetricStrip.vue` as one metric among up to
three. Recommendations show the top three
(`topRecommendations`) directly, with `RecommendationsDisclosure.vue` providing an
accessible (`aria-expanded`/`aria-controls`) toggle to reveal
`remainingRecommendations`. `CalculationModal.vue` is the "See calculation" surface,
showing inputs, assumptions, confidence, and `formula_version` for a given opportunity
type. Vitest (with Vue Test Utils and happy-dom) was added as the project's JS test
runner for these new components — component-level tests only, no browser/E2E tests, run
via `npm run test`.

## Consequences

- Report accuracy is bounded to what was true when the merchant submitted, not what the
  formulas would produce today; changing `config/assessment.php` or the formula logic
  does not retroactively change any already-submitted merchant's report. Re-running
  `OpportunityCalculationService` against an old assessment (not currently exposed) would
  be required to "refresh" a stale estimate.
- The `formula_version` stamped on each row is the seam for handling that: a future
  migration/backfill can target rows by version rather than guessing which reports are
  stale.
- Legacy assessments (submitted before this milestone) have no `assessment_opportunities`
  rows and always render the non-monetary fallback hero; there is no backfill in this
  milestone.
- Confidence can never reach `high` until a future milestone adds a non-banded, more
  precise input source (e.g. imported order data via a `MerchantDataSource`
  implementation); until then every opportunity a merchant sees is `medium` or `low`.
- This milestone adds the project's first JS test runner (Vitest, with Vue Test Utils and
  happy-dom) for the new report components; no browser/E2E suite was added, so
  interaction-level regressions in the hero/disclosure/modal flow are only caught by
  component tests and manual verification.
