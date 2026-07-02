#!/usr/bin/env sh
set -eu

if [ "${APP_ENV:-production}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required when APP_ENV=production."
    exit 1
fi

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" = "true" ]; then
    echo "APP_DEBUG must be false when APP_ENV=production."
    exit 1
fi

if [ "${BROADCAST_CONNECTION:-log}" = "reverb" ]; then
    if [ -z "${REVERB_APP_ID:-}" ] || [ -z "${REVERB_APP_KEY:-}" ] || [ -z "${REVERB_APP_SECRET:-}" ]; then
        echo "REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET are required when BROADCAST_CONNECTION=reverb."
        exit 1
    fi
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
