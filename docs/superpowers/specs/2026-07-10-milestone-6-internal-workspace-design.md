# Milestone 6: Internal Workspace

Date: 2026-07-10

## Context

Milestones 1-5 built the public-facing product: anonymous assessment wizard, scoring/recommendation engines, results dashboard, and a shareable public report. Per `docs/03_Codex_Execution_Plan.md`, Milestone 6 builds the internal workspace: a prospect list, assessment review, search, filtering, and talking points, replacing the current blank Breeze placeholder at `/dashboard`.

The app was recently re-themed to a consistent light theme (Welcome, Wizard, AssessmentResults, Reports/Show) with red/orange/yellow/green tier colors. This milestone builds fresh in that same theme rather than migrating anything.

GitHub issue #3 tracks deferred design-mockup features (narrative executive summary, dollar-impact quantification, peer benchmarking, etc.) that are explicitly out of scope here — see the Talking Points section below for how this spec avoids reintroducing them.

## Scope

In scope: a prospect list page and an assessment review (detail) page, both behind existing Laravel Breeze auth, reusing existing models (`Merchant`, `Assessment`, `Recommendation`) and the existing `AssessmentResults.vue` component.

Out of scope: any new schema/migration, role-based authorization beyond "authenticated," narrative/AI-generated sales copy, dollar-impact estimation, CRM pipeline features (all tracked in issue #3), draft/in-progress assessments (list is submitted-only).

## Data & Routing

No schema changes. A new `WorkspaceController` (thin, per project convention — no business logic beyond query composition) replaces the current inline `/dashboard` closure route:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/assessments/{assessment}', [WorkspaceController::class, 'show'])->name('workspace.assessments.show');
});
```

`/dashboard` keeps its existing name and URL so Breeze's post-login `redirect()->intended(route('dashboard', ...))` and any existing nav links keep working — only the rendered page changes, from the placeholder to the prospect list. Open to any authenticated + verified user; the `User.role` column is not wired into any authorization check for this milestone.

### `WorkspaceController::index()`

- Queries `Assessment::where('status', 'submitted')`, eager-loads `merchant`.
- Accepts `?search=` — a single string matched with `LIKE '%...%'` against `merchant.company_name` OR `merchant.contact_name`.
- Accepts `?sort=` (one of `company`, `tier`, `submitted_at`) and `?direction=` (`asc`|`desc`), validated with `$request->string('sort')->whenIn([...])` so arbitrary columns can't be injected; defaults to `submitted_at` descending. Sorting by `tier` orders by `overall_score` (tiers are score bands, so score order is the meaningful order).
- Paginates (Laravel's standard `paginate()`), returned via Inertia to `Workspace/Index.vue` with company/contact name, tier, score, submitted date per row, plus the current search/sort state for the UI to reflect back.

### `WorkspaceController::show(Assessment $assessment)`

- Loads the assessment with `merchant`, `recommendations`, and `answers`.
- 404s (via route-model binding) if the assessment doesn't exist. No additional guard for draft-status assessments — the list only ever links to submitted ones, so this path is only reached for real reviewable assessments.
- Builds the same result payload shape `ReportBuilderService` already produces (assessment scores/tiers/breakdown + recommendations), reusing that service so `AssessmentResults.vue` needs no changes.
- Adds `talkingPoints`: the assessment's `recommendations` sorted by priority (`high` → `medium` → `low`, matching the ranking `AssessmentResults.vue`'s `PRIORITY_COLORS` already uses) and truncated to the top 3, each exposing `title`, `description`, `expected_impact` verbatim from the existing `Recommendation` record — no new copy, computation, or narrative generation.
- Returned via Inertia to `Workspace/Show.vue` with the merchant profile (company name, contact name, contact email, website, submitted date), the results payload, and `talkingPoints`.

## Pages

- `resources/js/Pages/Workspace/Index.vue` — prospect list. A search input bound to `?search=`, a table with sortable column headers (Company/Contact, Tier + Score, Submitted) that toggle `?sort=`/`?direction=` via Inertia visits, standard pagination controls, each row linking to `workspace.assessments.show`. Light theme, consistent with the rest of the app (white cards, `border-slate-200`, `text-slate-900`/`600`, tier pill colors matching `AssessmentResults.vue`'s `TIER_COLORS`).
- `resources/js/Pages/Workspace/Show.vue` — assessment review. A "back to prospects" link, a header with company name, contact name/email, website, and submitted date, then `<AssessmentResults>` reused unmodified, then a "Talking Points" card listing the top 3 recommendations (title, description, expected_impact) as a simple numbered list — no new visual language beyond what `AssessmentResults.vue` already establishes.

Both pages render inside the existing `AuthenticatedLayout.vue` (Breeze's light-themed shell with top nav) — no new layout component.

## Talking Points: explicitly bounded

The design mockup's "talking points" are persuasive sales narration synthesized from raw metrics (e.g., "They approve ~310 returns per week by hand — lead with the time-savings story"). That is narrative generation and overlaps with GitHub issue #3's deferred narrative-summary and dollar-impact-quantification items.

This milestone's Talking Points are instead the assessment's own top 3 `Recommendation` records, verbatim, ranked by existing priority. Zero new computation, zero generated prose — fully consistent with the project's rule-based/transparent scoring guardrail, and it doesn't quietly pull issue #3 scope into this milestone.

## Testing

Feature tests for `WorkspaceController` (`tests/Feature/WorkspaceTest.php` or similar):

- Unauthenticated request to `/dashboard` redirects to login.
- Authenticated request to `/dashboard` lists only `submitted` assessments; a `draft` assessment for the same merchant does not appear.
- `?search=` filters rows by company name and separately by contact name.
- `?sort=company|tier|submitted_at` with `?direction=asc|desc` changes row order accordingly.
- `show()` returns 404 for a non-existent assessment ID.
- `show()` response includes the merchant header fields, the same results payload shape as the public report, and exactly the top 3 recommendations as `talkingPoints`, ordered `high` → `medium` → `low`.

Run the narrowest new test file first, then the full `php artisan test` suite before considering the milestone complete, per project convention. As with every prior frontend milestone, no live browser is available in this environment during implementation — a manual visual pass across both pages is owed after this lands, before Milestone 7.
