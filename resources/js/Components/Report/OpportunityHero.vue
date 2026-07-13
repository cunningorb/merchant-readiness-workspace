<script setup>
import { computed } from 'vue';
import ConfidenceBadge from './ConfidenceBadge.vue';
import EffortBadge from './EffortBadge.vue';

const props = defineProps({
    opportunity: {
        type: Object,
        required: true,
    },
    hasCalculation: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['see-calculation', 'contact-sales']);

const UNIT_LABELS = {
    hours_per_week: 'hours per week',
    contacts_per_month: 'contacts per month',
};

function formatDollars(value) {
    return `$${Math.round(value).toLocaleString('en-US')}`;
}

function formatNumber(value) {
    return Math.round(value).toLocaleString('en-US');
}

const kind = computed(() => props.opportunity.kind);

const monetaryRange = computed(() =>
    `${formatDollars(props.opportunity.minimum_value)}–${formatDollars(props.opportunity.maximum_value)}`);

const quantifiedRange = computed(() => {
    const unit = UNIT_LABELS[props.opportunity.unit] ?? '';
    const range = `${formatNumber(props.opportunity.minimum_value)}–${formatNumber(props.opportunity.maximum_value)}`;

    return unit ? `${range} ${unit}` : range;
});
</script>

<template>
    <section
        data-testid="opportunity-hero"
        class="rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-6 shadow-sm sm:p-8"
        aria-labelledby="opportunity-hero-heading"
    >
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Your estimated opportunity</p>

        <h2 v-if="kind === 'monetary'" id="opportunity-hero-heading" class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Your company could get back
            <span class="text-blue-700">{{ monetaryRange }}</span>
            in revenue this year
        </h2>

        <template v-else-if="kind === 'quantified'">
            <h2 id="opportunity-hero-heading" class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                {{ opportunity.title }}
            </h2>
            <p class="mt-2 text-2xl font-bold text-blue-700 sm:text-3xl">{{ quantifiedRange }}</p>
        </template>

        <h2 v-else id="opportunity-hero-heading" class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            {{ opportunity.title }}
        </h2>

        <p class="mt-3 max-w-2xl text-slate-600">{{ opportunity.summary }}</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <ConfidenceBadge :level="opportunity.confidence" />
            <EffortBadge v-if="opportunity.effort" :level="opportunity.effort" />
            <button
                type="button"
                data-testid="sales-contact-link"
                class="inline-flex items-center rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700"
                @click="$emit('contact-sales')"
            >
                Talk to the team
            </button>
            <button
                v-if="hasCalculation"
                type="button"
                class="inline-flex items-center rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-50"
                @click="$emit('see-calculation')"
            >
                See calculation
            </button>
        </div>

        <p v-if="kind !== 'fallback'" class="mt-4 text-xs text-slate-500">
            Estimated range based on your answers and clearly labeled assumptions — not a promise of results.
        </p>
    </section>
</template>
