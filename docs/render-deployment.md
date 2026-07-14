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
