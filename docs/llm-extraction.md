# LLM-Assisted Website Extraction

This document describes how the optional LLM-assisted path of the website-scan feature works:
configuration, privacy/data handling, input limits, evidence verification, fallback behavior, and
model configuration. It documents the behavior of the code as implemented
(`app/Services/WebsiteScan/LlmWebsiteExtractionStrategy.php`,
`app/Services/WebsiteScan/HybridWebsiteExtractionStrategy.php`, `app/Services/Llm/*`) — it is not a
decision record (see `docs/decisions/0008-hybrid-rules-llm-extraction.md` for the "why") and should
be kept in sync with that code rather than treated as a spec the code must match.

## Is this on by default?

No. `WEBSITE_EXTRACTION_STRATEGY` defaults to `rules`, and `LLM_ENABLED` defaults to `false`. With
either at its default, no code in this document ever runs and no external HTTP request is ever
made. Both must be explicitly set to turn this on.

## The three strategies

- **`rules`** (default) — `RulesWebsiteExtractionStrategy`. Deterministic regex/marker matching
  over scraped HTML. No external dependency, always available.
- **`llm`** — `LlmWebsiteExtractionStrategy` alone. Calls the configured provider for all eight
  target fields on every scan. Mostly useful for evaluating the LLM path in isolation; not the
  recommended production setting, since it gets none of rules' free, instant, always-available
  answers.
- **`hybrid`** (recommended when the LLM path is enabled) — `HybridWebsiteExtractionStrategy`.
  Runs rules first, then asks the LLM only for fields rules left missing or answered below "high"
  confidence. See ADR 0008 for the full merge/conflict logic.

Set via `WEBSITE_EXTRACTION_STRATEGY` (`config('assessment.website_extraction.strategy')`),
resolved by `WebsiteExtractionStrategyResolver`.

## Provider configuration

Only Groq is implemented today, behind a provider-neutral `App\Services\Llm\LlmClient` contract
(`extractStructured(array $messages, array $schema): array`). A future provider (OpenRouter,
Gemini) implements the same contract and is wired in via one more arm in `AppServiceProvider`'s
`LlmClient` binding — nothing above that layer changes.

| Env var | Config path | Purpose |
| --- | --- | --- |
| `LLM_ENABLED` | `llm.enabled` | Master on/off switch. |
| `LLM_PROVIDER` | `llm.provider` | Currently only `groq` is implemented. |
| `LLM_TIMEOUT_SECONDS` | `llm.timeout_seconds` | Per-request timeout passed to the HTTP client. |
| `LLM_MAX_INPUT_CHARACTERS` | `llm.max_input_characters` | Total characters of cleaned page text sent to the model, across all pages. |
| `LLM_MAX_PAGES` | `llm.max_pages` | Max crawled pages considered (the crawler itself caps at 4 pages regardless). |
| `LLM_DAILY_REQUEST_LIMIT` | `llm.daily_request_limit` | Application-wide daily cap on provider calls. |
| `GROQ_API_KEY` | `services.groq.key` | Never sent to the browser; read server-side only. |
| `GROQ_BASE_URL` | `services.groq.base_url` | Defaults to Groq's OpenAI-compatible endpoint. |
| `GROQ_MODEL` | `services.groq.model` | No default — must be set explicitly. See "Choosing a model" below. |

Two additional limits exist as config only (no env var listed in the original feature request, but
overridable via `LLM_REQUESTS_PER_ASSESSMENT_PER_HOUR` / `LLM_REQUESTS_PER_IP_PER_HOUR` if needed):
`llm.requests_per_assessment_per_hour` (default 3) and `llm.requests_per_ip_per_hour` (default 10).

### Choosing a model

Groq's available models and their individual token/context limits change over time — do not copy a
model ID from an old tutorial. Pick one from Groq's current supported-models list that:

- supports chat completions with reliable JSON output, and
- has enough context length for a handful of cleaned policy-page excerpts (a few thousand tokens is
  typically enough at this feature's `LLM_MAX_INPUT_CHARACTERS` default).

`GroqLlmClient` requests `response_format: { type: "json_schema", json_schema: { strict: true, ... } }`.
If the configured model doesn't support strict structured output, Groq's non-strict JSON object mode
still returns valid JSON that `LlmWebsiteExtractionStrategy`'s own validation (schema-shape checks,
evidence verification) treats as untrusted input regardless — an unsupported or misbehaving model
degrades to more rejected fields, not incorrect data reaching a merchant's answers.

## Free tier is not guaranteed

Groq's rate limits (requests/tokens per minute and per day) are set at the account/model level and
can change. This feature's own limits (`LLM_DAILY_REQUEST_LIMIT` and the per-assessment/per-IP
limits above) are deliberately configured stricter than Groq's typical free-tier limits so this
application's usage pattern shouldn't be the thing that exhausts a shared account — but that's a
safety margin, not a guarantee. Check Groq's current limits for the account and model in use before
relying on this in a demo, and treat any 429 response the same as any other provider failure (see
"Fallback behavior" below): the scan still completes, just without the LLM's contribution for that
call.

