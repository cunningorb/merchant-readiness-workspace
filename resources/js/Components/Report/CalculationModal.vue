<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import ConfidenceBadge from './ConfidenceBadge.vue';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    explanation: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const titleId = useId();
const dialogElement = ref(null);
let previouslyFocusedElement = null;
let previousBodyOverflow = '';

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

function activate() {
    previouslyFocusedElement = document.activeElement;
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);

    nextTick(() => {
        dialogElement.value?.focus();
    });
}

function deactivate() {
    document.body.style.overflow = previousBodyOverflow;
    document.removeEventListener('keydown', onKeydown);

    if (previouslyFocusedElement instanceof HTMLElement) {
        previouslyFocusedElement.focus();
    }
    previouslyFocusedElement = null;
}

watch(() => props.open, (open, wasOpen) => {
    if (open && !wasOpen) {
        activate();
    } else if (!open && wasOpen) {
        deactivate();
    }
});

onBeforeUnmount(() => {
    if (props.open) {
        deactivate();
    }
});

function humanize(key) {
    const spaced = String(key).replaceAll('_', ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

function toPercent(fraction) {
    return `${Math.round(fraction * 100)}%`;
}

function isFraction(value) {
    return typeof value === 'number' && value >= 0 && value <= 1;
}

function formatValue(value) {
    if (Array.isArray(value)) {
        return value.length ? value.join(', ') : 'None';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (value !== null && typeof value === 'object' && 'min' in value && 'max' in value) {
        return isFraction(value.min) && isFraction(value.max)
            ? `${toPercent(value.min)}–${toPercent(value.max)}`
            : `${value.min}–${value.max}`;
    }

    if (isFraction(value)) {
        return toPercent(value);
    }

    return value;
}

const lineItems = computed(() => {
    if (!props.explanation) {
        return [];
    }

    const inputs = Object.entries(props.explanation.inputs ?? {}).map(([key, value]) => ({
        key: `input-${key}`,
        label: humanize(key),
        value: formatValue(value),
        source: 'Your answer',
    }));

    const assumptions = Object.entries(props.explanation.assumptions ?? {}).map(([key, assumption]) => ({
        key: `assumption-${key}`,
        label: humanize(key),
        value: formatValue(assumption?.value ?? assumption),
        source: 'Configured assumption',
    }));

    return [...inputs, ...assumptions];
});
</script>

<template>
    <div v-if="open && explanation" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/50" aria-hidden="true" @click="emit('close')" />

        <div
            ref="dialogElement"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            tabindex="-1"
            class="relative max-h-full w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-xl focus:outline-none"
        >
            <div class="flex items-start justify-between gap-4">
                <h2 :id="titleId" class="text-lg font-semibold text-slate-900">
                    How we estimated: {{ explanation.title }}
                </h2>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-2.5 py-1 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
                    @click="emit('close')"
                >
                    Close
                </button>
            </div>

            <p class="mt-3 text-sm leading-6 text-slate-600">{{ explanation.formula_description }}</p>

            <div v-if="lineItems.length" class="mt-4">
                <h3 class="text-sm font-semibold text-slate-900">What went into this estimate</h3>
                <ul class="mt-2 space-y-2">
                    <li
                        v-for="item in lineItems"
                        :key="item.key"
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm"
                    >
                        <span class="font-medium text-slate-900">{{ item.label }}</span>
                        <span class="text-slate-600">{{ item.value }}</span>
                        <span class="w-full text-xs text-slate-500">{{ item.source }}</span>
                    </li>
                </ul>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <ConfidenceBadge :level="explanation.confidence" />
                <span class="text-xs text-slate-500">Formula version {{ explanation.formula_version }}</span>
            </div>

            <p class="mt-4 text-xs leading-5 text-slate-500">
                This is a heuristic estimate built from your answers and clearly labeled assumptions. It is not a
                prediction or a promise of results.
            </p>
        </div>
    </div>
</template>
