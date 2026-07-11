# Merchant Readiness Workspace

Merchant Readiness Workspace is a Laravel + Vue + Inertia application that helps ecommerce merchants evaluate the maturity of their returns operations through a guided assessment, transparent rule-based scoring, actionable recommendations, and a shareable report — all before any sales conversation.

## Live Demo

- **App:** https://commerce-cartographer.onrender.com
- **Start an assessment** (no login required): https://commerce-cartographer.onrender.com/assessment
- **Internal workspace login:** `admin@merchant-readiness.test` / `password` — seeded automatically, reviews the demo merchants below without needing to submit anything yourself.

The production database is seeded with three realistic demo merchants spanning the readiness tiers (Foundational, Established, Advanced), so the internal workspace's prospect list, search/sort, and review page all have something real to show.

## Features

- **Public assessment wizard** — six sections (Business, Catalog, Return Policy, Exchanges, Manual Operations, Platform), completable anonymously, with draft answers saved section-by-section.
- **Rule-based scoring and recommendations** — transparent, weighted section scoring rolled up into four readiness tiers (Foundational/Developing/Established/Advanced), with recommendations generated from the actual answers, not a black box.
- **Shareable public report** — every submitted assessment gets a secure, tokenized report URL, accessible without authentication.
- **Internal workspace** — an authenticated prospect list (search, sortable columns, tier filtering by sort) and a review page per assessment with a rule-based Talking Points panel for sales/CS conversations.
- **Demo data** — three fully-submitted demo merchants generated through the exact same services a real merchant uses (not hand-computed), plus `php artisan demo:reset` to regenerate them on demand without ever touching real prospect data.

## Tech Stack

- Laravel 12 (PHP 8.2)
- Vue 3 + Inertia.js
- Tailwind CSS
- PostgreSQL in production (Render), SQLite for local development
- Laravel queues (available for async work; not yet required by any current feature)

## Local Development

### Quick setup

```bash
composer run setup
```

This installs PHP and Node dependencies, copies `.env.example` to `.env`, generates an application key, runs migrations, and builds frontend assets.

### Manual setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

### Seed demo data

```bash
php artisan db:seed
```

Creates the admin user and three demo merchants. Safe to run more than once — it's idempotent per-merchant, so a partial run self-heals rather than duplicating anything. Regenerate the demo merchants at any time (including in production) with:

```bash
php artisan demo:reset
```

This command only ever deletes and recreates rows flagged `is_demo` — it can never touch a real prospect's submitted assessment.

### Run the app

```bash
php artisan serve
```

Or run the server, queue listener, log viewer, and Vite dev server together:

```bash
composer run dev
```

## Testing

```bash
php artisan test
```

## Architecture

- `docs/01_Architecture_Implementation_Document.md` — full architecture write-up.
- `docs/architecture-diagrams.md` — domain/data-flow and assessment-lifecycle diagrams.
- `docs/02_Design_Approach.md` — UI/UX direction.
- `docs/04_Product_Requirements_Document.md` — product requirements.

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

Developer dashboard links, local testing commands, and deployment utilities are collected in `docs/developer-resources.md`.

## Project Status

Every planned milestone is complete and deployed to production:

- Deployment foundation (Laravel, Render, CI, `/health`)
- Authentication, core models, and relationships
- Assessment question catalog and public wizard
- Scoring and recommendation engines
- Results dashboard
- Public, tokenized shareable report
- Internal workspace (prospect list, review page, talking points)
- Demo data and a safe reset command
- Polish, accessibility, loading states, and this documentation
