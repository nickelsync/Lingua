#!/bin/sh
# Migrate, seed-once, and cache before serving. Safe to re-run.
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Generate one with:" >&2
    echo "  docker run --rm <image> php artisan key:generate --show" >&2
    exit 1
fi

php artisan app:deploy

exec "$@"
