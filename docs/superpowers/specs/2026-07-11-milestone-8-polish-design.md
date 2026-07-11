# Milestone 8: Polish, Accessibility, Loading States, README, Diagrams

Date: 2026-07-11

## Context

Per `docs/03_Codex_Execution_Plan.md`, Milestone 8 is the final milestone: polish, accessibility, loading states, README, architecture diagrams, and deployment verification. Milestones 1-7 are complete, merged, and deployed to Render.

This spec was produced and self-approved without an interactive design review, per explicit instruction to execute the milestone end-to-end without pausing for questions. Scope decisions below reflect a direct audit of the current codebase rather than a negotiated design.

## Scope

In scope: the concrete gaps found in a direct audit of every Vue page and the project's documentation. Out of scope: the PRD's Future Scope items (Shopify Dev Store, CSV import, WooCommerce/BigCommerce connectors, AI recommendations) — these are explicitly listed under Milestone 8 in the execution plan as roadmap context, not milestone work, and CLAUDE.md's guardrail is explicit that future integrations get contracts/seams only, not implementations, until the MVP requires them. None of this milestone's work touches that boundary.

## Loading states

`Wizard.vue`'s `saveSection()` and `submitAssessment()` are raw `axios` calls that bypass Inertia's router entirely, so they get none of the built-in top-loading-bar feedback (`progress: { color: '#2563EB' }` in `app.js`) that every other page's navigation already has for free. A slow connection or Render's free-tier cold start gives no visual indication anything is happening, and nothing prevents a double-click from firing a duplicate request.

Fix: add `isSaving`/`isSubmitting` reactive flags. While either is true, disable the relevant buttons (`Previous`, `Save and continue`/`Save final draft section`, `Submit assessment`) and swap their label to a saving/submitting state. This also serves accessibility (`aria-busy`).

## Accessibility

Concrete, verified gaps (not a general aspirational audit):

- **`ApplicationLogo.vue`** — purely decorative SVG, always paired with visible text (page title, nav label) elsewhere. Currently exposed to assistive tech with no `aria-hidden`. Add `aria-hidden="true"` and `focusable="false"`.
- **`AssessmentResults.vue`** score ring — the SVG's `<text>` nodes for score/tier are individually readable, but as separate disconnected nodes rather than one coherent announcement. Add `role="img"` and a computed `aria-label` (e.g. "Score: 72 out of 100, Established tier") on the `<svg>`.
- **`Wizard.vue`**:
  - The status/error text (`status`, `submitError`, per-question errors) updates reactively but nothing announces the change to a screen reader. Add `aria-live="polite"` to the status line and the submit-error line.
  - Required questions are marked with a visual `*` only. Add `aria-required="true"` to the corresponding input/select elements.
  - The section-picker buttons rely on color alone (`border-blue-400 bg-blue-50`) to show the active section. Add `aria-current="step"` on the active one.
  - Loading-state additions above also improve `aria-busy` coverage on the affected buttons.
- **`Workspace/Index.vue`**:
  - The search `<input>` has a `placeholder` but no accessible name that survives once text is typed. Add `aria-label="Search by company or contact name"`.
  - The sortable `<th>` column headers are `@click`-only — a mouse-only interaction with no keyboard path and no `aria-sort` state. Wrap each header's label in a real `<button type="button">` (focusable, `Enter`/`Space`-activatable by default) and add `aria-sort="ascending"|"descending"|"none"` on the `<th>` itself, driven by the existing `filters.sort`/`filters.direction` state.
- **`Workspace/Show.vue`** — the "Back to prospects" link's `&larr;` arrow is a decorative glyph currently exposed to assistive tech as loose text ("leftwards arrow Back to prospects"). Wrap it in `<span aria-hidden="true">`.

No changes needed elsewhere: form `<label>` elements already wrap their inputs (the implicit, valid association pattern) throughout the wizard and multiselect groups; focus rings (`focus:ring-2`) are already present on all text/select inputs; `<Link>`/`<a>`/`<button>` elements throughout the app are native, already keyboard-focusable elements with no custom keyboard handling to audit.

## README

The current `README.md` is frozen at Milestone 0 ("Deployment milestone setup is in progress"), despite 7 milestones being complete and deployed. Rewrite it to reflect current reality:

- Project overview (unchanged framing, still accurate).
- Feature overview: assessment wizard, scoring/recommendations, public report, internal workspace, demo data — one line each.
- Live demo link (the Render URL) and demo login credentials, so anyone reviewing the repo can immediately explore the internal workspace without needing to seed anything themselves.
- Local development setup (verify against actual `composer.json` scripts — the existing instructions are close but should be double-checked against the `setup` composer script).
- Testing instructions (`php artisan test`).
- Deployment section — mostly accurate already, keep it, refresh anything now-stale (e.g. the "MySQL by default" line — this project has always used PostgreSQL in production and SQLite locally, not MySQL; that line predates the actual deployment and should be corrected).
- Link to the new architecture diagrams doc.
- Replace the stale "Milestone 0 Acceptance" / "Status: in progress" section with an accurate current-status summary.

## Architecture diagrams

A new `docs/architecture-diagrams.md`, linked from the README, with Mermaid diagrams (rendered natively by GitHub, zero new tooling/dependency):

1. **Domain/data flow diagram** — `Merchant` → `Assessment` → `AssessmentAnswer`/`Recommendation`/`Report`, and the four services that operate on them (`CreateAssessmentService`, `SaveAssessmentAnswersService`, `SubmitAssessmentService`, `ReportBuilderService`), plus `ReadinessScoringService`/`RecommendationEngine` as the scoring/recommendation seam.
2. **Assessment lifecycle sequence diagram** — anonymous visitor → draft creation → per-section answer saves → submit (validation, scoring, recommendation generation, report creation, all inside one transaction) → either the inline results view or the public report link.

## Deployment verification

A final checklist (not code) run after this branch merges: CI green on `main`, `/health` returns 200 on the live Render deployment, the demo data / admin login still work post-deploy, and the full local test suite passes. This mirrors the verification already performed after every prior milestone this session — no new mechanism needed, just a final confirmation pass.

## Testing

This milestone is almost entirely frontend (Vue template/script changes) and documentation — consistent with every prior frontend milestone in this project, there is no JS test suite, so verification is `npm run build` succeeding plus careful reasoning about the changes. The one behavior-bearing change with a clear test surface is `Workspace/Index.vue`'s `aria-sort` value, which is derived from existing server-provided `filters` state already covered by `WorkspaceTest.php`'s sort tests — no new backend test is needed since no backend code changes. Run the full `php artisan test` suite after these changes purely to confirm no accidental regression (none expected, since no PHP file is touched by this milestone's frontend/doc work).
