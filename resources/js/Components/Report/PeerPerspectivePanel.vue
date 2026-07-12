<script setup>
import { computed } from 'vue';
import BenchmarkMethodologyDisclosure from './BenchmarkMethodologyDisclosure.vue';

const props = defineProps({
    comparisons: {
        type: Array,
        default: () => [],
    },
});

// The methodology, source label and version are set-level rather than
// per-metric. All comparisons on a report currently come from the same
// benchmark set, so the disclosure is driven by the first comparison's
// fields; if future work ever mixes sets this would need per-group handling.
const methodologySource = computed(() => props.comparisons[0] ?? null);

function humanizeSourceType(sourceType) {
    if (!sourceType) {
        return null;
    }

    const spaced = String(sourceType).replaceAll('_', ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
</script>

<template>
    <section
        v-if="comparisons.length"
        data-testid="peer-perspective"
        aria-labelledby="peer-perspective-heading"
    >
        <h2 id="peer-perspective-heading" class="text-lg font-semibold text-slate-900">Peer perspective</h2>
        <p class="mt-1 text-sm text-slate-500">
            How your questionnaire answers compare to configured reference ranges.
        </p>

        <ul class="mt-4 space-y-3">
            <li
                v-for="item in comparisons"
                :key="item.metric_key"
                data-testid="peer-comparison-row"
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ item.label }}</h3>
                        <dl class="mt-2 flex flex-col gap-1 text-sm text-slate-600 sm:flex-row sm:gap-6">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Your value</dt>
                                <dd class="font-medium text-slate-900">{{ item.merchant_value_formatted }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Reference range</dt>
                                <dd class="font-medium text-slate-900">{{ item.range_formatted }}</dd>
                            </div>
                        </dl>
                    </div>
                    <span
                        data-testid="peer-source-label"
                        class="inline-flex shrink-0 items-center self-start rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600"
                    >
                        {{ item.source_label }}
                        <span v-if="item.source_type"> · {{ humanizeSourceType(item.source_type) }}</span>
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-600">{{ item.interpretation }}</p>
            </li>
        </ul>

        <div v-if="methodologySource" class="mt-4">
            <BenchmarkMethodologyDisclosure
                :methodology="methodologySource.methodology"
                :benchmark-version="methodologySource.benchmark_version"
            />
        </div>
    </section>
</template>
