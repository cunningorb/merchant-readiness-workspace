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

// The scan itself is one synchronous request/response (see
// StartWebsiteScanService), so there's no real per-phase signal from the
// backend to poll — this is a client-side approximation of progress, not a
// literal trace of server-side steps.
const SCAN_PHASES = ['Crawling site', 'Applying rules', 'Clarifying ambiguous values', 'Verifying evidence'];

const WEBSITE_SCAN_QUESTION_KEYS = [
    'business.company_name',
    'business.contact_email',
    'platform.ecommerce_platform',
    'platform.return_tools',
    'return_policy.window_days',
    'return_policy.policy_clarity',
    'exchanges.offered',
];

const ASSISTED_STEPS = [
    {
        key: 'profile',
        label: 'Profile',
        eyebrow: 'Step 1',
        title: 'Start with what your storefront already says',
        description: 'Scan your site for public policy and platform signals, then fill in the business basics that need a person.',
        sectionKeys: ['business', 'platform'],
        questionKeys: ['business.company_name', 'business.contact_email', 'platform.ecommerce_platform', 'platform.return_tools'],
        websiteScan: true,
        websiteScanLabel: 'Store website',
        websiteScanHelp: 'We scan public pages only and show the evidence before scoring.',
        websiteScanPlaceholder: 'example.com',
        websiteScanButton: 'Scan site',
    },
    {
        key: 'catalog',
        label: 'Catalog',
        eyebrow: 'Step 2',
        title: 'Add product complexity signals',
        description: 'Product and order exports help sharpen SKU, category, and order-volume assumptions. Manual answers still override imported signals.',
        sectionKeys: ['business', 'catalog'],
        questionKeys: ['business.monthly_order_volume', 'catalog.sku_count', 'catalog.fit_sensitive_categories'],
        csvDataTypes: ['catalog', 'orders_returns'],
    },
    {
        key: 'policy',
        label: 'Policy',
        eyebrow: 'Step 3',
        title: 'Confirm return and exchange rules',
        description: 'Use the public return policy page to prefill policy answers, then review anything the scan cannot infer.',
        sectionKeys: ['return_policy', 'exchanges'],
        websiteScan: true,
        websiteScanLabel: 'Return policy URL',
        websiteScanHelp: 'Paste the policy page if it is different from the storefront URL. Public policy text is used only to suggest answers.',
        websiteScanPlaceholder: 'example.com/pages/returns',
        websiteScanButton: 'Scan policy',
        hideWebsiteScanWhenComplete: true,
    },
    {
        key: 'operations',
        label: 'Operations',
        eyebrow: 'Step 4',
        title: 'Round out operational effort',
        description: 'Manual handling and inventory context show where automation could remove friction.',
        sectionKeys: ['manual_operations'],
        csvDataTypes: ['inventory_locations'],
    },
];

const props = defineProps({
    catalog: {
        type: Array,
        required: true,
    },
    initialAssessment: {
        type: Object,
        default: null,
    },
});

const currentSectionIndex = ref(0);
const assessmentId = ref(props.initialAssessment?.id ?? null);
const answers = ref(initialAnswers());
const status = ref('');
const errors = ref({});
const submitResult = ref(null);
const submitError = ref(null);
const isSubmitting = ref(false);
const isMounted = ref(false);
const manualEntryOpen = ref(false);
const websiteUrl = ref(props.initialAssessment?.merchant?.website ?? '');
const websiteScanState = ref('idle');
const websiteScanError = ref(null);
const websiteEvidence = ref(props.initialAssessment?.evidence ?? {});
const scanPhaseIndex = ref(0);
const scanPhaseLabel = computed(() => SCAN_PHASES[Math.min(scanPhaseIndex.value, SCAN_PHASES.length - 1)]);
let scanPhaseTimer = null;

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
const csvWarningsCount = ref(0);
const csvErrorsCount = ref(0);
const csvActionError = ref(null);
const demoScenario = ref(null);
const demoImportId = ref(null);
const demoState = ref('idle'); // idle | loading | done | error
const demoError = ref(null);
let pollTimer = null;

let debounceTimer = null;
let savePromise = null;
let pendingSectionIndex = null;
let startPromise = null;

