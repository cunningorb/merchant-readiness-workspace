# Assessment Wizard: Autosave Instead of Manual Draft Save

Date: 2026-07-11

## Context

The wizard currently requires an explicit "Save and continue" / "Save final draft section" button click to persist each section's answers, with a separate "Submit assessment" action. Per explicit instruction, this manual-save model is being replaced with silent autosave: a "Next" button purely for navigation, with answers persisted automatically in the background as the user types/selects, with no visible save gate blocking progression.

This spec was produced and self-approved without an interactive design review, per explicit instruction to execute end-to-end without pausing. The instruction specified the core requirements directly (remove the save button, autosave after every change, single-flight with deferral, tolerate slow-server latency, stay invisible to the user); this document works out the precise mechanism satisfying all of them, including several correctness hazards that a naive implementation would hit.

## Requirements (as given)

- No more explicit "save draft" action — a "Next" button just navigates.
- Autosave fires after every change (not a timer) — the explicit constraints given were written against this choice.
- Only one save request in flight at a time. If a change arrives while a save is in flight, defer it — don't fire a second concurrent request, and don't drop the change either.
- Must tolerate a slow server (a save that takes a while to come back) without breaking the above guarantee or confusing the user.
- Invisible to the user — no blocking UI, no button gating tied to autosave itself.

## Correctness hazards a naive implementation hits (and how this design avoids them)

**1. Debounce, not one request per keystroke.** Firing a request on every single `input` event would flood the server on fast typing. A short debounce (600ms of quiet) collapses rapid changes into one save, while still satisfying "after every change" — the save reflects the latest state after the user pauses, not a stale mid-edit snapshot.

**2. Single-flight with deferral, precisely.** If a save is in flight and a new change arrives, the new change must not spawn a second concurrent request (violates single-flight) and must not be silently dropped (violates "every change gets saved"). Mechanism: a module-level `savePromise` reference. `runSave()` is a no-op if `savePromise` is already set; instead it marks "there's more to save" via `pendingSectionIndex`. When the in-flight save's `.finally()` runs, it checks that marker and immediately starts exactly one more save if set — never more than one request in flight, never a lost change.

**3. Section misattribution if the user navigates before the debounce fires.** Only the currently-displayed section's inputs exist in the DOM (the template's `v-else-if` chain renders one section at a time), so a user can only ever be editing the active section — but they can switch sections faster than the 600ms debounce window. If the save payload were built from "whatever `currentSectionIndex` is when the save actually runs" rather than "whatever section was active when the change was queued," a fast section-switch would save the wrong section's answers (or the right section's answers under the wrong assumption). Fix: capture `currentSectionIndex.value` into `pendingSectionIndex` at the moment a change is queued, and build the save payload from that captured index, not from whatever `currentSectionIndex` reads at execution time.

**4. Stale error/status cross-contamination.** If a save for section A resolves (success or validation failure) after the user has already navigated to section B, blindly overwriting the shared `errors`/`status` state would misattribute section A's response to section B's currently-rendered fields. Fix: after a save resolves, only apply its errors/status if the section it was saving for still matches the currently-displayed section; otherwise, discard the response silently. This never loses data (the save still happened) — it only avoids a rare, cosmetic mis-highlighted error. The existing submit-time required-answer check remains the correctness safety net regardless: it validates the actual database state, not client-side UI state, so nothing dangerous can be submitted even if an error display was briefly suppressed.

**5. Phantom autosave on page load.** The wizard already has a `watchEffect` that seeds every multiselect question's answer to `[]` on mount (so `v-model` has a valid initial value for checkbox groups). If the new "save on every change" watcher started observing before that seeding settled, it would fire an autosave — and therefore create a real `Merchant`/`Assessment` draft row — for every page view, including bots, crawlers, and link-preview fetchers that never submit anything. Fix: gate the change-watcher behind a flag that only flips true in `onMounted` (after the component's initial setup, including the seeding effect, has fully run), not by relying on effect-ordering assumptions.

**6. Submit racing a pending or in-flight autosave.** Since answers are persisted via a separate endpoint from submit (`SubmitAssessmentService::submit()` validates against already-saved rows, it doesn't accept inline answer data), submit must never fire while the most recent edit hasn't been persisted yet — otherwise submit could reject on a stale "missing required answer" that the user actually already filled in seconds ago. Fix: before firing the submit request, flush any pending debounce immediately and await the entire save chain (including any cascaded re-save from hazard #2) to fully drain, via a loop that awaits `savePromise` until it's null.

**7. Navigating away shouldn't wait out the full debounce window.** If a user finishes a section and immediately clicks "Next" (or a section-picker button, or "Previous"), the pending debounced save for the section they're leaving shouldn't sit idle for up to 600ms before firing — the moment they navigate, that save should start immediately in the background (still non-blocking; navigation itself never waits on it). This minimizes the window in which a closed tab could lose the very last edit.

## What changes in the wizard

- `saveSection()` (manual, form-submit-triggered) is removed entirely, along with the "Save and continue" / "Save final draft section" button.
- The form's bottom button becomes "Next" (hidden entirely on the last section, since there's nothing to advance to) — pure navigation, no request fired directly by the click.
- `previousSection()`, `nextSection()`, and the section-picker buttons all trigger an immediate (non-blocking) flush of any pending debounced save for the section being left, per hazard #7.
- A `watch(answers, ..., { deep: true })`, gated by the `onMounted`-flipped flag from hazard #5, queues a save (hazard #1-#3) on every change to any answer.
- `submitAssessment()` gains one new first step: flush and fully await any pending/in-flight save (hazard #6) before firing the submit request. Its existing error handling (409 conflict, missing-answer validation) is unchanged.
- The status line becomes a quiet, non-blocking indicator ("All changes saved" / "Check the highlighted answers.") that updates as autosaves complete — still `role="status" aria-live="polite"` from the prior milestone's accessibility work, but no longer tied to disabling any button.
- No new backend endpoint or contract change — this is entirely a frontend behavior change against the existing `POST /api/assessments`, `POST /api/assessments/{id}/answers`, and `POST /api/assessments/{id}/submit` endpoints.

## Testing

No automated JS test suite exists in this project (consistent with every prior frontend milestone) — verification is `npm run build` succeeding plus rigorous code-level tracing of the seven correctness properties above, since there's no test harness to catch a regression in any of them. The full `php artisan test` suite is run to confirm zero backend regression (no PHP file changes expected). Given the complexity and the absence of automated coverage, the implementation review for this change should explicitly re-trace each of the seven hazards against the actual diff, not just check that the code "looks reasonable."
