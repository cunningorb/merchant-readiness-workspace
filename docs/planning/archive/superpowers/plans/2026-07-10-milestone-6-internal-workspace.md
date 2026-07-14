# Milestone 6: Internal Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the blank Breeze `/dashboard` placeholder with a real internal workspace — a searchable, sortable prospect list of submitted assessments, and an assessment review page with the existing score/breakdown UI plus a rule-based Talking Points panel.

**Architecture:** A new `WorkspaceController` (thin, two actions) replaces the placeholder `/dashboard` route. `index()` queries submitted assessments with search/sort; `show()` reuses `ReportBuilderService::buildPayload()` (built in Milestone 5) for the score/recommendation payload and augments it with merchant contact fields and a top-3-by-priority Talking Points list. Two new Vue pages render inside the existing (re-themed) `AuthenticatedLayout.vue`, reusing `AssessmentResults.vue` unmodified.

**Tech Stack:** Laravel (PHP 8.2), Eloquent, PHPUnit, Vue 3 + Inertia, Tailwind CSS. No new dependency, no schema changes.

## Global Constraints

- No new migration, model, or npm/composer dependency — the prospect list and review page are read paths over existing `Merchant`/`Assessment`/`Recommendation`/`Report` data.
- The prospect list shows one row per submitted `Assessment` (not collapsed by merchant) — a merchant with multiple assessments (e.g. per-franchise) appears as multiple distinct rows, disambiguated by contact name + submitted date, per the approved spec.
- The list is scoped to `status = 'submitted'` assessments only — drafts never appear.
- `/dashboard` keeps its existing route name and URL so Breeze's post-login redirect keeps working; it is now backed by `WorkspaceController::index` instead of a closure.
- Open to any authenticated + verified user (`auth`, `verified` middleware) — the `User.role` column is not wired into any authorization check this milestone.
- Talking Points are the assessment's own top 3 `Recommendation` records (by priority: high → medium → low), fields used verbatim — no new narrative generation, no dollar-impact estimation. This intentionally does not implement GitHub issue #3's deferred narrative-summary/dollar-impact items.
- Sort column is validated against an explicit allow-list (`company`, `tier`, `submitted_at`) before use in a query — never interpolate a raw request value into `orderBy()`.
- `AssessmentResults.vue` is not touched by this milestone — reused exactly as Milestone 5 left it. `Workspace/Index.vue` defines its own small tier-pill color map rather than importing from it.

---

## Task 1: Re-theme the authenticated workspace chrome

The shared Breeze layout and nav components still use Breeze's default gray/indigo palette — untouched by the earlier light-theme project because nothing meaningful rendered inside them yet. This milestone is the first real content behind that chrome, so bring it in line with the rest of the app's slate/blue palette before building on top of it.

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/Components/NavLink.vue`
- Modify: `resources/js/Components/ResponsiveNavLink.vue`
- Modify: `resources/js/Components/DropdownLink.vue`

**Interfaces:**
- Consumes: nothing new.
- Produces: no prop or behavior changes to any of these components — purely a class-attribute recolor. Later tasks render `Workspace/Index.vue` and `Workspace/Show.vue` inside `AuthenticatedLayout.vue` unchanged otherwise.

- [ ] **Step 1: Re-theme `AuthenticatedLayout.vue`**

Read the file first to confirm it matches. Replace every occurrence exactly as follows (each is a distinct line/attribute in the file):

Old:
```html
        <div class="min-h-screen bg-gray-100">
            <nav
                class="border-b border-gray-100 bg-white"
            >
```

New:
```html
        <div class="min-h-screen bg-slate-50">
            <nav
                class="border-b border-slate-200 bg-white"
            >
```

Old:
```html
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
```

New:
```html
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-slate-500 transition duration-150 ease-in-out hover:text-slate-700 focus:outline-none"
```

Old:
```html
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
```

New:
```html
                                class="inline-flex items-center justify-center rounded-md p-2 text-slate-400 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-500 focus:bg-slate-100 focus:text-slate-500 focus:outline-none"
```

Old:
```html
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
```

New:
```html
                    <div
                        class="border-t border-slate-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-slate-900"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-slate-500">
