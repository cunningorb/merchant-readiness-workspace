<script setup>
import { computed } from 'vue';
import ConfidenceBadge from './ConfidenceBadge.vue';

const props = defineProps({
    metrics: {
        type: Array,
        required: true,
    },
});

const visibleMetrics = computed(() => props.metrics.slice(0, 3));
</script>

<template>
    <div data-testid="metric-strip" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="metric in visibleMetrics"
            :key="metric.key"
            data-testid="supporting-metric"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <p class="text-sm font-medium text-slate-500">{{ metric.label }}</p>

            <template v-if="metric.source === 'score'">
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ metric.value }}
                    <span class="text-sm font-normal text-slate-500">out of 100</span>
                </p>
            </template>
            <template v-else>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ metric.value }}</p>
            </template>

            <div v-if="metric.confidence" class="mt-3">
                <ConfidenceBadge :level="metric.confidence" />
            </div>
        </div>
    </div>
</template>
