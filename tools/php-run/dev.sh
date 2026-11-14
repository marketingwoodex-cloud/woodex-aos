#!/usr/bin/env bash
# Start/stop the two halves of the local run of the original Woodex PHP app.
#
#   tools/php-run/dev.sh start        # MySQL bridge on 3306 + PHP runner on $PORT
#   tools/php-run/dev.sh stop
#   tools/php-run/dev.sh restart
#   tools/php-run/dev.sh status
#
# PORT defaults to 3000. Everything written by the two processes lands in .tmp/.
set -uo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo"
mkdir -p .tmp
port="${PORT:-3000}"

pid_file_bridge=".tmp/bridge.pid"
pid_file_runner=".tmp/runner.pid"

alive() {  # alive <pidfile>
  local f="$1"
  [[ -f "$f" ]] && kill -0 "$(cat "$f")" 2>/dev/null
}

stop_one() {  # stop_one <pidfile> <label>
  local f="$1" label="$2"
  if alive "$f"; then
    kill "$(cat "$f")" 2>/dev/null
    for _ in $(seq 1 40); do alive "$f" || break; sleep 0.1; done
    alive "$f" && kill -9 "$(cat "$f")" 2>/dev/null
    echo "stopped $label"
  else
    echo "$label not running"
  fi
  rm -f "$f"
}

start_bridge() {
  if alive "$pid_file_bridge"; then echo "bridge already running (pid $(cat "$pid_file_bridge"))"; return; fi
  # --trace is resolved to an absolute path: the session runs with its own cwd.
  nohup python3 tools/php-run/mysql_bridge.py \
    --db .tmp/php-live.sqlite --port 3306 --trace "$repo/.tmp/sql-trace.log" \
    > .tmp/mysql-bridge.log 2>&1 &
  echo $! > "$pid_file_bridge"
  for _ in $(seq 1 60); do
    alive "$pid_file_bridge" || break
    grep -qi "listening" .tmp/mysql-bridge.log 2>/dev/null && break
    sleep 0.2
  done
  if ! alive "$pid_file_bridge"; then
    echo "bridge failed to start:"; tail -3 .tmp/mysql-bridge.log; return 1
  fi
  echo "bridge pid $(cat "$pid_file_bridge") → 127.0.0.1:3306"
}

start_runner() {
  if alive "$pid_file_runner"; then echo "runner already running (pid $(cat "$pid_file_runner"))"; return; fi
  PORT="$port" nohup node tools/php-run/server.mjs > .tmp/php-runner.log 2>&1 &
  echo $! > "$pid_file_runner"
  for _ in $(seq 1 120); do
    alive "$pid_file_runner" || break
    grep -q "PHP errors" .tmp/php-runner.log 2>/dev/null && break
    sleep 0.2
  done
  if ! alive "$pid_file_runner"; then
    echo "runner failed to start:"; tail -5 .tmp/php-runner.log; return 1
  fi
  echo "runner pid $(cat "$pid_file_runner") → http://0.0.0.0:$port"
}

case "${1:-start}" in
  start)   start_bridge; start_runner ;;
  stop)    stop_one "$pid_file_runner" runner; stop_one "$pid_file_bridge" bridge ;;
  restart) stop_one "$pid_file_runner" runner; stop_one "$pid_file_bridge" bridge; start_bridge; start_runner ;;
  status)
    alive "$pid_file_runner" && echo "runner up (pid $(cat "$pid_file_runner"))" || echo "runner down"
    alive "$pid_file_bridge" && echo "bridge up (pid $(cat "$pid_file_bridge"))" || echo "bridge down"
    ;;
  *) echo "usage: $0 {start|stop|restart|status}"; exit 1 ;;
esac
