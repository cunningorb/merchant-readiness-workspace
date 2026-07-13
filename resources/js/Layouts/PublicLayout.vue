<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    contained: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8" aria-label="Main navigation">
                <Link href="/" class="text-sm font-bold tracking-tight text-slate-950">
                    Merchant Readiness
                </Link>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium text-slate-600">
                    <Link href="/assessment" class="transition hover:text-blue-700">Assessment</Link>
                    <Link href="/dashboard" class="transition hover:text-blue-700">Admin portal</Link>
                    <Link href="/login" class="rounded-full border border-slate-300 px-4 py-2 text-slate-700 transition hover:bg-slate-50">Log in</Link>
                </div>
            </nav>
        </header>

        <main :class="contained ? 'mx-auto w-full max-w-6xl px-6 py-12 sm:px-8' : ''">
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-6 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <p>Merchant Readiness Workspace. Quick gut check, clear next steps, no mystery math.</p>
                <div class="flex gap-4">
                    <Link href="/privacy" class="hover:text-blue-700">Privacy</Link>
                    <Link href="/terms" class="hover:text-blue-700">Terms</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
