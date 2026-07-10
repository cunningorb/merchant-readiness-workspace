<script setup>
import { computed } from 'vue';
import AssessmentResults from '../Assessment/AssessmentResults.vue';

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

function printReport() {
    window.print();
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-6 py-10 text-white sm:px-8">
        <section class="mx-auto max-w-5xl">
            <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="mb-2 inline-flex rounded-full border border-blue-300/30 bg-blue-400/10 px-4 py-2 text-sm font-medium text-blue-100">
                        Merchant Readiness Report
                    </p>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ report.merchant.company_name }}</h1>
                    <p v-if="profileLine" class="mt-2 text-slate-400">{{ profileLine }}</p>
                    <p class="mt-1 text-sm text-slate-500">Prepared on {{ preparedOn }}</p>
                </div>
                <button
                    type="button"
                    class="print:hidden rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/5"
                    @click="printReport"
                >
                    Print report
                </button>
            </div>

            <AssessmentResults :result="result" :catalog="catalog" />
        </section>
    </main>
</template>

<style>
@media print {
    body {
        background: white !important;
        color: black !important;
    }

    * {
        color: black !important;
        background: white !important;
        border-color: #d1d5db !important;
    }

    svg text {
        fill: black !important;
    }
}
</style>
