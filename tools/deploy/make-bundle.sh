#!/usr/bin/env bash
# Build an upload-ready archive of the site for hPanel's File Manager.
#
#   tools/deploy/make-bundle.sh                 # → .tmp/public_html.zip
#   tools/deploy/make-bundle.sh /path/out.zip
#
# The archive contains exactly what belongs in the document root: no
# connection.local.php, no sessions, no .sql/.orig/error_log files (they are
# excluded from git in the first place — this script re-checks).
set -euo pipefail

repo="$(cd "$(dirname "$0")/../.." && pwd)"
src="$repo/php-source/public_html"
out="${1:-$repo/.tmp/public_html.zip}"
stamp="$(date -u +%Y%m%d-%H%M%S)"

[[ -d "$src" ]] || { echo "missing $src" >&2; exit 1; }
mkdir -p "$(dirname "$out")"
rm -f "$out"

cd "$src"
zip -q -r -9 "$out" . \
  -x '*.orig' -x '*.bak' -x '*.sql' -x '*.log' -x 'error_log' \
  -x 'php.ini' -x '.user.ini' -x 'wdx_mysqli.php' \
  -x 'config/connection.local.php' -x 'lbpanel/config/connection.local.php' \
  -x 'sessions/*' -x '.wdx_data/*' -x '.DS_Store' -x '*/.*'

files=$(unzip -l "$out" | tail -1 | awk '{print $2}')
size=$(du -h "$out" | cut -f1)

cat > "$(dirname "$out")/public_html.manifest.txt" <<EOF
Woodex — deploy bundle
built      : $stamp (UTC)
source     : php-source/public_html @ $(git -C "$repo" rev-parse --short HEAD)
files      : $files
archive    : $(basename "$out") ($size)
excluded   : connection.local.php, sessions/, .wdx_data/, *.sql, *.orig, *.log,
             error_log, php.ini, .user.ini, wdx_mysqli.php, .DS_Store

Upload with hPanel → Files → File Manager into the staging document root,
then extract. Create config/connection.local.php there (see
docs/deploy-hostinger.md step 3) — the site will not start without it.
EOF

echo "built $out ($size, $files files)"
echo "manifest: $(dirname "$out")/public_html.manifest.txt"
