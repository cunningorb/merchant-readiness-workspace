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

const statRange = computed(() => (kind.value === 'monetary' ? monetaryRange.value : quantifiedRange.value));
const switchLabel = computed(() => {
    if (props.opportunity.type === 'retained_revenue') {
        return 'exchange-first automation';
    }

    if (props.opportunity.type === 'manual_work_savings') {
        return 'low-risk return automation';
    }

    if (props.opportunity.type === 'support_contact_reduction') {
        return 'clearer policy routing';
    }

    return 'a stronger returns workflow';
});

function compactMoney(value) {
    return `$${Math.round(value / 1000).toLocaleString('en-US')}K`;
}

const impactChip = computed(() => {
    if (kind.value !== 'monetary') {
        return `+ ${statRange.value}`;
    }

    return `+ ${compactMoney(props.opportunity.minimum_value)} - ${compactMoney(props.opportunity.maximum_value)} est. annual revenue`;
});
</script>

<template>
    <section
        data-testid="opportunity-hero"
        class="rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-50 via-white to-white p-6 shadow-sm sm:p-8"
        aria-labelledby="opportunity-hero-heading"
    >
        <div class="grid gap-8 md:grid-cols-[minmax(0,1fr),280px] md:items-center">
            <div>
                <p class="inline-flex items-center rounded-full border border-blue-200 bg-blue-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700">
                    <span aria-hidden="true">⚡</span>
                    <span class="ml-1">Recommended switch</span>
                </p>

                <h2 v-if="kind === 'monetary'" id="opportunity-hero-heading" class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                    Your returns could be earning more with <span class="text-blue-600">{{ switchLabel }}</span>.
                </h2>

                <template v-else-if="kind === 'quantified'">
                    <h2 id="opportunity-hero-heading" class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                        {{ opportunity.title }}
                    </h2>
                </template>

                <h2 v-else id="opportunity-hero-heading" class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                    {{ opportunity.title }}
                </h2>

                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ opportunity.summary }}</p>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span v-if="kind !== 'fallback'" class="inline-flex items-center rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">↗ {{ impactChip }}</span>
                    <ConfidenceBadge :level="opportunity.confidence" />
                    <EffortBadge v-if="opportunity.effort" :level="opportunity.effort" />
                </div>

                <p v-if="kind !== 'fallback'" class="mt-4 text-xs text-slate-500">
                    Estimated range based on your answers and clearly labeled assumptions — not a promise of results.
                </p>
            </div>

            <div class="flex flex-col gap-3 md:items-end">
                <button
                    type="button"
                    data-testid="sales-contact-link"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    @click="$emit('contact-sales')"
                >
                    Talk to the team
                </button>
                <button
                    v-if="hasCalculation"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-white px-5 py-2.5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50"
                    @click="$emit('see-calculation')"
                >
                    See the calculation
                </button>
            </div>
        </div>
    </section>
</template>
