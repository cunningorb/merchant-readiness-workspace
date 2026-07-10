<script setup>
import { computed } from 'vue';

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
    catalog: {
        type: Array,
        required: true,
    },
});

const RING_RADIUS = 52;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

const TIER_COLORS = {
    Foundational: { pill: 'border-rose-400/40 bg-rose-500/20 text-rose-200', bar: 'bg-rose-400', ring: '#fb7185' },
    Developing: { pill: 'border-amber-400/40 bg-amber-500/20 text-amber-200', bar: 'bg-amber-400', ring: '#fbbf24' },
    Established: { pill: 'border-blue-400/40 bg-blue-500/20 text-blue-200', bar: 'bg-blue-400', ring: '#60a5fa' },
    Advanced: { pill: 'border-emerald-400/40 bg-emerald-500/20 text-emerald-200', bar: 'bg-emerald-400', ring: '#34d399' },
};

function tierColors(tier) {
    return TIER_COLORS[tier] ?? TIER_COLORS.Foundational;
}

const overallScore = computed(() => props.result.assessment.overall_score);
const overallTier = computed(() => props.result.assessment.overall_tier);
const ringOffset = computed(() => RING_CIRCUMFERENCE * (1 - overallScore.value / 100));
</script>

<template>
    <div class="mt-8 space-y-8 rounded-3xl border border-white/10 bg-white/5 p-6">
        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-start">
            <svg viewBox="0 0 120 120" class="h-32 w-32 shrink-0">
                <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="10" />
                <circle
                    cx="60"
                    cy="60"
                    r="52"
                    fill="none"
                    :stroke="tierColors(overallTier).ring"
                    stroke-width="10"
                    stroke-linecap="round"
                    :stroke-dasharray="RING_CIRCUMFERENCE"
                    :stroke-dashoffset="ringOffset"
                    transform="rotate(-90 60 60)"
                />
                <text x="60" y="56" text-anchor="middle" class="fill-white" style="font-size: 28px; font-weight: 700;">{{ overallScore }}</text>
                <text x="60" y="76" text-anchor="middle" class="fill-slate-400" style="font-size: 12px;">out of 100</text>
            </svg>

            <div>
                <h2 class="text-xl font-semibold text-white">Assessment submitted</h2>
                <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-medium" :class="tierColors(overallTier).pill">
                    {{ overallTier }}
                </span>
            </div>
        </div>
    </div>
</template>
