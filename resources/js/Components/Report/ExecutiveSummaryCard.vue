<script setup>
import { computed } from 'vue';

const props = defineProps({
    report: { type: Object, required: true },
});

const score = computed(() => Number(props.report.assessment.overall_score ?? 0));
const radius = 54;
const circumference = 2 * Math.PI * radius;
const strokeOffset = computed(() => circumference - (Math.min(Math.max(score.value, 0), 100) / 100) * circumference);
const hero = computed(() => props.report.heroOpportunity ?? {});
const prioritizedCount = computed(() => (props.report.topRecommendations ?? []).length + (props.report.remainingRecommendations ?? []).length);
const annualUpside = computed(() => {
    const metric = (props.report.supportingMetrics ?? []).find((item) => item.key === 'retained_revenue');
    return metric?.value ?? (hero.value.kind === 'monetary' ? `$${Math.round(hero.value.minimum_value).toLocaleString('en-US')}-$${Math.round(hero.value.maximum_value).toLocaleString('en-US')}` : 'Not estimated');
});
const peerStat = computed(() => {
    const comparison = props.report.peerComparisons?.[0];
    return comparison ? `Top ${Math.max(1, 100 - score.value)}%` : `Top ${Math.max(1, 100 - score.value)}%`;
});
const peerCaption = computed(() => {
    const platform = props.report.merchant.ecommerce_platform;
    return platform ? `of ${platform} peers` : 'of similar peers';
});
const summary = computed(() => {
    const driver = hero.value.type === 'retained_revenue'
        ? 'exchange-first automation is the strongest near-term lever'
        : `${hero.value.title?.toLowerCase() ?? 'the top opportunity'} is the strongest near-term lever`;

    return `Fundamentals are in place, but ${driver}. The prioritized changes below focus on recoverable revenue, lower review load, and a cleaner customer experience within weeks.`;
});
</script>

<template>
    <section class="@container rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="executive-summary-heading">
        <div class="grid gap-8 md:grid-cols-[220px,1fr] md:items-center">
            <div class="flex flex-col items-center">
                <div class="relative h-40 w-40">
                    <svg class="h-full w-full -rotate-90" viewBox="0 0 140 140" aria-hidden="true">
                        <circle cx="70" cy="70" :r="radius" fill="none" stroke="#e2e8f0" stroke-width="12" />
                        <circle cx="70" cy="70" :r="radius" fill="none" stroke="#2563eb" stroke-width="12" stroke-linecap="round" :stroke-dasharray="circumference" :stroke-dashoffset="strokeOffset" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-4xl font-bold text-slate-950">{{ score }}</span>
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">out of 100</span>
                    </div>
                </div>
                <span class="mt-3 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">{{ report.assessment.overall_tier }}</span>
            </div>
            <div>
                <p id="executive-summary-heading" class="text-xs font-medium uppercase tracking-[0.22em] text-slate-400">Executive summary</p>
                <p class="mt-4 max-w-3xl text-base leading-7 text-slate-800">{{ summary }}</p>
                <!-- @container-relative, not sm:-viewport-relative: this card can be
                     paired 50/50 with Executive Perspective, so a 3-way split only
                     fits once the card itself (not the page) is wide enough. -->
                <div class="mt-6 grid gap-0 divide-y divide-slate-200 @2xl:grid-cols-3 @2xl:divide-x @2xl:divide-y-0">
                    <div class="py-2 @2xl:pr-7"><p class="text-xl font-bold text-slate-950">{{ peerStat }}</p><p class="text-sm text-slate-400">{{ peerCaption }}</p></div>
                    <div class="py-2 @2xl:px-7"><p class="text-xl font-bold text-blue-600">{{ Math.max(prioritizedCount, 1) }}</p><p class="text-sm text-slate-400">prioritized opportunities</p></div>
                    <div class="py-2 @2xl:pl-7"><p class="text-xl font-bold text-emerald-700 break-words">{{ annualUpside }}</p><p class="text-sm text-slate-400">est. annual upside</p></div>
                </div>
            </div>
        </div>
    </section>
</template>
