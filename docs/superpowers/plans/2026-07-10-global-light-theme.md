# Global Light Theme and RAG Tier Colors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-theme the 4 existing frontend pages from dark to light, and switch `AssessmentResults.vue`'s tier colors to a conventional red-orange-yellow-green scale, so the whole app is visually consistent before Milestone 6 (internal workspace) is built fresh in this same theme.

**Architecture:** A mechanical, one-file-at-a-time re-theme. Same layout, same structure, same behavior — only Tailwind color classes change, per a fixed palette-translation table. No new dependencies, no backend changes.

**Tech Stack:** Vue 3 (`<script setup>`), Tailwind CSS. No JS test framework in this project — verification is `npm run build` plus careful class-by-class review, consistent with every prior frontend milestone.

## Global Constraints

- Only color/style classes change. No layout, structure, prop, or behavior changes to any of the 4 files.
- Base palette: page bg `bg-slate-950`→`bg-slate-50`, card bg `bg-white/5`→`bg-white` with `border-slate-200`, primary text `text-white`→`text-slate-900`, secondary text `text-slate-300`/`400`→`text-slate-600`/`500`, brand accent `bg-blue-500`→`bg-blue-600`, accent pills `bg-blue-400/10 border-blue-300/30 text-blue-100`→`bg-blue-50 border-blue-200 text-blue-700`, general borders `border-white/10`→`border-slate-200`.
- Tier colors (`AssessmentResults.vue`'s `TIER_COLORS`): Foundational=red (`#ef4444`), Developing=orange (`#f97316`), Established=yellow (`#eab308`), Advanced=green (`#22c55e`).
- Priority colors (`AssessmentResults.vue`'s `PRIORITY_COLORS`) stay a **distinct** blue-intensity scale — do not reuse the tier red/orange/yellow/green colors for priority.
- `Reports/Show.vue`'s `@media print` stylesheet is removed entirely (dead weight once the page is light by default) — keep only `print:hidden` on the Print button.
- No new npm dependency.

---

## Task 1: Re-theme Welcome.vue

**Files:**
- Modify: `resources/js/Pages/Welcome.vue` (full-file replacement)

**Interfaces:**
- None — this file has no props/logic beyond the existing `appName` prop, unchanged.

- [ ] **Step 1: Replace the file**

Read the current `resources/js/Pages/Welcome.vue` first to confirm it matches. Then replace it entirely:

```vue
<script setup>
defineProps({
    appName: {
        type: String,
        default: 'Merchant Readiness Workspace',
    },
});
</script>

<template>
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <section class="mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center px-6 py-16 sm:px-8 lg:px-10">
            <div class="max-w-3xl">
                <p class="mb-5 inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                    Deployment milestone
                </p>
                <h1 class="text-4xl font-bold tracking-tight sm:text-6xl">
                    Merchant returns readiness, built on Laravel and Inertia.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                    {{ appName }} will help ecommerce merchants assess operational maturity, understand scoring, and share actionable recommendations before a sales conversation.
                </p>
                <a href="/assessment" class="mt-8 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                    Start assessment
                </a>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-blue-600">Milestone 0</p>
                    <p class="mt-2 text-2xl font-semibold">Deploy first</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Render should prove app boot, assets, `/health`, and database connectivity before features continue.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-blue-600">Stack</p>
                    <p class="mt-2 text-2xl font-semibold">Laravel + Vue</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">The app uses Laravel, Inertia, Vue, Tailwind, queues, and PostgreSQL on Render.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-blue-600">Guardrails</p>
                    <p class="mt-2 text-2xl font-semibold">Docs locked</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Architecture, product, design, and execution rules are captured for coding agents.</p>
                </div>
            </div>
        </section>
    </main>
</template>
```

- [ ] **Step 2: Verify**

Run: `npm run build`
Expected: builds successfully with no errors.

Run: `php artisan test`
Expected: PASS, same count as before this task (no backend files touched).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Welcome.vue
git commit -m "Re-theme Welcome page to light"
```

---

## Task 2: Re-theme Wizard.vue

**Files:**
- Modify: `resources/js/Pages/Assessment/Wizard.vue` (full-file replacement)

**Interfaces:**
- None — no changes to `<script setup>` logic, only template classes.

- [ ] **Step 1: Replace the file**

Read the current `resources/js/Pages/Assessment/Wizard.vue` first to confirm it matches. Then replace it entirely:

```vue
<script setup>
import axios from 'axios';
import { computed, ref, watch, watchEffect } from 'vue';
import AssessmentResults from './AssessmentResults.vue';

const props = defineProps({
    catalog: {
        type: Array,
        required: true,
    },
});

const currentSectionIndex = ref(0);
const assessmentId = ref(null);
const answers = ref({});
const status = ref('Start your assessment to save draft answers.');
const errors = ref({});
const submitResult = ref(null);
const submitError = ref(null);

const currentSection = computed(() => props.catalog[currentSectionIndex.value]);
const isLastSection = computed(() => currentSectionIndex.value === props.catalog.length - 1);

watchEffect(() => {
    props.catalog.forEach((section) => {
        section.questions.forEach((question) => {
            if (question.type === 'multiselect' && !Array.isArray(answers.value[question.key])) {
                answers.value[question.key] = [];
            }
        });
    });
});

function questionError(index) {
    const question = currentSection.value.questions[index];
    return errors.value[`answers.${index}.value`]?.[0]
        ?? errors.value[`answers.${index}.question_key`]?.[0]
        ?? errors.value[question.key]?.[0]
        ?? null;
}

function missingSectionLabels() {
    const keys = Object.keys(errors.value);
    const labels = [];

    props.catalog.forEach((section) => {
        const hasMissingQuestion = section.questions.some((question) => keys.includes(question.key));

        if (hasMissingQuestion) {
            labels.push(section.label);
        }
    });

    return labels;
}

watch(currentSectionIndex, () => {
    errors.value = {};
});

async function startAssessment() {
    if (assessmentId.value) {
        return;
    }

    const response = await axios.post('/api/assessments');
    assessmentId.value = response.data.assessment.id;
    status.value = 'Draft started. Answers save section by section.';
}

async function saveSection() {
    await startAssessment();

    errors.value = {};

    const payload = currentSection.value.questions.map((question) => ({
        question_key: question.key,
        value: answers.value[question.key] ?? (question.type === 'multiselect' ? [] : null),
    }));

    try {
        const response = await axios.post(`/api/assessments/${assessmentId.value}/answers`, {
            answers: payload,
        });

        status.value = `Draft saved with ${response.data.assessment.answers_count} answer(s).`;

        if (!isLastSection.value) {
            currentSectionIndex.value += 1;
        }
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
        status.value = 'Check the highlighted answers before continuing.';
    }
}

function previousSection() {
    currentSectionIndex.value = Math.max(0, currentSectionIndex.value - 1);
}

async function submitAssessment() {
    submitError.value = null;

    try {
        const response = await axios.post(`/api/assessments/${assessmentId.value}/submit`);
        submitResult.value = response.data;
    } catch (error) {
        if (error.response?.status === 409) {
            submitError.value = 'This assessment has already been submitted.';
        } else {
            errors.value = error.response?.data?.errors ?? {};

            const sections = missingSectionLabels();
            submitError.value = sections.length
                ? `Missing required answers in: ${sections.join(', ')}.`
                : 'Check the highlighted answers before submitting.';
        }
    }
}
</script>

<template>
    <main class="min-h-screen bg-slate-50 px-6 py-10 text-slate-900 sm:px-8">
        <section class="mx-auto max-w-5xl">
            <div class="mb-10 max-w-3xl">
                <p class="mb-4 inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                    Merchant Readiness Assessment
                </p>
                <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">Evaluate your returns operation.</h1>
                <p class="mt-5 text-lg leading-8 text-slate-600">
                    Complete each section to save a draft assessment, then submit to see your readiness score, breakdown, and recommendations.
                </p>
            </div>

            <template v-if="!submitResult">
                <div class="mb-8 grid gap-3 sm:grid-cols-6">
                    <button
                        v-for="(section, index) in catalog"
                        :key="section.key"
                        type="button"
                        class="rounded-2xl border px-4 py-3 text-left text-sm transition"
                        :class="index === currentSectionIndex ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600'"
                        @click="currentSectionIndex = index"
                    >
                        {{ section.label }}
                    </button>
                </div>

                <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="saveSection">
                    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-sm font-medium text-blue-600">Section {{ currentSectionIndex + 1 }} of {{ catalog.length }}</p>
                            <h2 class="mt-1 text-2xl font-semibold">{{ currentSection.label }}</h2>
                        </div>
                        <p class="text-sm text-slate-500">{{ status }}</p>
                    </div>

                    <div class="space-y-6">
                        <label v-for="(question, questionIndex) in currentSection.questions" :key="question.key" class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-900">
                                {{ question.label }}
                                <span v-if="question.required" class="text-blue-600">*</span>
                            </span>

                            <input
                                v-if="['text', 'email'].includes(question.type)"
                                v-model="answers[question.key]"
                                :type="question.type"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                            >

                            <select
                                v-else-if="question.type === 'select'"
                                v-model="answers[question.key]"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                            >
                                <option :value="null">Choose one</option>
                                <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                            </select>

                            <div v-else-if="question.type === 'multiselect'" class="grid gap-2 sm:grid-cols-2">
                                <label v-for="option in question.options" :key="option" class="flex items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700">
                                    <input v-model="answers[question.key]" type="checkbox" :value="option" class="rounded border-slate-300 bg-white text-blue-600">
                                    {{ option }}
                                </label>
                            </div>

                            <select
                                v-else-if="question.type === 'boolean'"
                                v-model="answers[question.key]"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                            >
                                <option :value="null">Choose one</option>
                                <option :value="true">Yes</option>
                                <option :value="false">No</option>
                            </select>

                            <p v-if="questionError(questionIndex)" class="mt-2 text-sm text-red-600">
                                {{ questionError(questionIndex) }}
                            </p>
                        </label>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-between">
                        <button type="button" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 disabled:opacity-40" :disabled="currentSectionIndex === 0" @click="previousSection">
                            Previous
                        </button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                            {{ isLastSection ? 'Save final draft section' : 'Save and continue' }}
                        </button>
                    </div>
                </form>

                <div v-if="isLastSection" class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-blue-300 bg-blue-50 px-5 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                        @click="submitAssessment"
                    >
                        Submit assessment
                    </button>
                </div>

                <p v-if="submitError" class="mt-3 text-right text-sm text-red-600">{{ submitError }}</p>
            </template>

            <AssessmentResults v-if="submitResult" :result="submitResult" :catalog="catalog" :report-url="submitResult.report.url" />
        </section>
    </main>
</template>
```

- [ ] **Step 2: Verify**

Run: `npm run build`
Expected: builds successfully with no errors.

Run: `php artisan test`
Expected: PASS, same count as before this task.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Assessment/Wizard.vue
git commit -m "Re-theme Wizard page to light"
```

---

## Task 3: Re-theme AssessmentResults.vue and switch tier colors to red/orange/yellow/green

**Files:**
- Modify: `resources/js/Pages/Assessment/AssessmentResults.vue` (full-file replacement)

**Interfaces:**
- Produces: `TIER_COLORS` now maps Foundational/Developing/Established/Advanced to red/orange/yellow/green (`pill`, `bar`, `ring` keys unchanged in shape). `PRIORITY_COLORS` keeps its existing distinct blue-intensity scale, restyled for light backgrounds. No prop or function signature changes — `Wizard.vue` (Task 2) and `Reports/Show.vue` (Task 4) consume this component identically to before.

- [ ] **Step 1: Replace the file**

Read the current `resources/js/Pages/Assessment/AssessmentResults.vue` first to confirm it matches. Then replace it entirely:

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
    reportUrl: {
        type: String,
        default: null,
    },
});

