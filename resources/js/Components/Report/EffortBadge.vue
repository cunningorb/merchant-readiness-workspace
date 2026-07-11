<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: {
        type: String,
        required: true,
    },
});

const LEVEL_CLASSES = {
    low: 'border-green-200 bg-green-50 text-green-700',
    medium: 'border-amber-200 bg-amber-50 text-amber-700',
    high: 'border-slate-200 bg-slate-50 text-slate-600',
};

const label = computed(() => `${props.level.charAt(0).toUpperCase()}${props.level.slice(1)} effort`);
const classes = computed(() => LEVEL_CLASSES[props.level] ?? LEVEL_CLASSES.high);
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium" :class="classes">
        {{ label }}
    </span>
</template>
