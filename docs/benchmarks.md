# Peer Benchmarking

This document describes how the "peer perspective" comparisons on the shareable report are
produced: what is benchmarked today, where the reference data comes from, how a benchmark is
selected for a given merchant, what the current data set does and does not represent, and how
to update it. It documents the behavior of the code as implemented in Milestone 10
(`app/Services/Benchmarks/*`, `config/benchmarks.php`, `database/seeders/BenchmarkSeeder.php`,
`app/Services/ReportBuilderService.php`) — it is not a decision record, and it should be kept in
sync with that code rather than treated as a spec the code must match.

## What's benchmarked today

Exactly three metrics are compared, each derived from a single banded questionnaire answer
(`config/benchmarks.php`):

| Metric key | Label | Unit | Source answer |
| --- | --- | --- | --- |
| `return_window_days` | Return window | days | `return_policy.window_days` |
| `manual_processing_hours_per_week` | Manual processing time | hours/week | `manual_operations.weekly_hours` |
| `catalog_sku_count` | Catalog size | SKU count | `catalog.sku_count` |

`MetricExtractionService` maps the merchant's selected band to a representative numeric
midpoint (e.g. "15-30 days" -> 22) using a band map — either defined directly in
`config/benchmarks.php` or, for manual processing hours, reused from
`config('assessment.opportunities.weekly_manual_hours_band_midpoints')` rather than duplicated.
If the underlying question was never answered, or the stored answer string doesn't match a
known band, the metric extracts to `null` and is skipped — no value is guessed.

### Why other metrics (e.g. exchange share) are not benchmarked yet

Metrics like exchange share, actual return rate, or refund cycle time would require real
order/return history. The assessment only collects banded, self-reported questionnaire answers
— there is no ingested order or return data in the product yet (that lands in a later milestone
via Shopify ingestion and a `MerchantDataSource` implementation). Benchmarking a metric the
product can't honestly compute from an actual data source would mean fabricating or guessing a
number, which conflicts with the no-fabrication discipline this milestone follows (the same
discipline `OpportunityCalculationService` uses for opportunity estimates — see
`docs/decisions/0004-opportunity-first-report.md`). The three metrics above were chosen because
each maps to exactly one already-collected, already-banded questionnaire answer with no
additional assumptions required.

## Source types

Every `BenchmarkSet` carries a `source_type` (`App\Enums\BenchmarkSourceType`), shown to the
merchant via `source_label`. The four values:

- **`illustrative`** — Configured, plausible-looking reference ranges that are not measured
  industry data. Used when the product has no real dataset yet. This is what ships today: the
  single active set is named "Illustrative Returns Benchmark" with `source_label` "Illustrative
  benchmark."
- **`configured`** — Reference ranges an operator has manually configured (e.g. from internal
  judgment or a partial dataset), distinct from a fully-vetted illustrative placeholder or an
  externally sourced number. Not currently seeded; the type exists as a seam for future data.
- **`external_reference`** — Ranges sourced from a named external publication or industry report.
  Not currently seeded.
- **`proprietary`** — Ranges derived from the product's own aggregated, measured merchant data
  (e.g. once enough merchants have submitted real order/return data through a future
  integration). Not currently seeded; this is the eventual replacement for `illustrative`.

