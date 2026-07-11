<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: {
        type: String,
        required: true,
    },
});

const LEVEL_CLASSES = {
    high: 'border-green-200 bg-green-50 text-green-700',
    medium: 'border-blue-200 bg-blue-50 text-blue-700',
    low: 'border-slate-200 bg-slate-50 text-slate-600',
};

const label = computed(() => `${props.level.charAt(0).toUpperCase()}${props.level.slice(1)} confidence`);
const classes = computed(() => LEVEL_CLASSES[props.level] ?? LEVEL_CLASSES.low);
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium" :class="classes">
        {{ label }}
    </span>
</template>
