# 0008. Hybrid Rules + LLM Website Extraction

## Status

Accepted

## Context

The website-scan assisted-autofill feature (`RulesWebsiteExtractionStrategy`) is deterministic
regex/marker matching over scraped public pages. It's cheap, fast, has no external dependency,
and is fully covered by existing tests — but it structurally can't answer anything that isn't a
literal keyword match (e.g. "is there a restocking fee," "are international returns paid by the
customer") and it can misjudge phrasing it wasn't written to recognize.

We wanted an optional LLM-assisted extraction path that fills those gaps without weakening what
the rules strategy already does well, without adding a hard dependency on a paid/rate-limited
third-party API, and without expanding the assessment's trust surface (a merchant's answers must
never be silently overwritten by a model's guess). `WebsiteExtractionStrategy` already existed as
a one-method contract (`extract(WebsiteScanResult $scan): array`) with `RulesWebsiteExtractionStrategy`
as its only real implementation and `LlmWebsiteExtractionStrategy` as an empty stub — this ADR is
about what filled that stub in and how it composes with rules, not about introducing the seam
itself.

## Decision

**Rules always runs first; the LLM is asked only for what rules didn't answer at "high"
confidence.** `HybridWebsiteExtractionStrategy` computes rules' suggestions, then for each of the
LLM's eight target fields (`return_window_days`, `exchanges_offered`, `exchange_incentive`,
`restocking_fee`, `final_sale_rules`, `policy_clarity`, `international_returns`,
`product_category_hints`) checks whether rules already produced a high-confidence answer for the
matching catalog question. Three of the eight fields map to an existing catalog question
(`return_policy.window_days`, `exchanges.offered`, `return_policy.policy_clarity`); the other five
have no rules equivalent at all and are always requested when the LLM is enabled. This means "never
overwrite a high-confidence rules result" is enforced by construction — a high-confidence field is
never even sent to the provider — rather than by a runtime check after the fact.

**Fields with no catalog question are stored as evidence-only "insights," never as answers.**
`exchange_incentive`, `restocking_fee`, `final_sale_rules`, `international_returns`, and
`product_category_hints` are mapped to synthetic `website_insight.*` question keys.
`ApplyWebsiteEvidenceToAnswersService` already no-ops when `AssessmentQuestionCatalog::question()`
returns null for a key, so these are structurally incapable of becoming an `AssessmentAnswer` — they
exist purely as `AssessmentAnswerEvidence` rows for the merchant to read as context.

**Confidence, not a vote, resolves rules/LLM disagreement — except on a tie, which requires manual
confirmation.** When both strategies produce a (verified) value for the same question and the
values differ, the higher-confidence one becomes the suggestion eligible to auto-fill the answer;
the loser is still stored as evidence (marked `superseded_by`) so the disagreement is visible, not
hidden. When confidence is equal and the values differ, both candidates are flagged
`requires_confirmation = true` and neither is used to auto-fill — the merchant must answer manually.
This flag is enforced once, centrally, in `ApplyWebsiteEvidenceToAnswersService::apply()`, which
already gates on "does an answer already exist"; the same filter now also gates on "does this
candidate require confirmation."

**No separate conflicts table.** A "conflict record" is just two `AssessmentAnswerEvidence` rows
sharing a `question_key`, both flagged `requires_confirmation`. The existing evidence table (grouped
by `question_key` in `WebsiteScanController`'s response) already gives the frontend everything it
needs to render both candidates side by side. Adding a dedicated conflicts table would duplicate
data the evidence table already holds for no query or auditability benefit this feature needs yet.

**Evidence verification is a hard gate, not a confidence signal.** Every LLM field response must
carry an `evidence_quote` and `source_url`; `LlmWebsiteExtractionStrategy` rejects the field
entirely (drops it, does not lower its confidence) if the quote isn't found verbatim (after
whitespace/case normalization) in the exact source block the model claims it came from. An
unverifiable claim is worth nothing here, not a "low confidence" data point — the model is used to
locate and interpret evidence, it is not itself the evidence.

**Rate limiting lives in `StartWebsiteScanService`, not inside the strategies.** The
`WebsiteExtractionStrategy` contract deliberately only ever receives a `WebsiteScanResult` (scraped
HTML) — no assessment ID, no request IP, no merchant data. That's what guarantees the LLM strategy
structurally cannot see merchant answers, CSV data, or contact details, satisfying the "never send
customer data" requirement by construction rather than by care at every call site. Per-assessment,
per-IP, and application-wide-daily limits (`LlmExtractionRateLimiter`, backed by Laravel's
`RateLimiter` facade) are therefore checked one layer up, where assessment and request context
already exist; exceeding any of them silently substitutes the rules strategy for that call and logs
a sanitized warning, using the same "degrade, don't fail" path as a provider timeout or bad response.

**Caching is keyed on content, not on a request ID.** `LlmWebsiteExtractionStrategy` caches the
decoded provider response under `sha256(scan url | sha256(all block urls+text) | model |
prompt_version | requested fields)`. Two scans of the same URL with unchanged page content hit the
cache; a re-scan after the merchant's site copy changes does not.

**Feature flag is environment configuration, not a runtime-toggle system.** This application has no
existing runtime feature-flag store (no `feature_flags` table, no Pennant, no admin toggle UI).
Building one is out of scope for this feature. `LLM_ENABLED` (config `llm.enabled`) is checked on
every call, so disabling it takes effect on the next deploy/restart, not instantly — acceptable
given the spec explicitly permits falling back to environment configuration when no runtime-flag
system exists.

**Scan-phase UI is a client-side approximation, not a real backend trace.** `StartWebsiteScanService`
runs synchronously within the single `POST .../website-scan` request/response (matching this
app's existing `QUEUE_CONNECTION=sync` approach to imports) — there is no streaming or polling
channel for the backend to report "now applying rules" vs. "now verifying evidence" mid-request.
The wizard cycles through the four phase labels on a fixed client-side timer while the request is
in flight and jumps to the final phase on success. This was chosen over building a real async
job + polling architecture for this feature alone, which would be a much larger, separately-scoped
change to how website scanning works end to end.

## Consequences

- A merchant only ever sees an LLM call attempted when `website_extraction.strategy` is `llm` or
  `hybrid` and `LLM_ENABLED=true`; with the rules-only default, none of this code path executes and
  no external request is ever made.
- The five insight-only fields are visible to the merchant as evidence but do not affect scoring or
  recommendations at all today — wiring them into scoring/opportunities is explicitly out of scope
  for this change, matching how imported evidence was left unconsumed by scoring in ADR 0005 until
  its own dedicated pass.
- Because rate limiting is orchestration-level, a future second LLM-consuming feature would need its
  own limiter (or a shared one factored out) — `LlmExtractionRateLimiter` today is scoped to website
  extraction only.
- The scan-phase indicator can finish "early" relative to its label (e.g. landing on "Verifying
  evidence" while the request is still technically crawling) on a fast connection, and can sit on an
  earlier label longer than the real work takes on a slow one, since it's not driven by actual
  server-side progress. If this becomes worth fixing, it needs a real async/polling redesign of the
  scan endpoint, not a change to this ADR's decisions.
- Adding a second provider (OpenRouter, Gemini) means implementing `LlmClient` and adding one match
  arm in `AppServiceProvider`'s binding — `LlmWebsiteExtractionStrategy`, `HybridWebsiteExtractionStrategy`,
  and everything downstream are already provider-agnostic.