## Privacy: what does and doesn't leave this application

- **Sent to the provider:** only the cleaned, visible text of pages already fetched by
  `WebsiteCrawler` from the merchant's own public website (via `SafePublicHttpUrl`'s SSRF
  protections — private/loopback/link-local addresses and non-http(s) schemes are rejected before
  any fetch happens), reduced to plain text by `WebsiteTextExtractor` (scripts, styles, nav,
  header, footer, and a best-effort set of cookie-banner containers stripped), capped at
  `LLM_MAX_INPUT_CHARACTERS` total and `LLM_MAX_PAGES` pages.
- **Never sent to the provider:** the merchant's questionnaire answers, contact email, CSV import
  contents, or any other assessment data. This isn't enforced by a filter that could have a gap —
  `LlmWebsiteExtractionStrategy::extract()` only ever receives a `WebsiteScanResult` (crawled HTML),
  which structurally contains none of that data. Nothing in this feature's call chain has both a
  reference to the assessment/merchant and a reference to the LLM client at the same time.
- **Never logged:** prompts and page content are excluded from the sanitized warning logged on any
  provider failure (`Log::warning('Website LLM extraction unavailable...', ['provider' => ...,
  'exception' => ..., 'reason' => ...])` — only the provider name, exception class, and the
  exception's own static, pre-sanitized message, never request/response bodies).
- **Never exposed to the browser:** `GROQ_API_KEY` is read only in `config/services.php` /
  `GroqLlmClient`, server-side. It never appears in an Inertia prop, a JSON response, or a Vite/JS
  bundle.

## Structured output and evidence verification

Every non-null field the model returns must include `value`, `confidence`
(`high`/`medium`/`low`), `evidence_quote`, `source_url`, and `explanation`. Before a field is
accepted:

1. `evidence_quote` and `source_url` must both be present and non-empty.
2. `source_url` must match one of the pages actually sent in this request.
3. `evidence_quote`, after whitespace/case normalization, must appear verbatim in that page's
   cleaned text.

Any field failing any of these checks is dropped entirely — not downgraded to low confidence. An
unverifiable claim is discarded, not trusted-but-flagged.

## Fields extracted

Three map to an existing assessment question and can become an `AssessmentAnswer` (only when no
manual answer already exists — see precedence below): `return_window_days` →
`return_policy.window_days`, `exchanges_offered` → `exchanges.offered`, `policy_clarity` →
`return_policy.policy_clarity`. The remaining five have no assessment question and are stored as
evidence-only insights under a synthetic `website_insight.*` key (visible to the merchant, never
turned into an answer): `exchange_incentive`, `restocking_fee`, `final_sale_rules`,
`international_returns`, `product_category_hints`.

## Precedence and conflicts

Manual answer > verified rules result > verified LLM result > nothing. A manual answer is never
overwritten. When rules and the LLM disagree on the same question, the higher-confidence one wins
and becomes the candidate that can fill a blank answer; the other is still stored as evidence
(marked `superseded_by`) so the disagreement stays visible. When confidence is tied and the values
differ, both candidates are marked `requires_confirmation` and neither auto-fills — the merchant
sees both in the wizard and must answer manually.

## Fallback behavior

None of the following ever surfaces an error to the merchant or fails the scan — the endpoint
still returns `201` with whatever rules produced:

- LLM disabled (`LLM_ENABLED=false`)
- Provider request times out or the connection fails
- Provider returns a non-2xx status (including rate-limit/quota responses)
- Provider response isn't valid JSON, or isn't shaped like the requested schema
- Per-assessment, per-IP, or daily request limit reached (this call falls back to the rules
  strategy specifically; a sanitized warning is logged noting which layer degraded)

## Caching

Provider responses are cached (`Cache::put`/`Cache::get`, 6-hour TTL) under a key derived from the
scan's URL, a hash of every source block's URL+text, the configured model, the prompt version, and
the requested field set. Re-scanning the same URL with unchanged page content is served from cache
without a new provider call; a content or field-set change is a cache miss.

## Frontend behavior

The wizard shows four phase labels while a scan is in flight ("Crawling site," "Applying rules,"
"Clarifying ambiguous values," "Verifying evidence"). Because the scan itself is one synchronous
request/response, these are a client-side approximation on a fixed timer, not a live trace of
server-side progress — see ADR 0008.

Each suggested answer shows its source label (`Website scan` or `Website scan — AI-assisted`), and
LLM-sourced suggestions additionally show a small "AI-assisted" badge — scoped to that one
suggestion, not a claim about the assessment as a whole. Evidence snippet and source URL are shown
alongside the value. A conflict (`requires_confirmation`) shows both candidates with a "Requires
confirmation" badge and prompts the merchant to pick the correct answer manually; neither candidate
is pre-filled.
