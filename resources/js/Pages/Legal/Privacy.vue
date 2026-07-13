<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <article class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-blue-700">Privacy, without the fog</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Privacy Policy</h1>
            <p class="mt-4 text-sm leading-6 text-slate-600">
                This product follows GDPR principles: only ask for what helps the assessment, explain how it is used, avoid customer personal data, and keep results easy to understand.
            </p>

            <div class="mt-8 space-y-6 text-sm leading-6 text-slate-700">
                <section>
                    <h2 class="text-base font-semibold text-slate-950">What we collect</h2>
                    <p class="mt-2">We collect the answers you give in the assessment: company context, contact email, catalog and returns details, platform/tooling choices, and optional import metadata.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Anonymous assessments</h2>
                    <p class="mt-2">You can start without an account. Submitted answers are still stored so the app can generate your score, recommendations, opportunity ranges, report, and review context.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Optional store data</h2>
                    <p class="mt-2">CSV and demo imports use store-level catalog, order, return, inventory, and location signals. They are designed to avoid customer identity data like names, addresses, phone numbers, or payment details.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Report links</h2>
                    <p class="mt-2">Reports use non-guessable links. Anyone with the link can view the report, so treat report URLs like confidential share links.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Future read-only integrations</h2>
                    <p class="mt-2">A future Shopify connection is expected to use read-only scopes for products, returns, inventory, and aggregate operating signals. It should not request or store customer personal data unless the product scope changes and this notice is updated.</p>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-950">Your choices</h2>
                    <p class="mt-2">You can skip store-data uploads and complete the assessment manually. For access, correction, or deletion requests, contact the workspace operator with the company name, contact email, or report link involved.</p>
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
