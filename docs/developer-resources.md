# Developer Resources

## Dashboards

- Render dashboard: https://dashboard.render.com/
- Neon dashboard: https://console.neon.tech/app/projects
- GitHub repository: https://github.com/cunningorb/merchant-readiness-workspace

## Local Setup

Required local tools:

- PHP 8.2+
- Composer
- Node.js 22+
- npm
- Docker, optional but useful for deployment parity

Install dependencies:

```bash
composer install
npm install
```

Create a local environment:

```bash
cp .env.example .env
php artisan key:generate
```

Run the app locally:

```bash
php artisan serve
npm run dev
```

Build frontend assets:

```bash
npm run build
```

Run Laravel tests:

```bash
php artisan test
```

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

Render deploys the app using `Dockerfile` and `render.yaml`.

Required Render environment variables:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=base64:...`
- `APP_URL=https://commerce-cartographer.onrender.com`
- `DB_CONNECTION=pgsql`
- `DATABASE_URL=<Neon unpooled connection string>`
- `LOG_CHANNEL=stderr`

Recommended Render environment variables:

- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=sync`

Deployment checks:

- Render build completes.
- Homepage loads.
- `/health` returns `{"status":"ok"}`.
- Startup migrations run successfully against Neon.

Use Neon's unpooled URL for Render while the container runs `php artisan migrate --force` at startup. The pooled URL can fail during migrations because it goes through PgBouncer.

Startup does not run `php artisan db:seed --force`; seed demo data only as an explicit operator action.

## Useful Commands

Generate a Laravel app key without PHP installed:

```powershell
$bytes=New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes); 'base64:'+[Convert]::ToBase64String($bytes)
```

Check Git status before committing:

```bash
git status --short
```

Trigger a Render deploy after pushing:

```bash
git push origin main
```