```

- [ ] **Step 2: Re-theme `NavLink.vue`**

Read the file first to confirm it matches. Replace the `classes` computed:

Old:
```js
const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out',
);
```

New:
```js
const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-blue-500 text-sm font-medium leading-5 text-slate-900 focus:outline-none focus:border-blue-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-slate-500 hover:text-slate-700 hover:border-slate-300 focus:outline-none focus:text-slate-700 focus:border-slate-300 transition duration-150 ease-in-out',
);
```

- [ ] **Step 3: Re-theme `ResponsiveNavLink.vue`**

Read the file first to confirm it matches. Replace the `classes` computed:

Old:
```js
const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-indigo-400 text-start text-base font-medium text-indigo-700 bg-indigo-50 focus:outline-none focus:text-indigo-800 focus:bg-indigo-100 focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out',
);
```

New:
```js
const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-blue-500 text-start text-base font-medium text-blue-700 bg-blue-50 focus:outline-none focus:text-blue-800 focus:bg-blue-100 focus:border-blue-700 transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 hover:border-slate-300 focus:outline-none focus:text-slate-800 focus:bg-slate-50 focus:border-slate-300 transition duration-150 ease-in-out',
);
```

- [ ] **Step 4: Re-theme `DropdownLink.vue`**

Read the file first to confirm it matches. Replace the class attribute:

Old:
```html
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
```

New:
```html
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-700 transition duration-150 ease-in-out hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
```

- [ ] **Step 5: Verify with a build**

There is no automated JS test suite in this project. Run `npm run build` to confirm it compiles.

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 6: Run the backend test suite**

Run: `php artisan test`
Expected: PASS (all existing tests — this task touches no PHP or route files).

- [ ] **Step 7: Commit**

```bash
git add resources/js/Layouts/AuthenticatedLayout.vue resources/js/Components/NavLink.vue resources/js/Components/ResponsiveNavLink.vue resources/js/Components/DropdownLink.vue
git commit -m "Re-theme authenticated workspace chrome to match the app's light theme"
```

---

## Task 2: Prospect list

**Files:**
- Create: `app/Http/Controllers/WorkspaceController.php`
- Modify: `routes/web.php` (replace the placeholder `/dashboard` closure)
- Delete: `resources/js/Pages/Dashboard.vue` (no longer referenced by any route)
- Create: `resources/js/Pages/Workspace/Index.vue`
- Test: `tests/Feature/WorkspaceTest.php`

**Interfaces:**
- Consumes: `Assessment` model (`merchant()` relation, `status`, `overall_score`, `overall_tier`, `submitted_at`), `Merchant` model (`company_name`, `contact_name`).
- Produces: named route `dashboard` now resolves to `WorkspaceController::index`. `WorkspaceController` class exists for Task 3 to add a `show()` method to.

`AssessmentResults.vue` is not touched by this task — it stays exactly as Milestone 5 left it, per the spec's "reused unmodified" requirement. `Workspace/Index.vue` needs the same four-tier color mapping for its tier pill, but duplicating four short lines is preferable to modifying a component that's already been through three review cycles just to share them (YAGNI — this is not a growing abstraction, it's a fixed, four-entry map unlikely to change independently in the two places).

- [ ] **Step 1: Write the failing controller/route tests**

`tests/Feature/WorkspaceTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_lists_only_submitted_assessments(): void
    {
        $user = User::factory()->create();
        $submitted = Assessment::factory()->create([
            'status' => 'submitted',
            'submitted_at' => now(),
            'overall_score' => 72,
            'overall_tier' => 'Established',
        ]);
        Assessment::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Workspace/Index')
            ->has('assessments.data', 1)
            ->where('assessments.data.0.id', $submitted->id)
        );
    }

    public function test_search_filters_by_company_name(): void
    {
        $user = User::factory()->create();
        $match = Merchant::factory()->create(['company_name' => 'Acme Corp']);
        $other = Merchant::factory()->create(['company_name' => 'Globex Inc']);
        Assessment::factory()->for($match)->create(['status' => 'submitted', 'submitted_at' => now()]);
        Assessment::factory()->for($other)->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?search=Acme');

        $response->assertInertia(fn ($page) => $page
            ->has('assessments.data', 1)
            ->where('assessments.data.0.merchant.company_name', 'Acme Corp')
        );
    }

    public function test_search_filters_by_contact_name(): void
    {
        $user = User::factory()->create();
        $match = Merchant::factory()->create(['contact_name' => 'Jane Doe']);
        Assessment::factory()->for($match)->create(['status' => 'submitted', 'submitted_at' => now()]);
        $other = Merchant::factory()->create(['contact_name' => 'Raj Patel']);
        Assessment::factory()->for($other)->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?search=Jane');

        $response->assertInertia(fn ($page) => $page
            ->has('assessments.data', 1)
            ->where('assessments.data.0.merchant.contact_name', 'Jane Doe')
        );
    }

    public function test_sorts_by_submitted_date(): void
    {
        $user = User::factory()->create();
        $older = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now()->subDays(5)]);
        $newer = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?sort=submitted_at&direction=asc');

        $response->assertInertia(fn ($page) => $page
            ->where('assessments.data.0.id', $older->id)
            ->where('assessments.data.1.id', $newer->id)
        );
    }

    public function test_sorts_by_tier_score(): void
    {
        $user = User::factory()->create();
        $low = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now(), 'overall_score' => 20, 'overall_tier' => 'Foundational']);
        $high = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now(), 'overall_score' => 90, 'overall_tier' => 'Advanced']);

        $response = $this->actingAs($user)->get('/dashboard?sort=tier&direction=asc');

        $response->assertInertia(fn ($page) => $page
            ->where('assessments.data.0.id', $low->id)
            ->where('assessments.data.1.id', $high->id)
        );
    }

    public function test_sorts_by_company_name(): void
    {
        $user = User::factory()->create();
        $zCorp = Merchant::factory()->create(['company_name' => 'Zeta Corp']);
        $aCorp = Merchant::factory()->create(['company_name' => 'Acme Corp']);
        $z = Assessment::factory()->for($zCorp)->create(['status' => 'submitted', 'submitted_at' => now()]);
        $a = Assessment::factory()->for($aCorp)->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?sort=company&direction=asc');

        $response->assertInertia(fn ($page) => $page
            ->where('assessments.data.0.id', $a->id)
            ->where('assessments.data.1.id', $z->id)
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/WorkspaceTest.php`
Expected: FAIL — `/dashboard` still renders the `Dashboard` placeholder, so the guest-redirect test fails (route has no `auth` gate rejection to check yet — actually it does via existing middleware, so this one may pass already) and every other test fails on `->component('Workspace/Index')` since that page doesn't exist and the controller doesn't exist.

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/WorkspaceController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = in_array($request->query('sort'), ['company', 'tier', 'submitted_at'], true)
            ? $request->query('sort')
            : 'submitted_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = Assessment::query()
            ->with('merchant')
            ->where('status', 'submitted')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->query('search').'%';

                $query->whereHas('merchant', function ($merchantQuery) use ($search) {
                    $merchantQuery->where('company_name', 'like', $search)
                        ->orWhere('contact_name', 'like', $search);
                });
            });

        match ($sort) {
            'company' => $query->join('merchants', 'merchants.id', '=', 'assessments.merchant_id')
                ->orderBy('merchants.company_name', $direction)
                ->select('assessments.*'),
            'tier' => $query->orderBy('overall_score', $direction),
            default => $query->orderBy('submitted_at', $direction),
        };

        $assessments = $query->paginate(15)->withQueryString();

        return Inertia::render('Workspace/Index', [
            'assessments' => $assessments,
            'filters' => [
                'search' => $request->query('search'),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }
}
```

- [ ] **Step 4: Wire the route and delete the placeholder page**

Read `routes/web.php` first to confirm it matches. Replace:

Old:
```php
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
```

New:
```php
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
```

Old:
```php
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```

New:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
});
```

Delete `resources/js/Pages/Dashboard.vue` — no route references it after this change.

- [ ] **Step 5: Create the prospect list page**

`resources/js/Pages/Workspace/Index.vue`:
```vue
<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    assessments: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const TIER_COLORS = {
    Foundational: { pill: 'border-red-300 bg-red-50 text-red-700' },
    Developing: { pill: 'border-orange-300 bg-orange-50 text-orange-700' },
    Established: { pill: 'border-yellow-300 bg-yellow-50 text-yellow-700' },
    Advanced: { pill: 'border-green-300 bg-green-50 text-green-700' },
};

