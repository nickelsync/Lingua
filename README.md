# Lingua

Lingua is a self-hosted English-learning platform for Indonesian speakers. It takes learners from absolute Beginner through the CEFR ladder (A1 → C2) to English Mastery with structured lessons, interactive exercises, timed level exams, vocabulary with spaced repetition, and speaking/listening practice — all without any paid external service.

**Stack:** Laravel 12 (PHP 8.2+), Vue 3 + Pinia + Vue Router (SPA), Tailwind CSS 4, PostgreSQL (Supabase-compatible), PWA.

## Features

- **Curriculum** — Beginner, A1, A2, B1, B2, C1, C2, and English Mastery. Levels contain courses → modules → lessons → sections and exercises. New users start at Beginner or take a placement test (50 multiple-choice + 10 speaking questions).
- **Exercises** — multiple choice, fill-in-the-blank, true/false, matching, rearrange, translation, typing, listening, reading, speaking, and writing. Untimed with immediate feedback; all grading is server-side.
- **Final exams** — timed, server-authoritative, sampled from a per-level question pool. 75% unlocks the next level; retakes allowed.
- **Vocabulary** — curated word bank with IPA, Indonesian meanings, and examples; personal saving, filters, flashcards, and an SM-2-style spaced-repetition review queue.
- **Speaking & listening** — browser Speech Synthesis (TTS) and Speech Recognition (STT). Speaking feedback honestly reflects transcript word similarity, not phonetic accuracy. Raw recordings are never stored — only transcripts.
- **Progress** — XP, daily streaks, achievements, activity history, per-skill stats, and a public profile (opt-in).
- **User content** — bookmarks, private notes, notifications, and search.
- **Admin CMS** — full content management (levels, courses, modules, lessons, sections, exercises, questions, vocabulary, achievements) with publishing, reordering, and duplication — no code required. Role checks enforced server-side.
- **PWA** — installable, with an offline shell. Opened lessons and vocabulary stay readable offline; cached learner data is cleared on logout, and auth/session endpoints are never cached.

The interface is English-only with a dark, grayscale design. Indonesian appears in lesson explanations, notes, and feedback where it supports learners. American English is the primary standard.

## Requirements

- PHP 8.2+ with `pdo_pgsql` (or `pdo_sqlite` for a quick start)
- Composer
- Node.js 20+ and npm
- PostgreSQL 14+ (local or Supabase)

## Local development

```bash
git clone <repo> lingua && cd lingua
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configure the database in `.env` (see below), then:

```bash
# Migrate and seed curriculum, vocabulary, achievements, and the admin account
ADMIN_PASSWORD='choose-a-strong-password' php artisan migrate --seed

npm run build        # or `npm run dev` for hot reload
php artisan serve    # http://localhost:8000
```

Sign in as `admin` with the password you chose, or register a regular account from the landing page.

### PostgreSQL

```bash
createdb lingua
createuser lingua --pwprompt
```

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lingua
DB_USERNAME=lingua
DB_PASSWORD=...
```

### Supabase

Lingua uses plain PostgreSQL, so a Supabase project works out of the box. In the Supabase dashboard open **Settings → Database** and copy the connection info into `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=db.<project-ref>.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<database password>
DB_SSLMODE=require
```

Storage: avatars use the local `public` disk by default (`php artisan storage:link`). To store uploads in Supabase Storage instead, create a bucket with S3 credentials (**Storage → Settings → S3 access keys**) and point the `AWS_*` variables plus `FILESYSTEM_DISK=s3` at it. This is optional; nothing requires it.

### Admin account

The seeder creates one admin only when `ADMIN_PASSWORD` is set (`ADMIN_USERNAME` defaults to `admin`). Without it, seeding still succeeds and prints a reminder; you can re-run `php artisan db:seed` later. Admins can promote other users from the admin panel.

## Authentication and account recovery

Lingua uses **username + password** — no email address is required or collected. At registration each user receives a one-time **recovery code**; it is shown once and stored only as a hash. A user who forgets their password resets it with `username + recovery code`, which also rotates the code. Admins can regenerate a user's recovery code from the admin panel.

## Content authoring

Curriculum lives in `database/seeders/content/*.php` (one file per level; format documented in `database/seeders/content/FORMAT.md`). Validate content before seeding:

```bash
php scripts/validate-content.php            # all levels
php scripts/validate-content.php a1 b2      # specific levels
```

Re-seeding is idempotent per level; the admin CMS can edit everything afterward.

## Testing

```bash
php artisan test
```

The suite (feature + unit) runs on in-memory SQLite; no services needed.

## Deploying to production

HTTPS is required in production for the PWA service worker and the browser speech APIs — terminate TLS at your proxy or platform.

### Docker (recommended)

The repository ships a production image (FrankenPHP, PHP 8.3) plus a compose file that bundles PostgreSQL:

```bash
cp .env.docker.example .env.docker        # fill in every value
docker compose run --rm --no-deps app php artisan key:generate --show   # → APP_KEY
docker compose up -d --build
```

The app listens on `127.0.0.1:8080`; point your HTTPS reverse proxy (Caddy, nginx, Traefik) at it. On boot the container runs `php artisan app:deploy`: migrations, one-time content seeding, admin-user upsert from `ADMIN_USERNAME`/`ADMIN_PASSWORD`, and framework caching. Re-deploys never overwrite lessons edited in the CMS; run `php artisan app:deploy --fresh-content` only if you want to restore the seeded curriculum.

To use managed PostgreSQL (e.g. Supabase) instead of the bundled `db` service, set `DB_HOST`/`DB_PORT`/`DB_SSLMODE` in `.env.docker` and drop the `db` service. The same image works on container platforms (Railway, Render, Fly.io, Cloud Run): supply the env vars from `.env.docker.example` and route traffic to port 8080.

### Bare-metal / PaaS without Docker

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
ADMIN_PASSWORD=... php artisan app:deploy
```

Serve `public/` with any PHP 8.3+ web server (extensions: `pdo_pgsql`, `intl`, `zip`, `opcache`).

## PWA & offline behavior

The service worker precaches the app shell and static assets, so the app loads offline and installs to the home screen. Read-only learning data (lessons, vocabulary, dashboard) is cached network-first so already-opened content stays readable offline; those caches are cleared on logout. Auth, session, and write endpoints are never cached. Offline, uncached pages show a connection notice, and safe progress actions queue locally and sync when the connection returns.

## Speech features and graceful fallback

- **Listening (TTS)** uses the browser's Speech Synthesis API — available in all modern browsers.
- **Speaking (STT)** uses the browser's Speech Recognition API (Chrome/Edge/Safari). Where unsupported, speaking questions offer a typed fallback and the rest of the platform is unaffected.
- Speaking is scored by comparing the recognized transcript to the target sentence (word-level similarity). Feedback says exactly that — it does not claim to measure pronunciation quality. Raw audio never leaves the browser and is never stored.

## Project layout

```
app/               Models, services (SRS, exams, streaks/XP, placement), API + admin controllers
database/          Migrations, factories, seeders, curriculum content (seeders/content/)
resources/js/      Vue SPA (pages, components, stores, router, composables)
resources/views/   Blade shells (landing + SPA mount)
routes/api.php     All JSON endpoints (auth, learning, exams, vocab, admin)
scripts/           Content validator + dev setup/run scripts
tests/             Feature + unit tests
```

## License

MIT
