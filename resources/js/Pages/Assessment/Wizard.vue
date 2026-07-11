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
const isSaving = ref(false);
const isSubmitting = ref(false);

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
    isSaving.value = true;

    try {
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
    } finally {
        isSaving.value = false;
    }
}

function previousSection() {
    currentSectionIndex.value = Math.max(0, currentSectionIndex.value - 1);
}

async function submitAssessment() {
    submitError.value = null;
    isSubmitting.value = true;

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
                        :aria-current="index === currentSectionIndex ? 'step' : undefined"
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
                            :disabled="currentSectionIndex === 0 || isSaving || isSubmitting"
                            @click="previousSection"
                        >
                            Previous
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:opacity-60"
                            :disabled="isSaving"
                            :aria-busy="isSaving"
                        >
                            {{ isSaving ? 'Saving…' : (isLastSection ? 'Save final draft section' : 'Save and continue') }}
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
