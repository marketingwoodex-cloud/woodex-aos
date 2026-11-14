#!/usr/bin/env python3
"""Deep integrity audit of the Woodex database + schema/code cross-check.

Runs against the local SQLite rebuild of the cPanel dump (.tmp/php-live.sqlite)
and against the PHP source, and reports:

  * row-level data problems (prices, slugs, images, ratings, stock)
  * referential problems (orphan joins, products without categories, order items
    pointing at deleted products, category tree inconsistencies)
  * schema problems the code would hit at runtime: SQL statements in the PHP
    source that mention tables or columns that do not exist in the database

Output: docs/database-deep-audit.md (+ .tmp/db-deep-audit.json)

Usage: python3 tools/db-deep-audit.py
"""

from __future__ import annotations

import json
import re
import sqlite3
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DB = ROOT / ".tmp" / "php-live.sqlite"
PHP = ROOT / "php-source" / "public_html"

TABLE_RX = re.compile(r"\b(?:from|join|into|update)\s+[`\"']?([a-z_][a-z0-9_]*)[`\"']?", re.I)
SQL_STMT_RX = re.compile(
    r"([\"']\s*(?:select|insert\s+into|update|delete\s+from)[^;\"']{8,})", re.I | re.S)
COL_RX = re.compile(r"\b(?:insert\s+into|update)\s+[`\"']?([a-z_][a-z0-9_]*)[`\"']?\s*(?:\(([^)]*)\)|set\s+([^w]*?)\bwhere\b)", re.I | re.S)
SQL_KEYWORDS = {"select", "where", "set", "values", "value", "and", "or", "as", "on", "group", "order",
                "limit", "duplicate", "key", "ignore", "into", "from", "table", "if", "exists", "not"}