const currentStep = computed(() => ASSISTED_STEPS[currentSectionIndex.value]);
const assistedStepCount = computed(() => ASSISTED_STEPS.length);
const isLastSection = computed(() => currentSectionIndex.value === ASSISTED_STEPS.length - 1);
const currentStepSections = computed(() => currentStep.value.sectionKeys
    .map((sectionKey) => {
        const section = props.catalog.find((candidate) => candidate.key === sectionKey);

        if (!section) {
            return null;
        }

        return {
            ...section,
            questions: section.questions.filter((question) => currentStep.value.questionKeys?.includes(question.key) ?? true),
        };
    })
    .filter((section) => section && section.questions.length));
const currentStepQuestions = computed(() => currentStepSections.value.flatMap((section) => section.questions));
const currentStepCsvDataTypes = computed(() => (currentStep.value.csvDataTypes ?? [])
    .map((dataTypeKey) => CSV_DATA_TYPES.find((dataType) => dataType.key === dataTypeKey))
    .filter(Boolean));

const isCsvUploading = computed(() =>
    Object.values(csvFiles.value).some((entry) => entry.state === 'uploading'),
);
const isCsvProcessing = computed(() =>
    csvImportStatus.value !== null && !TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value),
);
const isCsvTerminal = computed(() =>
    csvImportStatus.value !== null && TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value),
);
const allCsvDataTypesProcessed = computed(() => CSV_DATA_TYPES.every((dataType) =>
    csvFiles.value[dataType.key].state === 'processed',
));
const companyName = computed(() => answers.value['business.company_name'] || submitResult.value?.merchant?.company_name || null);
const wizardTitle = computed(() => companyName.value
    ? `Evaluate ${companyName.value}'s returns operation.`
    : 'Evaluate your returns operation.');
const missingRequiredStepQuestions = computed(() => currentStepQuestions.value.filter((question) => {
    if (!question.required) {
        return false;
    }

    return !isAnswered(answers.value[question.key]);
}));
const requiredStepQuestions = computed(() => currentStepQuestions.value.filter((question) => question.required));
const isCurrentStepComplete = computed(() => missingRequiredStepQuestions.value.length === 0);
const shouldShowWebsiteScan = computed(() => currentStep.value.websiteScan
    && !(currentStep.value.hideWebsiteScanWhenComplete && requiredStepQuestions.value.length > 0 && isCurrentStepComplete.value));

function csvUploadButtonLabel(dataTypeKey) {
    if (csvFiles.value[dataTypeKey].state === 'uploading') {
        return 'Uploading';
    }

    if (isCsvUploading.value) {
        return 'Waiting';
    }

    return 'Choose file';
}

function shouldShowCsvUploadSpinner(dataTypeKey) {
    return csvFiles.value[dataTypeKey].state === 'uploading'
        || (isCsvUploading.value && csvFiles.value[dataTypeKey].state !== 'processed');
}

function initialAnswers() {
    const seeded = {};

    (props.initialAssessment?.answers ?? []).forEach((answer) => {
        seeded[answer.question_key] = answer.value;
    });

    return seeded;
}

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
    clearInterval(scanPhaseTimer);
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

    if (TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value)) {
        csvImportStatus.value = null;
        csvErrorsCount.value = 0;
        csvActionError.value = null;
    }
});

function questionError(question, index) {
    return errors.value[`answers.${index}.value`]?.[0]
        ?? errors.value[`answers.${index}.question_key`]?.[0]
        ?? errors.value[question.key]?.[0]
        ?? null;
}

function evidenceForQuestion(questionKey) {
    return websiteEvidence.value[questionKey]?.[0] ?? null;
}

function evidenceCandidatesForQuestion(questionKey) {
    return websiteEvidence.value[questionKey] ?? [];
}

function hasUnresolvedConflict(questionKey) {
    return evidenceCandidatesForQuestion(questionKey).some((record) => record.requires_confirmation);
}

function isAnswered(value) {
    return value !== null && value !== undefined && value !== '' && (!Array.isArray(value) || value.length > 0);
}

