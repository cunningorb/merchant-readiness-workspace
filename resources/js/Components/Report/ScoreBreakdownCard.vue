<script setup>
import { computed } from 'vue';

const props = defineProps({
    assessment: { type: Object, required: true },
});

const SECTION_META = {
    return_policy: { label: 'Policy design', caption: 'How clear and contextual the return policy is.', weight: 30 },
    manual_operations: { label: 'Automation', caption: 'How much returns work still depends on manual handling.', weight: 30 },
    exchanges: { label: 'Exchange readiness', caption: 'How well the flow encourages exchanges over refunds.', weight: 20 },
    platform: { label: 'Return tooling', caption: 'How mature the current returns tooling stack is.', weight: 20 },
};

const rows = computed(() => Object.entries(props.assessment.section_scores ?? {})
    .map(([key, value]) => ({ key, ...value, ...(SECTION_META[key] ?? { label: key, caption: '', weight: 0 }) }))
    .sort((a, b) => b.weight - a.weight || a.score - b.score));

function isEstablished(row) {
    return ['Established', 'Advanced'].includes(row.tier);
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="score-breakdown-heading">
        <div class="flex items-center justify-between gap-4">
            <h2 id="score-breakdown-heading" class="text-lg font-bold text-slate-950">Score breakdown</h2>
            <p class="text-sm text-slate-500">{{ rows.length }} weighted categories</p>
        </div>
        <div class="mt-5 space-y-5">
            <div v-for="row in rows" :key="row.key" class="grid grid-cols-[210px,1fr,48px] items-center gap-4">
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ row.label }}</p>
                    <p class="text-xs text-slate-500">{{ row.caption }}</p>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full" :class="isEstablished(row) ? 'bg-blue-600' : 'bg-amber-500'" :style="{ width: `${Math.max(0, Math.min(100, row.score))}%` }" />
                </div>
                <p class="text-right text-sm font-bold text-slate-950">{{ row.score }}</p>
            </div>
        </div>
    </section>
</template>
