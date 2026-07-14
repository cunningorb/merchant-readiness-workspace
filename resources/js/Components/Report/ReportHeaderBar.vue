<script setup>
import { ref } from 'vue';

defineProps({
    companyName: { type: String, required: true },
    shareUrl: { type: String, required: true },
    backHref: { type: String, default: null },
});

defineEmits(['download', 'contact-sales']);

const shareOpen = ref(false);
const copied = ref(false);

async function copyShareUrl(shareUrl) {
    await navigator.clipboard?.writeText(shareUrl);
    copied.value = true;
}

function toggleShare() {
    shareOpen.value = !shareOpen.value;
    copied.value = false;
}
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
                <p class="text-sm font-bold text-slate-950">Commerce Cartographer</p>
                <span class="h-5 w-px bg-slate-200" aria-hidden="true"></span>
                <p class="truncate text-sm text-slate-500">{{ companyName }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" aria-haspopup="dialog" :aria-expanded="shareOpen" @click="toggleShare">
                        Share
                    </button>
                    <div v-if="shareOpen" class="absolute right-0 top-full z-40 mt-2 w-80 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-xl" role="dialog" aria-label="Share report">
                        <p class="text-sm font-semibold text-slate-950">Share this report</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Anyone with this secure link can view the report.</p>
                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            <p class="truncate">{{ shareUrl }}</p>
                        </div>
                        <button type="button" data-testid="copy-share-link" class="mt-3 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700" @click="copyShareUrl(shareUrl)">
                            {{ copied ? 'Copied link' : 'Copy shareable link' }}
                        </button>
                    </div>
                </div>
                <button type="button" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="$emit('download')">Download PDF</button>
                <button type="button" data-testid="header-contact-sales" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700" @click="$emit('contact-sales')">Talk to the team</button>
            </div>
        </div>
    </header>
</template>
