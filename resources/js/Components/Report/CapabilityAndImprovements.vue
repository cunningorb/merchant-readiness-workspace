<script setup>
import { computed } from 'vue';

const props = defineProps({
    assessment: { type: Object, required: true },
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

</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-950">Capability map</h2>
        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="item in capabilities" :key="item.key" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ item.tier }}</p>
                <p class="mt-3 text-2xl font-bold text-blue-700">{{ item.score }}</p>
            </div>
        </div>
    </section>
</template>
