#!/usr/bin/env sh
set -eu

ENV_FILE="${COPAZONE_ENV_FILE:-.env.production}"
BACKUP_FILE="${1:-}"
COMPOSE="docker compose --env-file ${ENV_FILE} -f docker-compose.prod.yml"

if [ ! -f "${ENV_FILE}" ]; then
    echo "Missing ${ENV_FILE}."
    exit 1
fi

if [ -z "${BACKUP_FILE}" ] || [ ! -f "${BACKUP_FILE}" ]; then
    echo "Usage: scripts/restore.sh backups/copazone-YYYYMMDD-HHMMSS.dump"
    exit 1
fi

printf 'This will replace data in the configured production database. Type RESTORE to continue: '
read -r CONFIRMATION

if [ "${CONFIRMATION}" != "RESTORE" ]; then
    echo "Restore cancelled."
    exit 1
fi

${COMPOSE} exec -T postgres sh -c 'pg_restore --clean --if-exists --no-owner --no-acl -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "${BACKUP_FILE}"

echo "Restore finished."
