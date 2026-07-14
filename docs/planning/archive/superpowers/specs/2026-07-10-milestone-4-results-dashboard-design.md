# Milestone 4: Results Dashboard

Date: 2026-07-10

## Context

Milestone 3 (scoring engine + rule-based recommendation engine) is merged. Per `docs/03_Codex_Execution_Plan.md`, Milestone 4 covers: results dashboard, charts, recommendation cards, responsive UI, with a STOP gate to verify mobile.

Two decisions from prior discussion bound this design:

1. **No persistent results URL.** The results view stays inline/client-side, rendered the moment `POST /api/assessments/{id}/submit` succeeds — no new route, no ownership/session token. "How does a merchant come back to view this later" is deliberately deferred to Milestone 5, whose public report already needs a secure, non-guessable token for exactly that purpose (per the existing `Report` model). Building a separate access mechanism here would be redundant work solved twice.
2. **Visual-only reuse of the design mockup.** `docs/ui-ux-design/` (see `screens/01-results.png`, `02-results.png`, `03-results.png` inside the zip) provides strong visual direction — score ring, card layout, breakdown bars, the blue-forward/Geist-style look — but its content model includes things we cannot honestly produce right now: peer benchmarking percentiles, dollar-value/percentage impact estimates, effort/time-to-implement tags, a narrative executive summary, and granular per-capability Ready/Partial/Gap status. All of these are tracked in [GitHub issue #3](https://github.com/cunningorb/merchant-readiness-workspace/issues/3) as deliberate future work, not built here.

## Scope

In scope:
- `AssessmentResults.vue`, a new component rendered by the wizard once a submit succeeds.
- A score ring (overall score + tier).
- A section breakdown that doubles as "top opportunities" by using the API's ranked (weakest-first) ordering.
- A capability-mapping badge row (per-section tier, visually distinct from the numeric breakdown).
- Recommendation cards (priority-sorted, as already returned by the API), with an empty state for a perfect score.
- Responsive layout, manually verified on mobile (no automated JS test suite exists in this project — same as Milestone 3).

Out of scope (tracked in issue #3 or deferred to a later milestone):
- Peer benchmarking, quantified $/% impact estimates, effort/time tags, narrative summary, granular per-capability status.
- Any new route, persistent URL, or ownership/session token.
- A charting library — the ring and bars are simple enough for hand-rolled SVG/CSS; no new dependency.

## Component architecture

New file: `resources/js/Pages/Assessment/AssessmentResults.vue`.

`Wizard.vue` replaces its current inline `<div v-if="submitResult">...</div>` result block with:

```html
<AssessmentResults v-if="submitResult" :result="submitResult" :catalog="catalog" />
```

Props:
- `result`: the full submit response, `{ assessment: { overall_score, overall_tier, section_scores, ranked_sections }, recommendations: [...] }` — passed straight through from `Wizard.vue`'s existing `submitResult` ref.
- `catalog`: the same `catalog` prop `Wizard.vue` already receives from Inertia, so section labels (e.g. `return_policy` → "Return Policy") are resolved from the one existing source of truth rather than duplicated in the new component.

`AssessmentResults.vue` owns all results presentation (ring, breakdown, capability badges, recommendation cards); `Wizard.vue` goes back to owning only the wizard flow (section navigation, per-section saving, submit triggering, error handling). Neither file needs to know about the other's internals beyond this one prop-passing boundary.

## Visual design

### Score ring

An inline SVG circle (`viewBox="0 0 120 120"`, `cx=60 cy=60 r=52`, `stroke-width=10`), with `stroke-dasharray` set to the circle's circumference (`2 * π * 52 ≈ 326.7`) and `stroke-dashoffset` computed as `circumference * (1 - overall_score / 100)`, so the filled arc grows clockwise from empty (score 0) to a full circle (score 100). Centered text shows the score ("68") with "/100" beneath it, and the tier (`overall_tier`) renders as a pill below the ring, colored per the tier palette below.

### Tier and priority color palette

A fixed mapping reused across the ring, breakdown bars, and capability badges, keeping the existing dark theme (`bg-slate-950` base, white/10 borders):

| Tier | Color |
|---|---|
| Foundational | rose/red |
| Developing | amber |
| Established | blue |
| Advanced | emerald/green |

Recommendation priority badges use a separate but analogous scheme: high = rose, medium = amber, low = slate/blue-gray.

### Score breakdown (doubles as "Top opportunities")

Iterates `result.assessment.ranked_sections` — already sorted ascending by score from the API, so the weakest area renders first with no re-sorting in the frontend. Each row shows: the section's human label (looked up from `catalog` by section key), a horizontal bar whose width is `score`%, colored by that section's tier, the numeric score, and a small tier badge. Because the list is already weakest-first, this single block satisfies both the "Score breakdown" and "Top opportunities" result requirements without displaying the same 4 sections twice.

### Capability mapping

A separate, visually distinct row of 4 compact badges — one per scored section (Return Policy, Manual Operations, Exchanges, Platform) — each showing just the label and its tier (no bar, no number). This is intentionally a qualitative restatement of the same underlying data as the breakdown, satisfying the "Capability Mapping" result requirement honestly with the data we have, without inventing the mockup's granular per-capability Ready/Partial/Gap status (that requires a capability catalog that doesn't exist yet — issue #3).

### Recommended actions

Cards from `result.recommendations`, already priority-sorted by the API. Each card shows: category badge, priority badge (color per the palette above), title, description, and `expected_impact` rendered as plain text — no invented numeric or dollar figures, consistent with the "avoid language that implies guaranteed financial outcomes" guardrail.

**Empty state:** if `recommendations` is an empty array (a perfect-score assessment, as already covered by `SubmitAssessmentServiceTest`'s 100-score fixture), render a positive message instead of an empty section — e.g. "No urgent opportunities identified — nice work." — rather than a blank gap in the layout.

### Responsive layout

Ring + breakdown stack in a single column on mobile (`sm:` breakpoint and below), side-by-side on larger screens. Recommendation cards go from a 2-column grid on desktop to a single column on mobile, matching the existing Tailwind responsive conventions already used in `Wizard.vue`'s section-picker grid.

## Testing / verification

No automated JS test suite exists in this project (no vitest/jest configured), consistent with how Milestone 3's wizard UI was verified. Verification for this milestone is:
1. Manual browser walkthrough: complete the wizard with a mix of weak/strong answers, submit, and visually confirm the ring, breakdown (weakest-first), capability badges, and recommendation cards render correctly with real data from the API.
2. Manual mobile check: resize the browser (or use devtools device emulation) to confirm the responsive stacking behaves as designed — this satisfies the Milestone 4 STOP gate ("verify mobile") from `docs/03_Codex_Execution_Plan.md`.
3. A perfect-score walkthrough (all "good" answers) to confirm the empty state renders instead of a blank recommendations section.

No backend changes are needed for this milestone — `overall_score`, `overall_tier`, `section_scores`, `ranked_sections`, and `recommendations` are all already returned by the existing `POST /api/assessments/{id}/submit` endpoint from Milestone 3.
