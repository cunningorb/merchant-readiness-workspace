<script setup>
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch, watchEffect } from 'vue';
import AssessmentResults from './AssessmentResults.vue';

const AUTOSAVE_DEBOUNCE_MS = 600;
const IMPORT_POLL_MS = 1500;

// Statuses at which an import has stopped moving on its own; polling ends here.
const TERMINAL_IMPORT_STATUSES = ['completed', 'completed_with_warnings', 'failed', 'cancelled'];

// The three CSV upload cards. Keys are the exact data_type values the backend
// validates against (ImportCoordinator::DATA_TYPE_*).
const CSV_DATA_TYPES = [
    { key: 'catalog', label: 'Products', hint: 'Product and variant export' },
    { key: 'orders_returns', label: 'Orders & returns', hint: 'Orders and refunds export' },
    { key: 'inventory_locations', label: 'Inventory & locations', hint: 'Stock levels by location export' },
];

// Demo scenarios use Task 4's exact scenario keys (DemoScenarios::SCENARIOS).
const DEMO_SCENARIOS = [
    { key: 'apparel', label: 'Apparel growth brand' },
    { key: 'footwear', label: 'Footwear multi-location brand' },
    { key: 'home_goods', label: 'Home-goods small merchant' },
];

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

// Explicit three-phase model. 'results' is reached only as a side effect of a
// successful submitAssessment() (see the submitResult watcher below), so this
// task never sets it directly.
const currentPhase = ref('questions');

// Import-step state. All of this is intentionally isolated from the draft state
// above (answers / assessmentId / currentSectionIndex): nothing here ever
// mutates those, so an import failure can never corrupt saved progress.
const importMode = ref(null); // null | 'csv' | 'demo'
const csvFiles = ref(freshCsvFiles());
const csvImportId = ref(null);
const csvImportStatus = ref(null); // null until process() is triggered
const csvErrorsCount = ref(0);
const csvActionError = ref(null);
let csvImportPromise = null;
const demoScenario = ref(null);
const demoImportId = ref(null);
const demoState = ref('idle'); // idle | loading | done | error
const demoError = ref(null);
let pollTimer = null;

let debounceTimer = null;
let savePromise = null;
let pendingSectionIndex = null;
let startPromise = null;

const currentSection = computed(() => props.catalog[currentSectionIndex.value]);
const isLastSection = computed(() => currentSectionIndex.value === props.catalog.length - 1);

const hasAttachedCsvFile = computed(() =>
    Object.values(csvFiles.value).some((entry) => entry.state === 'attached'),
);
const isCsvUploading = computed(() =>
    Object.values(csvFiles.value).some((entry) => entry.state === 'uploading'),
);
const isCsvProcessing = computed(() =>
    csvImportStatus.value !== null && !TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value),
);
const isCsvTerminal = computed(() =>
    csvImportStatus.value !== null && TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value),
);

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

// The interval poll is the same class of resource the autosave debounce timer
// once leaked; clear it on unmount so no request fires after the component dies.
onUnmounted(() => {
    stopPolling();
});

