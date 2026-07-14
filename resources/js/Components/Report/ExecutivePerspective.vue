<script setup>
import ConfidenceBadge from './ConfidenceBadge.vue';

defineProps({
    insight: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <section
        data-testid="executive-perspective"
        aria-labelledby="executive-perspective-heading"
        class="rounded-[2rem] border-2 border-blue-100 bg-gradient-to-br from-blue-50 via-white to-white p-6 shadow-sm sm:p-8"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Executive Perspective</p>
            <ConfidenceBadge :level="insight.confidence" />
        </div>

        <h3 id="executive-perspective-heading" class="mt-3 text-lg font-semibold text-slate-900">{{ insight.headline }}</h3>
        <p class="mt-3 text-sm leading-7 text-slate-700">{{ insight.executive_summary }}</p>

        <div class="mt-7 grid gap-6 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Why it matters</p>
                <p class="mt-2 text-base leading-7 text-slate-700">{{ insight.business_reasoning }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Getting started</p>
                <p class="mt-2 text-base leading-7 text-slate-700">{{ insight.implementation_notes }}</p>
            </div>
        </div>

        <p class="mt-7 text-xs leading-5 text-slate-500">Generated from your assessment data and publicly available business information. {{ insight.disclaimer }}</p>
    </section>
</template>
