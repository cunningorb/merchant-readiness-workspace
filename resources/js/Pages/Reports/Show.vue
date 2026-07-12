<script setup>
import { computed, ref } from 'vue';
import AssessmentResults from '../Assessment/AssessmentResults.vue';
import ActionPlan from '../../Components/Report/ActionPlan.vue';
import CalculationModal from '../../Components/Report/CalculationModal.vue';
import OpportunityHero from '../../Components/Report/OpportunityHero.vue';
import PeerPerspectivePanel from '../../Components/Report/PeerPerspectivePanel.vue';
import RecommendationCard from '../../Components/Report/RecommendationCard.vue';
import RecommendationsDisclosure from '../../Components/Report/RecommendationsDisclosure.vue';
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

const explanations = computed(() => props.report.calculationExplanations ?? {});

const heroHasCalculation = computed(() =>
    props.report.heroOpportunity.type != null && explanations.value[props.report.heroOpportunity.type] != null);

const enrichedMetrics = computed(() => props.report.supportingMetrics.map((metric) => ({
    ...metric,
    confidence: metric.source === 'opportunity' ? explanations.value[metric.key]?.confidence ?? null : null,
})));

const primaryRecommendation = computed(() => props.report.topRecommendations[0] ?? null);
const secondaryRecommendations = computed(() => props.report.topRecommendations.slice(1));
const remainingRecommendations = computed(() => props.report.remainingRecommendations ?? []);

function hasCalculation(recommendation) {
    return recommendation.opportunity_type != null && explanations.value[recommendation.opportunity_type] != null;
}

function confidenceFor(recommendation) {
    return explanations.value[recommendation.opportunity_type]?.confidence ?? null;
}

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

function printReport() {
    window.print();
}
</script>

<template>
    <main class="min-h-screen bg-slate-50 px-6 py-8 text-slate-900 sm:px-8">
        <section class="mx-auto max-w-5xl">
            <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="mb-2 inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                        Merchant Readiness Report
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ report.merchant.company_name }}</h1>
                    <p v-if="profileLine" class="mt-1 text-sm text-slate-500">{{ profileLine }}</p>
                    <p class="mt-1 text-sm text-slate-500">Prepared on {{ preparedOn }}</p>
                </div>
                <button
                    type="button"
                    class="print:hidden self-start rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    @click="printReport"
                >
                    Print report
                </button>
            </div>

            <div class="space-y-8">
                <OpportunityHero
                    :opportunity="report.heroOpportunity"
                    :has-calculation="heroHasCalculation"
                    @see-calculation="openCalculation(report.heroOpportunity.type)"
                />

                <SupportingMetricStrip :metrics="enrichedMetrics" />

                <section v-if="primaryRecommendation" aria-labelledby="primary-action-heading" data-testid="primary-action">
                    <h2 id="primary-action-heading" class="sr-only">Primary recommended action</h2>
                    <RecommendationCard
                        :recommendation="primaryRecommendation"
                        primary
                        :confidence="confidenceFor(primaryRecommendation)"
                        :has-calculation="hasCalculation(primaryRecommendation)"
                        @see-calculation="openCalculation(primaryRecommendation.opportunity_type)"
                    />
                </section>

                <section v-if="secondaryRecommendations.length" aria-labelledby="top-opportunities-heading">
                    <h2 id="top-opportunities-heading" class="text-lg font-semibold text-slate-900">Top opportunities</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <RecommendationCard
                            v-for="(recommendation, index) in secondaryRecommendations"
                            :key="index"
                            :recommendation="recommendation"
                            :confidence="confidenceFor(recommendation)"
                            :has-calculation="hasCalculation(recommendation)"
                            @see-calculation="openCalculation(recommendation.opportunity_type)"
                        />
                    </div>
                </section>

                <RecommendationsDisclosure
                    v-if="remainingRecommendations.length"
                    :recommendations="remainingRecommendations"
                    :calculation-explanations="explanations"
                    @see-calculation="openCalculation"
                />

                <PeerPerspectivePanel :comparisons="report.peerComparisons ?? []" />

                <ActionPlan :plan="report.actionPlan" />

                <section aria-labelledby="diagnostic-heading">
                    <h2 id="diagnostic-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-400">
                        Full diagnostic breakdown
                    </h2>
                    <AssessmentResults :result="result" :catalog="catalog" />
                </section>
            </div>

            <CalculationModal :open="activeExplanation !== null" :explanation="activeExplanation" @close="closeCalculation" />
        </section>
    </main>
</template>
