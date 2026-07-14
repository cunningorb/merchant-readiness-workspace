<script setup>
import { computed, ref } from 'vue';
import ActionPlan from '../../Components/Report/ActionPlan.vue';
import CalculationModal from '../../Components/Report/CalculationModal.vue';
import OpportunityHero from '../../Components/Report/OpportunityHero.vue';
import PeerPerspectivePanel from '../../Components/Report/PeerPerspectivePanel.vue';
import RecommendationCard from '../../Components/Report/RecommendationCard.vue';
import RecommendationsDisclosure from '../../Components/Report/RecommendationsDisclosure.vue';
import SupportingMetricStrip from '../../Components/Report/SupportingMetricStrip.vue';

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

const report = computed(() => props.result.report?.payload ?? null);
const companyName = computed(() => report.value?.merchant?.company_name ?? props.result.merchant?.company_name ?? 'your business');
const profileLine = computed(() => {
    if (!report.value) {
        return '';
    }

    const parts = [];

    if (report.value.merchant.monthly_order_volume) {
        parts.push(`${report.value.merchant.monthly_order_volume} orders/month`);
    }
    if (report.value.merchant.sku_count) {
        parts.push(`${report.value.merchant.sku_count} SKUs`);
    }
    if (report.value.merchant.ecommerce_platform) {
        parts.push(report.value.merchant.ecommerce_platform);
    }

    return parts.join(' · ');
});

const explanations = computed(() => report.value?.calculationExplanations ?? {});
const heroHasCalculation = computed(() =>
    report.value?.heroOpportunity?.type != null && explanations.value[report.value.heroOpportunity.type] != null);
const enrichedMetrics = computed(() => (report.value?.supportingMetrics ?? []).map((metric) => ({
    ...metric,
    confidence: metric.source === 'opportunity' ? explanations.value[metric.key]?.confidence ?? null : null,
})));
const primaryRecommendation = computed(() => report.value?.topRecommendations?.[0] ?? null);
const secondaryRecommendations = computed(() => report.value?.topRecommendations?.slice(1) ?? []);
const remainingRecommendations = computed(() => report.value?.remainingRecommendations ?? []);

const activeExplanationType = ref(null);
const activeExplanation = computed(() =>
    activeExplanationType.value != null ? explanations.value[activeExplanationType.value] ?? null : null);

function hasCalculation(recommendation) {
    return recommendation.opportunity_type != null && explanations.value[recommendation.opportunity_type] != null;
}

function confidenceFor(recommendation) {
    return explanations.value[recommendation.opportunity_type]?.confidence ?? null;
}

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
    <div v-if="report" class="mt-8 space-y-8">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="mb-2 inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                Merchant Readiness Report
            </p>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                {{ companyName }}'s returns opportunity report
            </h2>
            <p v-if="profileLine" class="mt-2 text-sm text-slate-500">{{ profileLine }}</p>
            <p v-if="reportUrl" class="mt-4 text-sm text-slate-600">
                Shareable report:
                <a :href="reportUrl" class="font-semibold text-blue-700 underline decoration-blue-300 underline-offset-2 hover:text-blue-900">{{ reportUrl }}</a>
            </p>
        </section>

        <OpportunityHero
            :opportunity="report.heroOpportunity"
            :has-calculation="heroHasCalculation"
            @see-calculation="openCalculation(report.heroOpportunity.type)"
        />

        <SupportingMetricStrip :metrics="enrichedMetrics" />

        <section aria-labelledby="talking-points-heading" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 id="talking-points-heading" class="text-lg font-semibold text-slate-900">Talking points</h2>
            <ol class="mt-4 space-y-4">
                <li v-for="(point, index) in report.talkingPoints" :key="index" class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">{{ index + 1 }}</span>
                    <div>
                        <p class="font-medium text-slate-900">{{ point.title }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ point.description }}</p>
                        <p class="mt-1 text-sm text-slate-500">Expected impact: {{ point.expected_impact }}</p>
                    </div>
                </li>
            </ol>
        </section>

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
        <CalculationModal :open="activeExplanation !== null" :explanation="activeExplanation" @close="closeCalculation" />
    </div>
</template>
