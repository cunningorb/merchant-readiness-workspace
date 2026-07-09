<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';

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

const currentSection = computed(() => props.catalog[currentSectionIndex.value]);
const isLastSection = computed(() => currentSectionIndex.value === props.catalog.length - 1);

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
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-6 py-10 text-white sm:px-8">
        <section class="mx-auto max-w-5xl">
            <div class="mb-10 max-w-3xl">
                <p class="mb-4 inline-flex rounded-full border border-blue-300/30 bg-blue-400/10 px-4 py-2 text-sm font-medium text-blue-100">
                    Merchant Readiness Assessment
                </p>
                <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">Evaluate your returns operation.</h1>
                <p class="mt-5 text-lg leading-8 text-slate-300">
                    Complete each section to save a draft assessment. Scoring and recommendations come in the next milestone.
                </p>
            </div>

            <div class="mb-8 grid gap-3 sm:grid-cols-6">
                <button
                    v-for="(section, index) in catalog"
                    :key="section.key"
                    type="button"
                    class="rounded-2xl border px-4 py-3 text-left text-sm transition"
                    :class="index === currentSectionIndex ? 'border-blue-300 bg-blue-500/20 text-white' : 'border-white/10 bg-white/5 text-slate-300'"
                    @click="currentSectionIndex = index"
                >
                    {{ section.label }}
                </button>
            </div>

            <form class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/20" @submit.prevent="saveSection">
                <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-sm font-medium text-blue-200">Section {{ currentSectionIndex + 1 }} of {{ catalog.length }}</p>
                        <h2 class="mt-1 text-2xl font-semibold">{{ currentSection.label }}</h2>
                    </div>
                    <p class="text-sm text-slate-300">{{ status }}</p>
                </div>

                <div class="space-y-6">
                    <label v-for="question in currentSection.questions" :key="question.key" class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-100">
                            {{ question.label }}
                            <span v-if="question.required" class="text-blue-200">*</span>
                        </span>

                        <input
                            v-if="['text', 'email'].includes(question.type)"
                            v-model="answers[question.key]"
                            :type="question.type"
                            class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none ring-blue-400 transition focus:ring-2"
                        >

                        <select
                            v-else-if="question.type === 'select'"
                            v-model="answers[question.key]"
                            class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none ring-blue-400 transition focus:ring-2"
                        >
                            <option :value="null">Choose one</option>
                            <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                        </select>

                        <div v-else-if="question.type === 'multiselect'" class="grid gap-2 sm:grid-cols-2">
                            <label v-for="option in question.options" :key="option" class="flex items-center gap-3 rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm text-slate-200">
                                <input v-model="answers[question.key]" type="checkbox" :value="option" class="rounded border-white/20 bg-slate-950 text-blue-500">
                                {{ option }}
                            </label>
                        </div>

                        <select
                            v-else-if="question.type === 'boolean'"
                            v-model="answers[question.key]"
                            class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none ring-blue-400 transition focus:ring-2"
                        >
                            <option :value="null">Choose one</option>
                            <option :value="true">Yes</option>
                            <option :value="false">No</option>
                        </select>

                        <p v-if="Object.keys(errors).some((key) => key.includes(question.key) || key.includes('value'))" class="mt-2 text-sm text-red-300">
                            This answer needs attention.
                        </p>
                    </label>
                </div>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-between">
                    <button type="button" class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 disabled:opacity-40" :disabled="currentSectionIndex === 0" @click="previousSection">
                        Previous
                    </button>
                    <button type="submit" class="rounded-xl bg-blue-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-950/40 transition hover:bg-blue-400">
                        {{ isLastSection ? 'Save final draft section' : 'Save and continue' }}
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
