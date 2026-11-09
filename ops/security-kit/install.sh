#!/bin/bash
# Install the Sharet security kit into one Laravel 11/12 app on the VPS, with a backup and automatic rollback.
#   install.sh <app-dir> <domain> [--uninstall]
# Run as root on the server, from a folder that holds app/SecurityKit (scp'd there first, e.g. /opt/security-kit).
set -euo pipefail
APP=${1:?app dir}; DOMAIN=${2:?domain}; MODE=${3:-install}
KIT=$(cd "$(dirname "$0")" && pwd)/app/SecurityKit
TS=$(date +%Y%m%d-%H%M%S)
BACKUP=/root/scha-backups/security-kit-$(basename "$(dirname "$APP")")-$(basename "$APP")-$TS.tgz
OWNER=$(stat -c %U "$APP"); GROUP=$(stat -c %G "$APP")
PHPV=$(grep -m1 -oE 'lsphp[0-9]+' "/usr/local/lsws/conf/vhosts/$DOMAIN/vhost.conf" || echo lsphp83)
PHP=/usr/local/lsws/$PHPV/bin/php
LINE='App\SecurityKit\SecurityKitServiceProvider::class,'
cd "$APP"
[ -f artisan ] && [ -f bootstrap/providers.php ] || { echo "not a Laravel 11+ app: $APP"; exit 2; }

artisan() { local out; out=$(sudo -u "$OWNER" "$PHP" artisan "$@" 2>&1) || { echo "artisan $* failed:"; echo "$out" | tail -15; return 1; }; }
caches() {  # rebuild only the caches the app already used
  local had_config=$1 had_routes=$2
  artisan view:clear || true
  if [ "$had_config" = 1 ]; then artisan config:cache; else artisan config:clear || true; fi
  if [ "$had_routes" = 1 ]; then artisan route:cache; else artisan route:clear || true; fi
}
code() { curl -s -o /dev/null -w '%{http_code}' --max-time 30 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN$1" -k; }

HAD_CONFIG=0; [ -f bootstrap/cache/config.php ] && HAD_CONFIG=1
HAD_ROUTES=0; ls bootstrap/cache/routes-v7.php >/dev/null 2>&1 && HAD_ROUTES=1
BEFORE_HOME=$(code /)

tar czf "$BACKUP" bootstrap/providers.php $( [ -d app/SecurityKit ] && echo app/SecurityKit ) bootstrap/cache
echo "backup: $BACKUP (home page was HTTP $BEFORE_HOME)"

rollback() {
  echo "ROLLING BACK $DOMAIN"
  rm -rf app/SecurityKit
  tar xzf "$BACKUP"
  chown -R "$OWNER:$GROUP" bootstrap/providers.php bootstrap/cache
  [ -d app/SecurityKit ] && chown -R "$OWNER:$GROUP" app/SecurityKit
  caches "$HAD_CONFIG" "$HAD_ROUTES" || true
  exit 1
}

if [ "$MODE" = --uninstall ]; then
  sed -i '/App\\SecurityKit\\SecurityKitServiceProvider::class,/d' bootstrap/providers.php
  rm -rf app/SecurityKit
  rm -f bootstrap/cache/config.php bootstrap/cache/services.php  # they still name the provider
  caches "$HAD_CONFIG" "$HAD_ROUTES"
  echo "uninstalled from $APP"; exit 0
fi

trap 'echo "failed at line $LINENO"; rollback' ERR
rm -rf app/SecurityKit
cp -r "$KIT" app/SecurityKit
chown -R "$OWNER:$GROUP" app/SecurityKit
grep -qF "$LINE" bootstrap/providers.php || sed -i "s#^\(\s*\)\];#    ${LINE//\\/\\\\}\n\1];#" bootstrap/providers.php
grep -qF "$LINE" bootstrap/providers.php
mkdir -p storage/logs/kyc && chown "$OWNER:$GROUP" storage/logs/kyc
caches "$HAD_CONFIG" "$HAD_ROUTES"
# Boot the app and ask the router (route:list can crash on an unrelated broken route in the app)
ROUTES=$(sudo -u "$OWNER" "$PHP" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo app("router")->has("securitykit.report") ? "security-report ok" : "route not registered";' 2>&1 || true)
grep -q security-report <<<"$ROUTES" || { echo "route missing:"; echo "$ROUTES" | tail -5; false; }
trap - ERR

AFTER_HOME=$(code /); REPORT=$(code /admin/security-report)
echo "home page HTTP $BEFORE_HOME -> $AFTER_HOME; /admin/security-report HTTP $REPORT"
if [ "$AFTER_HOME" != "$BEFORE_HOME" ] || [ "$REPORT" -ge 500 ]; then rollback; fi
echo "OK $DOMAIN"