function tierColors(tier) {
    return TIER_COLORS[tier] ?? TIER_COLORS.Foundational;
}

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get(route('dashboard'), {
        search: search.value || undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
    }, { preserveState: true, replace: true });
}

function sortBy(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';

    router.get(route('dashboard'), {
        search: props.filters.search || undefined,
        sort: column,
        direction,
    }, { preserveState: true, replace: true });
}

function sortIndicator(column) {
    if (props.filters.sort !== column) {
        return '';
    }

    return props.filters.direction === 'asc' ? '▲' : '▼';
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Prospects" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-900">Prospects</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form class="mb-6 flex gap-2" @submit.prevent="applySearch">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by company or contact name"
                            class="w-full max-w-sm rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                        >
                        <button
                            type="submit"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                            Search
                        </button>
                    </form>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-500">
                                <th class="cursor-pointer select-none py-2 pr-4 font-medium" @click="sortBy('company')">
                                    Company / Contact {{ sortIndicator('company') }}
                                </th>
                                <th class="cursor-pointer select-none py-2 pr-4 font-medium" @click="sortBy('tier')">
                                    Tier / Score {{ sortIndicator('tier') }}
                                </th>
                                <th class="cursor-pointer select-none py-2 pr-4 font-medium" @click="sortBy('submitted_at')">
                                    Submitted {{ sortIndicator('submitted_at') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="assessments.data.length === 0">
                                <td colspan="3" class="py-6 text-center text-slate-500">No submitted assessments match.</td>
                            </tr>
                            <tr
                                v-for="assessment in assessments.data"
                                :key="assessment.id"
                                class="border-b border-slate-100 last:border-0 hover:bg-slate-50"
                            >
                                <td class="py-3 pr-4">
                                    <Link :href="route('workspace.assessments.show', assessment.id)" class="font-medium text-slate-900 hover:text-blue-600">
                                        {{ assessment.merchant.company_name }}
                                    </Link>
                                    <p class="text-xs text-slate-500">{{ assessment.merchant.contact_name }}</p>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="tierColors(assessment.overall_tier).pill">
                                        {{ assessment.overall_tier }}
                                    </span>
                                    <span class="ml-2 text-slate-500">{{ assessment.overall_score }}/100</span>
                                </td>
                                <td class="py-3 pr-4 text-slate-600">{{ formatDate(assessment.submitted_at) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="assessments.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                        <Link
                            v-for="link in assessments.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="rounded-lg px-3 py-1 text-sm"
                            :class="[
                                link.active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                                link.url ? '' : 'pointer-events-none opacity-40',
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

Note: `route('workspace.assessments.show', ...)` is used here even though that route is added in Task 3 — this is fine, Ziggy resolves route names at runtime from the currently-generated route list, and Task 3 adds it before this page is exercised end-to-end. Task 2's own tests never click this link (they hit `/dashboard` directly), so this doesn't break Task 2's test run.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/WorkspaceTest.php`
Expected: PASS (7 tests)

- [ ] **Step 7: Verify the frontend still builds**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 8: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS (all tests, including the 7 new ones — no other test references the deleted `Dashboard.vue` page or the old closure route).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/WorkspaceController.php routes/web.php resources/js/Pages/Workspace/Index.vue tests/Feature/WorkspaceTest.php
git rm resources/js/Pages/Dashboard.vue
git commit -m "Add prospect list workspace page"
```

---

## Task 3: Assessment review page

**Files:**
- Modify: `app/Http/Controllers/WorkspaceController.php` (add `show()`)
- Modify: `routes/web.php` (add the review route)
- Create: `resources/js/Pages/Workspace/Show.vue`
- Modify: `tests/Feature/WorkspaceTest.php` (add review-page tests)

**Interfaces:**
- Consumes: `App\Services\ReportBuilderService::buildPayload()` (Milestone 5, `app/Services/ReportBuilderService.php`), `App\Services\AssessmentQuestionCatalog::sections()` (existing), `AssessmentResults.vue` (existing, unmodified).
- Produces: named route `workspace.assessments.show`, resolving `GET /dashboard/assessments/{assessment}`. `Workspace/Index.vue` (Task 2) already links to this route name.

- [ ] **Step 1: Write the failing tests**

Read `tests/Feature/WorkspaceTest.php` first to confirm it matches Task 2's version, then add these methods (and the two new `use` imports) to the class:

Add to the `use` block at the top of the file:
```php
use App\Models\Recommendation;
use App\Models\Report;
```

Add these test methods to the `WorkspaceTest` class:
```php
    public function test_shows_assessment_review_with_merchant_and_talking_points(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->create([
            'company_name' => 'Acme Corp',
            'contact_name' => 'Jane Doe',
            'contact_email' => 'jane@acme.com',
            'website' => 'acme.com',
        ]);
        $assessment = Assessment::factory()->for($merchant)->create([
            'status' => 'submitted',
            'submitted_at' => now(),
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => ['return_policy' => ['score' => 72, 'tier' => 'Established']],
        ]);
        Report::factory()->for($assessment)->create(['published_at' => now()]);

        Recommendation::factory()->for($assessment)->create(['priority' => 'low', 'title' => 'Low priority item']);
        Recommendation::factory()->for($assessment)->create(['priority' => 'high', 'title' => 'High priority item']);
        Recommendation::factory()->for($assessment)->create(['priority' => 'medium', 'title' => 'Medium priority item']);
        Recommendation::factory()->for($assessment)->create(['priority' => 'high', 'title' => 'Second high priority item']);

        $response = $this->actingAs($user)->get("/dashboard/assessments/{$assessment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Workspace/Show')
            ->where('report.merchant.company_name', 'Acme Corp')
            ->where('report.merchant.contact_name', 'Jane Doe')
            ->where('report.merchant.contact_email', 'jane@acme.com')
            ->where('report.merchant.website', 'acme.com')
            ->where('report.assessment.overall_score', 72)
            ->has('report.talking_points', 3)
            ->where('report.talking_points.0.title', 'High priority item')
            ->where('report.talking_points.1.title', 'Second high priority item')
            ->where('report.talking_points.2.title', 'Medium priority item')
        );
    }

    public function test_show_404s_for_unknown_assessment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard/assessments/does-not-exist');

        $response->assertNotFound();
    }

    public function test_show_404s_for_draft_assessment(): void
    {
        $user = User::factory()->create();
        $assessment = Assessment::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->get("/dashboard/assessments/{$assessment->id}");

        $response->assertNotFound();
    }

    public function test_show_requires_authentication(): void
    {
        $merchant = Merchant::factory()->create();
        $assessment = Assessment::factory()->for($merchant)->create(['status' => 'submitted', 'submitted_at' => now()]);
        Report::factory()->for($assessment)->create(['published_at' => now()]);

        $response = $this->get("/dashboard/assessments/{$assessment->id}");

        $response->assertRedirect('/login');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/WorkspaceTest.php`
Expected: FAIL on the four new tests — the route doesn't exist yet (404s where 200 is expected, and the "draft 404" test currently 404s for the wrong reason — no route at all — so it may spuriously pass; that's fine, it will be re-verified as a true guard-based 404 once Step 3 lands).

- [ ] **Step 3: Add `show()` to the controller**

Read `app/Http/Controllers/WorkspaceController.php` first to confirm it matches Task 2's version, then add the imports and method:

Old:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
```

New:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Recommendation;
use App\Services\AssessmentQuestionCatalog;
use App\Services\ReportBuilderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
```

Add this method to the end of the class, directly after `index()`'s closing brace:
```php

    public function show(Assessment $assessment, AssessmentQuestionCatalog $catalog, ReportBuilderService $service): Response
    {
        abort_if($assessment->status !== 'submitted', 404);

        $assessment->loadMissing(['merchant', 'recommendations', 'report']);

        $payload = $service->buildPayload($assessment->report);
        $payload['merchant']['contact_name'] = $assessment->merchant->contact_name;
        $payload['merchant']['contact_email'] = $assessment->merchant->contact_email;
        $payload['merchant']['website'] = $assessment->merchant->website;
        $payload['submitted_at'] = $assessment->submitted_at;
        $payload['talking_points'] = $assessment->recommendations
            ->sortBy('id')
            ->sortBy(fn (Recommendation $recommendation) => match ($recommendation->priority) {
                'high' => 0,
                'medium' => 1,
                'low' => 2,
                default => 3,
            })
            ->take(3)
            ->values()
            ->map(fn (Recommendation $recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'expected_impact' => $recommendation->expected_impact,
            ])
            ->all();

        return Inertia::render('Workspace/Show', [
            'report' => $payload,
            'catalog' => $catalog->sections(),
        ]);
    }
```

- [ ] **Step 4: Add the route**

Read `routes/web.php` first to confirm it matches Task 2's version, then replace:

Old:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
});
```

New:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/assessments/{assessment}', [WorkspaceController::class, 'show'])->name('workspace.assessments.show');
});
```

- [ ] **Step 5: Create the review page**

`resources/js/Pages/Workspace/Show.vue`:
```vue
<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AssessmentResults from '../Assessment/AssessmentResults.vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    catalog: {
        type: Array,
        required: true,
    },
});