const RING_RADIUS = 52;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

const TIER_COLORS = {
    Foundational: { pill: 'border-red-300 bg-red-50 text-red-700', bar: 'bg-red-500', ring: '#ef4444' },
    Developing: { pill: 'border-orange-300 bg-orange-50 text-orange-700', bar: 'bg-orange-500', ring: '#f97316' },
    Established: { pill: 'border-yellow-300 bg-yellow-50 text-yellow-700', bar: 'bg-yellow-500', ring: '#eab308' },
    Advanced: { pill: 'border-green-300 bg-green-50 text-green-700', bar: 'bg-green-500', ring: '#22c55e' },
};

function tierColors(tier) {
    return TIER_COLORS[tier] ?? TIER_COLORS.Foundational;
}

const overallScore = computed(() => props.result.assessment.overall_score);
const overallTier = computed(() => props.result.assessment.overall_tier);
const ringOffset = computed(() => RING_CIRCUMFERENCE * (1 - overallScore.value / 100));

function sectionLabel(key) {
    const section = props.catalog.find((candidate) => candidate.key === key);
    return section ? section.label : key;
}

const rankedSections = computed(() => Object.entries(props.result.assessment.ranked_sections));

const PRIORITY_COLORS = {
    high: 'border-blue-300 bg-blue-100 text-blue-800',
    medium: 'border-blue-200 bg-blue-50 text-blue-600',
    low: 'border-slate-200 bg-slate-50 text-slate-500',
};