The source type and source label are persisted per comparison
(`assessment_benchmark_comparisons.source_type` / `source_label`) at submit time and rendered
directly on the report (`PeerPerspectivePanel.vue`'s `data-testid="peer-source-label"` badge), so
a merchant always sees which kind of reference data they're being compared against.

## Resolution / fallback order

`BenchmarkResolver::resolve()` picks the single best-matching `BenchmarkValue` row for a metric
key and a comparison context (`industry`, `platform`, `annual_order_volume`), trying tiers in
order and returning the first match:

1. Exact match: same `industry`, `annual_order_volume` within the row's
   `annual_order_volume_min`/`max` range (if the row has one), and same `platform` (rows with a
   `null` platform match any platform).
2. Same `industry` and `annual_order_volume` range match, platform ignored.
3. Same `industry` only — volume and platform ignored.
4. Global fallback — the first active, in-window row for the metric with `industry` set to
   `null`.
5. No match — `resolve()` returns `null`.

Only rows belonging to a `BenchmarkSet` that is `is_active = true` and currently within its
optional `effective_from`/`effective_to` window are considered. Candidates are evaluated in
`id` order, so the first-created qualifying row wins a tier.

**A missing benchmark removes the comparison, it never fabricates one.** In
`BenchmarkComparisonService::compare()`, a metric is skipped entirely (not included in the
result array at all) if: the merchant's answer didn't extract to a value, `resolve()` returned
`null`, or the resolved row is missing `minimum_value`/`maximum_value`. A submitted assessment
can end up with zero, one, two, or three persisted `AssessmentBenchmarkComparison` rows, and the
report's "Peer perspective" section (`PeerPerspectivePanel.vue`) renders nothing at all when
there are none (`v-if="comparisons.length"`) — there is no placeholder or zeroed-out row.

## How context is derived

`MetricExtractionService::deriveContext()` produces the three dimensions the resolver matches
on:

- **`industry`** — derived from the multiselect answer `catalog.fit_sensitive_categories` by
  checking `config('benchmarks.catalog_profile_priority')` (`Apparel` -> `apparel`, `Footwear`
  -> `footwear`, `Home goods` -> `home_goods`) in order and returning the first category the
  merchant selected that has a mapping. `null` if none match or the question was unanswered.
- **`platform`** — the raw string answer to `platform.ecommerce_platform`, or `null` if
  unanswered.
- **`annual_order_volume`** — the banded answer to `business.monthly_order_volume`, mapped to a
  midpoint via `config('assessment.opportunities.order_volume_band_midpoints')` and multiplied
  by 12, or `null` if unanswered/unrecognized.

## Limitations

- **The current data set is a single configured illustrative set, not measured industry data.**
  `BenchmarkSeeder` seeds one `BenchmarkSet` ("Illustrative Returns Benchmark", version `1.0`,
  `source_type: illustrative`) with 12 `BenchmarkValue` rows (4 industries — `apparel`,
  `footwear`, `home_goods`, and a `null`-industry global fallback — for each of the 3 metrics).
  These ranges were configured for the product, not measured from real merchant behavior, and
  the report is transparent about that via the `source_label`/methodology text rather than
  presenting them as verified fact.
- **`industry` and `catalog_profile` are both derived from the same underlying signal today.**
  `BenchmarkValue` has a separate `catalog_profile` column in the schema, but nothing currently
  populates or resolves against it — `BenchmarkResolver` only matches on `industry`, and
  `MetricExtractionService::deriveIndustry()` is the sole source for that dimension, derived
  from `catalog.fit_sensitive_categories`. This is a stated limitation, not a bug: there isn't
  yet a distinct catalog-profile signal separate from the fit-sensitive-category answer used for
  industry.
- **No real order/return history informs any comparison.** Every metric and every context
  dimension comes from banded self-reported questionnaire answers, not from an integration. See
  "Why other metrics are not benchmarked yet" above.
- **Comparisons are frozen at submit time.** `SubmitAssessmentService::submit()` calls
  `BenchmarkComparisonService::compare()` once, inside the same transaction as scoring and
  recommendation generation, and persists the results to
  `assessment_benchmark_comparisons`. `ReportBuilderService::peerComparisons()` only ever reads
  those persisted rows — there is no recomputation path on the read side. Updating
  `BenchmarkSeeder` or the resolver logic does not retroactively change an already-submitted
  merchant's report; only a new submission picks up new data.

## Update process

Benchmark ranges live in the database (`benchmark_sets` / `benchmark_values`), not in code
constants, so changing them does not require touching `BenchmarkResolver` or
`BenchmarkComparisonService`. To add or change benchmark data:

1. Edit or extend `database/seeders/BenchmarkSeeder.php` — either add rows to the existing
   `values()` array (for corrections within the current set) or create a new `BenchmarkSet` with
   a bumped `version` string (e.g. `1.1`) for a data revision that should be tracked separately.
2. If introducing a new set that should supersede the old one, mark the old set's `is_active` to
   `false` (or set an `effective_to` date) so `BenchmarkResolver`'s candidate query stops
   considering it, and give the new set `is_active = true`.
3. Re-run the seeder in the target environment (`php artisan db:seed --class=BenchmarkSeeder`,
   or via the full `db:seed` if it's wired into `DatabaseSeeder`) and redeploy.
4. New comparisons only appear for assessments submitted after the change — existing reports are
   not recalculated (see Limitations above).

Adding a new benchmarked metric (beyond the current three) requires a code change: add an entry
to `config('benchmarks.metrics')` with its `label`, `unit`, `answer_key`, and band map, add the
metric key to `BenchmarkComparisonService::METRIC_ORDER`, and seed corresponding
`BenchmarkValue` rows for it.

## Prohibited claims

Report copy and any future benchmark-related UI must not claim or imply:

- That a merchant is "better than," "worse than," "beating," or "outperforming" peers.
  Interpretation text only states whether the merchant's value falls **below**, **within**, or
  **above** the resolved reference range — a neutral positional statement, not a value judgment
  (`BenchmarkComparisonService::interpretation()`).
- That a comparison is "proven," "guaranteed," or reflects a "verified industry average." The
  current data is explicitly labeled `illustrative` and described in its methodology text as
  "configured, illustrative reference ranges, not measured industry data."
- Any comparison without its source label visible. Every rendered comparison must show
  `source_label` (and the underlying `source_type`) so a merchant can see what kind of reference
  data they're looking at; `PeerPerspectivePanel.vue` renders this on every row, and the
  methodology disclosure surfaces the same set's `methodology` and `benchmark_version` text.
- Presenting seeded or illustrative data as verified fact. If a future data source (e.g.
  `external_reference` or `proprietary`) is added, its `source_label` and `methodology` must
  accurately describe its actual provenance rather than reusing illustrative-style language.
