# 0005. Provider-Agnostic Import Framework

## Status

Accepted

## Context

The product needed a way for a merchant to improve their assessment's accuracy with real store
signals — catalog size, order volume, return rate, inventory spread — without coupling the
domain to any one integration. The architecture guardrails already required future integrations
to sit behind a data-source contract rather than being wired directly into scoring or the wizard,
and the product guardrails require anonymous merchants to be able to complete the assessment
without a live store connection at all.

At the same time, no real Shopify (or any other platform) API integration exists yet, and
building one — OAuth, credential storage, real export-format parsing — is substantial scope on
its own. This milestone needed to prove the import framework end-to-end (schema, contracts,
orchestration, wizard UI, idempotency, evidence handoff) using data sources that don't require
external API access: a synthetic demo provider and a merchant-uploaded CSV provider in this
project's own documented format. A real Shopify provider was deliberately left for a later
milestone rather than attempted alongside the framework itself.

## Decision

**Aggregate-only metric tables; no raw per-order or per-inventory-item persistence.** Every table
an importer writes to (`merchant_products`, `merchant_product_variants`,
`merchant_order_metrics`, `merchant_return_metrics`, `merchant_inventory_metrics`,
`merchant_location_metrics`) stores catalog-level rows or a small number of pre-aggregated metric
rows per import — never a full order or return payload. `orders_and_returns.csv` and
`inventory_and_locations.csv` are each a single pre-aggregated summary row by design, not raw
transaction data the framework aggregates itself. This applies the project's existing "don't
persist full order payloads after aggregation" principle (already the pattern for opportunity
and benchmark calculations, which store computed results rather than the raw answers they were
derived from) one milestone earlier than a real order-history integration would otherwise force
the question. It also directly supports the "No customer PII" guardrail: aggregate-only tables
structurally can't carry a customer name, email, or address, and
`tests/Feature/Imports/ImportEndpointsTest.php::test_no_merchant_data_table_contains_a_customer_pii_column()`
enforces that no column on any of these tables even looks like an identity field.

**A three-phase coordinator API (`create` / `attachDataType` / `process`) shared by every
provider.** `ImportCoordinator` does not branch on provider. The three phases exist because the
two providers implemented today have genuinely different shapes of "when is there enough to
start": CSV needs multiple files to arrive across separate HTTP requests before it can process
anything (`attachDataType()` called once per uploaded file, with the actual `process()` trigger
requiring an explicit merchant action), while the demo provider has all its data available
synchronously in one call and drives `create()` → three `attachDataType()`s → `process()`
back-to-back. Rather than giving each provider its own orchestration path, both are expressed as
different call patterns against the same three methods, so `ImportCoordinator`, the queued jobs,
and the finalization/status logic are written and tested exactly once. A future Shopify provider
is expected to fit the same shape: `attachDataType()` calls as each data type's fetch completes,
`process()` once the provider decides it has enough.

