# Wizard Autosave Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the assessment wizard's manual "Save and continue" button with silent, debounced autosave, so section navigation becomes a plain "Next" button while every answer change is persisted automatically and safely in the background.

**Architecture:** A single rewrite of `Wizard.vue`'s save logic: a debounced, single-flight, deferred-retry save queue keyed by the section index captured at queue time (not read at execution time), gated against firing during initial mount, with navigation actions triggering a non-blocking immediate flush and submit triggering a full blocking flush-and-drain before it fires.

**Tech Stack:** Vue 3 `<script setup>`, axios (already a dependency), no new dependency.

## Global Constraints

- No backend change of any kind — this is entirely a rewrite of `resources/js/Pages/Assessment/Wizard.vue` against the existing `POST /api/assessments`, `POST /api/assessments/{id}/answers`, and `POST /api/assessments/{id}/submit` endpoints.
- No automated JS test suite exists in this project — verification is `npm run build` succeeding, a final `php artisan test` run confirming zero backend regression (expect the same 155 tests), and an explicit line-by-line trace of each of the seven correctness hazards below against the actual written code. This is not optional given there is no test harness to catch a regression in any of them.
- Never more than one `POST /api/assessments/{id}/answers` request in flight at a time.
- Never drop a change: if an edit happens while a save is in flight, exactly one more save must run after the in-flight one completes, capturing the state at the time it runs.
- A save started for section N must always send section N's questions, even if the user has since navigated to section M — the payload is built from the section index captured when the save was queued, not from whatever section is displayed when the request actually fires.
- A save's response (success or error) must only update visible error/status state if the section it was saving for is still the currently-displayed section when the response arrives — otherwise discard it silently (the save itself still happened; only the UI reaction is scoped).
- The autosave watcher must not fire during initial component mount (the pre-existing `watchEffect` that seeds every multiselect answer to `[]` must not be misread as a user edit) — gate the watcher behind a flag that only becomes true in `onMounted`.
- `submitAssessment()` must fully flush and await the entire pending/in-flight save chain — including any cascaded deferred re-save — before firing the submit request.
- Navigation (`Next`, `Previous`, section-picker buttons) must never wait on a save — trigger an immediate, non-blocking flush of any pending debounced save for the section being left, then navigate immediately regardless of that flush's outcome.

---

## Task 1: Replace manual save with autosave in the wizard

**Files:**
- Modify: `resources/js/Pages/Assessment/Wizard.vue`

**Interfaces:**
- Consumes: nothing new — same three API endpoints the wizard already calls.
- Produces: no prop changes. `AssessmentResults` is still rendered identically once `submitResult` is set. Nothing outside this file depends on any function name introduced here.

- [ ] **Step 1: Replace the entire `<script setup>` block**

Read `resources/js/Pages/Assessment/Wizard.vue` first to confirm it matches the current state (it should have `isSaving`/`isSubmitting` refs and the mutual-disable fix from the prior commit), then replace the whole `<script setup>...</script>` block with:

```vue
<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch, watchEffect } from 'vue';
import AssessmentResults from './AssessmentResults.vue';

const AUTOSAVE_DEBOUNCE_MS = 600;

const props = defineProps({
    catalog: {
        type: Array,
        required: true,
    },
});

const currentSectionIndex = ref(0);
const assessmentId = ref(null);
const answers = ref({});
const status = ref('');
const errors = ref({});
const submitResult = ref(null);
const submitError = ref(null);
const isSubmitting = ref(false);
const isMounted = ref(false);

let debounceTimer = null;
let savePromise = null;
let pendingSectionIndex = null;

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

onMounted(() => {
    isMounted.value = true;
});

watch(answers, () => {
    if (!isMounted.value) {
        return;
    }

    queueSave();
}, { deep: true });

watch(currentSectionIndex, () => {
    errors.value = {};
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

async function startAssessment() {
    if (assessmentId.value) {
        return;
    }

    const response = await axios.post('/api/assessments');
    assessmentId.value = response.data.assessment.id;
}

function queueSave() {
    pendingSectionIndex = currentSectionIndex.value;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSave, AUTOSAVE_DEBOUNCE_MS);
}

function flushSaveImmediately() {
    if (pendingSectionIndex !== null) {
        clearTimeout(debounceTimer);
        runSave();
    }
}

function runSave() {
    if (savePromise) {
        return;
    }

    const sectionIndex = pendingSectionIndex;
    pendingSectionIndex = null;

    savePromise = performSave(sectionIndex).finally(() => {
        savePromise = null;

        if (pendingSectionIndex !== null) {
            runSave();
        }
    });
}

async function performSave(sectionIndex) {
    await startAssessment();

    const section = props.catalog[sectionIndex];
    const payload = section.questions.map((question) => ({
        question_key: question.key,
        value: answers.value[question.key] ?? (question.type === 'multiselect' ? [] : null),
    }));

    try {
        await axios.post(`/api/assessments/${assessmentId.value}/answers`, {
            answers: payload,
        });

        if (currentSectionIndex.value === sectionIndex) {
            errors.value = {};
            status.value = 'All changes saved.';
        }
    } catch (error) {
        if (currentSectionIndex.value === sectionIndex) {
            errors.value = error.response?.data?.errors ?? {};
            status.value = 'Check the highlighted answers.';
        }
    }
}

async function flushPendingSave() {
    clearTimeout(debounceTimer);

    if (pendingSectionIndex !== null && !savePromise) {
        runSave();
    }

    while (savePromise) {
        await savePromise;
    }
}

function selectSection(index) {
    flushSaveImmediately();
    currentSectionIndex.value = index;
}

function nextSection() {
    flushSaveImmediately();

    if (!isLastSection.value) {
        currentSectionIndex.value += 1;
    }
}

function previousSection() {
    flushSaveImmediately();
    currentSectionIndex.value = Math.max(0, currentSectionIndex.value - 1);
}

async function submitAssessment() {
    submitError.value = null;
    isSubmitting.value = true;

    try {
        await startAssessment();
        await flushPendingSave();

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
    } finally {
        isSubmitting.value = false;
    }
}
</script>
```

- [ ] **Step 2: Replace the entire `<template>` block**

Replace the whole `<template>...</template>` block with:

```vue
<template>
    <main class="min-h-screen bg-slate-50 px-6 py-10 text-slate-900 sm:px-8">
        <section class="mx-auto max-w-5xl">
            <div class="mb-10 max-w-3xl">
                <p class="mb-4 inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                    Merchant Readiness Assessment
                </p>
                <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">Evaluate your returns operation.</h1>
                <p class="mt-5 text-lg leading-8 text-slate-600">
                    Complete each section, then submit to see your readiness score, breakdown, and recommendations. Your answers save automatically as you go.
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
                        :aria-current="index === currentSectionIndex ? 'step' : undefined"
                        @click="selectSection(index)"
                    >
                        {{ section.label }}
                    </button>
                </div>

                <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="nextSection">
                    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-sm font-medium text-blue-600">Section {{ currentSectionIndex + 1 }} of {{ catalog.length }}</p>
                            <h2 class="mt-1 text-2xl font-semibold">{{ currentSection.label }}</h2>
                        </div>
                        <p class="text-sm text-slate-500" role="status" aria-live="polite">{{ status }}</p>
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
                                :aria-required="question.required"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                            >

                            <select
                                v-else-if="question.type === 'select'"
                                v-model="answers[question.key]"
                                :aria-required="question.required"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                            >
                                <option :value="null">Choose one</option>
                                <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                            </select>

                            <div v-else-if="question.type === 'multiselect'" class="grid gap-2 sm:grid-cols-2">
                                <label v-for="option in question.options" :key="option" class="flex items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700">
                                    <input v-model="answers[question.key]" type="checkbox" :value="option" class="rounded border-slate-300 text-blue-600">
                                    {{ option }}
                                </label>
                            </div>

                            <select
                                v-else-if="question.type === 'boolean'"
                                v-model="answers[question.key]"
                                :aria-required="question.required"
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
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                            :disabled="currentSectionIndex === 0"
                            @click="previousSection"
                        >
                            Previous
                        </button>
                        <button
                            v-if="!isLastSection"
                            type="submit"
                            class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700"
                        >
                            Next
                        </button>
                    </div>
                </form>

                <div v-if="isLastSection" class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-blue-300 bg-blue-50 px-5 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 disabled:opacity-60"
                        :disabled="isSubmitting"
                        :aria-busy="isSubmitting"
                        @click="submitAssessment"
                    >
                        {{ isSubmitting ? 'Submitting…' : 'Submit assessment' }}
                    </button>
                </div>

                <p v-if="submitError" class="mt-3 text-right text-sm text-red-600" role="alert">{{ submitError }}</p>
            </template>

            <AssessmentResults v-if="submitResult" :result="submitResult" :catalog="catalog" :report-url="submitResult.report.url" />
        </section>
    </main>
</template>
```