function priorityClasses(priority) {
    return PRIORITY_COLORS[priority] ?? PRIORITY_COLORS.low;
}

const recommendations = computed(() => props.result.recommendations ?? []);
</script>

<template>
    <div class="mt-8 space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-start">
            <svg viewBox="0 0 120 120" class="h-32 w-32 shrink-0">
                <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="10" />
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
                <text x="60" y="56" text-anchor="middle" class="fill-slate-900" style="font-size: 28px; font-weight: 700;">{{ overallScore }}</text>
                <text x="60" y="76" text-anchor="middle" class="fill-slate-500" style="font-size: 12px;">out of 100</text>
            </svg>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">Assessment submitted</h2>
                <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-medium" :class="tierColors(overallTier).pill">
                    {{ overallTier }}
                </span>
            </div>
        </div>

        <div v-if="reportUrl" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
            Your shareable report:
            <a :href="reportUrl" class="font-semibold underline decoration-blue-400 underline-offset-2 hover:text-blue-900">{{ reportUrl }}</a>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-slate-900">Score breakdown</h3>
            <p class="mt-1 text-sm text-slate-500">Ranked by opportunity — weakest area first.</p>
            <ul class="mt-4 space-y-3">
                <li v-for="[key, section] in rankedSections" :key="key">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-900">{{ sectionLabel(key) }}</span>
                        <span class="text-slate-600">{{ section.score }}/100</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full" :class="tierColors(section.tier).bar" :style="{ width: `${section.score}%` }" />
                    </div>
                </li>
            </ul>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-slate-900">Capability mapping</h3>
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

        <div>
            <h3 class="text-lg font-semibold text-slate-900">Recommended actions</h3>
            <p v-if="recommendations.length === 0" class="mt-4 text-sm text-green-600">
                No urgent opportunities identified — nice work.
            </p>
            <div v-else class="mt-4 grid gap-4 sm:grid-cols-2">
                <div v-for="(recommendation, index) in recommendations" :key="index" class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-blue-600">{{ recommendation.category }}</span>
                        <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :class="priorityClasses(recommendation.priority)">
                            {{ recommendation.priority }}
                        </span>
                    </div>
                    <h4 class="mt-2 font-semibold text-slate-900">{{ recommendation.title }}</h4>
                    <p class="mt-1 text-sm text-slate-600">{{ recommendation.description }}</p>
                    <p class="mt-2 text-sm text-slate-500">Expected impact: {{ recommendation.expected_impact }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Verify**

Run: `npm run build`
Expected: builds successfully with no errors.

Run: `php artisan test`
Expected: PASS, same count as before this task.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Assessment/AssessmentResults.vue
git commit -m "Re-theme AssessmentResults to light and switch tier colors to red/orange/yellow/green"
```

---

## Task 4: Re-theme Reports/Show.vue and remove the now-dead print stylesheet

**Files:**
- Modify: `resources/js/Pages/Reports/Show.vue` (full-file replacement)

**Interfaces:**
- Consumes: `AssessmentResults` (Task 3) — same `:result`/`:catalog` usage as before, unchanged.

- [ ] **Step 1: Replace the file**

Read the current `resources/js/Pages/Reports/Show.vue` first to confirm it matches. Then replace it entirely:

```vue
<script setup>
import { computed } from 'vue';
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

    if (props.report.merchant.monthly_order_volume) {
        parts.push(`${props.report.merchant.monthly_order_volume} orders/month`);
    }
    if (props.report.merchant.sku_count) {
        parts.push(`${props.report.merchant.sku_count} SKUs`);
    }
    if (props.report.merchant.ecommerce_platform) {
        parts.push(props.report.merchant.ecommerce_platform);
    }

    return parts.join(' · ');
});

