<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import AssessmentResults from '../Assessment/AssessmentResults.vue';
import CalculationModal from '../../Components/Report/CalculationModal.vue';
import OpportunityHero from '../../Components/Report/OpportunityHero.vue';
import SupportingMetricStrip from '../../Components/Report/SupportingMetricStrip.vue';

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

const explanations = computed(() => props.report.calculationExplanations ?? {});

const heroHasCalculation = computed(() =>
    props.report.heroOpportunity.type != null && explanations.value[props.report.heroOpportunity.type] != null);

const enrichedMetrics = computed(() => props.report.supportingMetrics.map((metric) => ({
    ...metric,
    confidence: metric.source === 'opportunity' ? explanations.value[metric.key]?.confidence ?? null : null,
})));

const activeExplanationType = ref(null);
const activeExplanation = computed(() =>
    activeExplanationType.value != null ? explanations.value[activeExplanationType.value] ?? null : null);

function openCalculation(type) {
    if (type != null && explanations.value[type] != null) {
        activeExplanationType.value = type;
    }
}

function closeCalculation() {
    activeExplanationType.value = null;
}
</script>

<template>
    <Head :title="report.merchant.company_name" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('dashboard')" class="text-sm font-medium text-blue-600 hover:text-blue-700"><span aria-hidden="true">&larr;</span> Back to prospects</Link>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ report.merchant.company_name }}</h1>
                    <p v-if="profileLine" class="mt-1 text-slate-600">{{ profileLine }}</p>
                    <p class="mt-1 text-sm text-slate-500">Submitted {{ submittedOn }}</p>
                </div>

                <div class="space-y-8">
                    <OpportunityHero
                        :opportunity="report.heroOpportunity"
                        :has-calculation="heroHasCalculation"
                        @see-calculation="openCalculation(report.heroOpportunity.type)"
                    />

                    <SupportingMetricStrip :metrics="enrichedMetrics" />

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
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

                    <section aria-labelledby="diagnostic-heading">
                        <h2 id="diagnostic-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-400">
                            Full diagnostic breakdown
                        </h2>
                        <AssessmentResults :result="result" :catalog="catalog" />
                    </section>
                </div>

                <CalculationModal :open="activeExplanation !== null" :explanation="activeExplanation" @close="closeCalculation" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