function updateManualPromptAfterAssistedFill() {
    manualEntryOpen.value = missingRequiredStepQuestions.value.length > 0;
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
            replaceResumeUrl(response.data.assessment.resume_url);
        }).catch((error) => {
            startPromise = null;
            throw error;
        });
    }

    await startPromise;
}

function replaceResumeUrl(resumeUrl) {
    if (!resumeUrl || typeof window === 'undefined') {
        return;
    }

    window.history.replaceState(window.history.state, '', resumeUrl);
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

        const step = ASSISTED_STEPS[sectionIndex];
        const payload = stepQuestions(step).map((question) => ({
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

function stepQuestions(step) {
    return step.sectionKeys
        .map((sectionKey) => props.catalog.find((section) => section.key === sectionKey))
        .filter(Boolean)
        .flatMap((section) => section.questions)
        .filter((question) => step.questionKeys?.includes(question.key) ?? true);
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
    manualEntryOpen.value = false;
}

function nextSection() {
    flushSaveImmediately();

    if (!isLastSection.value) {
        currentSectionIndex.value += 1;
        manualEntryOpen.value = false;
    }
}

function previousSection() {
    flushSaveImmediately();
    currentSectionIndex.value = Math.max(0, currentSectionIndex.value - 1);
    manualEntryOpen.value = false;
}

async function scanWebsite() {
    websiteScanError.value = null;
    websiteScanState.value = 'scanning';
    scanPhaseIndex.value = 0;
    clearInterval(scanPhaseTimer);
    scanPhaseTimer = setInterval(() => {
        if (scanPhaseIndex.value < SCAN_PHASES.length - 1) {
            scanPhaseIndex.value += 1;
        }
    }, 900);

    try {
        await startAssessment();

        const response = await axios.post(`/api/assessments/${assessmentId.value}/website-scan`, {
            url: websiteUrl.value,
        });

        websiteEvidence.value = response.data.evidence ?? {};
        websiteUrl.value = response.data.merchant?.website ?? websiteUrl.value;

        const responseAnswerKeys = new Set((response.data.answers ?? []).map((answer) => answer.question_key));
        WEBSITE_SCAN_QUESTION_KEYS.forEach((questionKey) => {
            if (!responseAnswerKeys.has(questionKey)) {
                answers.value[questionKey] = null;
            }
        });

        (response.data.answers ?? []).forEach((answer) => {
            answers.value[answer.question_key] = answer.value;
        });

        scanPhaseIndex.value = SCAN_PHASES.length - 1;
        websiteScanState.value = 'done';
        updateManualPromptAfterAssistedFill();
        status.value = 'Website scan applied where answers were blank.';
    } catch (error) {
        websiteScanState.value = 'error';
        websiteScanError.value = error.response?.data?.message ?? 'We could not scan that site. Check the URL and try again.';
    } finally {
        clearInterval(scanPhaseTimer);
        scanPhaseTimer = null;
    }
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

async function continueFromLastStep() {
    flushSaveImmediately();

    if (allCsvDataTypesProcessed.value) {
        await submitAssessment();

        return;
    }

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

async function createCsvImport() {
    await startAssessment();

    // 'method' is server-derived for csv imports; we only send the provider.
    const response = await axios.post(`/api/assessments/${assessmentId.value}/imports`, {
        provider: 'csv',
    });

    csvImportId.value = response.data.data_import.id;

    return csvImportId.value;
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
    csvActionError.value = null;

    try {
        const importId = await createCsvImport();

        const form = new FormData();
        form.append('data_type', dataType);
        form.append('file', file);

        await axios.post(
            `/api/assessments/${assessmentId.value}/imports/${importId}/files`,
            form,
        );

        entry.state = 'processing';
        csvImportStatus.value = 'queued';
        await processCsvImport(importId);
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
    csvWarningsCount.value = dataImport.warnings_count ?? 0;
    csvErrorsCount.value = dataImport.errors_count ?? 0;
}

function csvIssueCount() {
    return csvImportStatus.value === 'completed_with_warnings'
        ? (csvWarningsCount.value || csvErrorsCount.value)
        : csvErrorsCount.value;
}

function csvTerminalClass() {
    return {
        completed: 'rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800',
        completed_with_warnings: 'rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800',
        failed: 'rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800',
    }[csvImportStatus.value] ?? 'rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700';
}

function csvTerminalMessage() {
    if (csvImportStatus.value === 'completed') {
        return 'Store data processed. Suggested answers were added where blanks existed.';
    }

    if (csvImportStatus.value === 'completed_with_warnings') {
        return `Import finished, but ${csvIssueCount()} item(s) could not be used. The rest made it in and will be used in your estimate.`;
    }

    if (csvImportStatus.value === 'failed') {
        return `Import failed (${csvIssueCount()} error(s)). None of the uploaded data could be used. You can try again or keep going without it.`;
    }

    return 'Import finished.';
}

function updateCsvFileStatesForTerminalImport() {
    Object.values(csvFiles.value).forEach((entry) => {
        if (entry.state !== 'processing') {
            return;
        }

        if (csvImportStatus.value === 'completed' || csvImportStatus.value === 'completed_with_warnings') {
            entry.state = 'processed';
        } else if (csvImportStatus.value === 'failed') {
            entry.state = 'error';
            entry.error = `Import failed (${csvErrorsCount.value} error(s)). Please try a different CSV or continue without it.`;
        }
    });
}

function applyImportedAnswers(responseData) {
    (responseData.answers ?? []).forEach((answer) => {
        answers.value[answer.question_key] = answer.value;
    });
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

async function processCsvImport(importId = csvImportId.value) {
    csvActionError.value = null;

    try {
        const response = await axios.post(
            `/api/assessments/${assessmentId.value}/imports/${importId}/process`,
        );

        applyImportSnapshot(response.data.data_import);
        applyImportedAnswers(response.data);
        if (TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value)) {
            updateCsvFileStatesForTerminalImport();
            updateManualPromptAfterAssistedFill();
        }

        // If the queue ran synchronously the import is already terminal; only
        // poll when it is still in flight.
        if (!TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value)) {
            startPolling();
        }
    } catch (error) {
        csvActionError.value = 'We could not start the import. Please try again.';
        throw error;
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
            applyImportedAnswers(response.data);
            if (demoState.value === 'done') {
                updateManualPromptAfterAssistedFill();
            }

            return;
        }

        const response = await axios.get(
            `/api/assessments/${assessmentId.value}/imports/${csvImportId.value}`,
        );

        applyImportSnapshot(response.data.data_import);
        applyImportedAnswers(response.data);

        if (TERMINAL_IMPORT_STATUSES.includes(csvImportStatus.value)) {
            updateCsvFileStatesForTerminalImport();
            updateManualPromptAfterAssistedFill();
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

    // A cancelled import is terminal on the backend, so recovery means starting
    // a fresh one-file import on the next upload.
    resetCsvImport();
}

function resetCsvImport() {
    stopPolling();
    csvFiles.value = freshCsvFiles();
    csvImportId.value = null;
    csvImportStatus.value = null;
    csvWarningsCount.value = 0;
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
        applyImportedAnswers(response.data);
        if (demoState.value === 'done') {
            updateManualPromptAfterAssistedFill();
        }
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
            <div class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-3xl">
                    <p class="mb-4 inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                        Merchant Readiness Assessment
                    </p>
                    <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ wizardTitle }}</h1>
                    <p class="mt-5 text-lg leading-8 text-slate-600">
                        Scan your site, add lightweight store exports where useful, and only answer manually when evidence is missing or needs correction. Your answers save automatically as you go.
                    </p>
                </div>
                <Link href="/" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Exit assessment
                </Link>
            </div>

            <template v-if="currentPhase === 'questions'">
                <div class="mb-8 grid gap-3 sm:grid-cols-4">
                    <button
                        v-for="(step, index) in ASSISTED_STEPS"
                        :key="step.key"
                        type="button"
                        class="rounded-2xl border px-4 py-3 text-left text-sm transition"
                        :class="index === currentSectionIndex ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600'"
                        :aria-current="index === currentSectionIndex ? 'step' : undefined"
                        @click="selectSection(index)"
                    >
                        <span class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ step.eyebrow }}</span>
                        {{ step.label }}
                    </button>
                </div>

                <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="nextSection">
                    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-sm font-medium text-blue-600">Step {{ currentSectionIndex + 1 }} of {{ assistedStepCount }}</p>
                            <h2 class="mt-1 text-2xl font-semibold">{{ currentStep.title }}</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ currentStep.description }}</p>
                        </div>
                        <p class="text-sm text-slate-500" role="status" aria-live="polite">{{ status }}</p>
                    </div>

                    <div v-if="shouldShowWebsiteScan" class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                            <label class="flex-1">
                                <span class="block text-sm font-semibold text-slate-900">{{ currentStep.websiteScanLabel }}</span>
                                <span class="mt-1 block text-sm text-slate-600">{{ currentStep.websiteScanHelp }}</span>
                                <input
                                    v-model="websiteUrl"
                                    type="url"
                                    :placeholder="currentStep.websiteScanPlaceholder"
                                    class="mt-3 w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                                >
                            </label>
                            <button
                                type="button"
                                data-testid="scan-website"
                                class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:opacity-50"
                                :disabled="websiteScanState === 'scanning' || !websiteUrl"
                                :aria-busy="websiteScanState === 'scanning'"
                                @click="scanWebsite"
                            >
                                {{ websiteScanState === 'scanning' ? 'Scanning...' : currentStep.websiteScanButton }}
                            </button>
                        </div>
                        <p v-if="websiteScanState === 'scanning'" class="mt-3 text-sm font-medium text-blue-700" role="status" aria-live="polite">{{ scanPhaseLabel }}&hellip;</p>
                        <p v-if="websiteScanError" class="mt-3 text-sm text-red-600" role="alert">{{ websiteScanError }}</p>
                        <p v-else-if="websiteScanState === 'done'" class="mt-3 text-sm font-medium text-blue-800">Scan complete. Suggested answers were added only where blanks existed.</p>
                    </div>

                    <div v-if="currentStepCsvDataTypes.length" class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Optional CSV evidence</h3>
                        <p class="mt-1 text-sm text-slate-600">For best results, upload a full year when available. A quarter is useful, and one month can still improve directional estimates.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="dataType in currentStepCsvDataTypes"
                                :key="dataType.key"
                                class="rounded-xl border border-slate-200 bg-white p-4"
                            >
                                <p class="text-sm font-semibold text-slate-900">{{ dataType.label }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ dataType.hint }}</p>
                                <p class="mt-1 text-xs text-slate-500">Recommended range: year. Accepted: month, quarter, or year.</p>

                                <label
                                    class="mt-3 inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                                    :class="isCsvProcessing || isCsvUploading ? 'cursor-not-allowed bg-blue-100 text-blue-700 opacity-80' : 'cursor-pointer bg-blue-600 text-white hover:bg-blue-700'"
                                >
                                    <span v-if="shouldShowCsvUploadSpinner(dataType.key)" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                                    {{ csvUploadButtonLabel(dataType.key) }}
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
                                    <template v-if="csvFiles[dataType.key].state === 'uploading'">Uploading {{ csvFiles[dataType.key].filename }}...</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'processing'">Processing {{ csvFiles[dataType.key].filename }}...</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'processed'">Processed: {{ csvFiles[dataType.key].filename }}</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'error'">{{ csvFiles[dataType.key].error }}</template>
                                </p>
                            </div>
                        </div>

                        <p v-if="csvActionError" class="mt-4 text-sm text-red-600" role="alert">{{ csvActionError }}</p>

                        <div v-if="isCsvProcessing" class="mt-5 flex flex-wrap items-center gap-3" data-testid="csv-progress">
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

                        <div v-else-if="isCsvTerminal" class="mt-5" data-testid="csv-result">
                            <div :class="csvTerminalClass()">{{ csvTerminalMessage() }}</div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mb-4 inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        :aria-expanded="manualEntryOpen"
                        @click="manualEntryOpen = !manualEntryOpen"
                    >
                        {{ manualEntryOpen ? 'Hide manual answers' : 'Review or edit manual answers' }}
                    </button>

                    <div v-if="manualEntryOpen" class="space-y-8">
                        <section v-for="section in currentStepSections" :key="section.key" class="rounded-2xl border border-slate-200 p-5">
                            <h3 class="text-base font-semibold text-slate-900">{{ section.label }}</h3>
                            <div class="mt-5 space-y-6">
                        <label v-for="(question, questionIndex) in section.questions" :key="question.key" class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-900">
                                {{ question.label }}
                                <span v-if="question.required" class="text-blue-600">*</span>
                            </span>

                            <div v-if="evidenceForQuestion(question.key)" class="mb-2 space-y-2">
                                <div
                                    v-for="(record, recordIndex) in (hasUnresolvedConflict(question.key) ? evidenceCandidatesForQuestion(question.key) : [evidenceForQuestion(question.key)])"
                                    :key="recordIndex"
                                    class="rounded-xl border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-800"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span>Suggested from {{ record.source_label }}: {{ record.value }}</span>
                                        <span
                                            v-if="record.provider === 'groq'"
                                            class="inline-flex items-center rounded-full bg-blue-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-900"
                                        >AI-assisted</span>
                                        <span
                                            v-if="record.requires_confirmation"
                                            class="inline-flex items-center rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900"
                                        >Requires confirmation</span>
                                    </div>
                                    <span v-if="record.evidence_snippet" class="mt-1 block text-blue-700">"{{ record.evidence_snippet }}"</span>
                                    <a
                                        v-if="record.evidence_url"
                                        :href="record.evidence_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 block truncate text-blue-600 underline"
                                    >{{ record.evidence_url }}</a>
                                </div>
                                <p v-if="hasUnresolvedConflict(question.key)" class="text-xs text-amber-700">
                                    The rules scan and the AI-assisted scan disagree here — pick the correct answer below.
                                </p>
                            </div>

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

                            <p v-if="questionError(question, currentStepQuestions.findIndex((stepQuestion) => stepQuestion.key === question.key))" class="mt-2 text-sm text-red-600">
                                {{ questionError(question, currentStepQuestions.findIndex((stepQuestion) => stepQuestion.key === question.key)) }}
                            </p>
                        </label>
                            </div>
                        </section>
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
                            data-testid="next-step"
                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700"
                            :class="isCurrentStepComplete ? 'bg-blue-600 shadow-xl shadow-blue-300 ring-4 ring-blue-100' : 'bg-blue-500 shadow-lg shadow-blue-200'"
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
                        @click="continueFromLastStep"
                    >
                        {{ isSubmitting ? 'Submitting...' : 'Continue' }}
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

                                <label
                                    class="mt-3 inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                                    :class="isCsvProcessing || isCsvUploading ? 'cursor-not-allowed bg-blue-100 text-blue-700 opacity-80' : 'cursor-pointer bg-blue-600 text-white hover:bg-blue-700'"
                                >
                                    <span v-if="shouldShowCsvUploadSpinner(dataType.key)" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                                    {{ csvUploadButtonLabel(dataType.key) }}
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
                                    <template v-else-if="csvFiles[dataType.key].state === 'processing'">Processing {{ csvFiles[dataType.key].filename }}…</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'processed'">Processed: {{ csvFiles[dataType.key].filename }}</template>
                                    <template v-else-if="csvFiles[dataType.key].state === 'error'">{{ csvFiles[dataType.key].error }}</template>
                                </p>
                            </div>
                        </div>

                        <p v-if="csvActionError" class="mt-4 text-sm text-red-600" role="alert">{{ csvActionError }}</p>

                        <!-- In-flight -->
                        <div v-if="isCsvProcessing" class="mt-5 flex flex-wrap items-center gap-3" data-testid="csv-progress">
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
                            <div :class="csvTerminalClass()">{{ csvTerminalMessage() }}</div>

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

        <footer class="mx-auto mt-12 flex max-w-5xl flex-col gap-3 border-t border-slate-200 pt-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <Link href="/" class="font-medium text-slate-600 hover:text-blue-700">Back to home</Link>
            <div class="flex gap-4">
                <Link href="/privacy" class="hover:text-blue-700">Privacy</Link>
                <Link href="/terms" class="hover:text-blue-700">Terms</Link>
            </div>
        </footer>
    </main>
</template>
