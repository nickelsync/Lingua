#!/usr/bin/env bash
# Idempotent workspace setup: PostgreSQL, PHP deps, JS deps, DB schema + seed.
set -euo pipefail
cd "$(dirname "$0")/.."

# 1. PostgreSQL
if command -v pg_lsclusters >/dev/null 2>&1; then
    if pg_lsclusters | grep -q down; then
        service postgresql start || pg_ctlcluster 16 main start
    fi
fi
for i in $(seq 1 15); do
    pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1 && break
    sleep 1
done

# 2. Role + databases (no-ops when they already exist)
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='lingua'" | grep -q 1 \
    || sudo -u postgres psql -c "CREATE ROLE lingua LOGIN PASSWORD 'lingua_dev'"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='lingua'" | grep -q 1 \
    || sudo -u postgres createdb -O lingua lingua
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='lingua_test'" | grep -q 1 \
    || sudo -u postgres createdb -O lingua lingua_test

# 3. PHP / JS dependencies
[ -d vendor ] || composer install --no-interaction --prefer-dist
[ -f .env ] || { cp .env.example .env; php artisan key:generate --force; }
[ -d node_modules ] || npm ci

# 4. Schema + content (migrate is idempotent; seed only on empty DB)
php artisan migrate --force
if ! php artisan tinker --execute='echo \App\Models\CefrLevel::count();' 2>/dev/null | grep -qE '^[1-9]'; then
    ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin-secret-123}" php artisan db:seed --force
fi

# 5. Frontend assets
[ -d public/build ] || npm run build
echo "setup complete"
