#!/bin/sh

set -eu

echo "Running required database migrations..."
php artisan migrate --force

echo "Warming Laravel production caches..."
php artisan config:cache || echo "Warning: config cache warmup failed; continuing without it." >&2
php artisan route:cache || echo "Warning: route cache warmup failed; continuing without it." >&2
php artisan view:cache || echo "Warning: view cache warmup failed; continuing without it." >&2

echo "Starting Apache on port ${PORT:-10000}..."
exec apache2-foreground
