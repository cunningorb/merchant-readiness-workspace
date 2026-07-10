# Milestone 4: Results Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the wizard's minimal, unstyled post-submit block with a proper results dashboard (score ring, ranked section breakdown, capability mapping, recommendation cards) built entirely from data the `POST /api/assessments/{id}/submit` endpoint already returns.

**Architecture:** A new `AssessmentResults.vue` component owns all results presentation; `Wizard.vue` is reduced back to owning only the wizard flow and passes its existing `submitResult` and `catalog` data through as props. No backend changes, no new dependencies — the score ring and bars are hand-rolled SVG/CSS.

**Tech Stack:** Vue 3 (`<script setup>`), Tailwind CSS, Inertia — matching the existing `Wizard.vev` conventions. No JS test framework exists in this project (no vitest/jest); verification is manual/browser, same as Milestone 3's wizard UI.

## Global Constraints

- No new route, no persistent results URL, no ownership/session token — the results view is client-side/inline only, per the approved design spec.
- No new npm dependency — score ring and bars are hand-rolled SVG/CSS.
- No invented data: only `overall_score`, `overall_tier`, `section_scores`, `ranked_sections`, and `recommendations` (with fields `title`, `description`, `category`, `priority`, `expected_impact`) as already returned by the submit endpoint. Do not add peer benchmarking, dollar/percentage impact estimates, effort/time tags, or narrative summary text — those are tracked in GitHub issue #3.
- Score breakdown and "Top opportunities" are the same list, ordered by `ranked_sections` (already weakest-first from the API) — do not build a separate, second "top opportunities" list.
- Capability mapping is a separate, visually distinct block from the numeric breakdown (badges only: label + tier, no score/bar).
- Tier color palette: Foundational=rose, Developing=amber, Established=blue, Advanced=emerald. Priority color palette: high=rose, medium=amber, low=slate.
- Verify mobile responsiveness manually before considering this milestone's STOP gate satisfied (per `docs/03_Codex_Execution_Plan.md`).

---

## Task 1: AssessmentResults.vue skeleton — score ring and tier pill

**Files:**
- Create: `resources/js/Pages/Assessment/AssessmentResults.vue`
- Modify: `resources/js/Pages/Assessment/Wizard.vue:224-242` (replace the inline result block) and `resources/js/Pages/Assessment/Wizard.vue:1-3` (add the component import)

**Interfaces:**
- Produces: `AssessmentResults` component with props `result: Object` (shape `{ assessment: { overall_score, overall_tier, section_scores, ranked_sections }, recommendations: [] }`) and `catalog: Array` (shape `[{ key, label, questions: [{ key, label, ... }] }]`, identical to what `Wizard.vue` already receives from Inertia). Later tasks (2-4) add to this same file's `<script setup>` and `<template>`.

- [ ] **Step 1: Create the component file**

