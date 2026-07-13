# Data Ingestion (Provider-Agnostic Import Framework)

This document describes how the assessment wizard's optional "improve accuracy with store
data" step actually works: the provider-agnostic contracts and coordinator that run every
import, the import lifecycle, idempotency, the two providers implemented today, and how
imported evidence relates to a merchant's explicit answers. It documents the behavior of the
code as implemented in Milestone 11 (`app/Contracts/*`, `app/Services/Imports/*`,
`app/Jobs/Process*ImportJob.php`, `app/Http/Controllers/ImportController.php`,
`resources/js/Pages/Assessment/Wizard.vue`'s import phase) — it is not a decision record (see
`docs/decisions/0005-provider-agnostic-imports.md` for the "why"), and it should be kept in
sync with that code rather than treated as a spec the code must match.

## Architecture overview

Everything is built behind a small set of contracts (`app/Contracts/`) so a new data source can
be added without touching the domain that consumes it:

- **`MerchantDataProvider`** — identifies a provider (`provider()`, e.g. `'demo'` / `'csv'`) and
  declares which of the three data types it can supply (`supports(string $dataType)`).
- **`CatalogImporter`** / **`OrderReturnImporter`** / **`InventoryImporter`** — one method each
  (`importCatalog`, `importOrdersAndReturns`, `importInventory`), each taking the `DataImport`
  row being processed and persisting whatever aggregate rows that data type produces. A provider
  implements the subset of these it supports.
- **`ImportNormalizer`** — normalizes one raw source record (a CSV row today; a future API
  response fragment) into a normalized associative array shape. Not all current importers use
  this seam directly, but it is the contract a future provider's row-shaping logic should
  implement.
- **`ImportMetricCalculator`** — computes aggregate metrics from the normalized rows produced for
  a given import.

Providers register their importers with **`ImportProviderRegistry`** (`app/Services/Imports/
ImportProviderRegistry.php`), a singleton with a `register()` call per provider and per-data-type
lookup methods (`catalogImporterFor()`, `orderReturnImporterFor()`, `inventoryImporterFor()`).
The queued jobs and nothing else consult this registry at run time, so adding a provider is
"implement the contracts, register them" without any change to `ImportCoordinator` or the jobs.

### The `ImportCoordinator`'s three-phase API

`ImportCoordinator` (`app/Services/Imports/ImportCoordinator.php`) is the single orchestrator
every provider goes through. Its API is deliberately three phases because the two providers
implemented today have different shapes of "when is there enough to start":

1. **`create(Assessment, provider, method, ?DataConnection)`** — opens a `DataImport` row in the
   `created` status with an empty `data_types` array. No jobs are dispatched.
2. **`attachDataType(DataImport, dataType, ?fingerprintSeed)`** — declares one of the three data
   types (`ImportCoordinator::DATA_TYPE_CATALOG` / `DATA_TYPE_ORDERS_RETURNS` /
   `DATA_TYPE_INVENTORY_LOCATIONS`) as part of this import, optionally with a fingerprint seed
   (see Idempotency below). Appending an already-attached data type is a no-op. May only be
   called while the import is still `created`; calling it after `process()` throws
   `LogicException`, because the pending-work set is frozen once processing starts.
3. **`process(DataImport)`** — the single "go" trigger. Either short-circuits an idempotent
   duplicate re-import (see Idempotency) or transitions the import to `queued` and dispatches one
   queued job per attached data type (`ProcessCatalogImportJob`, `ProcessOrderReturnImportJob`,
   `ProcessInventoryImportJob`, all extending the shared `ProcessImportJob` base).

The CSV flow spreads `attachDataType()` calls across separate HTTP requests as the merchant
uploads each file (`ImportController::storeFile()`), then calls `process()` once explicitly
(`ImportController::process()`). The demo flow (`DemoDataProvider::startImport()`) calls
`create()`, all three `attachDataType()`s, and `process()` back-to-back in one method, since all
demo data is available synchronously. Both share the exact same coordinator — no
provider-specific branching exists inside `ImportCoordinator` itself.

### Queued-job-per-data-type execution and race-safe finalization