const preparedOn = computed(() => new Date(props.report.published_at).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}));

function printReport() {
    window.print();
}
</script>

<template>
    <main class="min-h-screen bg-slate-50 px-6 py-10 text-slate-900 sm:px-8">
        <section class="mx-auto max-w-5xl">
            <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="mb-2 inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                        Merchant Readiness Report
                    </p>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ report.merchant.company_name }}</h1>
                    <p v-if="profileLine" class="mt-2 text-slate-500">{{ profileLine }}</p>
                    <p class="mt-1 text-sm text-slate-500">Prepared on {{ preparedOn }}</p>
                </div>
                <button
                    type="button"
                    class="print:hidden rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    @click="printReport"
                >
                    Print report
                </button>
            </div>

            <AssessmentResults :result="result" :catalog="catalog" />
        </section>
    </main>
</template>
```

Note: the `<style>` block with the `@media print` overrides is intentionally removed entirely — it existed only to force the dark theme into black-on-white for printing, which is no longer needed now that the page is light by default.

- [ ] **Step 2: Verify**

Run: `npm run build`
Expected: builds successfully with no errors.

Run: `php artisan test`
Expected: PASS, same count as before this task (`ReportAccessTest`'s assertions only check HTTP status/JSON, not styling, so they're unaffected).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Reports/Show.vue
git commit -m "Re-theme report page to light and remove dead print stylesheet"
```

---

## Final verification

- [ ] Confirm all 4 tasks are complete and committed.
- [ ] Confirm `php artisan test` passes with the same count as before this plan (no backend changes anywhere in this plan).
- [ ] Confirm CI is green on the branch before merging, per the "Deployment must be proven before feature development continues" guardrail in CLAUDE.md.
- [ ] No automated visual verification was possible in this environment (no browser available) — flag to the user that a manual visual pass across all 4 re-themed pages is still owed before starting Milestone 6.
