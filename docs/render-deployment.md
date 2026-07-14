# Render Deployment

This project uses a Docker deployment on Render because Render does not provide a first-class raw PHP/Laravel runtime.

## Service Settings

- Language/runtime: Docker
- Root directory: blank
- Dockerfile path: `./Dockerfile`
- Instance type: Free
- Health check path: `/health`

`render.yaml` also defines these settings for Blueprint-based setup.

## Required Environment Variables

Set these in Render after creating the web service:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=base64:...`
- `APP_URL=https://your-render-service.onrender.com`
- `DB_CONNECTION=pgsql`
- `DATABASE_URL=...`
- `QUEUE_CONNECTION=sync`
- `MAIL_MAILER=resend`
- `MAIL_FROM_ADDRESS=no_reply@normalview.pro`
- `MAIL_FROM_NAME="Commerce Cartographer"`
- `RESEND_KEY=...`

Use the Neon unpooled connection string for `DATABASE_URL` while Render runs Laravel migrations during startup. The pooled URL is better for normal application traffic later, but it can fail during migration DDL.

Imports use Laravel jobs. On the current single-service Render deployment, `QUEUE_CONNECTION=sync` is required so import jobs execute immediately instead of waiting in the `jobs` table with no worker. If a dedicated worker service is provisioned later, set `QUEUE_CONNECTION=database` on both services and run `php artisan queue:work --tries=1` in the worker.

Report emails use Resend. Store `RESEND_KEY` as a secret environment variable in Render, not in the repository. The app also accepts Laravel's conventional `RESEND_API_KEY` name, but the current Render service uses `RESEND_KEY`. The sender domain for `no_reply@normalview.pro` must remain verified in Resend, including Resend's DNS records.

The Docker startup command runs `php artisan migrate --force` but does not run `php artisan db:seed --force`. Seed demo data or reset demo rows only as an explicit operator action, not on every production boot.

## Optional Environment Variables (LLM-Assisted Features)

Everything below is optional. Unset (or `LLM_ENABLED=false`), the website-scan LLM strategy and the Executive Perspective report card stay fully off with no behavior change — see `docs/llm-extraction.md` and `docs/decisions/0008-hybrid-rules-llm-extraction.md` / `0009-ai-recommendation-insight.md` for what each one does.

- `WEBSITE_EXTRACTION_STRATEGY` — `rules` (default) | `llm` | `hybrid`
- `LLM_ENABLED` — `true` to turn on the LLM path at all
- `LLM_PROVIDER` — `groq` (only provider implemented today)
- `LLM_TIMEOUT_SECONDS`, `LLM_MAX_INPUT_CHARACTERS`, `LLM_MAX_PAGES`, `LLM_DAILY_REQUEST_LIMIT` — website-scan tuning knobs
- `GROQ_API_KEY` — set as a secret, never in the repo
- `GROQ_BASE_URL` — defaults to Groq's OpenAI-compatible endpoint if unset
- `GROQ_MODEL` — **must** be a model that supports Groq's structured JSON output. As of this writing only `openai/gpt-oss-20b` and `openai/gpt-oss-120b` support strict schema mode on Groq; other models (e.g. `llama-3.3-70b-versatile`) fall back to unstructured JSON mode and reliably fail this app's evidence-verification step, so the LLM path silently contributes nothing even though it's "enabled." Verify against Groq's current supported-models page before picking a different one.
- `AI_RECOMMENDATION_INSIGHT_ENABLED`, `AI_RECOMMENDATION_INSIGHT_CACHE_TTL_HOURS`, `AI_RECOMMENDATION_INSIGHT_MAX_SECONDS` — Executive Perspective tuning knobs

In `render.yaml` all of these are declared `sync: false`, meaning Render will never reset a value entered in the dashboard back to something in the blueprint — set them once there and they stick across deploys.

Generate `APP_KEY` with a Laravel environment before deploying production traffic:

```bash
php artisan key:generate --show
```

## Milestone 0 Verification

Before feature work continues, verify:

- The Render build completes.
- The homepage loads publicly.
- `https://your-render-service.onrender.com/health` returns `{"status":"ok"}`.
- Database migrations run successfully during startup.
- CI or the relevant Laravel test command is green.

PostgreSQL is acceptable on Render because the architecture allows PostgreSQL when the deployment target requires it.