Each attached data type runs as its own queued job (`ProcessCatalogImportJob`,
`ProcessOrderReturnImportJob`, `ProcessInventoryImportJob`), all subclassing
`ProcessImportJob` (`app/Jobs/ProcessImportJob.php`). A job holds only the `DataImport` id (not
the model), resolves its importer from `ImportProviderRegistry` for the import's provider, and
invokes the importer's single method inside a try/catch. On success or on a caught exception (an
exception is recorded as a `DataImportError` and treated as a per-data-type failure, not fatal to
other data types), the job calls `ImportCoordinator::finalizeDataType($dataImportId, $dataType,
$failed)`.

`finalizeDataType()` is the race-safe hand-off: it locks the `DataImport` row `FOR UPDATE` inside
a DB transaction, re-reads `pending_data_types` from that locked row (never from a stale
in-memory snapshot), removes the finishing data type, and — only if that removal empties the
pending list — computes the overall status and stamps `completed_at`. This means whichever job
happens to finish last is the one that finalizes the import, safely, even if two jobs finish
within milliseconds of each other. The project's local/CI database is SQLite, which does not
enforce row-level locking, so this guard's real concurrent-safety is exercised by the code but
not proven under real concurrency by the automated test suite — it depends on the production
database (Postgres) actually enforcing `FOR UPDATE`.

## Import lifecycle

`ImportStatus` (`app/Enums/ImportStatus.php`) has 9 cases:

```
created -> validating -> queued -> importing -> processing -> completed
                                                             -> completed_with_warnings
                                                             -> failed
(any non-terminal status) -> cancelled
```

- **`created`** — the import exists; data types can still be attached.
- **`validating`** — set briefly by `process()` right before dispatching jobs (a placeholder step
  today; no validation logic currently runs during this specific status, but it exists as a
  named transition point for a future milestone to hook into).
- **`queued`** — `process()` has dispatched one job per data type.
- **`importing`** — the first job to actually run flips the import here and stamps `started_at`
  (`ProcessImportJob::markProcessing()`).
- **`processing`** — every job (including the first) then immediately re-marks the import
  `processing` before doing its work; this is the steady "in flight" state most jobs run under.
- **`completed`** — every attached data type finalized with zero failures
  (`ImportCoordinator::overallStatus()`).
- **`completed_with_warnings`** — at least one data type failed, but not all of them.
- **`failed`** — every attached data type failed (`errors_count >= count(data_types)`).
- **`cancelled`** — set by `ImportCoordinator::cancel()`, callable from any non-terminal status
  (`created`, `validating`, `queued`, `importing`, `processing`). Calling `cancel()` on an import
  already in a terminal status (`completed` / `completed_with_warnings` / `failed` / `cancelled`)
  is a documented no-op, not an error, so a caller (the wizard) can cancel optimistically without
  racing the finalizer. A cancelled import's in-flight jobs also no-op if they run afterward:
  `ProcessImportJob::handle()` returns immediately if the import is missing or already
  `cancelled`, and `finalizeDataType()` does the same.

## Idempotency

Idempotency is opt-in per data type, driven by the optional `fingerprintSeed` argument to
`attachDataType()`. The CSV flow always provides one: `ImportController::storeFile()` computes a
`sha256` file hash (`hash_file('sha256', ...)`) of the uploaded file and passes it as the seed.
The demo flow never provides seeds — re-running a demo scenario for a merchant is meant to always
produce a fresh, fully populated import, not be deduped against a prior demo run.

At `process()` time (`ImportCoordinator::process()`):

1. A combined fingerprint is computed over whatever seeds are present, sorted by data type:
   `sha256(merchant_id | provider | method | "dataType1:seed1|dataType2:seed2|...")`. This is
   always stored on `metadata.fingerprint`, even for imports (like demo) that don't participate
   in deduplication, purely for audit.
2. **Only when every attached data type provided a seed** does the coordinator treat this as a
   content-addressable re-import candidate. It then looks for a prior `DataImport` with the exact
   same fingerprint, in a `completed` or `completed_with_warnings` status, belonging to the same
   merchant (across any of that merchant's assessments, via `whereHas('assessment', ...)`
   scoped to `merchant_id` — not scoped to the current assessment).