`resources/js/Pages/Assessment/AssessmentResults.vue`:
```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
    catalog: {
        type: Array,
        required: true,
    },
});

const RING_RADIUS = 52;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

const TIER_COLORS = {
    Foundational: { pill: 'border-rose-400/40 bg-rose-500/20 text-rose-200', bar: 'bg-rose-400', ring: '#fb7185' },
    Developing: { pill: 'border-amber-400/40 bg-amber-500/20 text-amber-200', bar: 'bg-amber-400', ring: '#fbbf24' },
    Established: { pill: 'border-blue-400/40 bg-blue-500/20 text-blue-200', bar: 'bg-blue-400', ring: '#60a5fa' },
    Advanced: { pill: 'border-emerald-400/40 bg-emerald-500/20 text-emerald-200', bar: 'bg-emerald-400', ring: '#34d399' },
};

function tierColors(tier) {
    return TIER_COLORS[tier] ?? TIER_COLORS.Foundational;
}

const overallScore = computed(() => props.result.assessment.overall_score);
const overallTier = computed(() => props.result.assessment.overall_tier);
const ringOffset = computed(() => RING_CIRCUMFERENCE * (1 - overallScore.value / 100));
</script>

<template>
    <div class="mt-8 space-y-8 rounded-3xl border border-white/10 bg-white/5 p-6">
        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-start">
            <svg viewBox="0 0 120 120" class="h-32 w-32 shrink-0">
                <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="10" />
                <circle
                    cx="60"
                    cy="60"
                    r="52"
                    fill="none"
                    :stroke="tierColors(overallTier).ring"
                    stroke-width="10"
                    stroke-linecap="round"
                    :stroke-dasharray="RING_CIRCUMFERENCE"
                    :stroke-dashoffset="ringOffset"
                    transform="rotate(-90 60 60)"
                />
                <text x="60" y="56" text-anchor="middle" class="fill-white" style="font-size: 28px; font-weight: 700;">{{ overallScore }}</text>
                <text x="60" y="76" text-anchor="middle" class="fill-slate-400" style="font-size: 12px;">out of 100</text>
            </svg>

            <div>
                <h2 class="text-xl font-semibold text-white">Assessment submitted</h2>
                <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-medium" :class="tierColors(overallTier).pill">
                    {{ overallTier }}
                </span>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Wire the component into Wizard.vue — add the import**

Read `resources/js/Pages/Assessment/Wizard.vue` first to confirm it still matches. Then change the top of `<script setup>`:

Old:
```js
<script setup>
import axios from 'axios';
import { computed, ref, watch, watchEffect } from 'vue';
```

New:
```js
<script setup>
import axios from 'axios';
import { computed, ref, watch, watchEffect } from 'vue';
import AssessmentResults from './AssessmentResults.vue';
```

- [ ] **Step 3: Wire the component into Wizard.vue — replace the inline result block**

Replace this entire block (currently lines 224-242 of `Wizard.vue`):

Old:
```html
            <div v-if="submitResult" class="mt-8 rounded-3xl border border-white/10 bg-white/5 p-6">
                <h2 class="text-xl font-semibold">Assessment submitted</h2>
                <p class="mt-2 text-slate-200">
                    Overall score: {{ submitResult.assessment.overall_score }}/100 ({{ submitResult.assessment.overall_tier }})
                </p>
                <ul class="mt-4 space-y-1 text-sm text-slate-300">
                    <li v-for="(section, key) in submitResult.assessment.section_scores" :key="key">
                        {{ key }}: {{ section.score }}/100 ({{ section.tier }})
                    </li>
                </ul>
                <div class="mt-6 space-y-4">
                    <div v-for="(recommendation, index) in submitResult.recommendations" :key="index" class="rounded-2xl border border-white/10 p-4">
                        <p class="text-xs uppercase tracking-wide text-blue-200">{{ recommendation.category }} - {{ recommendation.priority }}</p>
                        <h3 class="mt-1 font-semibold">{{ recommendation.title }}</h3>
                        <p class="mt-1 text-sm text-slate-300">{{ recommendation.description }}</p>
                        <p class="mt-2 text-sm text-slate-400">Expected impact: {{ recommendation.expected_impact }}</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
```

New:
```html
            <AssessmentResults v-if="submitResult" :result="submitResult" :catalog="catalog" />
        </section>
    </main>
</template>
```

- [ ] **Step 4: Manually verify in the browser**

Herd serves the app at `http://merchant-readiness-workspace.test`. Steps:
1. Navigate to `http://merchant-readiness-workspace.test/assessment`.
2. Fill in and save every section through to Platform (last section), using weak answers to get a low score (e.g. 14-day return window, "Not documented" policy, exchanges not offered, 50+ weekly hours, 2+ bottlenecks, "Email/spreadsheets" tooling).
3. Click "Submit assessment".
4. Confirm: the ring's colored arc fills proportionally to the score (a low score like 9-20 should show a small arc), the number in the center matches `overall_score`, and the tier pill shows the correct tier name in the correct color (a low score should show the rose "Foundational" pill).

Expected: ring and tier pill render correctly with real data; no console errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Assessment/AssessmentResults.vue resources/js/Pages/Assessment/Wizard.vue
git commit -m "Add AssessmentResults component with score ring and tier pill"
```

---

## Task 2: Score breakdown / top opportunities

**Files:**
- Modify: `resources/js/Pages/Assessment/AssessmentResults.vue`

**Interfaces:**
- Consumes: `result.assessment.ranked_sections` (shape `{ [sectionKey]: { score: number, tier: string }, ... }`, already ordered ascending by score by the API) and `catalog` (Task 1's props).
- Produces: a `sectionLabel(key)` helper and a `rankedSections` computed, both usable by later tasks (3-4) in the same file.

- [ ] **Step 1: Add the `sectionLabel` helper and `rankedSections` computed**

In `resources/js/Pages/Assessment/AssessmentResults.vue`, in `<script setup>`, add after the existing `ringOffset` computed:

```js
function sectionLabel(key) {
    const section = props.catalog.find((candidate) => candidate.key === key);
    return section ? section.label : key;
}

