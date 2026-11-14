#!/usr/bin/env bash
# Install the local-run mysqli compatibility layer into the extracted source.
#
#   tools/php-run/apply-shim.sh          # install (idempotent, keeps .orig backups)
#   tools/php-run/apply-shim.sh revert   # restore the original connection files
#
# Why: PHP-WASM's mysqlnd aborts the process when a prepared statement returns
# rows (see tools/php-run/shims/wdx_mysqli.php for the full write-up). WdxMysqli
# keeps the app's API but runs statements as escaped text queries, which the
# bridge handles reliably.
#
# Only these files are touched — everything else in public_html/ stays as the
# client delivered it:
#   connection.php, config/connection.php, lbpanel/config/connection.php
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
docroot="$repo/php-source/public_html"
shim_src="$repo/tools/php-run/shims/wdx_mysqli.php"
shim_dst="$docroot/wdx_mysqli.php"

files=("connection.php" "config/connection.php" "lbpanel/config/connection.php")

if [[ "${1:-install}" == "revert" ]]; then
  for f in "${files[@]}"; do
    if [[ -f "$docroot/$f.orig" ]]; then
      mv "$docroot/$f.orig" "$docroot/$f"
      echo "restored $f"
    fi
  done
  rm -f "$shim_dst"
  echo "shim removed"
  exit 0
fi

[[ -d "$docroot" ]] || { echo "source tree missing: $docroot"; exit 1; }
cp "$shim_src" "$shim_dst"

for f in "${files[@]}"; do
  path="$docroot/$f"
  [[ -f "$path" ]] || { echo "skip (missing) $f"; continue; }
  if grep -q "wdx_mysqli.php" "$path"; then
    echo "already patched: $f"
    continue
  fi
  [[ -f "$path.orig" ]] || cp "$path" "$path.orig"

  # Relative path from this file's directory back to the document root.
  depth=$(awk -F/ '{print NF-1}' <<< "$f")
  prefix=""
  for ((i = 0; i < depth; i++)); do prefix+="../"; done

  python3 - "$path" "$prefix" <<'PY'
import io, sys
path, prefix = sys.argv[1], sys.argv[2]
text = io.open(path, encoding='utf-8', errors='surrogateescape').read()
require = (
    "// Local run only: route prepared statements through escaped text queries.\n"
    "// See tools/php-run/shims/wdx_mysqli.php — remove this line for production.\n"
    f"require_once __DIR__ . '/{prefix}wdx_mysqli.php';\n"
)
if text.startswith('#<?php'):
    text = text.replace('#<?php', "<?php\n" + require, 1)
elif text.lstrip().startswith('<?php'):
    index = text.index('<?php') + len('<?php')
    text = text[:index] + "\n" + require + text[index:]
else:
    raise SystemExit(f"no <?php tag in {path}")
text = text.replace('new mysqli(', 'new WdxMysqli(')
io.open(path, 'w', encoding='utf-8', errors='surrogateescape').write(text)
PY
  echo "patched: $f"
done

echo
echo "shim installed. Start the app with: tools/php-run/dev.sh start"