3. If a match is found, the new import is short-circuited: it's stamped `completed` immediately,
   with `metadata.duplicate_of_import_id` pointing at the original, and **no jobs are dispatched
   at all**. If no match is found, or not every data type was seeded, the import proceeds through
   the normal queued-job path.

Concretely, "reimport is idempotent" in this codebase means: uploading the exact same three CSV
files again (byte-for-byte, so the file hashes match) is recognized as a duplicate and
short-circuited — no new `MerchantProduct` / `MerchantOrderMetric` / etc. rows are created, and
no jobs run. It is **not** full data-diffing — changing even one byte of one file produces a
different fingerprint and a fresh import, and there is no row-level reconciliation between an old
and new import of similar-but-not-identical data.

## Providers implemented today

### `demo`

`DemoDataProvider` (`app/Services/Imports/Demo/DemoDataProvider.php`) supplies entirely
synthetic, fixture-based data for the public demo and the wizard's "Use demo data" option. It
must never be presented as real merchant data. `DemoDataProvider::startImport(Assessment,
scenario)` is a convenience entry point that drives the full three-phase coordinator API for a
single scenario in one call.

There are exactly three fixture scenarios (`DemoScenarios::SCENARIOS`,
`app/Services/Imports/Demo/DemoScenarios.php`): `apparel`, `footwear`, `home_goods`. Each is a
static, hand-authored data block (products with variants, an order metric, a return metric, an
inventory metric, a location metric) with no I/O and no Eloquent dependency — the three demo
importers (`DemoCatalogImporter`, `DemoOrderReturnImporter`, `DemoInventoryImporter`) read from
it and persist the rows. A product's `variants` array is a deliberately representative sample
(2-4 rows), not one row per unit of `variant_count`.

### `csv`

`CsvMerchantDataProvider` (`app/Services/Imports/Csv/CsvMerchantDataProvider.php`) lets a
merchant upload their own store data as three files instead of connecting a live integration.
This is **explicitly not real Shopify export parsing** — there is no alias detection, no
Shopify-specific column mapping, no handling of Shopify's actual export formats. It is a simple,
documented column format of this project's own design, proving the import framework end-to-end.
Real Shopify export parsing (or a live Shopify API provider) is a future milestone's scope.

Uploads go through `POST /api/assessments/{assessment}/imports/{import}/files`
(`StoreDataImportFileRequest`): one file per data type, `.csv` extension, `csv`/`txt` mimetype,
capped at 5MB (a framework-proving upload path, not a bulk-data pipeline).

The three files and their exact columns:

- **`products.csv`** — one row per product. Headers: `title, product_type, vendor, tags,
  description_length, status, variant_count, has_size_option, has_color_option, media_count,
  price, compare_at_price, sku, inventory_tracked`. A row missing `title`, or with an unparseable
  numeric field, is rejected individually (recorded as a `DataImportError` with its row number)
  and processing continues with the remaining rows — one bad row never costs the whole file
  (`CsvCatalogImporter`).
- **`orders_and_returns.csv`** — a single pre-aggregated summary row (not raw per-order
  transaction rows), with dates as `YYYY-MM-DD`. Required: `period_start`, `period_end`,
  `order_count`; if any is missing or invalid the whole file is rejected (`CsvOrderReturnImporter`
  throws, caught by the job and recorded as one `DataImportError`, failing only this data type).
  Optional columns (`annualized_order_volume`, `average_order_value`, `refund_amount_total`,
  `refund_units_total`, `estimated_refund_rate`, `exchange_share`, `refund_only_share`,
  `average_time_to_refund_days`) default to `null` when blank or unparseable rather than
  rejecting the file.
- **`inventory_and_locations.csv`** — a single pre-aggregated summary row. No single column is
  strictly required, but a row where every column (`percent_multi_location_stock`,
  `percent_low_or_zero_stock`, `sku_completeness`, `exchange_availability_risk`,
  `active_location_count`, `operational_complexity_score`) is blank or unparseable rejects the
  whole file — there's nothing useful to import from it (`CsvInventoryImporter`).

### Not implemented: Shopify (or any live API provider)

