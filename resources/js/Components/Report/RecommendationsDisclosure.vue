<script setup>
import { computed, ref, useId } from 'vue';
import RecommendationCard from './RecommendationCard.vue';

const props = defineProps({
    recommendations: {
        type: Array,
        required: true,
    },
    calculationExplanations: {
        type: Object,
        default: () => ({}),
    },
});

defineEmits(['see-calculation']);

const expanded = ref(false);
const panelId = useId();

const label = computed(() => {
    const count = props.recommendations.length;

    return `View all ${count} recommendation${count === 1 ? '' : 's'}`;
});

function explanationFor(recommendation) {
    return recommendation.opportunity_type != null
        && props.calculationExplanations[recommendation.opportunity_type] != null;
}

function confidenceFor(recommendation) {
    return props.calculationExplanations[recommendation.opportunity_type]?.confidence ?? null;
}
</script>

<template>
    <div>
        <button
            type="button"
            :aria-expanded="expanded ? 'true' : 'false'"
            :aria-controls="panelId"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
            @click="expanded = !expanded"
        >
            {{ label }}
            <svg
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-4 w-4 text-slate-500 transition-transform"
                :class="{ 'rotate-180': expanded }"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div v-show="expanded" :id="panelId" class="mt-4 grid gap-4 sm:grid-cols-2">
            <RecommendationCard
                v-for="(recommendation, index) in recommendations"
                :key="index"
                :recommendation="recommendation"
                :confidence="confidenceFor(recommendation)"
                :effort="recommendation.effort"
                :has-calculation="explanationFor(recommendation)"
                @see-calculation="$emit('see-calculation', recommendation.opportunity_type)"
            />
        </div>
    </div>
</template>
