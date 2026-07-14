# Developer Resources

This is the complete path from a fresh clone to a fully working local environment — including the
optional AI features. If something here goes stale, fix this file rather than letting the README's
short version and this one drift apart; the README's Local Development section is a quick-start,
this is the full picture.

## Dashboards

- Render dashboard: https://dashboard.render.com/
- Neon dashboard: https://console.neon.tech/app/projects
- GitHub repository: https://github.com/cunningorb/merchant-readiness-workspace
- GroqCloud console (only needed for the optional AI features): https://console.groq.com/

## Prerequisites

- PHP 8.2+ (`composer.json` pins `^8.2`)
- Composer
- Node.js 22+ and npm
- SQLite support in PHP (bundled with most PHP installs) — local dev uses SQLite, not Postgres/MySQL
- A local dev server. Any of these work; use whichever you already have:
  - [Laravel Herd](https://herd.laravel.com/) (Windows/Mac) — serves the project automatically at
    `http://<folder-name>.test` once the repo is inside Herd's sites path, no extra command needed
  - `php artisan serve` (built into Laravel, works anywhere)
- Docker — optional, only useful for reproducing the Render build locally

## 1. Clone and install

```bash
git clone https://github.com/cunningorb/merchant-readiness-workspace.git
cd merchant-readiness-workspace
composer run setup
```

`composer run setup` does everything in one shot: `composer install`, copies `.env.example` to
`.env` (only if `.env` doesn't already exist — safe to re-run), `php artisan key:generate`,
`php artisan migrate --force`, `npm install`, `npm run build`.

If you'd rather run each step yourself (e.g. to inspect `.env` before migrating):

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

`DB_CONNECTION=sqlite` is the default in `.env.example`. Laravel creates `database/database.sqlite`
automatically on first `migrate` if it doesn't exist — you don't need to touch it manually. No
local Postgres/MySQL install is needed; production uses Postgres (see Render section below), but
local dev intentionally doesn't need to match that.

## 2. Seed demo data

```bash
php artisan db:seed
```

Creates the admin user (`admin@merchant-readiness.test` / `password`) and three demo merchants
spanning the readiness tiers. Idempotent — safe to re-run, and a partial run self-heals rather than
duplicating rows. Regenerate the demo merchants on demand (including in production) with:

```bash
php artisan demo:reset
```

This only ever touches rows flagged `is_demo` — it can never affect a real submitted assessment.

## 3. Run the app

Herd: nothing to run — it's already serving the folder at its `.test` URL once the repo is in
Herd's sites path.

Without Herd, either:

```bash
php artisan serve
```

or run the app server, queue listener, log viewer, and Vite dev server together:

```bash
composer run dev
```

Vite's dev server (`npm run dev`, included in `composer run dev`) gives you hot module reload for
`resources/js`; without it, you're serving whatever `npm run build` last produced in `public/build`.

## 4. Run the tests

```bash
php artisan test    # PHP — Pest/PHPUnit
npm test             # JS — Vitest
```

`phpunit.xml` hard-overrides `LLM_ENABLED=false` (among other test-isolation env vars) regardless
of what your `.env` has — this is deliberate, so a real `GROQ_API_KEY` in your local `.env` can
never cause the test suite to make live network calls. Tests that specifically exercise the LLM
path set `config(['llm.enabled' => true, ...])` and `Http::fake()` explicitly; see
`tests/Unit/Services/WebsiteScan/LlmWebsiteExtractionStrategyTest.php` or
`tests/Unit/Services/Ai/RecommendationInsightServiceTest.php` for the pattern.

## 5. Optional: LLM-assisted features (website scan + Executive Perspective)

Two features in this app can be assisted by an LLM (Groq) on top of their fully-functional
deterministic defaults: website-scan assisted autofill (`rules` by default, can add an `llm` or
`hybrid` mode) and the Executive Perspective card on the report page (off by default). Full design
detail: `docs/llm-extraction.md`, `docs/decisions/0008-hybrid-rules-llm-extraction.md`,
`docs/decisions/0009-ai-recommendation-insight.md`. Quick setup:

1. Create a free account at https://console.groq.com/ and generate an API key
   (Dashboard → API Keys).
2. Pick a model from Groq's current
   [supported models page](https://console.groq.com/docs/models) — **do not** just copy a model ID
   from an old tutorial, and don't assume any model works. As of this writing, only
   `openai/gpt-oss-20b` and `openai/gpt-oss-120b` support Groq's strict JSON-schema structured
   output. Other models (e.g. `llama-3.3-70b-versatile`) silently fall back to unstructured JSON
   mode, and this app's evidence-verification step (every LLM claim must be a verbatim, verifiable
   quote from the source) will then reject virtually everything the model returns — the feature
   will look "on" but contribute nothing, with no error surfaced anywhere except a log line. If you
   change models later, re-verify structured-output support first.
3. Add to your local `.env`:

   ```env
   WEBSITE_EXTRACTION_STRATEGY=hybrid
   LLM_ENABLED=true
   LLM_PROVIDER=groq
   GROQ_API_KEY=gsk_your_key_here
   GROQ_MODEL=openai/gpt-oss-120b
   ```

   Everything else (`LLM_TIMEOUT_SECONDS`, `LLM_MAX_INPUT_CHARACTERS`, `LLM_MAX_PAGES`,
   `LLM_DAILY_REQUEST_LIMIT`, `AI_RECOMMENDATION_INSIGHT_*`) has a working default in
   `config/llm.php` / `config/ai.php` — only override them if you have a specific reason to.
4. Verify it actually works end to end before trusting it — submit an assessment with a real
   recommendation and a scannable website, then check `storage/logs/laravel.log` for
   `LLM extraction ...` or `Recommendation insight generation ...` warning lines, which mean it
   fell back to the deterministic path instead of failing loudly. A clean run produces no such log
   lines and a populated `aiInsight` in the report's Inertia payload.

Never commit `GROQ_API_KEY` (or any other secret) — `.env` is gitignored; only `.env.example`
(with values blank) is tracked.

## Neon

The repo is linked to Neon with `.neon`, which stores only project IDs and no secrets.

Pull local Neon environment variables when needed:

```bash
npx -y neonctl env pull
```

Verify database access:

```bash
npx -y neonctl psql -- -c "SELECT 1 AS ok"
```

Do not commit `.env`, `.env.local`, database URLs, passwords, or API keys.

## Render

Render deploys the app using `Dockerfile` and `render.yaml`. `render.yaml` declares every env var
the app needs; anything marked `sync: false` there is a value you set once in the Render dashboard
and Render will never reset it back to something from the blueprint on a later sync/deploy.

Required Render environment variables (all `sync: false` except where noted):

- `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr` — literal values in `render.yaml`,
  not dashboard-managed
- `APP_KEY=base64:...`
- `APP_URL=https://commerce-cartographer.onrender.com`
- `DB_CONNECTION=pgsql` — literal value in `render.yaml`
- `DATABASE_URL=<Neon unpooled connection string>`
- `RESEND_KEY=...`

Recommended (literal values already set in `render.yaml`, no action needed):

- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=sync`

Optional (LLM-assisted features — see `docs/render-deployment.md` for the full list and the
model-selection gotcha above): `WEBSITE_EXTRACTION_STRATEGY`, `LLM_ENABLED`, `LLM_PROVIDER`,
`LLM_TIMEOUT_SECONDS`, `LLM_MAX_INPUT_CHARACTERS`, `LLM_MAX_PAGES`, `LLM_DAILY_REQUEST_LIMIT`,
`GROQ_API_KEY`, `GROQ_BASE_URL`, `GROQ_MODEL`, `AI_RECOMMENDATION_INSIGHT_ENABLED`,
`AI_RECOMMENDATION_INSIGHT_CACHE_TTL_HOURS`, `AI_RECOMMENDATION_INSIGHT_MAX_SECONDS`. Every one of
these is safe to leave unset — both features stay off with zero behavior change.

Deployment checks:

- Render build completes.
- Homepage loads.
- `/health` returns `{"status":"ok"}`.
- Startup migrations run successfully against Neon.

Use Neon's unpooled URL for Render while the container runs `php artisan migrate --force` at
startup. The pooled URL can fail during migrations because it goes through PgBouncer.

Startup does not run `php artisan db:seed --force`; seed demo data only as an explicit operator
action (`php artisan db:seed` or `php artisan demo:reset` via a Render shell).

## Useful Commands

Generate a Laravel app key without PHP installed:

```powershell
$bytes=New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes); 'base64:'+[Convert]::ToBase64String($bytes)
```

Run a single PHP test file or one test by name:

```bash
php artisan test tests/Feature/RecommendationInsightReportTest.php
php artisan test --filter=test_returns_null_when_master_llm_switch_is_off
```

Run a single JS test file:

```bash
npx vitest run resources/js/Pages/Reports/__tests__/Show.test.js
```

Check Git status before committing:

```bash
git status --short
```

Trigger a Render deploy after pushing (only relevant if `autoDeploy` is ever turned off — today
`render.yaml` has `autoDeploy: true`, so a normal push to `main` deploys automatically):

```bash
git push origin main
```
