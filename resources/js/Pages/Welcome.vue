<script setup>
defineProps({
    appName: {
        type: String,
        default: 'Merchant Readiness Workspace',
    },
});
</script>

<template>
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <section class="mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center px-6 py-16 sm:px-8 lg:px-10">
            <div class="max-w-3xl">
                <p class="mb-5 inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                    Deployment milestone
                </p>
                <h1 class="text-4xl font-bold tracking-tight sm:text-6xl">
                    Merchant returns readiness, built on Laravel and Inertia.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                    {{ appName }} will help ecommerce merchants assess operational maturity, understand scoring, and share actionable recommendations before a sales conversation.
                </p>
                <a href="/assessment" class="mt-8 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                    Start assessment
                </a>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-blue-600">Milestone 0</p>
                    <p class="mt-2 text-2xl font-semibold">Deploy first</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Render should prove app boot, assets, `/health`, and database connectivity before features continue.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-blue-600">Stack</p>
                    <p class="mt-2 text-2xl font-semibold">Laravel + Vue</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">The app uses Laravel, Inertia, Vue, Tailwind, queues, and PostgreSQL on Render.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-blue-600">Guardrails</p>
                    <p class="mt-2 text-2xl font-semibold">Docs locked</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Architecture, product, design, and execution rules are captured for coding agents.</p>
                </div>
            </div>
        </section>
    </main>
</template>
