<script setup>
import { computed } from 'vue';

const props = defineProps({
    assessment: { type: Object, required: true },
    recommendations: { type: Array, default: () => [] },
});

const LABELS = {
    return_policy: 'Policy',
    manual_operations: 'Automation',
    exchanges: 'Exchanges',
    platform: 'Tooling',
};

const capabilities = computed(() => Object.entries(props.assessment.section_scores ?? {})
    .map(([key, value]) => ({ key, label: LABELS[key] ?? key, ...value }))
    .sort((a, b) => a.score - b.score));

const EFFORT_ORDER = { low: 0, medium: 1, high: 2 };

const improvements = computed(() => [...props.recommendations]
    .sort((a, b) => (EFFORT_ORDER[a.effort] ?? 3) - (EFFORT_ORDER[b.effort] ?? 3))
    .slice(0, 6));
</script>

<template>
    <section class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-950">Capability map</h2>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <div v-for="item in capabilities" :key="item.key" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ item.tier }}</p>
                    <p class="mt-3 text-2xl font-bold text-blue-700">{{ item.score }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-950">Recommended improvements</h2>
            <ol class="mt-5 space-y-3">
                <li v-for="(item, index) in improvements" :key="item.title" class="flex gap-3 text-sm text-slate-700">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">{{ index + 1 }}</span>
                    <span>{{ item.title }}</span>
                </li>
            </ol>
        </div>
    </section>
</template>
