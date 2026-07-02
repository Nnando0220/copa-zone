#!/usr/bin/env sh
set -eu

ENV_FILE="${COPAZONE_ENV_FILE:-.env.production}"
BACKUP_DIR="${BACKUP_DIR:-backups}"
COMPOSE="docker compose --env-file ${ENV_FILE} -f docker-compose.prod.yml"

if [ ! -f "${ENV_FILE}" ]; then
    echo "Missing ${ENV_FILE}."
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/copazone-${TIMESTAMP}.dump"

${COMPOSE} exec -T postgres sh -c 'pg_dump --format=custom --no-owner --no-acl -U "$POSTGRES_USER" "$POSTGRES_DB"' > "${BACKUP_FILE}"

find "${BACKUP_DIR}" -name 'copazone-*.dump' -type f -mtime +14 -delete

echo "Backup written to ${BACKUP_FILE}."
