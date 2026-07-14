# Assisted Autofill Wizard Plan

## Goal

Reduce the number of questions merchants must answer manually in the assessment wizard.

The wizard should become assisted-first: merchants provide a website URL, optionally upload Shopify CSV exports in the relevant sections, and the application auto-fills what it can with visible evidence. The existing questions remain available under manual-entry accordions, and manual answers always override CSV and website-derived answers.

## Product Flow

The assessment wizard should be reorganized into four merchant-facing steps:

1. Business & Platform
2. Product Catalog
3. Policy & Exchanges
4. Manual Operations

The first three steps prioritize assisted input. Manual Operations remains mostly manual because the product cannot reliably infer the merchant's internal labor process from public pages or standard Shopify exports.

## Step 1: Business & Platform

Primary cards:

- Scan your website
- Upload Shopify orders CSV

Manual-entry accordion:

- Company name
- Work email
- Monthly order volume or resolved order-volume band
- Ecommerce platform

Website scan should happen only when the merchant clicks `Scan site`.

The scan should inspect public pages only. It should not require credentials, crawl checkout, or collect customer personal data.

CSV guidance:

- Recommended: last full year of Shopify orders.
- Also supported: last full quarter or last full month.
- A full year gives the clearest annual view.
- A quarter is usable and can be annualized.
- A month is directional.
- Partial current-period exports should show a warning.

## Step 2: Product Catalog

Primary card:

- Upload Shopify products CSV

Manual-entry accordion:

- SKU count
- Fit-sensitive categories

CSV autofill should derive SKU count and product/category signals where possible.

## Step 3: Policy & Exchanges

Primary card:

- Scan your website

If the merchant scanned in step 1, this step should reuse that URL and let them rescan if needed.

Manual-entry accordion:

- Return window
- Policy clarity
- Exchanges offered
- Exchange incentives

Website scan should identify likely return policy pages and extract evidence from policy text.

## Step 4: Manual Operations

Manual-first fields:

- Return approvals / review style
- Approval volume or workload proxy where available
- Support agents on returns where available
- Policy exceptions or bottlenecks
- Weekly manual returns hours
- Common bottlenecks

This step should explain that the system cannot reliably detect these fields yet and needs the merchant to fill them in.

## Evidence And Answer Resolution

Every auto-filled answer should have evidence.

Evidence should include:

- Source type: `manual`, `website`, `csv`, or `demo`
- Source label
- Confidence: `high`, `medium`, or `low`
- Evidence URL when available
- Evidence snippet when available
- Observed period start/end when derived from dated CSV rows
- Metadata for importer/scanner details

Answer priority:

1. Manual
2. CSV
3. Website scan
4. Demo/fallback

Manual always wins. CSV can replace website-derived suggestions unless a manual answer exists. Conflicts should be visible to the merchant before submit.

## Website Extraction Strategy

Use a strategy pattern.

Default strategy:

- `RulesWebsiteExtractionStrategy`
- Regex and deterministic rule extraction.
- No paid APIs and no LLM dependency.

Future strategy:

- `LlmWebsiteExtractionStrategy`
- Same contract, disabled until an LLM provider is explicitly configured.

Suggested contract:

```php
interface WebsiteExtractionStrategy
{
    public function extract(WebsiteScanResult $scan): ExtractedAssessmentData;
}
```

Resolver config:

```php
'website_extraction' => [
    'strategy' => env('WEBSITE_EXTRACTION_STRATEGY', 'rules'),
]
```

The LLM strategy should exist only as a seam. It should not call an LLM until explicitly configured later.

## Backend Architecture

New durable records:

- `WebsiteScan`
- `AssessmentAnswerEvidence`

New services/contracts:

- `StartWebsiteScanService`
- `WebsiteCrawler`
- `WebsiteExtractionStrategy`
- `RulesWebsiteExtractionStrategy`
- `LlmWebsiteExtractionStrategy`
- `AutofillAssessmentAnswersService`
- `AssessmentAnswerResolver`
- `CsvObservedPeriodService`

