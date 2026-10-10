#!/usr/bin/env bash
# Managed preview: ensure PostgreSQL is up, then serve Laravel on :8000.
set -euo pipefail
cd "$(dirname "$0")/.."
if command -v pg_lsclusters >/dev/null 2>&1 && pg_lsclusters | grep -q down; then
    service postgresql start || pg_ctlcluster 16 main start
fi
exec php artisan serve --host=0.0.0.0 --port=8000
