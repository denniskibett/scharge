#!/usr/bin/env bash
# Deploy scharge to scharge.sharet.africa from this Mac, safely. Run from the project root:
#
#   bash scripts/push.sh            PREVIEW only (default): build, test, then list what would change on the server
#   bash scripts/push.sh --go       build, test, upload changed files, then run scripts/deploy.sh on the server
#   bash scripts/push.sh --go --skip-tests
#
# Server layout (docs/SYSTEM_AUDIT.md §13): the app is /home/scharge.sharet.africa/laravel and the web root is
# /home/scharge.sharet.africa/public_html beside it. Uploads never delete anything on the server (live has files the
# repo does not), never touch .env, vendor, storage, uploads or the web root's index.php / .htaccess.
#
# Settings (override per run, e.g. DEPLOY_HOST=root@1.2.3.4 bash scripts/push.sh):
DEPLOY_HOST="${DEPLOY_HOST:-root@185.113.249.146}"
DEPLOY_SITE="${DEPLOY_SITE:-scharge.sharet.africa}"
APP_PATH="${APP_PATH:-/home/${DEPLOY_SITE}/laravel}"
WEB_PATH="${WEB_PATH:-/home/${DEPLOY_SITE}/public_html}"
DEPLOY_PORT="${DEPLOY_PORT:-22}"
# Test suites that pass today (the rest fail until the migrations are rebuilt: SYSTEM_AUDIT.md §2).
TESTS="${TESTS:-RentAutomationTest|CompanyIsolationTest|RentCollectionTest|BookingsTest|WhiteLabelTest|KycLogTest}"
set -euo pipefail
cd "$(dirname "$0")/.."

go=0; skip_tests=0
for arg in "$@"; do
  case "$arg" in
    --go) go=1 ;;
    --skip-tests) skip_tests=1 ;;
    *) echo "Unknown option: $arg (use --go and/or --skip-tests)" >&2; exit 1 ;;
  esac
done

mkdir -p "$HOME/.ssh"
SSH_OPTS=(-p "$DEPLOY_PORT" -o ControlMaster=auto -o "ControlPath=$HOME/.ssh/scharge-%r@%h:%p" -o ControlPersist=120)
ssh_run() { ssh "${SSH_OPTS[@]}" "$DEPLOY_HOST" "$@"; }
finish() { ssh "${SSH_OPTS[@]}" -O exit "$DEPLOY_HOST" 2>/dev/null || true; }
trap finish EXIT

echo "==> Front-end build"
npm run build

if [ "$skip_tests" -eq 0 ]; then
  echo "==> Tests (${TESTS})"
  php artisan test --filter="${TESTS}"
fi

echo "==> Checking the server"
ssh_run "test -f '${APP_PATH}/artisan'" || { echo "${APP_PATH} is not the scharge app (no artisan). Nothing uploaded." >&2; exit 1; }
ssh_run "test -f '${APP_PATH}/.env'" || { echo "${APP_PATH}/.env is missing. Nothing uploaded." >&2; exit 1; }
web_ok=0
if ssh_run "test -f '${WEB_PATH}/index.php' && grep -q 'laravel' '${WEB_PATH}/index.php'"; then
  web_ok=1
else
  echo "    ${WEB_PATH}/index.php does not load ../laravel: public assets will only go to ${APP_PATH}/public." >&2
fi

mode=(--itemize-changes)
if [ "$go" -eq 0 ]; then
  mode=(--dry-run --itemize-changes)
  echo "==> PREVIEW: files that WOULD change (nothing is uploaded; run with --go to deploy)"
else
  echo "==> Uploading changed files"
fi

# The app (no --delete: the server keeps files the repo does not have).
# --checksum: only files whose content differs travel (timestamps differ on almost everything).
rsync -az --checksum --human-readable "${mode[@]}" -e "ssh ${SSH_OPTS[*]}" \
  --exclude='.env' --exclude='.env.*' --exclude='.git/' --exclude='.DS_Store' --exclude='.phpunit.result.cache' \
  --exclude='node_modules/' --exclude='vendor/' --exclude='tests/' --exclude='.claude/' --exclude='ops/' \
  --exclude='*.sql' --exclude='*.sql.gz' --exclude='database/*.sqlite' \
  --exclude='public/hot' --exclude='public/storage' --exclude='public/uploads/' \
  --exclude='storage/' --exclude='bootstrap/cache/*.php' --exclude='error_log' --exclude='.user.ini' \
  ./ "${DEPLOY_HOST}:${APP_PATH}/"

# Public assets to the web root too (built CSS/JS, images), never its index.php or .htaccess.
if [ "$web_ok" -eq 1 ]; then
  rsync -az --checksum --human-readable "${mode[@]}" -e "ssh ${SSH_OPTS[*]}" \
    --exclude='.DS_Store' --exclude='index.php' --exclude='.htaccess' --exclude='hot' --exclude='storage' --exclude='uploads/' --exclude='.well-known/' \
    public/ "${DEPLOY_HOST}:${WEB_PATH}/"
fi

if [ "$go" -eq 0 ]; then
  echo "Preview finished. Lines starting with '<f' would be uploaded. Nothing changed on the server."
  echo "Migrations that would run: $(grep -v '^#' scripts/deploy-migrations.txt | grep -c . ) listed in scripts/deploy-migrations.txt"
  exit 0
fi

echo "==> Deploying on the server"
ssh_run "cd '${APP_PATH}' && bash scripts/deploy.sh && owner=\$(stat -c %U '/home/${DEPLOY_SITE}') && chown -R \"\$owner:\$owner\" '${APP_PATH}' && chmod -R ug+rwX storage bootstrap/cache"

echo "==> Checking the live site"
for path in /up / /login; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://${DEPLOY_SITE}${path}" || echo 000)
  echo "    ${path}  ${code}"
done
echo "Done: https://${DEPLOY_SITE}"
