#!/usr/bin/env bash
# Build a fresh local database from the cpanel dump and seed the local accounts.
#
#   tools/php-run/rebuild-db.sh            # rebuild .tmp/php-live.sqlite
#
# The old file is kept as .tmp/php-live.sqlite.bak-<timestamp> so nothing is lost
# if a page was mid-edit. Stops the bridge first (SQLite file is in use).
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo"

db=".tmp/php-live.sqlite"
dump="db-source/woodexbaks.sql"

[[ -f "$dump" ]] || { echo "dump not found: $dump"; exit 1; }

if [[ -f "$db" ]]; then
  backup="$db.bak-$(date +%Y%m%d-%H%M%S)"
  cp "$db" "$backup"
  echo "backed up existing database → $backup"
fi

echo "importing $dump …"
python3 tools/db-audit/audit.py "$dump" --out-db "$db" --json .tmp/php-live.json | tail -3

echo "seeding local accounts …"
python3 tools/php-run/seed_users.py --db "$db"

python3 - "$db" <<'PY'
import sqlite3, sys
conn = sqlite3.connect(sys.argv[1])
tables = conn.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='table'").fetchone()[0]
counts = {t: conn.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0]
          for t in ("products", "categories", "orders", "faqs", "quotation_requests", "users")
          if conn.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (t,)).fetchone()}
print(f"database ready: {tables} tables, " + ", ".join(f"{k}={v}" for k, v in counts.items()))
PY