def audit() -> dict:
    db = sqlite3.connect(str(DB))
    db.row_factory = sqlite3.Row
    q = lambda s, *a: db.execute(s, a).fetchall()  # noqa: E731
    one = lambda s, *a: q(s, *a)[0][0]  # noqa: E731
    tables = {r[0] for r in q("select name from sqlite_master where type='table'")}
    cols = {t: [c[1] for c in q(f'pragma table_info("{t}")')] for t in tables}
    out: dict = {"counts": {}, "findings": defaultdict(list), "schema_code": []}

    for t in sorted(tables):
        if t.startswith("sqlite_"):
            continue
        out["counts"][t] = one(f'select count(*) from "{t}"')

    f = out["findings"]
    img_dir = PHP / "assets" / "images"

    def has_image(rel: str) -> bool:
        if not rel:
            return False
        p = rel.replace("\\/", "/").lstrip("/")
        p = re.sub(r"^https?://[^/]+/(woodex/)?", "", p)
        return (PHP / p).exists()

    # ---------------- products ----------------
    if "products" in tables:
        P = "products"
        for r in q(f"select id,product_name,current_price,old_price,is_sale_item,slug,quantity,sold_quantity,"
                   f"rating_count,rating_sum,images,category,product_sku from {P}"):
            pid, name = r["id"], r["product_name"] or "(unnamed)"
            if not r["current_price"] or r["current_price"] <= 0:
                f["products.bad_price"].append(f"#{pid} {name}: current_price={r['current_price']}")
            if r["old_price"] and r["current_price"] and r["old_price"] < r["current_price"]:
                f["products.old_price_below_price"].append(
                    f"#{pid} {name}: old {r['old_price']} < current {r['current_price']}")
            if r["is_sale_item"] and not r["old_price"]:
                f["products.sale_without_old_price"].append(f"#{pid} {name}")
            if not r["slug"]:
                f["products.missing_slug"].append(f"#{pid} {name}")
            elif not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", r["slug"]):
                f["products.odd_slug"].append(f"#{pid} {name} → {r['slug']!r}")
            if r["quantity"] is not None and r["quantity"] < 0:
                f["products.negative_stock"].append(f"#{pid} {name}: {r['quantity']}")
            if (r["rating_count"] or 0) > 0 and not r["rating_sum"]:
                f["products.rating_inconsistent"].append(f"#{pid} {name}: count={r['rating_count']} sum=0")
            try:
                imgs = json.loads(r["images"]) if r["images"] else []
                if not isinstance(imgs, list) or not imgs:
                    f["products.no_image"].append(f"#{pid} {name}")
                else:
                    missing = [i for i in imgs if not has_image(i)]
                    if missing:
                        f["products.image_file_missing"].append(f"#{pid} {name}: {missing}")
            except json.JSONDecodeError:
                f["products.invalid_image_json"].append(f"#{pid} {name}: {str(r['images'])[:60]}")
        for r in q(f"select slug,group_concat(product_name,' | ') n,count(*) c from {P} group by slug having c>1"):
            f["products.duplicate_slug"].append(f"{r['slug']!r} × {r['c']}: {r['n']}")
        for r in q(f"select product_name,count(*) c from {P} group by product_name having c>1"):
            f["products.duplicate_name"].append(f"{r['product_name']!r} × {r['c']}")
        for r in q(f"select product_sku,count(*) c from {P} where product_sku is not null and product_sku!='' "
                   f"group by product_sku having c>1"):
            f["products.duplicate_sku"].append(f"{r['product_sku']!r} × {r['c']}")

        # category linkage: legacy products.category vs product_categories
        if "product_categories" in tables:
            for r in q(f"select p.id,p.product_name,p.category from {P} p "
                       f"where not exists(select 1 from product_categories pc where pc.product_id=p.id)"):
                f["products.no_category_link"].append(f"#{r['id']} {r['product_name']} (category={r['category']!r})")
            for r in q("select pc.product_id,pc.category_id from product_categories pc "
                       "where not exists(select 1 from products p where p.id=pc.product_id)"):
                f["product_categories.orphan_product"].append(f"product #{r['product_id']} → cat #{r['category_id']}")
            if "categories" in tables:
                for r in q("select pc.product_id,pc.category_id from product_categories pc "
                           "where not exists(select 1 from categories c where c.id=pc.category_id)"):
                    f["product_categories.orphan_category"].append(
                        f"product #{r['product_id']} → cat #{r['category_id']}")
            mism = []
            for r in q(f"select p.id,p.product_name,p.category,group_concat(pc.category_id) ids from {P} p "
                       f"left join product_categories pc on pc.product_id=p.id group by p.id"):
                ids = {int(x) for x in (r["ids"] or "").split(",") if x}
                legacy = r["category"]
                if legacy not in (None, "") and str(legacy).isdigit() and int(legacy) not in ids:
                    mism.append(f"#{r['id']} {r['product_name']}: products.category={legacy} but links={sorted(ids)}")
            f["products.category_column_mismatch"] = mism

    # ---------------- categories ----------------
    if "categories" in tables:
        C = "categories"
        cids = {r[0] for r in q(f"select id from {C}")}
        for r in q(f"select id,parent_id,category_name,slug,level,path,main_image,background_image,is_active from {C}"):
            cid, name = r["id"], r["category_name"]
            if r["parent_id"] and r["parent_id"] not in cids:
                f["categories.orphan_parent"].append(f"#{cid} {name}: parent {r['parent_id']} missing")
            if r["slug"] and not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", r["slug"]):
                f["categories.odd_slug"].append(f"#{cid} {name} → {r['slug']!r}")
            if not has_image(r["main_image"]):
                f["categories.main_image_missing"].append(f"#{cid} {name}: {r['main_image']}")
            if not has_image(r["background_image"]):
                f["categories.background_image_missing"].append(f"#{cid} {name}: {r['background_image']}")
            mi = (r["main_image"] or "").lower()
            if "logo" in mi:
                f["categories.logo_used_as_photo"].append(f"#{cid} {name}: {r['main_image']}")
            if not r["is_active"]:
                n = one("select count(*) from product_categories where category_id=?", cid)
                if n:
                    f["categories.inactive_but_has_products"].append(f"#{cid} {name}: {n} products")
        for r in q(f"select slug,group_concat(category_name,' | ') n,count(*) c from {C} group by slug"):
            if r["c"] > 1:
                f["categories.duplicate_slug"].append(f"{r['slug']!r} × {r['c']}: {r['n']}")
        empty = [f"#{r['id']} {r['category_name']}" for r in q(
            f"select c.id,c.category_name from {C} c where not exists("
            f"select 1 from product_categories pc where pc.category_id=c.id) order by c.sort_order")]
        f["categories.empty"] = empty
        # parent links that disagree with the stored level
        for r in q(f"select id,category_name,level,parent_id from {C}"):
            if (r["parent_id"] in (None, 0)) != (r["level"] == 0):
                f["categories.level_mismatch"].append(
                    f"#{r['id']} {r['category_name']}: level={r['level']} parent={r['parent_id']}")

    # ---------------- orders / quotes ----------------
    if {"orders", "order_items"} <= tables:
        prod = {r[0] for r in q("select id from products")}
        # order_items.order_id stores orders.order_number (string), not orders.id — the
        # application joins that way (dashboard.php, fetch_order_items.php, review.php).
        for r in q("select oi.id,oi.order_id from order_items oi "
                   "where not exists(select 1 from orders o where o.order_number=oi.order_id)"):
            f["order_items.orphan_order"].append(
                f"item #{r['id']} → order_number {r['order_id']!r} not found in orders")
        for r in q("select id,order_id,product_id,product_name from order_items"):
            if r["product_id"] and r["product_id"] not in prod:
                f["order_items.deleted_product"].append(
                    f"item #{r['id']} (order {r['order_id']}) → product #{r['product_id']} {r['product_name']}")
        for r in q("select o.id,o.order_number,o.total_amount,"
                   "coalesce(sum(oi.subtotal),0) s,coalesce(sum(oi.price*oi.quantity),0) s2 "
                   "from orders o left join order_items oi on oi.order_id=o.order_number group by o.id"):
            if abs((r["total_amount"] or 0) - r["s"]) > 1:
                f["orders.total_mismatch"].append(
                    f"#{r['id']} {r['order_number']}: total={r['total_amount']} items={r['s']} (price*Qty={r['s2']})")
        for r in q("select o.id,o.order_number from orders o where not exists("
                   "select 1 from order_items oi where oi.order_id=o.order_number)"):
            f["orders.no_items"].append(f"#{r['id']} {r['order_number']}")
        for r in q("select status,count(*) c from orders group by status"):
            f["orders.status_values"].append(f"{r['status']}: {r['c']}")
        for r in q("select payment_status,count(*) c from orders group by payment_status"):
            f["orders.payment_status_values"].append(f"{r['payment_status']}: {r['c']}")

    if {"quotation_requests", "quotation_request_items"} <= tables:
        prod = {r[0] for r in q("select id from products")} if "products" in tables else set()
        for r in q("select q.id,q.quote_no,coalesce(sum(i.line_total),0) s from quotation_requests q "
                   "left join quotation_request_items i on i.request_id=q.id group by q.id"):
            if abs((r["s"] or 0)) == 0:
                f["quotation_requests.empty"].append(f"#{r['id']} {r['quote_no']}")
        for r in q("select id,request_id,product_id,product_name from quotation_request_items"):
            if r["product_id"] and prod and r["product_id"] not in prod:
                f["quotation_items.deleted_product"].append(
                    f"item #{r['id']} (quote {r['request_id']}) → product #{r['product_id']} {r['product_name']}")
        for r in q("select q.id,q.quote_no from quotation_requests q where not exists("
                   "select 1 from quotation_request_items i where i.request_id=q.id)"):
            f["quotation_requests.no_items"].append(f"#{r['id']} {r['quote_no']}")

    # ---------------- settings / sliders ----------------
    for t in ("hero_slides", "hero_slides_alt", "blogs", "projects", "footer_settings"):
        if t not in tables:
            continue
        for col in cols[t]:
            if not any(k in col for k in ("image", "logo", "cover", "gallery", "payment_logos")):
                continue
            for r in q(f'select rowid rid,"{col}" v from "{t}" where "{col}" is not null and "{col}"!=""'):
                v = r["v"]
                vals = re.findall(r"[^\"',\[\]{}]+?\.(?:jpg|jpeg|png|webp|gif|svg)", str(v), re.I)
                for val in vals:
                    if not has_image(val.strip()):
                        f[f"{t}.{col}_missing"].append(f"row {r['rid']}: {val.strip()}")

    # ---------------- schema vs code ----------------
    known_tables = tables | {"dual"}
    seen_tables = defaultdict(set)
    for path in PHP.rglob("*.php"):
        if "/vendor/" in path.as_posix() or "/ckeditor/" in path.as_posix():
            continue
        try:
            src = path.read_text("utf-8", "replace")
        except OSError:
            continue
        rel = path.relative_to(PHP).as_posix()
        sql_text = "\n".join(m.group(1) for m in SQL_STMT_RX.finditer(src))
        for m in TABLE_RX.finditer(sql_text):
            t = m.group(1).lower()
            if t in SQL_KEYWORDS or t.startswith("information_schema"):
                continue
            seen_tables[t].add(rel)
        for m in COL_RX.finditer(sql_text):
            t = m.group(1).lower()
            if t not in tables:
                continue
            if m.group(2):
                names = [c.strip().strip("`'\"") for c in m.group(2).split(",")]
            else:
                names = [re.split(r"[=\s]+", c.strip())[0].strip("`'\"") for c in m.group(3).split(",")]
            for name in names:
                name = re.sub(r"^[`'\"(]+|[`'\")]+$", "", name.strip())
                if not name or not re.fullmatch(r"[a-z_][a-z0-9_]*", name, re.I):
                    continue
                if name.lower() in ("select", "now()", "null"):
                    continue
                if name not in cols[t]:
                    f["schema.missing_column"].append(f"{rel}: {t}.{name}")

    for t, where in sorted(seen_tables.items()):
        # strip obvious false positives coming from prose/PHP keywords
        if t in known_tables or t in SQL_KEYWORDS:
            continue
        out["schema_code"].append({"table": t, "referenced_in": sorted(where)[:4]})

    out["findings"] = {k: v for k, v in sorted(out["findings"].items())}
    return out


