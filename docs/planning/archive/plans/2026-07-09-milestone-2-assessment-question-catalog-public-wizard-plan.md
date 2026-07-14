# Milestone 2 Assessment Question Catalog And Public Wizard Plan

## Source Prompt

From `docs/03_Codex_Execution_Plan.md`:

> Milestone 2
>
> Assessment question catalog
>
> Public wizard
>
> Validation
>
> Draft saving
>
> Tests
>
> STOP.
>
> Verify end-to-end.

## Scope

- Add a server-side assessment question catalog with the documented sections: Business, Catalog, Return Policy, Exchanges, Manual Operations, Platform.
- Add a public anonymous assessment wizard route.
- Add `POST /api/assessments` for draft assessment creation.
- Add `POST /api/assessments/{assessment}/answers` for validated draft answer saving.
- Keep scoring, recommendations, and report generation out of this milestone.

## Verification

- `npm run build`
- `php artisan test`
- Manual end-to-end wizard verification before moving to Milestone 3.
