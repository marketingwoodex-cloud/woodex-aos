#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
if [[ $# -ne 1 || "${WOODEX_CONFIRM_STAGING:-}" != 'staging.blackibex.net' ]]; then
  echo 'Usage: WOODEX_CONFIRM_STAGING=staging.blackibex.net bash deployment/publish-staging.sh /absolute/staging/document/root' >&2
  echo 'Back up the target first. This copies and overwrites matching files; it does not delete stale files.' >&2
  exit 2
fi
if [[ "$1" != /* || ! -d "$1" || -L "$1" ]]; then
  echo 'The target must be an existing, non-symlink absolute directory.' >&2
  exit 2
fi
TARGET="$(cd "$1" && pwd -P)"
if [[ "$TARGET" == '/' || "$TARGET" == "$ROOT" || "$TARGET" == "$ROOT/"* || "$ROOT" == "$TARGET/"* ]]; then
  echo 'The target must be outside the repository and must not contain it.' >&2
  exit 2
fi
if [[ -e "$TARGET/.git" ]] || [[ -n "$(find "$TARGET" -type l -print -quit)" ]]; then
  echo 'Refusing a Git checkout or a target containing symbolic links.' >&2
  exit 2
fi
# Export only tracked application files, never Git metadata or project tooling.
# The export is from the committed HEAD; commit changes before publishing.
git -C "$ROOT" archive HEAD | tar -x -C "$TARGET" \
  --exclude='./deployment' --exclude='deployment' \
  --exclude='./preview' --exclude='preview' \
  --exclude='./downloads' --exclude='downloads' \
  --exclude='./database' --exclude='database' \
  --exclude='.gitignore' --exclude='README.md' \
  --exclude='PROJECT_MASTER_REPORT.md' --exclude='PREVIEW_STATUS_AND_REMAINING_WORK.md' \
  --exclude='public_html.part*.zip'
echo 'Committed staging application files copied. Verify password protection and the domain before testing.'
