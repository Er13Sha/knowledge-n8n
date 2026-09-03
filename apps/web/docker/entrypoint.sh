#!/bin/sh
set -eu

mkdir -p \
    /app/bootstrap/cache \
    /app/storage/app/private \
    /app/storage/app/public \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/testing \
    /app/storage/framework/views \
    /app/storage/logs

chown -R www-data:www-data /app/storage /app/bootstrap/cache

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "postgres";
        $port = (int) (getenv("DB_PORT") ?: 5432);
        $deadline = time() + (int) (getenv("DB_WAIT_TIMEOUT") ?: 60);

        do {
            $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 2);

            if ($socket) {
                fclose($socket);
                exit(0);
            }

            usleep(250000);
        } while (time() < $deadline);

        fwrite(STDERR, "Database is not reachable at {$host}:{$port}\n");
        exit(1);
    '
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${OPTIMIZE_LARAVEL:-true}" = "true" ]; then
    php artisan optimize:clear --no-interaction
    php artisan optimize --no-interaction
fi

exec docker-php-entrypoint "$@"
