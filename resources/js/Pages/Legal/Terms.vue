<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <article class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-blue-700">The ground rules</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Terms of Service</h1>
            <p class="mt-4 text-sm leading-6 text-slate-600">
                These terms cover the Merchant Readiness Workspace assessment, reports, recommendations, and optional import tools.
            </p>

            <div class="mt-8 space-y-6 text-sm leading-6 text-slate-700">
                <section>
                    <h2 class="text-base font-semibold text-slate-950">Informational estimates</h2>
                    <p class="mt-2">Scores, opportunity ranges, benchmarks, and recommendations are helpful estimates, not promises. They are not guaranteed financial outcomes, predictions, legal advice, or operational guarantees.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Your responsibility</h2>
                    <p class="mt-2">You are responsible for the answers and files you provide. Please do not upload customer personal data or anything you are not allowed to share.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Report sharing</h2>
                    <p class="mt-2">Report links are tokenized, but anyone with the link can open them. Share report URLs only with people who should see the assessment context.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">No live returns processing</h2>
                    <p class="mt-2">This app is for assessment and reporting. It does not process live returns, issue refunds, create exchanges, or replace your ecommerce returns platform.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Demo and imported data</h2>
                    <p class="mt-2">Demo datasets are synthetic. CSV imports follow this product's documented format, so third-party exports may need cleanup before they work.</p>
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
