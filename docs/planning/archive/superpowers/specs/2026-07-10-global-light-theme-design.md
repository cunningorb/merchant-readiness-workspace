# Global Light Theme and Red/Yellow/Green Tier Colors

Date: 2026-07-10

## Context

Milestones 2-5 built `Welcome.vue`, `Wizard.vue`, `AssessmentResults.vue`, and `Reports/Show.vue` all using a dark theme (`bg-slate-950`, white/translucent cards) with an arbitrary tier color palette (Foundational=rose, Developing=amber, Established=blue, Advanced=emerald). This was discovered mid-brainstorm for Milestone 6 (internal workspace): the workspace's existing auth scaffold (Laravel Breeze) is light-themed, the design mockup's workspace screens are light-themed, and the user wants the *whole* app light-themed with tier/score colors on a conventional red-yellow-green scale, so "good vs. bad" reads intuitively everywhere.

Rather than build Milestone 6 in the new theme while every other page stays dark (an inconsistent product), this is its own focused sub-project: re-theme the 4 existing pages first, then build Milestone 6 fresh in the same theme.

## Scope

In scope: `resources/js/Pages/Welcome.vue`, `resources/js/Pages/Assessment/Wizard.vue`, `resources/js/Pages/Assessment/AssessmentResults.vue`, `resources/js/Pages/Reports/Show.vue`. A mechanical re-theme — same layout, same structure, same behavior, only color classes change.

Out of scope: any new page, any behavior change, Milestone 6 itself (a separate spec follows this one), the internal workspace's own styling (built fresh in this theme, not migrated).

## Base palette

A direct translation of the current dark structure, keeping the existing blue-forward brand accent:

| Role | Dark (current) | Light (new) |
|---|---|---|
| Page background | `bg-slate-950` | `bg-slate-50` |
| Card background | `bg-white/5` (translucent) | `bg-white` with `border-slate-200` and a subtle shadow |
| Primary text | `text-white` | `text-slate-900` |
| Secondary text | `text-slate-300` / `text-slate-400` | `text-slate-600` / `text-slate-500` |
| Brand accent (buttons, links) | `bg-blue-500`, `text-blue-100`/`200` | `bg-blue-600`, `text-blue-700`/`600` |
| Accent pills (light bg) | `bg-blue-400/10 border-blue-300/30 text-blue-100` | `bg-blue-50 border-blue-200 text-blue-700` |
| Borders (general) | `border-white/10` | `border-slate-200` |

This mapping applies wherever these classes appear in `Welcome.vue`, `Wizard.vue`, `AssessmentResults.vue`, and `Reports/Show.vue` (form inputs, buttons, section-picker chrome, etc.) — an implementer should treat this table as the mechanical translation rule, not a partial list.

## Tier colors (AssessmentResults.vue)

Replaces the current `TIER_COLORS` map. Badge style adapted for light backgrounds — a soft tint, a colored border, and dark-tinted text, replacing the dark theme's translucent-overlay style:

```js
const TIER_COLORS = {
    Foundational: { pill: 'border-red-300 bg-red-50 text-red-700', bar: 'bg-red-500', ring: '#ef4444' },
    Developing:   { pill: 'border-orange-300 bg-orange-50 text-orange-700', bar: 'bg-orange-500', ring: '#f97316' },
    Established:  { pill: 'border-yellow-300 bg-yellow-50 text-yellow-700', bar: 'bg-yellow-500', ring: '#eab308' },
    Advanced:     { pill: 'border-green-300 bg-green-50 text-green-700', bar: 'bg-green-500', ring: '#22c55e' },
};
```

This single map drives the score ring's arc color, the score-breakdown bars, and the capability-mapping badges — all three already consume `tierColors(tier)` today, so updating this one map propagates everywhere tier color is used.

## Priority colors (AssessmentResults.vue)

Stays a **distinct** scale from tier (per explicit decision — priority is urgency, not a good/bad state, and should not be visually confused with tier). A blue-intensity scale, light-theme equivalent of the current rose/amber/slate scheme:

```js
const PRIORITY_COLORS = {
    high:   'border-blue-300 bg-blue-100 text-blue-800',
    medium: 'border-blue-200 bg-blue-50 text-blue-600',
    low:    'border-slate-200 bg-slate-50 text-slate-500',
};
```

## Score ring specifics

- Track (background) circle: `stroke="rgba(255,255,255,0.1)"` → `stroke="#e2e8f0"` (a light gray, visible against the white card).
- Score number text: `fill-white` → `fill-slate-900`.
- "out of 100" text: `fill-slate-400` → `fill-slate-500`.
- The colored progress arc's `:stroke="tierColors(overallTier).ring"` binding is unchanged in mechanism — only the hex values in `TIER_COLORS` change (see above).

## Cleanup: remove Reports/Show.vue's print stylesheet

The current `@media print { body {...} * {...} svg text {...} }` block exists solely to force the dark theme into black-on-white for printing. Once the page is light-themed by default, printing works correctly without any override — the block becomes dead weight. Remove it entirely. Keep `print:hidden` on the Print button (still needed so the button itself doesn't appear in printed output).

## Testing

No automated JS test suite exists in this project (consistent with every prior frontend milestone). Verification is `npm run build` succeeding, careful review of which classes changed where, and the full `php artisan test` suite passing (this re-theme touches no backend code, so the count should be unchanged). As with every prior frontend change in this project, no live browser has been available during implementation in this environment — an actual visual pass is owed by the user after this lands, before starting Milestone 6 on top of it.
