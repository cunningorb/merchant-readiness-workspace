<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    assessments: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const TIER_COLORS = {
    Foundational: { pill: 'border-red-300 bg-red-50 text-red-700' },
    Developing: { pill: 'border-orange-300 bg-orange-50 text-orange-700' },
    Established: { pill: 'border-yellow-300 bg-yellow-50 text-yellow-700' },
    Advanced: { pill: 'border-green-300 bg-green-50 text-green-700' },
};

function tierColors(tier) {
    return TIER_COLORS[tier] ?? TIER_COLORS.Foundational;
}

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get(route('dashboard'), {
        search: search.value || undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
    }, { preserveState: true, replace: true });
}

function sortBy(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';

    router.get(route('dashboard'), {
        search: props.filters.search || undefined,
        sort: column,
        direction,
    }, { preserveState: true, replace: true });
}

function sortIndicator(column) {
    if (props.filters.sort !== column) {
        return '';
    }

    return props.filters.direction === 'asc' ? '▲' : '▼';
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Prospects" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-900">Prospects</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <form class="flex gap-2" @submit.prevent="applySearch">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search by company or contact name"
                                class="w-full max-w-sm rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900 outline-none ring-blue-500 transition focus:ring-2"
                            >
                            <button
                                type="submit"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                            >
                                Search
                            </button>
                        </form>

                        <Link
                            :href="route('assessment.wizard')"
                            class="inline-flex justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                            New assessment
                        </Link>
                    </div>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-500">
                                <th class="cursor-pointer select-none py-2 pr-4 font-medium" @click="sortBy('company')">
                                    Company / Contact {{ sortIndicator('company') }}
                                </th>
                                <th class="cursor-pointer select-none py-2 pr-4 font-medium" @click="sortBy('tier')">
                                    Tier / Score {{ sortIndicator('tier') }}
                                </th>
                                <th class="cursor-pointer select-none py-2 pr-4 font-medium" @click="sortBy('submitted_at')">
                                    Submitted {{ sortIndicator('submitted_at') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="assessments.data.length === 0">
                                <td colspan="3" class="py-6 text-center text-slate-500">No submitted assessments match.</td>
                            </tr>
                            <tr
                                v-for="assessment in assessments.data"
                                :key="assessment.id"
                                class="border-b border-slate-100 last:border-0 hover:bg-slate-50"
                            >
                                <td class="py-3 pr-4">
                                    <Link :href="route('workspace.assessments.show', assessment.id)" class="font-medium text-slate-900 hover:text-blue-600">
                                        {{ assessment.merchant.company_name }}
                                    </Link>
                                    <p class="text-xs text-slate-500">{{ assessment.merchant.contact_name }}</p>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="tierColors(assessment.overall_tier).pill">
                                        {{ assessment.overall_tier }}
                                    </span>
                                    <span class="ml-2 text-slate-500">{{ assessment.overall_score }}/100</span>
                                </td>
                                <td class="py-3 pr-4 text-slate-600">{{ formatDate(assessment.submitted_at) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="assessments.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                        <Link
                            v-for="link in assessments.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="rounded-lg px-3 py-1 text-sm"
                            :class="[
                                link.active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                                link.url ? '' : 'pointer-events-none opacity-40',
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
