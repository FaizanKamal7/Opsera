#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	echo "================================================="
	echo " Initializing container [${APP_ENV}] environment "
	echo "================================================="

	set_local_dir_permissions() {
		echo "Setting \"$1\" directory permissions"
		mkdir -p "$1"
		chmod -R 0777 "$1"
		chown -R expertm:expertm "$1"
		setfacl -R -m u:root:rwX -m u:www-data:rwX -m u:expertm:rwX "$1"
		setfacl -dR -m u:root:rwX -m u:www-data:rwX -m u:expertm:rwX "$1"
	}

	if ! id "expertm" >/dev/null 2>&1; then
		EXPERTM_UID=${EXPERTM_UID:-1000}
		EXPERTM_GID=${EXPERTM_GID:-${EXPERTM_UID}}
		echo "Creating ExpertM user (${EXPERTM_UID}) and group (${EXPERTM_GID}) ..."
		groupadd -g $EXPERTM_GID -o expertm
		useradd -rm -d /home/expertm -s /bin/bash -g $EXPERTM_GID -u $EXPERTM_UID -o -c "ExpertM" expertm > /dev/null
		if [ ! -z "$EXPERTM_GROUPS" ]; then
			echo "Adding ExpertM user to groups: ${EXPERTM_GROUPS}"
			usermod -a -G "$EXPERTM_GROUPS" expertm
		fi
		set_local_dir_permissions var
		set_local_dir_permissions vendor
		for dir in tools/*; do
			if [ -d "$dir/vendor" ]; then
				set_local_dir_permissions "$dir/vendor"
			fi
		done
		if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
			if [ -d "/app/.git" ]; then
				echo "Setting git safe directory"
				gosu expertm git config --global --add safe.directory /app
			fi
			echo "Installing composer vendor packages"
			gosu expertm composer install --prefer-dist --no-progress --no-interaction
		fi
	else
		echo "Found ExpertM user (expertm:`id -u expertm`) and group (expertm:`id -g expertm`)"
	fi

	gosu expertm php bin/console -V

	if [ "$APP_ENV" = "dev" ] || [ "$APP_ENV" = "test" ]; then
		echo "Clearing the cache"
		gosu expertm php bin/console cache:clear
	fi

	if grep -q ^DB_NAME= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(gosu expertm php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable"
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo "The database is now ready and reachable"
		fi

		echo "Checking for available database migrations:"
		if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
			gosu expertm php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing --allow-no-migration
		else
			echo "No migrations detected!"
		fi
	fi

	echo "Container initialization finished!"
fi

exec docker-php-entrypoint "$@"
