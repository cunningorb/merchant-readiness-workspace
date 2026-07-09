<script setup>
defineProps({
    appName: {
        type: String,
        default: 'Merchant Readiness Workspace',
    },
});
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-white">
        <section class="mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center px-6 py-16 sm:px-8 lg:px-10">
            <div class="max-w-3xl">
                <p class="mb-5 inline-flex rounded-full border border-blue-300/30 bg-blue-400/10 px-4 py-2 text-sm font-medium text-blue-100">
                    Deployment milestone
                </p>
                <h1 class="text-4xl font-bold tracking-tight sm:text-6xl">
                    Merchant returns readiness, built on Laravel and Inertia.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                    {{ appName }} will help ecommerce merchants assess operational maturity, understand scoring, and share actionable recommendations before a sales conversation.
                </p>
                <a href="/assessment" class="mt-8 inline-flex rounded-xl bg-blue-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-950/40 transition hover:bg-blue-400">
                    Start assessment
                </a>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-blue-950/20">
                    <p class="text-sm font-medium text-blue-200">Milestone 0</p>
                    <p class="mt-2 text-2xl font-semibold">Deploy first</p>
                    <p class="mt-3 text-sm leading-6 text-slate-300">Render should prove app boot, assets, `/health`, and database connectivity before features continue.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-blue-950/20">
                    <p class="text-sm font-medium text-blue-200">Stack</p>
                    <p class="mt-2 text-2xl font-semibold">Laravel + Vue</p>
                    <p class="mt-3 text-sm leading-6 text-slate-300">The app uses Laravel, Inertia, Vue, Tailwind, queues, and PostgreSQL on Render.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-2xl shadow-blue-950/20">
                    <p class="text-sm font-medium text-blue-200">Guardrails</p>
                    <p class="mt-2 text-2xl font-semibold">Docs locked</p>
                    <p class="mt-3 text-sm leading-6 text-slate-300">Architecture, product, design, and execution rules are captured for coding agents.</p>
                </div>
            </div>
        </section>
    </main>
</template>
