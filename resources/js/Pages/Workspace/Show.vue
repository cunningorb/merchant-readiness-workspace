<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AssessmentResults from '../Assessment/AssessmentResults.vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    catalog: {
        type: Array,
        required: true,
    },
});

const result = computed(() => ({
    assessment: props.report.assessment,
    recommendations: props.report.recommendations,
}));

const profileLine = computed(() => {
    const parts = [];

    if (props.report.merchant.contact_name) {
        parts.push(props.report.merchant.contact_name);
    }
    if (props.report.merchant.contact_email) {
        parts.push(props.report.merchant.contact_email);
    }
    if (props.report.merchant.website) {
        parts.push(props.report.merchant.website);
    }

    return parts.join(' · ');
});

const submittedOn = computed(() => new Date(props.report.submitted_at).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}));
</script>

<template>
    <Head :title="report.merchant.company_name" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('dashboard')" class="text-sm font-medium text-blue-600 hover:text-blue-700">&larr; Back to prospects</Link>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ report.merchant.company_name }}</h1>
                    <p v-if="profileLine" class="mt-1 text-slate-600">{{ profileLine }}</p>
                    <p class="mt-1 text-sm text-slate-500">Submitted {{ submittedOn }}</p>
                </div>

                <AssessmentResults :result="result" :catalog="catalog" />

                <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Talking Points</h3>
                    <ol class="mt-4 space-y-4">
                        <li v-for="(point, index) in report.talking_points" :key="index" class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">{{ index + 1 }}</span>
                            <div>
                                <p class="font-medium text-slate-900">{{ point.title }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ point.description }}</p>
                                <p class="mt-1 text-sm text-slate-500">Expected impact: {{ point.expected_impact }}</p>
                            </div>
                        </li>
                    </ol>
                    <p v-if="report.talking_points.length === 0" class="mt-4 text-sm text-slate-500">No recommendations to highlight.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
