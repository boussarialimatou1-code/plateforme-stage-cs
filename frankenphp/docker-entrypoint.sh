#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	###> dunglas/symfony-docker ###
	# Install the project the first time PHP is started
	# This block will remove itself after the installation

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if [ -n "$DATABASE_URL" ]; then
    echo 'Waiting for database server to be ready...'
    ATTEMPTS_LEFT_TO_REACH_DATABASE=60
    until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null; do
        sleep 1
        ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
        echo "Still waiting for database server... $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
    done
    if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
        echo 'The database server is not up or not reachable'
        exit 1
    fi
    echo 'Database is ready'
    if [ "$(find ./migrations -iname '*.php' -print -quit)" ]; then
        php bin/console doctrine:migrations:migrate --no-interaction
    fi
fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
