#!/usr/bin/env sh
set -eu

ENV_FILE="${COPAZONE_ENV_FILE:-.env.production}"
COMPOSE="docker compose --env-file ${ENV_FILE} -f docker-compose.prod.yml"

if [ ! -f "${ENV_FILE}" ]; then
    echo "Missing ${ENV_FILE}. Copy .env.production.example and fill the production values first."
    exit 1
fi

${COMPOSE} build
${COMPOSE} up -d postgres

echo "Waiting for PostgreSQL healthcheck..."
POSTGRES_CONTAINER="$(${COMPOSE} ps -q postgres)"
until [ "$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${POSTGRES_CONTAINER}")" = "healthy" ]; do
    sleep 2
done

${COMPOSE} run --rm backend php artisan migrate --force --ansi
${COMPOSE} run --rm backend php artisan storage:link --force --ansi
${COMPOSE} run --rm backend php artisan optimize --ansi
${COMPOSE} up -d
${COMPOSE} exec backend php artisan queue:restart --ansi || true

echo "Checking /up..."
${COMPOSE} exec web wget -qO- http://127.0.0.1/up >/dev/null

echo "Production stack is ready."
