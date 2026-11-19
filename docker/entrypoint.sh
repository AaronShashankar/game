#!/bin/sh
# Apply any pending migrations, then start Apache.
set -e
php /var/www/html/database/migrate.php
exec docker-php-entrypoint "$@"