const rankedSections = computed(() => Object.entries(props.result.assessment.ranked_sections));
```

- [ ] **Step 2: Add the score breakdown block to the template**

Add this new `<div>` right after the closing `</div>` of the ring/tier block (i.e., directly before the outer `</div>` that closes the component's root `<div class="mt-8 space-y-8 ...">`):

```html
        <div>
            <h3 class="text-lg font-semibold text-white">Score breakdown</h3>
            <p class="mt-1 text-sm text-slate-400">Ranked by opportunity — weakest area first.</p>
            <ul class="mt-4 space-y-3">
                <li v-for="[key, section] in rankedSections" :key="key">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-100">{{ sectionLabel(key) }}</span>
                        <span class="text-slate-300">{{ section.score }}/100</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full" :class="tierColors(section.tier).bar" :style="{ width: `${section.score}%` }" />
                    </div>
                </li>
            </ul>
        </div>
```

- [ ] **Step 3: Manually verify in the browser**

Using the same weak-answers submission from Task 1 (re-run the wizard flow if needed):
1. Confirm the breakdown lists all 4 scored sections (Return Policy, Manual Operations, Exchanges, Platform).
2. Confirm the order is ascending by score — the weakest section (lowest score) appears first.
3. Confirm each bar's width visually matches its score (a 0-score section should show a near-empty bar; a 30-score section should show a partially-filled bar).

Expected: breakdown renders correctly, weakest-first, bar widths match scores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Assessment/AssessmentResults.vue
git commit -m "Add score breakdown ranked by opportunity"
```

---

## Task 3: Capability mapping

**Files:**
- Modify: `resources/js/Pages/Assessment/AssessmentResults.vue`

**Interfaces:**
- Consumes: `rankedSections` and `sectionLabel()` (Task 2), `tierColors()` (Task 1).

- [ ] **Step 1: Add the capability mapping block to the template**

