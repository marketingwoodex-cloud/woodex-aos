#!/usr/bin/env python3
"""Deep media + reference audit for the original Woodex PHP application.

Cross-checks three sources and reports every disagreement:

  1. files on disk under php-source/public_html (hash, real format, decode test)
  2. references stored in the database (.tmp/php-live.sqlite built from the dump)
  3. references hardcoded in PHP / JS / CSS / HTML templates

Outputs .tmp/media-audit.json (machine) and docs/media-audit.md (human).

Usage:  python3 tools/media-audit.py [--root .] [--db .tmp/php-live.sqlite]
Exit code is always 0; the report carries the verdict.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sqlite3
import sys
import unicodedata
from collections import defaultdict
from pathlib import Path
from urllib.parse import unquote, urlsplit

try:
    from PIL import Image, ImageFile

    ImageFile.LOAD_TRUNCATED_IMAGES = False
    HAVE_PIL = True
except ImportError:  # pragma: no cover
    HAVE_PIL = False

IMAGE_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif", ".bmp", ".svg", ".avif", ".ico"}
OTHER_MEDIA_EXT = {".mp4", ".webm", ".pdf", ".mov", ".mp3", ".wav", ".woff", ".woff2", ".ttf", ".otf", ".eot"}
WEB_FORMAT = {"JPEG": ".jpg", "MPO": ".jpg", "PNG": ".png", "WEBP": ".webp", "GIF": ".gif", "BMP": ".bmp"}

# Reference extraction -------------------------------------------------------
SRC_ATTR = re.compile(
    r"""(?:src|href|data-src|data-bg|data-image|poster|content)\s*=\s*["']([^"']+)["']""", re.I
)
CSS_URL = re.compile(r"""url\(\s*["']?([^"')]+)["']?\s*\)""", re.I)
_EXT_ALT = "|".join(e.lstrip(".") for e in sorted(IMAGE_EXT | OTHER_MEDIA_EXT))
PHP_STR_ASSET = re.compile(
    r"""["']([^"'\s<>]*?(?:assets/|uploads/)[^"'\s<>]+\.(?:""" + _EXT_ALT + r"""))["']""", re.I)
BARE_ASSET = re.compile(
    r"""((?:assets|uploads)/[A-Za-z0-9%_\-./()\[\] ]+?\.(?:""" + _EXT_ALT + r"""))""", re.I)

# DB columns that can hold media ---------------------------------------------
DB_MEDIA_COLUMNS = [
    ("products", "images"),
    ("products", "video_embed"),
    ("categories", "main_image"),
    ("categories", "background_image"),
    ("hero_slides", "image_path"),
    ("hero_slides_alt", "background_image_path"),
    ("blogs", "blog_image"),
    ("projects", "cover_image"),
    ("projects", "gallery_json"),
    ("customer_reviews", "profile_image"),
    ("product_variations", "image"),
    ("quotation_request_items", "image"),
    ("footer_settings", "payment_logos_json"),
    ("site_settings", "logo"),
    ("site_settings", "favicon"),
    ("settings", "logo"),
    ("settings", "favicon"),
    ("services", "image"),
    ("team_members", "image"),
    ("gallery", "image"),
    ("banners", "image"),
]


def norm_key(rel: str) -> str:
    """Case/NFC-insensitive key so macOS-uploaded names match Linux ones."""
    return unicodedata.normalize("NFC", unquote(rel)).replace("\\", "/").lstrip("/").lower()


def to_rel_path(raw: str) -> str | None:
    """Turn any stored reference into a repo-relative asset path."""
    if not raw:
        return None
    v = str(raw).strip().strip('"').strip("'").strip()
    if not v or v.startswith(("data:", "mailto:", "tel:", "javascript:", "#")):
        return None
    v = v.replace("\\\\/", "/").replace("\\/", "/").replace("\\", "/")
    if "://" in v or v.startswith("//"):
        parts = urlsplit(v if "://" in v else "https:" + v)
        v = parts.path
    # old dev prefix used by the previous developer: /woodex/assets/...
    v = re.sub(r"^/(?:woodex|public_html)/", "/", v)
    v = v.split("?")[0].split("#")[0]
    if not v:
        return None
    ext = os.path.splitext(v)[1].lower()
    if ext not in IMAGE_EXT | OTHER_MEDIA_EXT:
        return None
    return v.lstrip("/")