const result = computed(() => ({
    assessment: props.report.assessment,
    recommendations: props.report.recommendations,
}));

const profileLine = computed(() => {
    const parts = [];

    if (props.report.merchant.contact_name) {
        parts.push(props.report.merchant.contact_name);
    }
    if (props.report.merchant.contact_email) {
        parts.push(props.report.merchant.contact_email);
    }
    if (props.report.merchant.website) {
        parts.push(props.report.merchant.website);
    }

    return parts.join(' · ');
});

const submittedOn = computed(() => new Date(props.report.submitted_at).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}));
</script>

<template>
    <Head :title="report.merchant.company_name" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('dashboard')" class="text-sm font-medium text-blue-600 hover:text-blue-700">&larr; Back to prospects</Link>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ report.merchant.company_name }}</h1>
                    <p v-if="profileLine" class="mt-1 text-slate-600">{{ profileLine }}</p>
                    <p class="mt-1 text-sm text-slate-500">Submitted {{ submittedOn }}</p>
                </div>

                <AssessmentResults :result="result" :catalog="catalog" />

                <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Talking Points</h3>
                    <ol class="mt-4 space-y-4">
                        <li v-for="(point, index) in report.talking_points" :key="index" class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">{{ index + 1 }}</span>
                            <div>
                                <p class="font-medium text-slate-900">{{ point.title }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ point.description }}</p>
                                <p class="mt-1 text-sm text-slate-500">Expected impact: {{ point.expected_impact }}</p>
                            </div>
                        </li>
                    </ol>
                    <p v-if="report.talking_points.length === 0" class="mt-4 text-sm text-slate-500">No recommendations to highlight.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/WorkspaceTest.php`
Expected: PASS (11 tests)

- [ ] **Step 7: Verify the frontend builds**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 8: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS (all tests).

- [ ] **Step 9: Manually verify in the browser**

Herd serves the app at `http://merchant-readiness-workspace.test`. Steps:
1. Log in with `admin@merchant-readiness.test` / `password`.
2. Confirm `/dashboard` shows the prospect list (nav bar now slate/blue, not Breeze gray).
3. Submit a fresh assessment via `/assessment` in another tab/session, then refresh the prospect list and confirm the new row appears with the correct tier pill color, score, and submitted date.
4. Use the search box to find it by company name, then by contact name.
5. Click each column header and confirm sort order changes, including toggling ascending/descending on a second click.
6. Click a row and confirm the review page shows the same score ring/breakdown/capability mapping as the merchant's own results view, plus the merchant header and a Talking Points panel listing at most 3 items ordered by priority.
7. Click "Back to prospects" and confirm it returns to the list.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/WorkspaceController.php routes/web.php resources/js/Pages/Workspace/Show.vue tests/Feature/WorkspaceTest.php
git commit -m "Add assessment review page with talking points"
```

---

## Final verification

- [ ] Confirm all 3 tasks are complete and committed.
- [ ] Confirm the Milestone 6 STOP gate ("verify authentication") is satisfied by Task 3's manual walkthrough (Step 9) and the `test_guests_are_redirected_to_login`/`test_show_requires_authentication` tests.
- [ ] Confirm CI is green on the branch before merging, per the "Deployment must be proven before feature development continues" guardrail in CLAUDE.md.