Preferred MVP behavior:

- Scan endpoint is synchronous for a small page set and has a conservative timeout.
- Store extracted snippets/evidence, not full raw HTML.
- Auto-apply suggestions only when no manual answer exists.
- Existing answer save endpoint marks manual answers as manual evidence.

## API Surface

Add minimal endpoints:

```text
POST /api/assessments/{assessment}/website-scan
GET /api/assessments/{assessment}/evidence
```

The website scan response should include:

- scan status
- updated suggestions
- evidence grouped by question key
- updated merchant website URL

## CSV Import Integration

Move CSV upload cards into relevant wizard steps, but keep the existing provider-agnostic import framework.

Business & Platform:

- Shopify orders CSV
- Detect observed order period
- Resolve order volume band

Product Catalog:

- Shopify products CSV
- Resolve SKU count band

Policy & Exchanges:

- Website scan is primary for MVP

Manual Operations:

- Manual-first for MVP

Observed-period rules:

- Year: strongest confidence
- Quarter: usable, annualized where needed
- Month: directional
- Under 30 days: warning
- Partial current period: warning

Reports and calculation explanations should show the observed period where imported evidence was used.

## Frontend Architecture

The current wizard should be refactored before deep feature work continues.

Candidate components:

- `WizardShell.vue`
- `WizardProgress.vue`
- `AssistedStep.vue`
- `WebsiteScanCard.vue`
- `CsvUploadCard.vue`
- `EvidencePill.vue`
- `ManualEntryAccordion.vue`
- `QuestionField.vue`

For the first implementation slice, a UI-only four-step grouping can be added without changing backend question keys.

## Implementation Slices

### Slice 1: Planning, Evidence, And Scan Scaffolding

- Move planning documents into `docs/planning/`.
- Add this plan.
- Add `AssessmentAnswerEvidence` model and migration.
- Add `WebsiteScan` model and migration.
- Add website extraction strategy contract plus rules and LLM strategy classes.
- Add scan endpoint that validates/stores URL and runs the rules strategy.
- Add tests for scan endpoint, strategy selection, and evidence persistence.

### Slice 2: Wizard Four-Step Assisted Shell

- Rework wizard navigation to four assisted steps.
- Group existing backend catalog questions under the new steps.
- Add manual-entry accordions around existing questions.
- Keep existing autosave semantics intact.

### Slice 3: CSV In Relevant Tabs

- Extract CSV upload UI into a component.
- Render Shopify orders upload in Business & Platform.
- Render Shopify products upload in Product Catalog.
- Add recommended range copy and period selection UI.
- Keep backend import framework intact.

### Slice 4: Autofill Resolution

- Add answer source/evidence resolution service.
- CSV-derived monthly order volume and SKU count suggestions.
- Website-derived company/platform/policy suggestions.
- Manual override protection.

### Slice 5: Evidence In Reports

- Show source/evidence snippets for answers used in calculations.
- Show CSV observed period in calculation modals.
- Add conflict messaging where useful.

## Testing Strategy

Backend:

- Website scan saves URL and scan record.
- Rules strategy extracts return windows from fixture HTML.
- Rules strategy extracts platform hints.
- LLM strategy is inert unless configured.
- Manual answer overrides website and CSV evidence.
- CSV observed-period detection handles month, quarter, year, partial periods.
- Evidence is returned by API grouped by question key.

Frontend:

- Scan does not run until `Scan site` is clicked.
- Manual accordion expands and saves manual answers.
- CSV cards appear on the intended steps.
- Recommended time-range copy appears.
- Evidence snippets render when suggestions exist.

## Non-Goals For First Slice

- No real Shopify OAuth.
- No LLM provider call.
- No full web crawler.
- No raw HTML storage.
- No simulation of CSV in demo data.