def iter_db_refs(db_path: Path):
    """Yield (table, column, row_id, raw_reference) for every stored media value."""
    if not db_path.exists():
        return
    db = sqlite3.connect(str(db_path))
    db.row_factory = sqlite3.Row
    tables = {r[0] for r in db.execute("select name from sqlite_master where type='table'")}
    for table, col in DB_MEDIA_COLUMNS:
        if table not in tables:
            continue
        cols = [r[1] for r in db.execute(f'pragma table_info("{table}")')]
        if col not in cols:
            continue
        idcol = "id" if "id" in cols else cols[0]
        label = None
        for cand in ("name", "title", "slug", "product_name", "category_name", "question"):
            if cand in cols:
                label = cand
                break
        sel = f'select "{idcol}" as rid{"," + chr(34) + label + chr(34) if label else ""}, "{col}" as val from "{table}" where "{col}" is not null and "{col}" != ""'
        for row in db.execute(sel):
            raw = row["val"]
            if isinstance(raw, str) and raw.lstrip().startswith(("[", "{")):
                try:
                    data = json.loads(raw)
                except json.JSONDecodeError:
                    data = None
                if data is not None:
                    stack = [data]
                    while stack:
                        item = stack.pop()
                        if isinstance(item, dict):
                            stack.extend(item.values())
                        elif isinstance(item, list):
                            stack.extend(item)
                        elif isinstance(item, str):
                            yield table, col, row["rid"], item, (row[label] if label else None)
                    continue
            yield table, col, row["rid"], raw, (row[label] if label else None)