- [ ] **Step 3: Trace all seven correctness hazards against the written code**

Before building or committing, read back through the file you just wrote and confirm each of these explicitly (this substitutes for the automated test coverage this project doesn't have for frontend code):

1. **Debounce collapses rapid changes.** `queueSave()` calls `clearTimeout(debounceTimer)` before setting a new one — confirm a burst of changes within `AUTOSAVE_DEBOUNCE_MS` only ever results in the *last* `setTimeout` actually firing.
2. **Single-flight, never dropped.** `runSave()`'s first line is `if (savePromise) { return; }`. Confirm the `.finally()` callback checks `pendingSectionIndex !== null` and calls `runSave()` again if so — this is what turns "a change arrived mid-flight" into "exactly one more save runs after," never zero and never more than one concurrent request.
3. **Section captured at queue time.** `queueSave()` sets `pendingSectionIndex = currentSectionIndex.value` — confirm `performSave(sectionIndex)` receives that captured value as a parameter and uses it (`props.catalog[sectionIndex]`) to build the payload, never reading `currentSectionIndex.value` directly inside `performSave`.
4. **Stale response scoping.** Confirm both the success and catch branches inside `performSave` check `if (currentSectionIndex.value === sectionIndex)` before touching `errors.value`/`status.value` — a response for a section the user has already left must not overwrite what's currently displayed.
5. **No phantom autosave on mount.** Confirm `isMounted` starts `false`, is only set `true` inside `onMounted`, and the `watch(answers, ...)` callback's first line checks `if (!isMounted.value) { return; }` — the pre-existing `watchEffect` that seeds multiselect arrays to `[]` runs during setup, before `onMounted` fires, so that seeding must never reach `queueSave()`.
6. **Submit drains the whole chain.** Confirm `submitAssessment()` calls `await flushPendingSave()` before the actual submit POST, and that `flushPendingSave()` both starts a save immediately if one was pending-but-not-yet-fired (`if (pendingSectionIndex !== null && !savePromise) { runSave(); }`) and then loops `while (savePromise) { await savePromise; }` — this loop correctly drains a cascaded re-save too, because `runSave()`'s `.finally()` callback (which reassigns `savePromise`, possibly to a new promise) always completes before the promise it's attached to resolves, so by the time an `await savePromise` in the loop returns, `savePromise` already reflects whatever the `.finally()` callback left it as.
7. **Navigation never blocks on a save.** Confirm `selectSection`, `nextSection`, and `previousSection` all call `flushSaveImmediately()` (fire-and-forget, not awaited) and then update `currentSectionIndex` unconditionally — a slow or failing save must never prevent the user from moving to another section.

Also confirm the edge case fix: `submitAssessment()` calls `await startAssessment()` before `flushPendingSave()`, so clicking Submit without ever having triggered an autosave (e.g. a user who never actually typed anything) still gets a valid `assessmentId` before the submit request is built — without this, the URL would be built with `assessmentId.value` still `null`.

- [ ] **Step 4: Verify the build**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 5: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS (155 tests — this task touches no PHP file, so the count is unchanged).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Assessment/Wizard.vue
git commit -m "Replace manual draft save with silent debounced autosave"
```

---

## Final verification

- [ ] Confirm the task is complete and committed.
- [ ] Confirm CI is green on the branch before merging, per the "Deployment must be proven before feature development continues" guardrail in CLAUDE.md.
- [ ] Per explicit instruction, after the final review passes, proceed directly through `finishing-a-development-branch` choosing the merge-locally option without pausing, then push to `origin/main`, then verify the Render deployment goes live and `/health` returns 200.