**Idempotency is a merchant-scoped fingerprint match, not full data-diffing.** Every attached
data type may carry an opaque fingerprint seed (CSV uses a `sha256` hash of the uploaded file
content; demo intentionally provides none). At `process()` time, if every attached data type
supplied a seed, the coordinator combines them into one fingerprint
(`sha256(merchant_id | provider | method | sorted per-data-type seeds)`) and looks for a prior
`completed`/`completed_with_warnings` import with the same fingerprint for the same merchant
(across that merchant's assessments, not just the current one). A match short-circuits the new
import to `completed` immediately with no jobs dispatched, recording
`metadata.duplicate_of_import_id`. This scope was chosen deliberately narrow: it recognizes
"you uploaded the exact same file(s) again" cheaply and safely, without attempting row-level
reconciliation between two similar-but-not-identical imports, which would require real
data-diffing logic this milestone does not need. Demo imports opt out entirely (no seeds
provided) because re-running a demo scenario is supposed to always produce a fresh import, not be
deduped against a merchant's own prior demo run.

**`csv` is a distinct, explicitly-limited provider — not a Shopify export reader.** The CSV
column formats (`products.csv`, `orders_and_returns.csv`, `inventory_and_locations.csv`) are this
project's own design, documented in `docs/data-ingestion.md`, chosen for the fewest columns
needed to prove the framework end-to-end. They are not Shopify's real export schema, and
`CsvMerchantDataProvider`'s docblock states this explicitly. `csv` and the not-yet-implemented
`shopify` are kept as distinct provider keys (`data_imports.provider` is a free-form string, not
enum-backed like `data_imports.method`/`ImportMethod`; distinctness is maintained by contract and
convention — each `MerchantDataProvider::provider(): string` implementation returns its own key,
and `ImportProviderRegistry` is keyed on that string) rather than treating CSV as a stand-in for
Shopify, because a real Shopify provider will need alias detection and Shopify's actual column/field mapping — different
implementation entirely, just satisfying the same contracts. Keeping the keys distinct means a
future Shopify provider is additive (a new registry registration) rather than a breaking change
to how CSV imports are identified, fingerprinted, or reported on.

**`AssessmentEvidenceService` merges answers and imports, but nothing consumes it yet.** The
non-overwrite guarantee — an explicit questionnaire answer always wins over an imported value,
regardless of import status or recency — was built now because it is a correctness property that
has to be true from the first line of merge logic; retrofitting it after scoring or opportunities
already read blended values would risk a silent regression in a merchant's trust that their own
input was respected. Only two evidence keys are supported (`business.monthly_order_volume`,
`catalog.sku_count`) because those are the two questionnaire answers that map cleanly to a single
import-derived metric with no additional assumptions. `AssessmentEvidenceService` is deliberately
not wired into `ReadinessScoringService`, `OpportunityCalculationService`,
`BenchmarkComparisonService`, or `ReportBuilderService` in this milestone — consuming imported
evidence in scoring/opportunities/benchmarks changes what a merchant is shown and deserves its
own dedicated design pass (confidence-level implications, band-vs-precise-value handling, report
copy) rather than being folded in as a side effect of the import framework landing.

**The wizard's import step: always skippable, cancellable, and retriable; no cross-reload
resume.** The import step sits between the last questionnaire section and submission
(`Wizard.vue`'s `import` phase), and every path through it — "Skip for now," "Continue
manually," "Continue without this data" after a failed CSV import, and "Cancel" mid-import — ends
in the same place: `submitAssessment()` with whatever evidence (or none) was actually imported.
An import failure or a user's decision not to import never blocks or corrupts the questionnaire
draft, because the import step's state (`importMode`, `csvFiles`, `csvImportId`, `csvImport
Status`, `demoScenario`, `demoState`) is intentionally isolated from the draft state (`answers`,
`assessmentId`, `currentSectionIndex`) — the import step reads `assessmentId` and calls
`startAssessment()`, but never writes to any draft field or the autosave timers. Resuming an
in-progress import after a page reload was explicitly out of scope: the wizard's existing session
model does not persist draft state across a reload for questionnaire answers either (there is no
"resume my draft" flow today), so requiring imports to survive a reload when questions don't
would be an inconsistent, one-off guarantee. "Connect Shopify" is rendered but genuinely disabled
(`disabled` / `aria-disabled="true"`, no click handler) rather than hidden, so the option is
visible as a roadmap signal without implying it currently works.

## Consequences

- A future Shopify (or other platform) API provider only needs to implement
  `MerchantDataProvider` plus whichever of `CatalogImporter` / `OrderReturnImporter` /
  `InventoryImporter` it supports, register with `ImportProviderRegistry`, and add a
  `data_connections` row at connect time — `ImportCoordinator`, the queued jobs, the wizard's
  polling/status UI, and idempotency all already work unmodified. It will very likely need real
  per-order/return aggregation logic that today's CSV provider deliberately does not attempt.
- Aggregate-only storage means the framework cannot support any future feature that needs
  per-order or per-SKU-transaction detail (e.g. a returns-timeline view, cohort analysis) without
  a new milestone adding raw storage and revisiting the "don't persist full payloads" principle
  for that specific case.
- Idempotency will falsely treat two *different* CSV exports as new imports if a merchant changes
  even one byte (e.g. re-exporting on a different day with one more order) — this is intentional
  (see above) but means duplicate detection only ever catches literal re-uploads, not "roughly
  the same data."
- Because `AssessmentEvidenceService` is unconsumed, completing an import today changes nothing
  about a merchant's score, opportunities, or report — the wizard's "Your estimate will use these
  signals" copy is aspirational until a future milestone wires evidence into those services. That
  wiring is the natural next milestone once this framework has proven itself.
- The wizard's lack of cross-reload resume is a known, accepted rough edge shared with the
  existing questionnaire flow, not a new gap this milestone introduced — but it means a merchant
  who reloads mid-CSV-upload loses upload progress (though not their answers) and must start the
  import over.
