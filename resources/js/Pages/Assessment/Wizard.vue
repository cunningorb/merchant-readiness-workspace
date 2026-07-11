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
let startPromise = null;

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
