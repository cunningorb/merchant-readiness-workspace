<script setup>
import { ref, useId } from 'vue';

defineProps({
    methodology: {
        type: String,
        default: '',
    },
    benchmarkVersion: {
        type: String,
        default: '',
    },
});

const expanded = ref(false);
const panelId = useId();
</script>

<template>
    <div data-testid="benchmark-methodology">
        <button
            type="button"
            :aria-expanded="expanded ? 'true' : 'false'"
            :aria-controls="panelId"
            class="inline-flex items-center gap-2 text-sm font-medium text-blue-700 transition hover:text-blue-800"
            @click="expanded = !expanded"
        >
            How this benchmark works
            <svg
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': expanded }"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div v-show="expanded" :id="panelId" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <p>{{ methodology }}</p>
            <p v-if="benchmarkVersion" class="mt-2 text-xs text-slate-400">Benchmark version {{ benchmarkVersion }}</p>
        </div>
    </div>
</template>
