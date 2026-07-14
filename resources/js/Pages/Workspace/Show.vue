<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import CapabilityAndImprovements from '../../Components/Report/CapabilityAndImprovements.vue';
import CalculationModal from '../../Components/Report/CalculationModal.vue';
import ExecutiveSummaryCard from '../../Components/Report/ExecutiveSummaryCard.vue';
import OpportunityHero from '../../Components/Report/OpportunityHero.vue';
import RecommendationCard from '../../Components/Report/RecommendationCard.vue';
import RecommendationsDisclosure from '../../Components/Report/RecommendationsDisclosure.vue';
import ReportHeaderBar from '../../Components/Report/ReportHeaderBar.vue';
import ScoreBreakdownCard from '../../Components/Report/ScoreBreakdownCard.vue';

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

const fallbackRecommendations = computed(() => {
    if ((props.report.topRecommendations ?? []).length > 0) {
        return [];
    }

    const hero = props.report.heroOpportunity;

    return [
        {
            title: hero.type === 'retained_revenue' ? 'Switch to exchange-first automation' : hero.title,
            description: hero.summary,
            category: hero.type === 'retained_revenue' ? 'exchanges' : 'manual_operations',
            priority: 'high',
            expected_impact: hero.kind === 'monetary' ? 'Retained revenue' : 'Operational lift',
            opportunity_type: hero.type,
            effort: hero.effort,
        },
        {
            title: 'Automate low-risk approvals',
            description: 'Auto-approve returns that meet clear criteria and route only genuine exceptions to the team.',
            category: 'manual_operations',
            priority: 'high',
            expected_impact: 'Lower manual review load',
            opportunity_type: 'manual_work_savings',
            effort: 'medium',
        },
        {
            title: 'Segment return policies by category',
            description: 'Replace one blanket policy with distinct windows and rules for categories that create avoidable returns.',
            category: 'return_policy',
            priority: 'medium',
            expected_impact: 'Lower avoidable returns',
            opportunity_type: 'support_contact_reduction',
            effort: 'low',
        },
    ];
});
const displayTopRecommendations = computed(() => [...(props.report.topRecommendations ?? []), ...fallbackRecommendations.value].slice(0, 3));
const primaryRecommendation = computed(() => displayTopRecommendations.value[0] ?? null);
const secondaryRecommendations = computed(() => displayTopRecommendations.value.slice(1));
const remainingRecommendations = computed(() => props.report.remainingRecommendations ?? []);
const allRecommendations = computed(() => [
    ...displayTopRecommendations.value,
    ...(props.report.remainingRecommendations ?? []),
]);
const EFFORT_ORDER = { low: 0, medium: 1, high: 2 };
const recommendedImprovements = computed(() => [...allRecommendations.value]
    .sort((a, b) => (EFFORT_ORDER[a.effort] ?? 3) - (EFFORT_ORDER[b.effort] ?? 3))
    .slice(0, 3));

const activeExplanationType = ref(null);
const salesModalOpen = ref(false);
const activeExplanation = computed(() =>
    activeExplanationType.value != null ? explanations.value[activeExplanationType.value] ?? null : null);

const contactEmail = computed(() => props.report.merchant.contact_email || 'the email provided');

function openCalculation(type) {
    if (type != null && explanations.value[type] != null) {
        activeExplanationType.value = type;
    }
}

function closeCalculation() {
    activeExplanationType.value = null;
}

function openSalesContact() {
    salesModalOpen.value = true;
}

function closeSalesContact() {
    salesModalOpen.value = false;
}

function hasCalculation(recommendation) {
    return recommendation.opportunity_type != null && explanations.value[recommendation.opportunity_type] != null;
}

function confidenceFor(recommendation) {
    return explanations.value[recommendation.opportunity_type]?.confidence ?? null;
}

function notifyContactRequest() {
    axios.post(`/api/reports/${props.report.token}/contact`).catch(() => {});
}

function handleSalesContact() {
    salesModalOpen.value = true;
    notifyContactRequest();
}

function printReport() {
    window.print();
}
</script>

<template>
    <Head :title="report.merchant.company_name" />

    <AuthenticatedLayout>
        <ReportHeaderBar :company-name="report.merchant.company_name" :share-url="report.url" :back-href="route('dashboard')" @download="printReport" @contact-sales="handleSalesContact" />

        <div class="py-8">
            <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
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
                        @contact-sales="handleSalesContact"
                    />

                    <section v-if="recommendedImprovements.length" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="recommended-improvements-heading">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Recommended improvements</p>
                        <h2 id="recommended-improvements-heading" class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Start with the changes most likely to clean up returns friction.</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                            These are the first moves to discuss with the merchant's operations, support, and ecommerce teams. They prioritize practical workflow changes over broad platform replacement, so you can pressure-test the recommendations against their current policy, exchange behavior, and manual workload.
                        </p>
                        <ol class="mt-5 grid gap-3 sm:grid-cols-3">
                            <li v-for="(item, index) in recommendedImprovements" :key="item.title" class="flex gap-3 text-sm text-slate-700">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">{{ index + 1 }}</span>
                                <span>{{ item.title }}</span>
                            </li>
                        </ol>
                    </section>

                    <ExecutiveSummaryCard :report="report" />
                    <ScoreBreakdownCard :assessment="report.assessment" />

                    <section aria-labelledby="top-opportunities-heading">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <h2 id="top-opportunities-heading" class="text-lg font-bold text-slate-950">Top opportunities</h2>
                            <p class="text-sm text-slate-500">Ranked by business impact</p>
                        </div>
                        <RecommendationCard
                            v-if="primaryRecommendation"
                            class="mt-4"
                            :recommendation="primaryRecommendation"
                            primary
                            :confidence="confidenceFor(primaryRecommendation)"
                            :effort="primaryRecommendation.effort"
                            :has-calculation="hasCalculation(primaryRecommendation)"
                            @see-calculation="openCalculation(primaryRecommendation.opportunity_type)"
                            @contact-sales="handleSalesContact"
                        />
                        <div v-if="secondaryRecommendations.length" class="mt-4 grid gap-4 sm:grid-cols-2">
                            <RecommendationCard
                                v-for="(recommendation, index) in secondaryRecommendations"
                                :key="index"
                                :recommendation="recommendation"
                                :confidence="confidenceFor(recommendation)"
                                :effort="recommendation.effort"
                                :has-calculation="hasCalculation(recommendation)"
                                @see-calculation="openCalculation(recommendation.opportunity_type)"
                            />
                        </div>
                    </section>

                    <RecommendationsDisclosure v-if="remainingRecommendations.length" :recommendations="remainingRecommendations" :calculation-explanations="explanations" @see-calculation="openCalculation" />

                    <CapabilityAndImprovements :assessment="report.assessment" />

                </div>

                <CalculationModal :open="activeExplanation !== null" :explanation="activeExplanation" @close="closeCalculation" />

                <div v-if="salesModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4" role="dialog" aria-modal="true" aria-labelledby="sales-contact-heading">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                        <h2 id="sales-contact-heading" class="text-lg font-semibold text-slate-900">We’ll take it from here</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            A sales team member will be contacting them at <span class="font-semibold text-slate-900">{{ contactEmail }}</span> shortly.
                        </p>
                        <button type="button" class="mt-5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700" @click="closeSalesContact">
                            Got it
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
