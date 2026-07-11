<script setup>
import { computed } from 'vue';

const props = defineProps({
    plan: {
        type: Object,
        required: true,
    },
});

const thisWeek = computed(() => props.plan.this_week ?? []);
const planNext = computed(() => props.plan.plan_next ?? []);
const hasItems = computed(() => thisWeek.value.length > 0 || planNext.value.length > 0);
</script>

<template>
    <section v-if="hasItems" aria-labelledby="action-plan-heading">
        <h2 id="action-plan-heading" class="text-lg font-semibold text-slate-900">Your action plan</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div v-if="thisWeek.length" class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-blue-700">Do this this week</h3>
                <ul class="mt-3 space-y-2">
                    <li v-for="title in thisWeek" :key="title" class="flex gap-2 text-sm text-slate-700">
                        <span class="mt-0.5 text-blue-600" aria-hidden="true">→</span>
                        {{ title }}
                    </li>
                </ul>
            </div>
            <div v-if="planNext.length" class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Plan next</h3>
                <ul class="mt-3 space-y-2">
                    <li v-for="title in planNext" :key="title" class="flex gap-2 text-sm text-slate-700">
                        <span class="mt-0.5 text-slate-400" aria-hidden="true">→</span>
                        {{ title }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
