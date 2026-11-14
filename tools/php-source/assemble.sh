#!/usr/bin/env bash
# Assembles the split public_html archive pushed to the repository root,
# verifies it against the original sizes, and extracts it into php-source/.
#
#   tools/php-source/assemble.sh
#
# Expected parts (24 MiB chunks of a 98,599,986-byte zip):
#   public_html.part1.zip  25,165,824
#   public_html.part2.zip  25,165,824
#   public_html.part3.zip  25,165,824
#   public_html.part4.zip  23,102,514   <- short tail
#
# A single unsplit public_html.zip is also accepted (drop it in the repo root
# or pass its path as $1).
set -euo pipefail

cd "$(dirname "$0")/../.."
EXPECTED_BYTES=98599986
WORK=.tmp/php-source
DEST=php-source

single="${1:-}"
if [ -n "$single" ] && [ -f "$single" ]; then
  zip_path="$single"
else
  parts=(public_html.part*.zip)
  if [ ! -e "${parts[0]}" ]; then
    echo "No public_html.part*.zip or public_html.zip found in $(pwd)" >&2
    exit 1
  fi
  n_parts=${#parts[@]}
  echo "Found $n_parts part(s): ${parts[*]}"
  mkdir -p "$WORK"
  # sort by the numeric suffix so part10 never precedes part2
  printf '%s\n' "${parts[@]}" | sort -V | while read -r part; do
    cp "$part" "$WORK/"
  done
  cat "$WORK"/public_html.part*.zip > "$WORK/public_html.zip"
  zip_path="$WORK/public_html.zip"
fi

size=$(stat -c %s "$zip_path")
echo "Assembled size: $size bytes (expected $EXPECTED_BYTES)"
if [ "$size" -ne "$EXPECTED_BYTES" ]; then
  echo "Size mismatch — archive is incomplete or a part is missing." >&2
  echo "Missing bytes: $((EXPECTED_BYTES - size))" >&2
  exit 1
fi

python3 - "$zip_path" <<'PY'
import sys, zipfile
path = sys.argv[1]
with zipfile.ZipFile(path) as archive:
    bad = archive.testzip()
    if bad:
        raise SystemExit(f"Corrupt member: {bad}")
    names = archive.namelist()
    print(f"Archive OK — {len(names)} entries, {sum(i.file_size for i in archive.infolist())/1048576:.1f} MiB uncompressed")
PY

rm -rf "$DEST"
mkdir -p "$DEST"
unzip -q "$zip_path" -d "$DEST"
echo
echo "Extracted to $DEST/"
find "$DEST" -type f | sed 's/.*\.//' | sort | uniq -c | sort -rn | head -15
echo
echo "PHP files: $(find "$DEST" -name '*.php' | wc -l)"
