#!/usr/bin/env python3
"""Local content repairs for the .tmp/php-live.sqlite copy of the dump.

Only two, both explicitly derived from data problems found by
tools/media-audit.py and tools/db-deep-audit.py:

1. Category cards that show the Woodex *logo* instead of a photo.
   22 of the 53 categories have `main_image = assets/images/categories/Woodex-Logo*.png`,
   so their tile in the shop grid renders a logo where furniture belongs. When the
   category has products, its card is switched to the first photo of the first
   product in that category. Categories without products are left alone.

2. The hero slider contains a leftover test slide (kicker "sss", title "ssssss")
   whose background file was never uploaded. It is unpublished locally.

Everything else — prices, SKUs, missing photos, deleted product references — is
reported by the audits but NOT changed, because those are business decisions that
belong in the admin panel.

The dump itself is untouched; re-run tools/php-run/rebuild-db.sh to start clean,
then this script to re-apply.

Usage: python3 tools/php-run/content-fixes.py [--db .tmp/php-live.sqlite] [--dry-run]
"""

from __future__ import annotations

import argparse
import json
import sqlite3
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PUB = ROOT / "php-source" / "public_html"


def first_product_image(db: sqlite3.Connection, category_id: int) -> str | None:
    row = db.execute(
        """
        select p.images from products p
        join product_categories pc on pc.product_id = p.id
        where pc.category_id = ? and p.images is not null and p.images != ''
        order by p.id limit 1
        """,
        (category_id,),
    ).fetchone()
    if not row:
        return None
    try:
        images = json.loads(row[0])
    except (TypeError, json.JSONDecodeError):
        return None
    for item in images if isinstance(images, list) else []:
        rel = str(item).replace("\\/", "/").lstrip("/")
        if (PUB / rel).exists():
            return rel
    return None


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--db", default=str(ROOT / ".tmp" / "php-live.sqlite"))
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    db = sqlite3.connect(args.db)
    db.row_factory = sqlite3.Row
    changed_categories = 0

    rows = db.execute(
        "select id, category_name, main_image from categories "
        "where main_image like '%Logo%' order by sort_order, id"
    ).fetchall()
    for row in rows:
        replacement = first_product_image(db, row["id"])
        if not replacement:
            continue
        print(f"category #{row[' id'] if False else row['id']:>3} {row['category_name'][:34]:34s} "
              f"{row['main_image'].split('/')[-1]:22s} → {replacement.split('/')[-1]}")
        if not args.dry_run:
            db.execute("update categories set main_image = ? where id = ?", (replacement, row["id"]))
        changed_categories += 1

    slides = db.execute(
        "select id, kicker, title, image_path from hero_slides where is_published = 1"
    ).fetchall()
    changed_slides = 0
    for row in slides:
        image = (row["image_path"] or "").lstrip("/")
        missing_image = bool(image) and not (PUB / image).exists()
        test_copy = any(str(row[k] or "").strip().lower() in {"sss", "ssssss", "test", "asd"}
                        for k in ("kicker", "title"))
        if missing_image or test_copy:
            reason = "test copy" if test_copy else "image never uploaded"
            print(f"hero slide #{row['id']}: unpublish ({reason}) — {row['title']!r}")
            if not args.dry_run:
                db.execute("update hero_slides set is_published = 0 where id = ?", (row["id"],))
            changed_slides += 1

    if not args.dry_run:
        db.commit()
    print(f"\n{'would change' if args.dry_run else 'changed'}: "
          f"{changed_categories} category cards, {changed_slides} hero slides")
    return 0


if __name__ == "__main__":
    sys.exit(main())