A real Shopify API provider is planned but does not exist yet. The `data_connections` table
(migration `2026_07_12_010000_create_data_connections_table.php`) and its OAuth/credential-storage
columns (`provider`, `status`, `credentials`, `granted_scopes`, `connected_at`,
`disconnected_at`) exist as schema only — nothing writes to this table today. In the wizard, the
"Connect Shopify" card is genuinely disabled: the button has `disabled` / `aria-disabled="true"`
and its `@click` handler is never wired, so selecting it creates nothing (`Wizard.vue`, the
`data-testid="connect-shopify"` button).

## Evidence and provenance

`AssessmentEvidenceService` (`app/Services/Imports/AssessmentEvidenceService.php`) merges an
assessment's explicit questionnaire answers with values derived from that merchant's latest
usable import (an import in `completed` or `completed_with_warnings` status, most recent by id —
`latestUsableImport()`), producing an `EvidenceRecord` per supported key
(`AssessmentEvidenceService::mergedEvidence()`).

The non-overwrite guarantee lives in `EvidenceRecord`'s constructor
(`app/Services/Imports/EvidenceRecord.php`): if the explicit answer is "answered" (not `null`,
not `''`, not `[]` — the same convention `SubmitAssessmentService` already uses), it always wins
(`source: 'merchant_answer'`) regardless of what any import produced. Only when the answer is
unanswered does an imported value get a chance, and only if it is itself "answered" (`source:
'imported'`); otherwise the record's authoritative value is `null` (`source: 'none'`). An
imported value can never silently overwrite an explicit answer.

Exactly two evidence keys are supported today:

- **`business.monthly_order_volume`** — derived by dividing the latest import's
  `MerchantOrderMetric.annualized_order_volume` by 12 and rounding, scoped to the specific
  assessment and the specific `source_import_id`.
- **`catalog.sku_count`** — derived by counting `MerchantProduct` rows for the merchant scoped to
  that same `source_import_id`.

**Nothing in the product currently consumes this evidence.** `AssessmentEvidenceService` is a
standalone primitive — it is not called from `ReadinessScoringService`, `OpportunityCalculation
Service`, `BenchmarkComparisonService`, or `ReportBuilderService`. Wiring imported evidence into
scoring, opportunities, and benchmarks is a future milestone's work.

## No customer PII

**Guardrail: no table this framework writes to may contain a customer-identifying column.**
Every aggregate table imports write to (`merchant_products`, `merchant_product_variants`,
`merchant_order_metrics`, `merchant_return_metrics`, `merchant_inventory_metrics`,
`merchant_location_metrics`) stores catalog- and metric-level aggregates only — no customer name,
email, phone, address, or other identity data. This is enforced structurally, not just by
convention: `tests/Feature/Imports/ImportEndpointsTest.php::
test_no_merchant_data_table_contains_a_customer_pii_column()` introspects the schema of each of
those tables and asserts no column name contains a forbidden substring (`email`, `phone`,
`address`, `ssn`, `credit_card`, `date_of_birth`) or exactly matches a forbidden name (`name`,
`customer_name`, `contact_name`, `first_name`, `last_name`, `full_name`, `zip`, `zip_code`,
`postal_code`). Any migration that adds such a column to one of these tables fails this test.

## Known limitations

- **No real Shopify (or any live API) parsing.** The `csv` provider's column format is this
  project's own design, not Shopify's actual export schema, and there is no OAuth/API provider
  implementation at all — see "Not implemented: Shopify" above.
- **No per-order or per-inventory-item raw storage.** `orders_and_returns.csv` and
  `inventory_and_locations.csv` are single pre-aggregated summary rows in this milestone; nothing
  in the framework persists raw per-order or per-inventory-item records. Computing aggregates from
  raw transaction-level data is deferred to the milestone that adds real Shopify ingestion.
- **CSV formats are not Shopify's real export columns.** A merchant cannot upload an unmodified
  Shopify products/orders export today; the three CSV files must match this project's documented
  headers.
- **No resume-after-page-reload for an in-progress import.** If a merchant reloads the wizard
  mid-import, the in-memory `assessmentId` / `csvImportId` / `csvFiles` state in `Wizard.vue` is
  lost and there is no client-side mechanism to reattach to the in-flight `DataImport`. This
  mirrors an existing, pre-Milestone-11 limitation: the wizard's question-answering session model
  also does not persist across a reload (there is no "resume my draft" flow for answers either),
  and this milestone did not change that.
