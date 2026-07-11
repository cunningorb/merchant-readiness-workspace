# Milestone 7: Demo Data and Reset Command

Date: 2026-07-11

## Context

Per `docs/03_Codex_Execution_Plan.md`, Milestone 7 replaces the current bare demo data (one admin user, one unanswered draft assessment) with three realistic, fully-submitted merchants whose answers produce genuinely different scores/tiers/recommendations, plus a command to regenerate that data on demand.

This matters now specifically because Milestone 6 (internal workspace) just shipped: the prospect list's search/sort/tier display and the review page's Talking Points panel have nothing worth demonstrating against a single bare draft assessment. It also matters because `DatabaseSeeder` was just wired to run automatically on every production deploy (`php artisan db:seed --force` in the Docker `CMD`, added to fix a login blocker) and made idempotent — this milestone's demo data must fit that same idempotent-on-every-restart model, and must never conflict with real prospect data that now exists in production from actual public-wizard submissions.

## Schema change: `is_demo` on merchants

A migration adds `is_demo` (`boolean`, `default(false)`) to the `merchants` table. This is the sole mechanism for distinguishing demo content from real prospect submissions — every piece of code that creates or deletes demo data operates through this flag, never through name-matching or environment checks. All child tables (`assessments`, `assessment_answers`, `recommendations`, `reports`) already `cascadeOnDelete()` on their parent foreign key, so deleting a demo `Merchant` row cleanly removes its entire assessment/answers/recommendations/report chain with no additional cleanup code.

## Demo data content and architecture

A single class, `Database\Seeders\DemoMerchantsSeeder`, is the one source of truth for demo content. It is called from two places — `DatabaseSeeder::run()` (guarded, so a fresh database gets demo data automatically on first deploy) and the new `demo:reset` command (unconditional, after clearing existing demo rows) — so the merchant/answer content is defined exactly once.

Rather than hand-inserting `Merchant`/`Assessment`/`Recommendation` rows with pre-computed scores, `DemoMerchantsSeeder` drives the exact same services a real merchant's wizard submission uses:

```php
$assessment = $createService->createAnonymousDraft();       // CreateAssessmentService
$assessment->merchant->update([
    'is_demo' => true,
    'contact_name' => $profile['contact_name'],
    'website' => $profile['website'],
]);
$saveService->save($assessment, $profile['answers']);       // SaveAssessmentAnswersService — includes
                                                              // business.company_name/contact_email, which
                                                              // syncs the merchant's identity fields automatically
$submitService->submit($assessment);                        // SubmitAssessmentService — scores, generates
                                                              // recommendations, creates the report
```

This means scoring, recommendation generation, and report creation for demo merchants are produced by the real `ReadinessScoringService`, `RecommendationEngine`, and `ReportBuilderService` — not duplicated or hand-computed. If scoring rules change later, regenerating demo data automatically reflects the change correctly.

The whole seeder is guarded by `if (! Merchant::where('is_demo', true)->exists())`, matching the idempotency pattern already established for the admin user in `DatabaseSeeder` — safe to call on every container start without creating duplicates.

### Three profiles

Chosen to span the tier spectrum widely, with exact answers verified against the current scoring config (`config/scoring.php` weights: return_policy 30%, manual_operations 30%, exchanges 20%, platform 20%; per-answer point values from each `App\Services\Scoring\Questions\*Scorer`):

| Merchant | Business profile | Return policy | Manual ops | Exchanges | Platform | Overall | Tier |
|---|---|---|---|---|---|---|---|
| **Thistle & Bloom Apparel** | Small apparel shop, 1,000-10,000 orders/mo, WooCommerce | 0 | 0 | 0 | 0 | **0** | Foundational |
| **Northline Outdoor Supply** | Mid-size outdoor gear, 10,001-50,000 orders/mo, Shopify | 67 | 74 | 75 | 67 | **71** | Established |
| **Vantage Home Goods** | Large home goods retailer, 50,000+ orders/mo, custom platform | 100 | 100 | 100 | 100 | **100** | Advanced |

Each profile's answer set includes every required question (so `SubmitAssessmentService::submit()`'s completeness check passes) plus the optional multiselect questions where they affect scoring (`exchanges.incentives`, `manual_operations.common_bottlenecks`) or add realism (`catalog.fit_sensitive_categories`). Exact answer values are specified in the implementation plan, not this document — this table is the target outcome the plan's answer sets must produce.

## Reset command

`php artisan demo:reset`:

1. Deletes every `Merchant::where('is_demo', true)` row (cascades through the full chain).
2. Calls `DemoMerchantsSeeder` again — since step 1 cleared all demo rows, the seeder's existence guard passes and it recreates all three fresh.

No confirmation prompt: the command can only ever affect `is_demo` rows, never real prospect data, so it is safe to run at any time in any environment — including directly against the live Render deployment via a one-off job if the demo data needs a refresh, with no risk to real submissions.

## Testing

- `DemoMerchantsSeederTest`: running it creates exactly 3 `is_demo` merchants, each `submitted` with the exact `overall_score`/`overall_tier` from the table above, each with a generated `Report`; running it a second time is a no-op (idempotent).
- `DemoResetCommandTest`: running `demo:reset` against an empty database creates the 3 demo merchants; running it again after they already exist still results in exactly 3 (the old rows are gone, not duplicated); a real (non-demo) merchant created alongside is completely untouched by the reset.
- `DatabaseSeederTest` (existing): update its assertions, which currently expect exactly 1 `Merchant`/`Assessment` from the old bare demo block, to expect 3 demo merchants once `DatabaseSeeder` calls `DemoMerchantsSeeder` instead.
- Full `php artisan test` run after, to confirm no regressions elsewhere — this milestone touches shared seeding infrastructure that other tests may incidentally depend on.