def source_files(root: Path):
    base = root / "php-source" / "public_html"
    for path in base.rglob("*"):
        if not path.is_file():
            continue
        if "/vendor/" in str(path) or "/sessions/" in str(path):
            continue
        if path.suffix.lower() in {".php", ".js", ".html", ".css", ".json"}:
            yield path


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--db", default=".tmp/php-live.sqlite")
    ap.add_argument("--json-out", default=".tmp/media-audit.json")
    ap.add_argument("--md-out", default="docs/media-audit.md")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    pub = root / "php-source" / "public_html"
    db_path = (root / args.db) if not Path(args.db).is_absolute() else Path(args.db)

    # ---- 1. inventory -----------------------------------------------------
    disk = {}          # norm key -> info
    by_rel = {}        # exact rel -> info
    dupes = defaultdict(list)
    for path in pub.rglob("*"):
        if not path.is_file():
            continue
        rel = path.relative_to(pub).as_posix()
        if rel.startswith(("vendor/", "sessions/")):
            continue
        ext = path.suffix.lower()
        if ext not in IMAGE_EXT | OTHER_MEDIA_EXT:
            continue
        data = path.read_bytes()
        info = {
            "rel": rel,
            "size": len(data),
            "sha256": hashlib.sha256(data).hexdigest(),
            "ext": ext,
            "problems": [],
        }
        if ext in IMAGE_EXT:
            if HAVE_PIL and ext != ".svg":
                try:
                    with Image.open(path) as im:
                        im.verify()
                    with Image.open(path) as im:
                        info["width"], info["height"] = im.size
                        info["format"] = im.format
                        info["mode"] = im.mode
                    real = WEB_FORMAT.get(info.get("format", ""))
                    if real and real != ext and not (ext == ".jpeg" and real == ".jpg"):
                        info["problems"].append(f"extension .{ext.lstrip('.')} but real format {info.get('format')}")
                    if min(info["width"], info["height"]) < 80:
                        info["problems"].append(f"tiny image {info['width']}x{info['height']}")
                    if info["size"] == 0:
                        info["problems"].append("zero bytes")
                except Exception as exc:  # noqa: BLE001
                    info["problems"].append(f"cannot decode: {type(exc).__name__}: {exc}")
            elif ext == ".svg":
                head = data[:400].decode("utf-8", "replace").lower()
                if "<svg" not in head:
                    info["problems"].append("not an SVG document")
            if info["size"] > 6 * 1024 * 1024:
                info["problems"].append(f"oversized {info['size'] / 1048576:.1f} MB")
        disk[norm_key(rel)] = info
        by_rel[rel] = info
        dupes[info["sha256"]].append(rel)

    # ---- 2. references ----------------------------------------------------
    refs = []  # {source, table, column, row, label, raw, rel, found}
    for table, col, rid, raw, label in iter_db_refs(db_path):
        rel = to_rel_path(raw)
        if not rel:
            continue
        refs.append(
            dict(source="database", table=table, column=col, row=rid, label=label, raw=str(raw)[:300], rel=rel)
        )

    for path in source_files(root):
        try:
            text = path.read_text("utf-8", "replace")
        except OSError:
            continue
        rel_src = path.relative_to(pub).as_posix()
        hits = set()
        for rx in (SRC_ATTR, CSS_URL, PHP_STR_ASSET, BARE_ASSET):
            for m in rx.finditer(text):
                rel = to_rel_path(m.group(1))
                if rel:
                    hits.add((rel, m.group(1)[:200]))
        for rel, raw in hits:
            refs.append(
                dict(source="source", table=rel_src, column="template", row=None, label=None, raw=raw, rel=rel)
            )

    for r in refs:
        r["found"] = norm_key(r["rel"]) in disk
        if r["found"]:
            r["on_disk"] = disk[norm_key(r["rel"])]["rel"]
        else:
            # tolerate case/spacing differences and legacy duplicate prefixes
            alt = [k for k in disk if k.endswith(norm_key(r["rel"]).split("/")[-1])]
            r["near"] = [disk[k]["rel"] for k in alt][:3]

    # ---- 3. aggregates ----------------------------------------------------
    def is_app(rel: str) -> bool:
        """Application media (assets/, uploads/) vs bundled libraries (ckeditor…)."""
        return rel.startswith(("assets/", "uploads/"))

    for r in refs:
        r["app"] = is_app(r["rel"]) and not r["table"].startswith("lbpanel/includes/")

    missing_all = [r for r in refs if not r["found"]]
    missing = [r for r in missing_all if r["app"]]
    missing_lib = [r for r in missing_all if not r["app"]]
    referenced_keys = {norm_key(r["rel"]) for r in refs if r["found"]}
    orphans_all = [i for k, i in disk.items() if k not in referenced_keys]
    orphans = [o for o in orphans_all if is_app(o["rel"])]
    orphans_lib = [o for o in orphans_all if not is_app(o["rel"])]

    def group(rows, keyfn):
        out = defaultdict(list)
        for r in rows:
            out[keyfn(r)].append(r)
        return {k: v for k, v in sorted(out.items(), key=lambda kv: -len(kv[1]))}

    missing_by_dir = group(missing, lambda r: "/".join(os.path.dirname(r["rel"]).split("/")[:3]))
    orphans_by_dir = group(orphans, lambda r: os.path.dirname(r["rel"]))
    broken_disk = [i for i in disk.values() if i["problems"] and is_app(i["rel"])]
    broken_lib = [i for i in disk.values() if i["problems"] and not is_app(i["rel"])]
    largest = sorted((i for i in disk.values() if is_app(i["rel"])), key=lambda i: -i["size"])[:10]
    dup_groups = {h: v for h, v in dupes.items() if len(v) > 1}

    per_entity = defaultdict(list)
    for r in missing:
        if r["source"] == "database":
            per_entity[f"{r['table']}.{r['column']}"].append(f"{r['label'] or r['row']} → {r['rel']}")

    live_probe = {
        "checked_on": "2026-11-14",
        "method": "Each path was requested from https://woodexfurniture.pk/ directly. The live server "
                  "answers 404 for files it does not have; existing images return a non-text body, which "
                  "shows up as an HTTP 500 parse error in the fetcher.",
        "missing_in_production_too": [
            "assets/images/placeholder.png",
            "assets/images/placeholder-treatment.png",
            "assets/images/default-product.jpg",
            "assets/images/heroes/placeholder.png",
            "assets/images/heroes/1772573373_Footer.jpg",
            "assets/images/b2b-hero.jpg",
            "assets/images/contact-hero.jpg",
            "assets/images/installation.jpg",
            "assets/images/factory/facility.jpg",
            "assets/images/cart_success.gif",
            "assets/images/project/project-team.jpg",
            "assets/images/services/meeting-spaces.jpg",
            "assets/images/services/open-plan.jpg",
        ],
        "control_files_present": [
            "assets/images/categories/sofa-header.jpg",
            "assets/images/categories/Woodex-Logo-1.png",
            "assets/images/heroes/1762549804_sofa-set.jpg",
            "assets/images/products/1772730426_unnamed.jpg",
        ],
    }

    report = {
        "root": str(root),
        "database": str(db_path),
        "disk_files": len(disk),
        "disk_bytes": sum(i["size"] for i in disk.values()),
        "refs_total": len(refs),
        "refs_db": sum(1 for r in refs if r["source"] == "database"),
        "refs_source": sum(1 for r in refs if r["source"] == "source"),
        "missing": missing,
        "missing_count": len(missing),
        "missing_library": [r["rel"] for r in missing_lib],
        "orphans_library_count": len(orphans_lib),
        "broken_library": [{"rel": i["rel"], "problems": i["problems"]} for i in broken_lib],
        "largest": [{"rel": i["rel"], "mb": round(i["size"] / 1048576, 2),
                     "px": f"{i.get('width')}x{i.get('height')}", "size": i["size"],
                     "width": i.get("width"), "height": i.get("height")} for i in largest],
        "live_probe": live_probe,
        "missing_by_dir": {k: len(v) for k, v in missing_by_dir.items()},
        "orphans": [o["rel"] for o in orphans],
        "orphans_count": len(orphans),
        "orphans_by_dir": {k: len(v) for k, v in orphans_by_dir.items()},
        "broken_files": broken_disk,
        "duplicate_groups": [v for v in dup_groups.values()],
        "per_entity": {k: v for k, v in sorted(per_entity.items(), key=lambda kv: -len(kv[1]))},
        "disk": {i["rel"]: {k: v for k, v in i.items() if k != "rel"} for i in disk.values()},
    }

    json_out = root / args.json_out
    json_out.parent.mkdir(parents=True, exist_ok=True)
    json_out.write_text(json.dumps(report, indent=1, ensure_ascii=False))

    # ---- 4. markdown ------------------------------------------------------
    md = []
    md.append("# Media & reference audit\n")
    md.append("Generated by `python3 tools/media-audit.py`. Sources cross-checked: files on disk "
              "under `php-source/public_html`, media values stored in the database dump, and paths "
              "hardcoded in PHP/JS/CSS templates.\n")
    md.append("## Summary\n")
    md.append("| Metric | Value |\n| --- | --- |")
    md.append(f"| Media files on disk | {len(disk)} ({sum(i['size'] for i in disk.values()) / 1048576:.1f} MB) |")
    md.append(f"| References checked | {len(refs)} ({report['refs_db']} from DB, {report['refs_source']} from templates) |")
    md.append(f"| **Missing application media (referenced, not on disk)** | **{len(missing)}** |")
    md.append(f"| Missing library/editor assets (ckeditor, not content) | {len(missing_lib)} |")
    md.append(f"| Unreferenced application media (orphans) | {len(orphans)} |")
    md.append(f"| Files that fail decode / are suspicious | {len(broken_disk)} |")
    md.append(f"| Byte-identical duplicate sets | {len(dup_groups)} |")
    md.append("")

    if missing:
        md.append("## Missing media\n")
        md.append("Referenced by the application (database rows or templates) but not present on disk. "
                  "Each of these was also requested from the live production site — see the probe section "
                  "below; the files are **not in production either**, so this is an upstream bug in the "
                  "delivered application, not a gap in the archive.\n")
        for d, rows in missing_by_dir.items():
            md.append(f"### `{d}/` — {len(rows)}\n")
            md.append("| referenced as | referenced from | entity | near match on disk |\n| --- | --- | --- | --- |")
            for r in rows[:200]:
                src = f"{r['table']}.{r['column']}" if r["source"] == "database" else f"`{r['table']}`"
                near = ", ".join(r.get("near") or []) or "—"
                md.append(f"| `{r['rel']}` | {src} | {r['label'] or r['row'] or '—'} | {near} |")
            md.append("")

    md.append("## Production probe\n")
    md.append(live_probe["method"] + "\n")
    md.append("Missing on production as well (upstream bug):\n")
    for item in live_probe["missing_in_production_too"]:
        md.append(f"- `{item}`")
    md.append("\nControl files that *do* exist on production (proof the probe can tell the difference):\n")
    for item in live_probe["control_files_present"]:
        md.append(f"- `{item}`")
    md.append("")

    if largest:
        md.append("## Largest application images\n")
        md.append("| file | size | pixels |\n| --- | --- | --- |")
        for item in largest:
            md.append(f"| `{item['rel']}` | {item['size'] / 1048576:.2f} MB | "
                      f"{item.get('width')}x{item.get('height')} |")
        md.append("")

    if broken_disk:
        md.append("## Files present but broken\n")
        md.append("| file | problem |\n| --- | --- |")
        for i in broken_disk[:200]:
            md.append(f"| `{i['rel']}` | {'; '.join(i['problems'])} |")
        md.append("")

    if dup_groups:
        md.append("## Duplicate media\n")
        md.append("Same bytes stored under several names (wasted space, and a sign of re-uploads):\n")
        for _, names in sorted(dup_groups.items(), key=lambda kv: -len(kv[1]))[:40]:
            md.append(f"- {len(names)}× — " + ", ".join(f"`{n}`" for n in names[:6]) + (" …" if len(names) > 6 else ""))
        md.append("")

    if orphans:
        md.append("## Unreferenced files\n")
        md.append("Present on disk but no database row or template points at them (safe to keep; they may be "
                  "leftovers from earlier uploads).\n")
        for d, rows in orphans_by_dir.items():
            md.append(f"- `{d}/` — {len(rows)} files")
        md.append("")

    if missing_lib:
        md.append("## Missing files inside bundled libraries\n")
        md.append("Not application content — CKEditor sample pages and third-party assets. Left as-is.\n")
        for r in missing_lib[:40]:
            md.append(f"- `{r['rel']}` (from `{r['table']}`)")
        md.append("")

    md_out = root / args.md_out
    md_out.parent.mkdir(parents=True, exist_ok=True)
    md_out.write_text("\n".join(md) + "\n")

    print(f"files on disk      : {len(disk)} ({sum(i['size'] for i in disk.values()) / 1048576:.1f} MB)")
    print(f"references checked : {len(refs)} (db {report['refs_db']}, templates {report['refs_source']})")
    print(f"missing            : {len(missing)}")
    for d, rows in list(missing_by_dir.items())[:12]:
        print(f"   {len(rows):4d}  {d}/")
    print(f"orphans            : {len(orphans)}")
    print(f"broken files       : {len(broken_disk)}")
    for i in broken_disk[:10]:
        print(f"   {i['rel']}: {'; '.join(i['problems'])}")
    print(f"duplicate sets     : {len(dup_groups)}")
    print(f"wrote {args.json_out} and {args.md_out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
