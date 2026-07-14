<script setup>
defineProps({
    companyName: { type: String, required: true },
    backHref: { type: String, default: null },
});

defineEmits(['share', 'download', 'contact-sales']);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur print:static print:hidden">
        <div class="mx-auto flex max-w-[1165px] flex-col gap-3 px-6 py-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <a :href="backHref ?? '#'" class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700" aria-label="Back">
                    <svg viewBox="0 0 20 20" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M7.5 4.5H4.5v3" />
                        <path d="M4.8 7.2A6 6 0 1 1 6 15" stroke-linecap="round" />
                    </svg>
                </a>
                <p class="text-sm font-bold text-slate-950">Readiness Report</p>
                <span class="h-5 w-px bg-slate-200" aria-hidden="true"></span>
                <p class="truncate text-sm text-slate-500">{{ companyName }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="$emit('share')">Share</button>
                <button type="button" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="$emit('download')">Download PDF</button>
                <button type="button" data-testid="header-contact-sales" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700" @click="$emit('contact-sales')">Talk to the team</button>
            </div>
        </div>
    </header>
</template>
