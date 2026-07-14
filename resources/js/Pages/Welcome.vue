<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <section class="grid items-center gap-10 py-8 lg:grid-cols-[1.1fr_0.9fr] lg:py-16">
            <div>
                <p class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700">
                    Returns readiness checkup
                </p>
                <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-950 sm:text-6xl">
                    See what your returns process is quietly costing you.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                    Answer a few focused questions, get a plain-English score, and walk away with the moves most likely to clean up the chaos.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link href="/assessment" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                        Start assessment
                    </Link>
                    <Link href="/sample-report" class="rounded-xl border border-blue-200 bg-white px-5 py-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                        View sample report
                    </Link>
                </div>
                <p class="mt-4 text-sm text-slate-500">
                    No account needed. Optional imports use store-level signals, not customer PII.
                </p>
            </div>

            <div class="rounded-[2rem] border-2 border-blue-100 bg-gradient-to-br from-blue-50 via-white to-white p-8 shadow-sm">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">What you leave with</p>
                    <dl class="mt-6 space-y-5">
                        <div>
                            <dt class="font-semibold text-slate-950">A real readiness score</dt>
                            <dd class="mt-1 text-sm text-slate-600">A breakdown of where your policy, exchanges, ops, and tools are solid or getting messy.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-950">Opportunity ranges</dt>
                            <dd class="mt-1 text-sm text-slate-600">Practical estimates with the assumptions shown, so you can judge the math yourself.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-950">A shareable report</dt>
                            <dd class="mt-1 text-sm text-slate-600">Send a clean link to your team with the score, next steps, and peer context in one place.</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="grid gap-4 pb-12 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-blue-600">No black box</p>
                <p class="mt-2 text-xl font-semibold">The score explains itself</p>
                <p class="mt-3 text-sm leading-6 text-slate-600">Recommendations come from clear rules, and the calculations show their work.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-blue-600">Useful immediately</p>
                <p class="mt-2 text-xl font-semibold">Next steps, not fluff</p>
                <p class="mt-3 text-sm leading-6 text-slate-600">You get a short list of actions worth discussing before anyone books a demo.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-blue-600">Privacy-minded</p>
                <p class="mt-2 text-xl font-semibold">GDPR principles</p>
                <p class="mt-3 text-sm leading-6 text-slate-600">The data flow is built around minimization, transparency, and keeping customer PII out of imports.</p>
            </div>
        </section>
    </PublicLayout>
</template>
