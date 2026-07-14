# 0009. AI Recommendation Insight

## Status

Accepted

## Context

Every recommendation the deterministic rules engine produces reads the same regardless of which
merchant is looking at it — the title, description, and expected impact are generic template
copy. We wanted the highest-impact recommendation to come with a short, merchant-specific
explanation of *why* it matters, without touching what gets recommended or how it's prioritized.
That decision-making stays entirely inside the existing rule-based `RecommendationEngine` and
`OpportunityRankingService`; this feature only explains the output, on top of the existing
Groq/`LlmClient` integration built for website-scan extraction (ADR 0008).

## Decision

**One AI call per report, for the top recommendation only.** `RecommendationInsightService`
receives an already-ranked `Recommendation` model — it never re-ranks, never picks, never sees the
rest of the recommendation list. If there's no top recommendation (an assessment with none), no
call happens at all.

**`policy_score` / `automation_score` / `exchange_score` / `risk_score` map onto the four real
scoring sections** (`return_policy`, `manual_operations`, `exchanges`, `platform` respectively).
Scoring has no dedicated "risk" section, so `risk_score` is the `platform` section score —
platform/tooling maturity is the closest existing proxy for operational risk this app scores today.

**`MerchantPublicContextService` never populates `market_cap`.** The spec explicitly forbids
Yahoo Finance, SEC integration, and any API key — the only way to get a real, current market cap
under those constraints would be to hardcode a number, which is wrong the moment it's written and
directly violates the same prompt's "never invent data" rule applied to the app's own code. Ticker,
sector, and industry are still populated from a small static name→company mapping (10 well-known
public retailers) when the scraped homepage `<title>` trivially matches one; `market_cap` stays
`null` always, documented in code rather than silently omitted from the shape.

**Report-generation latency budget (~5s) is enforced by clamping `llm.timeout_seconds`
transiently, not by changing the shared LlmClient.** The spec is explicit that the existing LLM
provider abstraction must not be modified, and `llm.timeout_seconds` (15s default) is tuned for
the website-scan feature's larger payloads. `RecommendationInsightService` reads the current value,
temporarily lowers it to `ai.recommendation_insight.max_generation_seconds` (default 4) around the
single `LlmClient::extractStructured()` call, and restores the original value in a `finally` block
— even on exception. This is a config-value change for the duration of one call, not a code change
to `GroqLlmClient` or the `LlmClient` contract.

**Endpoints that don't display the report skip insight generation entirely.**
`ReportBuilderService::buildPayload()` takes an `$includeInsight` flag (default `true`).
`AssessmentController::submit()` and `ReportController::contact()` both pass `false` — submit
should feel fast, and the "notify sales" click doesn't render anything that would show an insight.
Only `ReportController::show()`/`apiShow()` and `WorkspaceController`'s internal review pay the
generation cost, and only on a cache miss (24h TTL, keyed on assessment + recommendation + prompt
version + provider + model).

**Any failure — disabled, timeout, bad JSON, malformed schema — degrades to `aiInsight: null`,
never an error.** `RecommendationInsight::fromArray()` requires every field non-blank and a valid
confidence enum; a response that fails that validation is treated identically to a network failure
(logged with provider name and exception class only — never prompt or context content — and
discarded, not cached). `ReportBuilderService` additionally wraps the call in its own `try/catch`
as defense in depth, since a broken report page is a worse outcome than a missing insight section.

## Consequences

- The AI section can be absent from a report for reasons ranging from "feature disabled" to "Groq
  had an outage" to "the model's output didn't validate" — the report page has no way to
  distinguish these (by design; see FAILURE requirements), so debugging a missing insight requires
  checking `storage/logs/laravel.log` for the sanitized warning, not the UI.
- Because insight generation is skipped on submit, a merchant's very first view of their own report
  page (immediately after submitting) will incur the generation latency once, live. Every
  subsequent view — by that merchant, or by internal staff reviewing the same report — is served
  from cache.
- `risk_score` reads as "platform section score" internally; if a future milestone adds a genuine
  risk-scoring dimension, this mapping should be revisited rather than assumed still correct.
- `market_cap` staying permanently `null` means the public-context enrichment is effectively
  ticker/sector/industry-only today. Wiring a real market-cap source is a deliberately separate,
  larger decision (a real financial-data integration) that this feature does not make.
