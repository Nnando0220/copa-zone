#!/usr/bin/env sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --optimize-autoloader
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

if [ "${DB_CONNECTION}" = "pgsql" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."

    until php -r '
        $host = getenv("DB_HOST") ?: "postgres";
        $port = getenv("DB_PORT") ?: "5432";
        $database = getenv("DB_DATABASE") ?: "copazone";
        $username = getenv("DB_USERNAME") ?: "copazone";
        $password = getenv("DB_PASSWORD") ?: "";

        try {
            new PDO("pgsql:host={$host};port={$port};dbname={$database}", $username, $password);
            exit(0);
        } catch (Throwable $exception) {
            exit(1);
        }
    '; do
        sleep 2
    done
fi

php artisan config:clear --ansi
php artisan migrate --force --ansi

exec "$@"
