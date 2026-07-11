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
    reportUrl: {
        type: String,
        default: null,
    },
});

const RING_RADIUS = 52;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

const TIER_COLORS = {
    Foundational: { pill: 'border-red-300 bg-red-50 text-red-700', bar: 'bg-red-500', ring: '#ef4444' },
    Developing: { pill: 'border-orange-300 bg-orange-50 text-orange-700', bar: 'bg-orange-500', ring: '#f97316' },
    Established: { pill: 'border-yellow-300 bg-yellow-50 text-yellow-700', bar: 'bg-yellow-500', ring: '#eab308' },
    Advanced: { pill: 'border-green-300 bg-green-50 text-green-700', bar: 'bg-green-500', ring: '#22c55e' },
};

function tierColors(tier) {
    return TIER_COLORS[tier] ?? TIER_COLORS.Foundational;
}

const overallScore = computed(() => props.result.assessment.overall_score);
const overallTier = computed(() => props.result.assessment.overall_tier);
const ringOffset = computed(() => RING_CIRCUMFERENCE * (1 - overallScore.value / 100));
const scoreSummary = computed(() => `Score: ${overallScore.value} out of 100, ${overallTier.value} tier`);

function sectionLabel(key) {
    const section = props.catalog.find((candidate) => candidate.key === key);
    return section ? section.label : key;
}

const rankedSections = computed(() => Object.entries(props.result.assessment.ranked_sections));

const PRIORITY_COLORS = {
    high: 'border-blue-300 bg-blue-100 text-blue-800',
    medium: 'border-blue-200 bg-blue-50 text-blue-600',
    low: 'border-slate-200 bg-slate-50 text-slate-500',
};

function priorityClasses(priority) {
    return PRIORITY_COLORS[priority] ?? PRIORITY_COLORS.low;
}

const recommendations = computed(() => props.result.recommendations ?? []);
</script>

<template>
    <div class="mt-8 space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-start">
            <svg viewBox="0 0 120 120" class="h-32 w-32 shrink-0" role="img" :aria-label="scoreSummary">
                <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="10" />
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
                <text x="60" y="56" text-anchor="middle" class="fill-slate-900" style="font-size: 28px; font-weight: 700;">{{ overallScore }}</text>
                <text x="60" y="76" text-anchor="middle" class="fill-slate-500" style="font-size: 12px;">out of 100</text>
            </svg>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">Assessment submitted</h2>
                <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-medium" :class="tierColors(overallTier).pill">
                    {{ overallTier }}
                </span>
            </div>
        </div>

        <div v-if="reportUrl" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
            Your shareable report:
            <a :href="reportUrl" class="font-semibold underline decoration-blue-400 underline-offset-2 hover:text-blue-900">{{ reportUrl }}</a>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-slate-900">Score breakdown</h3>
            <p class="mt-1 text-sm text-slate-500">Ranked by opportunity — weakest area first.</p>
            <ul class="mt-4 space-y-3">
                <li v-for="[key, section] in rankedSections" :key="key">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-900">{{ sectionLabel(key) }}</span>
                        <span class="text-slate-600">{{ section.score }}/100</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full" :class="tierColors(section.tier).bar" :style="{ width: `${section.score}%` }" />
                    </div>
                </li>
            </ul>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-slate-900">Capability mapping</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="[key, section] in rankedSections"
                    :key="key"
                    class="rounded-2xl border px-4 py-3 text-center"
                    :class="tierColors(section.tier).pill"
                >
                    <p class="text-sm font-medium">{{ sectionLabel(key) }}</p>
                    <p class="mt-1 text-xs uppercase tracking-wide">{{ section.tier }}</p>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-slate-900">Recommended actions</h3>
            <p v-if="recommendations.length === 0" class="mt-4 text-sm text-green-600">
                No urgent opportunities identified — nice work.
            </p>
            <div v-else class="mt-4 grid gap-4 sm:grid-cols-2">
                <div v-for="(recommendation, index) in recommendations" :key="index" class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-blue-600">{{ recommendation.category }}</span>
                        <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :class="priorityClasses(recommendation.priority)">
                            {{ recommendation.priority }}
                        </span>
                    </div>
                    <h4 class="mt-2 font-semibold text-slate-900">{{ recommendation.title }}</h4>
                    <p class="mt-1 text-sm text-slate-600">{{ recommendation.description }}</p>
                    <p class="mt-2 text-sm text-slate-500">Expected impact: {{ recommendation.expected_impact }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
