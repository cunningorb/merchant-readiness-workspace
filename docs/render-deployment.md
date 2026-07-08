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
