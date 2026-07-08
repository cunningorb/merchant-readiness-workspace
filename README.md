# Merchant Readiness Workspace

Merchant Readiness Workspace is a Laravel + Vue + Inertia application that helps ecommerce merchants evaluate the maturity of their returns operations through guided assessments, transparent scoring, actionable recommendations, and shareable reports.

## Tech Stack

- Laravel
- Vue
- Inertia
- Tailwind CSS
- PostgreSQL on Render, MySQL by default where available
- Laravel queues

## Goals

- Deliver value before sales engagement.
- Help merchants identify operational improvements.
- Demonstrate modern Laravel architecture and product thinking.
- Prove deployment before feature development continues.

## Local Development

Install dependencies and build assets:

```bash
composer install
npm install
npm run build
```

Create the local environment and app key:

```bash
cp .env.example .env
php artisan key:generate
```

Run the app:

```bash
php artisan serve
```

## Render Deployment

Render is configured through Docker:

- `Dockerfile`
- `render.yaml`
- `/health`
- `docs/render-deployment.md`

Render settings when creating the service manually:

- Language/runtime: Docker
- Root directory: blank
- Dockerfile path: `./Dockerfile`
- Instance type: Free
- Health check path: `/health`

Required production environment variables are documented in `docs/render-deployment.md`. For Neon, set `DATABASE_URL` in Render rather than separate `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values.

## Milestone 0 Acceptance

Feature development should not continue until these are verified:

- Public Render deployment loads.
- `/health` returns OK.
- Database migrations run against Render PostgreSQL.
- CI or the relevant Laravel test command is green.

## Status

Deployment milestone setup is in progress.
