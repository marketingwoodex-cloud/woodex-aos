#!/usr/bin/env bash
# Foreground launcher: MySQL bridge in the background, PHP runner in the
# foreground. Use this when the process must stay attached and keep supervising
# the server (e.g. a preview/supervisor host); otherwise use dev.sh.
#
#   PORT=3000 tools/php-run/serve.sh
set -uo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo"
mkdir -p .tmp

cleanup() {
  [[ -n "${bridge_pid:-}" ]] && kill "$bridge_pid" 2>/dev/null
}
trap cleanup EXIT INT TERM

python3 tools/php-run/mysql_bridge.py \
  --db .tmp/php-live.sqlite --port 3306 --trace "$repo/.tmp/sql-trace.log" \
  > .tmp/mysql-bridge.log 2>&1 &
bridge_pid=$!
echo "bridge pid $bridge_pid → 127.0.0.1:3306"

for _ in $(seq 1 60); do
  grep -qi "listening" .tmp/mysql-bridge.log 2>/dev/null && break
  kill -0 "$bridge_pid" 2>/dev/null || { echo "bridge died:"; tail -3 .tmp/mysql-bridge.log; exit 1; }
  sleep 0.2
done

exec env PORT="${PORT:-3000}" HOST="${HOST:-0.0.0.0}" node tools/php-run/server.mjs