// Results are shown whenever submitResult is set. Keep the explicit phase in
// sync (and stop any in-flight poll) rather than driving 'results' by hand from
// each submit call site.
watch(submitResult, (value) => {
    if (value) {
        stopPolling();
        currentPhase.value = 'results';
    }
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

    if (!startPromise) {
        startPromise = axios.post('/api/assessments').then((response) => {
            assessmentId.value = response.data.assessment.id;
        }).catch((error) => {
            startPromise = null;
            throw error;
        });
    }

    await startPromise;
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
    if (savePromise || pendingSectionIndex === null) {
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
    try {
        await startAssessment();

        const section = props.catalog[sectionIndex];
        const payload = section.questions.map((question) => ({
            question_key: question.key,
            value: answers.value[question.key] ?? (question.type === 'multiselect' ? [] : null),
        }));

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
            status.value = error.response
                ? 'Check the highlighted answers.'
                : 'Connection issue — your last change may not be saved. Keep editing to retry.';
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

// --- Import step ("Improve accuracy with store data") ------------------------
//
// This entire section is additive and side-effect-isolated from the draft:
// it reads assessmentId / startAssessment() but never writes answers,
// currentSectionIndex, or the autosave timers.

function freshCsvFiles() {
    return {
        catalog: { state: 'idle', filename: null, error: null },
        orders_returns: { state: 'idle', filename: null, error: null },
        inventory_locations: { state: 'idle', filename: null, error: null },
    };
}

// Last section's primary action: bank any pending answer edits, then advance to
// the import step. It deliberately does NOT submit — submission now happens from
// within the import step.
function goToImportStep() {
    flushSaveImmediately();
    currentPhase.value = 'import';
}

// "Back to questions": answers are already in answers.value and autosaved, so
// this is pure navigation. Stop any poll so it can't outlive the step.
function goBackToQuestions() {
    stopPolling();
    currentPhase.value = 'questions';
}

function selectImportMode(mode) {
    importMode.value = mode;
}

async function ensureCsvImport() {
    if (csvImportId.value) {
        return;
    }

    if (!csvImportPromise) {
        csvImportPromise = startAssessment().then(async () => {
            // 'method' is server-derived for csv imports; we only send the provider.
            const response = await axios.post(`/api/assessments/${assessmentId.value}/imports`, {
                provider: 'csv',
            });

            csvImportId.value = response.data.data_import.id;
        }).catch((error) => {
            csvImportPromise = null;
            throw error;
        });
    }

    await csvImportPromise;
}

async function onCsvFileSelected(dataType, event) {
    const file = event.target?.files?.[0];

    if (!file) {
        return;
    }

    const entry = csvFiles.value[dataType];
    entry.filename = file.name;
    entry.error = null;
    entry.state = 'uploading';

    try {
        await ensureCsvImport();

        const form = new FormData();
        form.append('data_type', dataType);
        form.append('file', file);

        await axios.post(
            `/api/assessments/${assessmentId.value}/imports/${csvImportId.value}/files`,
            form,
        );

        entry.state = 'attached';
    } catch (error) {
        entry.state = 'error';
        entry.error = uploadErrorMessage(error);
    }
}

function uploadErrorMessage(error) {
    const data = error.response?.data;
    const firstError = data?.errors ? Object.values(data.errors)[0] : null;

    if (Array.isArray(firstError) && firstError.length) {
        return firstError[0];
    }

    return data?.message ?? 'That file could not be uploaded. Please try a different CSV.';
}

function applyImportSnapshot(dataImport) {
    csvImportStatus.value = dataImport.status;
    csvErrorsCount.value = dataImport.errors_count ?? 0;
}

function applyDemoImportSnapshot(dataImport) {
    demoImportId.value = dataImport.id;

    if (dataImport.status === 'completed' || dataImport.status === 'completed_with_warnings') {
        demoState.value = 'done';
        stopPolling();
    } else if (dataImport.status === 'failed' || dataImport.status === 'cancelled') {
        demoState.value = 'error';
        demoError.value = 'We could not load that demo dataset. Please try again.';
        stopPolling();
    } else {
        demoState.value = 'loading';
        startPolling();
    }
}

async function processCsvImport() {
    csvActionError.value = null;

    try {
        const response = await axios.post(
            `/api/assessments/${assessmentId.value}/imports/${csvImportId.value}/process`,
        );

        applyImportSnapshot(response.data.data_import);

        // If the queue ran synchronously the import is already terminal; only
        // poll when it is still in flight.
        if (!TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value)) {
            startPolling();
        }
    } catch (error) {
        csvActionError.value = 'We could not start the import. Please try again.';
    }
}

function startPolling() {
    stopPolling();
    pollTimer = setInterval(pollImport, IMPORT_POLL_MS);
}

function stopPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function pollImport() {
    try {
        if (importMode.value === 'demo' && demoImportId.value) {
            const response = await axios.get(
                `/api/assessments/${assessmentId.value}/imports/${demoImportId.value}`,
            );

            applyDemoImportSnapshot(response.data.data_import);

            return;
        }

        const response = await axios.get(
            `/api/assessments/${assessmentId.value}/imports/${csvImportId.value}`,
        );

        applyImportSnapshot(response.data.data_import);

        if (TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value)) {
            stopPolling();
        }
    } catch (error) {
        // Transient poll failure: leave the interval running for the next tick.
    }
}

async function cancelCsvImport() {
    stopPolling();

    try {
        await axios.post(`/api/assessments/${assessmentId.value}/imports/${csvImportId.value}/cancel`);
    } catch (error) {
        // Cancel is best-effort; reset the UI regardless of the response.
    }

    // A cancelled import is terminal on the backend (it cannot be re-processed
    // or have more files attached), so returning to the pre-process state means
    // starting a fresh import — the same recovery path as "Try again".
    resetCsvImport();
}

function resetCsvImport() {
    stopPolling();
    csvFiles.value = freshCsvFiles();
    csvImportId.value = null;
    csvImportPromise = null;
    csvImportStatus.value = null;
    csvErrorsCount.value = 0;
    csvActionError.value = null;
}

async function useDemoScenario(scenario) {
    stopPolling();
    demoScenario.value = scenario;
    demoImportId.value = null;
    demoError.value = null;
    demoState.value = 'loading';

    try {
        await startAssessment();

        const response = await axios.post(`/api/assessments/${assessmentId.value}/imports`, {
            provider: 'demo',
            scenario,
        });

        applyDemoImportSnapshot(response.data.data_import);
    } catch (error) {
        stopPolling();
        demoState.value = 'error';
        demoError.value = 'We could not load that demo dataset. Please try again.';
    }
}

function importStatusLabel(status) {
    return {
        validating: 'Queued…',
        queued: 'Queued…',
        importing: 'Importing…',
        processing: 'Processing…',
    }[status] ?? 'Working…';
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
                    Complete each section, then submit to see your readiness score, breakdown, and recommendations. Your answers save automatically as you go.
                </p>
            </div>

            <template v-if="currentPhase === 'questions'">
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
                        data-testid="continue-to-import"
                        class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700"
                        @click="goToImportStep"
                    >
                        Continue
                    </button>
                </div>

                <p v-if="submitError" class="mt-3 text-right text-sm text-red-600" role="alert">{{ submitError }}</p>
            </template>

            <template v-else-if="currentPhase === 'import'">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="max-w-2xl">
                            <h2 class="text-2xl font-semibold tracking-tight">Make the estimate sharper with data you already have</h2>
                            <p class="mt-3 text-slate-600">
                                Connect read-only Shopify data later, or upload exports now. We use store-level catalog, order, return, and inventory signals. Customer identity data is not needed.
                            </p>
                            <p class="mt-2 text-sm text-slate-500">
                                Review our
                                <Link href="/privacy" class="font-medium text-blue-700 hover:text-blue-800">Privacy Policy</Link>
                                and
                                <Link href="/terms" class="font-medium text-blue-700 hover:text-blue-800">Terms</Link>
                                before adding store data.
                            </p>
                        </div>
                        <button
                            type="button"
                            data-testid="skip-import"
                            class="shrink-0 rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                            :disabled="isSubmitting"
                            :aria-busy="isSubmitting"
                            @click="submitAssessment"
                        >
                            {{ isSubmitting ? 'Submitting…' : 'Skip for now' }}
                        </button>
                    </div>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <!-- Connect Shopify — genuinely disabled, creates nothing -->
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 opacity-70">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-base font-semibold text-slate-700">Connect Shopify</h3>
                                <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-600">Coming soon</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">Best estimate · Read-only data · Products, returns, and inventory · About 2-5 minutes</p>
                            <button
                                type="button"
                                data-testid="connect-shopify"
                                disabled
                                aria-disabled="true"
                                class="mt-4 w-full cursor-not-allowed rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-400"
                            >
                                Connect Shopify
                            </button>
                        </div>

                        <!-- Upload Shopify exports -->
                        <div
                            class="rounded-2xl border p-5 transition"
                            :class="importMode === 'csv' ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-white'"
                        >
                            <h3 class="text-base font-semibold text-slate-900">Upload Shopify exports</h3>
                            <p class="mt-2 text-sm text-slate-600">Good estimate · CSV exports · Products, orders &amp; returns, inventory · A few minutes</p>
                            <button
                                type="button"
                                data-testid="choose-csv"
                                class="mt-4 w-full rounded-xl border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-50"
                                @click="selectImportMode('csv')"
                            >
                                Upload exports
                            </button>
                        </div>

                        <!-- Use demo data -->
                        <div
                            class="rounded-2xl border p-5 transition"
                            :class="importMode === 'demo' ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-white'"
                        >
                            <h3 class="text-base font-semibold text-slate-900">Use demo data</h3>
                            <p class="mt-2 text-sm text-slate-600">Explore instantly · Synthetic sample store · Not your real data · Loads in seconds</p>
                            <button
                                type="button"
                                data-testid="choose-demo"
                                class="mt-4 w-full rounded-xl border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-50"
                                @click="selectImportMode('demo')"
                            >
                                Try a sample store
                            </button>
                        </div>

                        <!-- Continue manually -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="text-base font-semibold text-slate-900">Continue manually</h3>
                            <p class="mt-2 text-sm text-slate-600">Skip the data step and score from your answers only. You can come back before submitting.</p>
                            <button
                                type="button"
                                data-testid="continue-manually"
                                class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                :disabled="isSubmitting"
                                :aria-busy="isSubmitting"
                                @click="submitAssessment"
                            >
                                {{ isSubmitting ? 'Submitting…' : 'Continue without data' }}
                            </button>
                        </div>
                    </div>

                    <!-- CSV upload panel -->
                    <div v-if="importMode === 'csv'" data-testid="csv-panel" class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h4 class="text-sm font-semibold text-slate-900">Upload CSV exports</h4>
                        <p class="mt-1 text-sm text-slate-600">Add any of the three exports below. Each one takes a single .csv file.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="dataType in CSV_DATA_TYPES"
                                :key="dataType.key"
                                class="rounded-xl border border-slate-200 bg-white p-4"
                            >
                                <p class="text-sm font-semibold text-slate-900">{{ dataType.label }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ dataType.hint }}</p>

                                <label class="mt-3 inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Choose file
                                    <input
                                        type="file"
                                        accept=".csv"
                                        class="sr-only"
                                        :data-testid="`csv-input-${dataType.key}`"
                                        :disabled="isCsvProcessing || isCsvUploading"
                                        @change="onCsvFileSelected(dataType.key, $event)"
                                    >
                                </label>

                                <p
                                    v-if="csvFiles[dataType.key].state !== 'idle'"
                                    class="mt-2 text-xs"
                                    :class="csvFiles[dataType.key].state === 'error' ? 'text-red-600' : 'text-slate-600'"
                                    :data-testid="`csv-state-${dataType.key}`"
                                >
                                    <template v-if="csvFiles[dataType.key].state === 'uploading'">Uploading {{ csvFiles[dataType.key].filename }}…</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'attached'">Attached: {{ csvFiles[dataType.key].filename }}</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'error'">{{ csvFiles[dataType.key].error }}</template>
                                </p>
                            </div>
                        </div>

                        <p v-if="csvActionError" class="mt-4 text-sm text-red-600" role="alert">{{ csvActionError }}</p>

                        <!-- Pre-process actions -->
                        <div v-if="!isCsvProcessing && !isCsvTerminal" class="mt-5 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                data-testid="process-import"
                                class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:opacity-40"
                                :disabled="!hasAttachedCsvFile || isCsvUploading"
                                @click="processCsvImport"
                            >
                                Process import
                            </button>
                        </div>

                        <!-- In-flight -->
                        <div v-else-if="isCsvProcessing" class="mt-5 flex flex-wrap items-center gap-3" data-testid="csv-progress">
                            <span class="text-sm font-medium text-slate-700">{{ importStatusLabel(csvImportStatus) }}</span>
                            <button
                                type="button"
                                data-testid="cancel-import"
                                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-white"
                                @click="cancelCsvImport"
                            >
                                Cancel
                            </button>
                        </div>

                        <!-- Terminal outcomes -->
                        <div v-else-if="isCsvTerminal" class="mt-5" data-testid="csv-result">
                            <div v-if="csvImportStatus === 'completed'" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                                Your store data is in. The estimate will use these signals.
                            </div>
                            <div v-else-if="csvImportStatus === 'completed_with_warnings'" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                Import finished, but {{ csvErrorsCount }} item(s) could not be used. The rest made it in and will be used in your estimate.
                            </div>
                            <div v-else-if="csvImportStatus === 'failed'" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                Import failed ({{ csvErrorsCount }} error(s)). None of the uploaded data could be used. You can try again or keep going without it.
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button
                                    v-if="csvImportStatus === 'completed' || csvImportStatus === 'completed_with_warnings'"
                                    type="button"
                                    data-testid="csv-continue"
                                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:opacity-60"
                                    :disabled="isSubmitting"
                                    :aria-busy="isSubmitting"
                                    @click="submitAssessment"
                                >
                                    {{ isSubmitting ? 'Submitting…' : 'Continue' }}
                                </button>

                                <template v-if="csvImportStatus === 'failed'">
                                    <button
                                        type="button"
                                        data-testid="csv-try-again"
                                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-white"
                                        @click="resetCsvImport"
                                    >
                                        Try again
                                    </button>
                                    <button
                                        type="button"
                                        data-testid="csv-continue-without"
                                        class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="isSubmitting"
                                        :aria-busy="isSubmitting"
                                        @click="submitAssessment"
                                    >
                                        {{ isSubmitting ? 'Submitting…' : 'Continue without this data' }}
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Demo panel -->
                    <div v-if="importMode === 'demo'" data-testid="demo-panel" class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h4 class="text-sm font-semibold text-slate-900">Load a demo dataset</h4>
                        <p class="mt-1 text-sm text-slate-600">Synthetic sample data, clearly labeled and never treated like your real store.</p>

                        <div class="mt-4 flex flex-wrap gap-3">
                            <button
                                v-for="scenario in DEMO_SCENARIOS"
                                :key="scenario.key"
                                type="button"
                                :data-testid="`demo-${scenario.key}`"
                                class="rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-60"
                                :class="demoScenario === scenario.key ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-white'"
                                :disabled="demoState === 'loading'"
                                @click="useDemoScenario(scenario.key)"
                            >
                                {{ scenario.label }}
                            </button>
                        </div>

                        <p v-if="demoState === 'loading'" class="mt-4 text-sm text-slate-600" data-testid="demo-loading">Loading demo data…</p>

                        <div v-else-if="demoState === 'done'" class="mt-4" data-testid="demo-done">
                            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                                Demo data loaded. Your estimate will use this sample store's signals.
                            </div>
                            <button
                                type="button"
                                data-testid="demo-continue"
                                class="mt-4 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:opacity-60"
                                :disabled="isSubmitting"
                                :aria-busy="isSubmitting"
                                @click="submitAssessment"
                            >
                                {{ isSubmitting ? 'Submitting…' : 'Continue' }}
                            </button>
                        </div>

                        <p v-else-if="demoState === 'error'" class="mt-4 text-sm text-red-600" role="alert" data-testid="demo-error">{{ demoError }}</p>
                    </div>

                    <div class="mt-8 flex items-center justify-between border-t border-slate-200 pt-6">
                        <button
                            type="button"
                            data-testid="back-to-questions"
                            class="text-sm font-semibold text-slate-600 transition hover:text-slate-900"
                            @click="goBackToQuestions"
                        >
                            ← Back to questions
                        </button>
                    </div>

                    <p v-if="submitError" class="mt-3 text-right text-sm text-red-600" role="alert">{{ submitError }}</p>

                    <p class="mt-6 text-center text-xs text-slate-500">
                        By submitting, you acknowledge this assessment is heuristic and agree to the
                        <Link href="/privacy" class="font-medium text-blue-700 hover:text-blue-800">Privacy Policy</Link>
                        and
                        <Link href="/terms" class="font-medium text-blue-700 hover:text-blue-800">Terms</Link>.
                    </p>
                </div>
            </template>

            <AssessmentResults v-if="submitResult" :result="submitResult" :catalog="catalog" :report-url="submitResult.report.url" />
        </section>
    </main>
</template>
