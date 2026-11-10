#!/usr/bin/env bash
# Spins up a throw-away WordPress (SQLite, PHP built-in server) with the theme & plugins for e2e tests.
# Usage: tests/e2e/setup-wp.sh [dir] [port]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DIR="${1:-$ROOT/.wp}"
PORT="${2:-8088}"
mkdir -p "$DIR" && cd "$DIR"
[ -f wp-cli.phar ] || curl -sSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
WP="php $DIR/wp-cli.phar --path=$DIR/site --allow-root"
if [ ! -f site/wp-load.php ]; then
  mkdir -p site && $WP core download --locale=en_US --quiet
fi
if [ ! -f site/wp-content/db.php ]; then
  curl -sSLo sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
  unzip -qo sqlite.zip -d site/wp-content/plugins/
  sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$DIR/site/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    site/wp-content/plugins/sqlite-database-integration/db.copy > site/wp-content/db.php
fi
[ -f site/wp-config.php ] || $WP config create --dbname=wp --dbuser=x --dbpass=x --skip-check --quiet --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'BMN_SMS_DRIVER', 'log' );
PHP
ln -sfn "$ROOT/wordpress/themes/boshrouyeh-khesht" site/wp-content/themes/boshrouyeh-khesht
for p in boshrouyeh-core boshrouyeh-eservices khesht-builder; do ln -sfn "$ROOT/wordpress/plugins/$p" "site/wp-content/plugins/$p"; done
$WP core is-installed 2>/dev/null || $WP core install --url="http://localhost:$PORT" --title="شهرداری بشرویه" --admin_user=admin --admin_password="${WP_PASS:-admin123}" --admin_email=admin@example.com --skip-email --quiet
# WP-CLI + SQLite drop-in can store the docroot sub-path (/site) in the URLs; pin them.
$WP option update siteurl "http://localhost:$PORT" --quiet
$WP option update home "http://localhost:$PORT" --quiet
$WP option update timezone_string Asia/Tehran --quiet
$WP rewrite structure '/%postname%/' --quiet
$WP theme activate boshrouyeh-khesht --quiet
$WP plugin activate boshrouyeh-core boshrouyeh-eservices khesht-builder --quiet
$WP bmn demo
$WP rewrite flush --quiet
# Setup runs under CLI (no HTTP_HOST); start the log clean so only request-time errors are checked.
rm -f "$DIR/site/wp-content/debug.log"
echo "WordPress ready in $DIR/site — start with: php -S localhost:$PORT -t $DIR/site $ROOT/tests/e2e/router.php"