def write_md(a: dict) -> str:
    f = a["findings"]
    md = ["# Database & schema deep audit\n",
          "Generated by `python3 tools/db-deep-audit.py` against `.tmp/php-live.sqlite` "
          "(rebuilt from `db-source/woodexbaks.sql`) and the PHP source in `php-source/public_html`.\n",
          "## Row counts\n", "| table | rows |\n| --- | --- |"]
    for t, n in sorted(a["counts"].items()):
        if n:
            md.append(f"| `{t}` | {n} |")
    empty = [t for t, n in a["counts"].items() if not n]
    if empty:
        md.append("\nEmpty tables (schema present, no data): " + ", ".join(f"`{t}`" for t in sorted(empty)) + "\n")

    groups = {
        "Referential integrity": [k for k in f if any(s in k for s in (".orphan", "no_category_link", "level_mismatch", "quotation_requests.no_items", "orders.no_items", "no_image"))],
        "Products": [k for k in f if k.startswith("products.")],
        "Categories": [k for k in f if k.startswith("categories.")],
        "Orders & quotations": [k for k in f if k.startswith(("orders.", "order_items.", "quotation"))],
        "Media stored in database": [k for k in f if k.endswith(("_missing",)) and not k.startswith(("products.", "categories."))],
        "Schema vs code": [k for k in f if k.startswith("schema.")],
    }
    used = set()
    for title, keys in groups.items():
        keys = [k for k in keys if k in f]
        if not keys:
            continue
        md.append(f"## {title}\n")
        for k in keys:
            rows = f[k]
            used.add(k)
            md.append(f"**`{k}`** — {len(rows)}")
            for row in rows[:40]:
                md.append(f"- {row}")
            if len(rows) > 40:
                md.append(f"- … {len(rows) - 40} more (see .tmp/db-deep-audit.json)")
            md.append("")
    rest = {k: v for k, v in f.items() if k not in used}
    if rest:
        md.append("## Other findings\n")
        for k, rows in rest.items():
            md.append(f"**`{k}`** — {len(rows)}")
            for row in rows[:25]:
                md.append(f"- {row}")
            md.append("")
    if a["schema_code"]:
        md.append("## Tables referenced by code but absent from the database\n")
        md.append("| table | referenced in |\n| --- | --- |")
        for row in a["schema_code"]:
            md.append(f"| `{row['table']}` | {', '.join(row['referenced_in'])} |")
        md.append("")
    return "\n".join(md) + "\n"


if __name__ == "__main__":
    if not DB.exists():
        sys.exit(f"database not found: {DB} — run tools/php-run/rebuild-db.sh first")
    a = audit()
    (ROOT / ".tmp" / "db-deep-audit.json").write_text(json.dumps(a, indent=1))
    (ROOT / "docs" / "database-deep-audit.md").write_text(write_md(a))
    total = sum(len(v) for v in a["findings"].values())
    print(f"findings: {total} across {len(a['findings'])} categories, {len(a['counts'])} tables")
    for k, v in a["findings"].items():
        print(f"  {len(v):5d}  {k}")
    if a["schema_code"]:
        print("\ntables referenced by code but missing:", [r["table"] for r in a["schema_code"]])
    print("\nwrote docs/database-deep-audit.md and .tmp/db-deep-audit.json")
