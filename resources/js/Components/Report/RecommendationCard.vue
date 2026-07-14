<script setup>
import { computed } from 'vue';
import ConfidenceBadge from './ConfidenceBadge.vue';
import EffortBadge from './EffortBadge.vue';

const props = defineProps({
    recommendation: {
        type: Object,
        required: true,
    },
    primary: {
        type: Boolean,
        default: false,
    },
    confidence: {
        type: String,
        default: null,
    },
    effort: {
        type: String,
        default: null,
    },
    hasCalculation: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['see-calculation', 'contact-sales']);

const PRIORITY_CLASSES = {
    high: 'border-blue-300 bg-blue-100 text-blue-800',
    medium: 'border-blue-200 bg-blue-50 text-blue-600',
    low: 'border-slate-200 bg-slate-50 text-slate-500',
};

const priorityClasses = computed(() => PRIORITY_CLASSES[props.recommendation.priority] ?? PRIORITY_CLASSES.low);

const priorityLabel = computed(() => {
    const priority = props.recommendation.priority;

    return `${priority.charAt(0).toUpperCase()}${priority.slice(1)} priority`;
});

const EYEBROWS = {
    exchanges: 'Recommended vendor',
    catalog: 'Catalog',
    manual_operations: 'Automation',
    platform: 'Automation',
    return_policy: 'Policy',
};

const eyebrow = computed(() => {
    const category = props.recommendation.category ?? 'recommended action';

    return EYEBROWS[category] ?? category.replaceAll('_', ' ');
});
</script>

<template>
    <article
        data-testid="recommendation-card"
        class="rounded-2xl border bg-white shadow-sm"
        :class="primary ? 'border-blue-300 p-6 ring-1 ring-blue-200 sm:p-7' : 'border-slate-200 p-5'"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-bold uppercase tracking-wide text-blue-600">{{ eyebrow }}</p>
            <span class="rounded-full border px-2.5 py-0.5 text-xs font-medium" :class="priorityClasses">
                {{ priorityLabel }}
            </span>
        </div>

        <h3 class="mt-2 font-semibold text-slate-900" :class="primary ? 'text-xl' : 'text-base'">
            {{ recommendation.title }}
        </h3>
        <p class="mt-1 text-sm text-slate-600">{{ recommendation.description }}</p>

        <div class="mt-5 border-t border-slate-100 pt-4">
            <button
                v-if="primary"
                type="button"
                data-testid="primary-card-contact-sales"
                class="mb-4 inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 lg:w-1/2"
                @click="$emit('contact-sales')"
            >
                Talk to the team
            </button>
            <div class="flex flex-wrap items-center gap-2">
            <ConfidenceBadge v-if="confidence" :level="confidence" />
            <EffortBadge v-if="effort" :level="effort" />
            <button
                v-if="hasCalculation"
                type="button"
                class="inline-flex items-center rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-50"
                @click="$emit('see-calculation')"
            >
                See calculation
            </button>
            </div>
        </div>
    </article>
</template>
