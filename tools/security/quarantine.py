#!/usr/bin/env python3
"""Move the files that must never be served out of the web root.

    python3 tools/security/quarantine.py            # move them to .tmp/quarantine/
    python3 tools/security/quarantine.py --check    # list them only

Everything moved here is recoverable from `.tmp/quarantine/` (git-ignored) —
nothing is deleted. The same items still have to be removed on the host; see
`tools/security/README.md`.
"""

from __future__ import annotations

import argparse
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DOCROOT = ROOT / "php-source" / "public_html"
QUARANTINE = ROOT / ".tmp" / "quarantine"

EXACT = [
    "error_log",
    "php.ini",
    ".user.ini",
    "php_errors.log",
    "composer.lock",
    "wdx_mysqli.php",          # local-run shim, never deployed
]
PATTERNS = ["*.orig", "*.bak", "*.sql", "*.log", "*.zip", "*~"]
SKIP_DIRS = {"assets", "lbpanel/uploads", "lbpanel/ckeditor", "phpmailer"}


def targets() -> list[Path]:
    found: list[Path] = []
    for name in EXACT:
        path = DOCROOT / name
        if path.exists():
            found.append(path)
    for pattern in PATTERNS:
        for path in DOCROOT.rglob(pattern):
            if not path.is_file():
                continue
            rel = str(path.relative_to(DOCROOT))
            if any(rel.startswith(skip + "/") for skip in SKIP_DIRS):
                continue
            found.append(path)
    sessions = DOCROOT / "sessions"
    if sessions.is_dir():
        found.append(sessions)
    return sorted(set(found))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true")
    args = ap.parse_args()

    items = targets()
    if not items:
        print("nothing to quarantine — the web root is clean")
        return 0

    print(f"{len(items)} item(s):")
    for path in items:
        rel = path.relative_to(DOCROOT)
        size = sum(f.stat().st_size for f in path.rglob("*") if f.is_file()) if path.is_dir() else path.stat().st_size
        print(f"  {str(rel):<45} {size / 1024:8.1f} KB" + ("/dir" if path.is_dir() else ""))

    if args.check:
        return 0

    for path in items:
        rel = path.relative_to(DOCROOT)
        dest = QUARANTINE / rel
        dest.parent.mkdir(parents=True, exist_ok=True)
        if dest.exists():
            shutil.rmtree(dest) if dest.is_dir() else dest.unlink()
        shutil.move(str(path), str(dest))
    print(f"\nmoved to {QUARANTINE.relative_to(ROOT)}/")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
