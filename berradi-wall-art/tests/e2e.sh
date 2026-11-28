#!/usr/bin/env bash
# Runs the HTTP end-to-end suite on a throw-away copy of the project and a fresh database.
#   DB_USER=bwa DB_PASS=secret DB_NAME=bwa_install tests/e2e.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="${WORK_DIR:-$(mktemp -d)}/bwa-e2e"
PORT="${PORT:-8099}"
DB_NAME="${DB_NAME:-bwa_install}"
rm -rf "$WORK" && mkdir -p "$WORK" && cp -r "$ROOT/." "$WORK/"
rm -f "$WORK/.env" "$WORK/storage/installed.lock" "$WORK"/storage/logs/*.log
if [ -n "${MYSQL_ADMIN_CMD:-}" ]; then
  $MYSQL_ADMIN_CMD -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
fi
cd "$WORK"
php -S "127.0.0.1:$PORT" -t public tests/server-router.php > "$WORK/server.log" 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null || true' EXIT
sleep 1
BWA_HTTP_URL="http://127.0.0.1:$PORT" BWA_TEST_DB_DATABASE="$DB_NAME" BWA_TEST_DB_USERNAME="${DB_USER:-root}" \
  BWA_TEST_DB_PASSWORD="${DB_PASS:-}" BWA_TEST_DB_HOST="${DB_HOST:-localhost}" php tests/http.php && status=0 || status=$?
if ls storage/logs/*.log >/dev/null 2>&1 && grep -q "ERROR" storage/logs/*.log; then
  echo "--- application errors logged during the run:"; grep "ERROR" storage/logs/*.log | cut -c1-400
fi
exit $status