Add this new `<div>` directly after the score breakdown `<div>` added in Task 2 (still before the component's root-closing `</div>`):

```html
        <div>
            <h3 class="text-lg font-semibold text-white">Capability mapping</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="[key, section] in rankedSections"
                    :key="key"
                    class="rounded-2xl border px-4 py-3 text-center"
                    :class="tierColors(section.tier).pill"
                >
                    <p class="text-sm font-medium">{{ sectionLabel(key) }}</p>
                    <p class="mt-1 text-xs uppercase tracking-wide">{{ section.tier }}</p>
                </div>
            </div>
        </div>
```

- [ ] **Step 2: Manually verify in the browser**

Using the same weak-answers submission:
1. Confirm a row/grid of 4 badges appears below the score breakdown, one per scored section.
2. Confirm each badge shows the section label and its tier, colored per the tier palette (a "Foundational" section should show the rose color, matching the tier pill and breakdown bar color used for that same section elsewhere on the page).
3. Confirm this block reads as visually distinct from the numeric breakdown above it (badges only, no bars or scores).

Expected: capability mapping renders as 4 distinct badges with correct labels/tiers/colors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Assessment/AssessmentResults.vue
git commit -m "Add capability mapping badges"
```

---

## Task 4: Recommendation cards and empty state

**Files:**
- Modify: `resources/js/Pages/Assessment/AssessmentResults.vue`

**Interfaces:**
- Consumes: `result.recommendations` (array of `{ title, description, category, priority, expected_impact }`, already priority-sorted by the API).
- Produces: a `recommendations` computed and `priorityClasses()` helper, used only within this task's own template block.

- [ ] **Step 1: Add the `PRIORITY_COLORS` map, `priorityClasses` helper, and `recommendations` computed**

In `resources/js/Pages/Assessment/AssessmentResults.vue`, in `<script setup>`, add after `rankedSections`:

```js
const PRIORITY_COLORS = {
    high: 'border-rose-400/40 bg-rose-500/20 text-rose-200',
    medium: 'border-amber-400/40 bg-amber-500/20 text-amber-200',
    low: 'border-slate-400/40 bg-slate-500/20 text-slate-200',
};

function priorityClasses(priority) {
    return PRIORITY_COLORS[priority] ?? PRIORITY_COLORS.low;
}

const recommendations = computed(() => props.result.recommendations ?? []);
```

- [ ] **Step 2: Add the recommended actions block to the template**

Add this new `<div>` directly after the capability mapping `<div>` added in Task 3 (still before the component's root-closing `</div>`):

```html
        <div>
            <h3 class="text-lg font-semibold text-white">Recommended actions</h3>
            <p v-if="recommendations.length === 0" class="mt-4 text-sm text-emerald-300">
                No urgent opportunities identified — nice work.
            </p>
            <div v-else class="mt-4 grid gap-4 sm:grid-cols-2">
                <div v-for="(recommendation, index) in recommendations" :key="index" class="rounded-2xl border border-white/10 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-blue-200">{{ recommendation.category }}</span>
                        <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :class="priorityClasses(recommendation.priority)">
                            {{ recommendation.priority }}
                        </span>
                    </div>
                    <h4 class="mt-2 font-semibold text-white">{{ recommendation.title }}</h4>
                    <p class="mt-1 text-sm text-slate-300">{{ recommendation.description }}</p>
                    <p class="mt-2 text-sm text-slate-400">Expected impact: {{ recommendation.expected_impact }}</p>
                </div>
            </div>
        </div>
```

- [ ] **Step 3: Manually verify in the browser — weak-answers case**

Using the same weak-answers submission:
1. Confirm recommendation cards render (should be 6, per the answers used across Tasks 1-3), each showing category, priority badge (colored per palette), title, description, and expected impact text.
2. Confirm cards are sorted with "high" priority cards before "medium" before "low".

- [ ] **Step 4: Manually verify in the browser — perfect-score case**

1. Start a new assessment (reload `http://merchant-readiness-workspace.test/assessment`) and fill every section with the best possible answer for each question (e.g. "More than 60 days" return window, "Contextual policy by product/order" clarity, exchanges offered = Yes with all 4 incentives selected, "Under 5" weekly hours, no bottlenecks selected, "Custom automation" tooling).
2. Submit.
3. Confirm the overall score is 100, tier is "Advanced", and the "Recommended actions" section shows "No urgent opportunities identified — nice work." instead of an empty gap.

Expected: both scenarios render correctly — cards with real data for the weak case, the empty-state message for the perfect-score case.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Assessment/AssessmentResults.vue
git commit -m "Add recommendation cards with empty state"
```

---

## Task 5: Responsive/mobile verification

**Files:**
- Modify: `resources/js/Pages/Assessment/AssessmentResults.vue` (only if the manual check in Step 1 finds a layout problem)

**Interfaces:**
- None — this task only verifies and, if needed, adjusts Tailwind breakpoint classes already used in Tasks 1-4. No new props, computed values, or functions.

- [ ] **Step 1: Manually verify mobile layout in the browser**

Using browser devtools device emulation (or by narrowing the browser window to roughly 375px wide, a common mobile viewport width):
1. Reload the results view from a completed submission (weak-answers case is fine).
2. Confirm the score ring and tier pill stack in a single column (the `flex-col sm:flex-row` on the ring/tier container should already do this — verify it visually).
3. Confirm the score breakdown list remains fully readable with no horizontal overflow or text clipping.
4. Confirm the capability mapping grid drops from 4 columns to 2 columns (`sm:grid-cols-2 lg:grid-cols-4` should already do this at mobile width — verify visually) and that the badges aren't cramped.
5. Confirm the recommendation cards drop from a 2-column grid to a single column (`sm:grid-cols-2` should already do this — verify visually) and remain fully readable.
6. Confirm no element causes horizontal page scroll at mobile width.

- [ ] **Step 2: Fix any layout issue found**

If Step 1 finds a problem (e.g. text overflow, a badge grid that doesn't collapse, horizontal scroll), fix it by adjusting the specific Tailwind classes on the affected element in `resources/js/Pages/Assessment/AssessmentResults.vue` — for example, if the capability grid is still cramped at exactly 375px, change `sm:grid-cols-2` to `grid-cols-1 sm:grid-cols-2` so it's explicitly single-column below the `sm:` breakpoint rather than relying on the default `grid` behavior. Re-verify visually after any change.

- [ ] **Step 3: Full end-to-end walkthrough**

1. Complete a fresh wizard run with weak/mixed answers end to end (start → all 6 sections saved → submit).
2. Confirm every piece of the dashboard renders together correctly: ring, tier pill, ranked breakdown, capability badges, recommendation cards.
3. Confirm the full `php artisan test` suite still passes (no backend changes were made in this plan, but this confirms nothing was accidentally broken):

Run: `php artisan test`
Expected: all tests passing (same count as before this plan — no backend files were touched).

- [ ] **Step 4: Commit (only if Step 2 made changes)**

```bash
git add resources/js/Pages/Assessment/AssessmentResults.vue
git commit -m "Fix mobile layout in results dashboard"
```

If Step 2 required no changes, skip this commit — there's nothing to commit.

---

## Final verification

- [ ] Confirm all 5 tasks are complete and committed (Task 5 may have no commit if no mobile fix was needed).
- [ ] Confirm the Milestone 4 STOP gate ("verify mobile") from `docs/03_Codex_Execution_Plan.md` is satisfied by Task 5's walkthrough.
- [ ] Confirm CI is green on the branch before merging, per the "Deployment must be proven before feature development continues" guardrail in CLAUDE.md.
