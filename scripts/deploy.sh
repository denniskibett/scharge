#!/usr/bin/env bash
# scharge production deploy, run on the server from the app folder (scripts/push.sh --go runs it):
#   bash scripts/deploy.sh
# Backs up the database first, then runs ONLY the migrations listed in scripts/deploy-migrations.txt (a plain
# `php artisan migrate` would try to re-run old migrations that do not match production: SYSTEM_AUDIT.md §2).
set -euo pipefail
cd "$(dirname "$0")/.."
PHP="${PHP:-$(command -v php || echo /usr/local/lsws/lsphp83/bin/php)}"

echo "==> Maintenance mode"
"$PHP" artisan down --retry=30 || true
trap 'status=$?; if [ $status -ne 0 ]; then echo "Deploy failed (exit $status): bringing the site back up." >&2; "$PHP" artisan up || true; fi' EXIT

echo "==> Back up the database"
env_value() { { grep -E "^$1=" .env || true; } | tail -n 1 | cut -d= -f2- | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
db_name="$(env_value DB_DATABASE)"
mkdir -p storage/backups
umask 077
{
  echo "[client]"
  echo "user=\"$(env_value DB_USERNAME | sed 's/["\\]/\\&/g')\""
  echo "password=\"$(env_value DB_PASSWORD | sed 's/["\\]/\\&/g')\""
  echo "host=$(env_value DB_HOST || true)"
} > storage/backups/.my.cnf
sed -i 's/^host=$/host=127.0.0.1/' storage/backups/.my.cnf
backup="storage/backups/$(date +%Y%m%d-%H%M%S).sql.gz"
if [ -n "$db_name" ] && mysqldump --defaults-extra-file=storage/backups/.my.cnf --single-transaction --routines "$db_name" | gzip > "$backup" && [ -s "$backup" ]; then
  rm -f storage/backups/.my.cnf
  echo "    saved to ${backup}"
  off_home="${BACKUP_COPY_DIR:-/var/backups/scharge}"
  if mkdir -p "$off_home" 2>/dev/null && cp "$backup" "$off_home/" 2>/dev/null; then
    chmod 700 "$off_home"; find "$off_home" -name '*.sql.gz' -mtime +30 -delete 2>/dev/null || true
    echo "    copy kept in ${off_home} (outside the home folder, 30 days)"
  fi
else
  rm -f storage/backups/.my.cnf "$backup"
  echo "    database backup failed: stopping before any change" >&2
  exit 1
fi
umask 022

echo "==> Folders"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache

echo "==> PHP dependencies"
if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
  echo "    composer not found: vendor left as it is" >&2
fi

echo "==> Migrations (only those listed in scripts/deploy-migrations.txt; already-run ones are skipped)"
while IFS= read -r m; do
  case "$m" in ''|\#*) continue ;; esac
  "$PHP" artisan migrate --path="database/migrations/${m}" --force
done < scripts/deploy-migrations.txt

if [ "${ACCESS_SYNC:-0}" = "1" ]; then
  # Only when asked (ACCESS_SYNC=1): it resets built-in roles to their default access (SYSTEM_AUDIT.md).
  echo "==> Access catalogue (roles and menu)"
  "$PHP" artisan access:sync
fi

echo "==> Caches"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo "==> Restart queue workers"
"$PHP" artisan queue:restart || true

echo "==> Live"
"$PHP" artisan up
echo "Done. Check /up, /login, /rent. The scheduler cron must run 'php artisan schedule:run' every minute."
