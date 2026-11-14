#!/usr/bin/env python3
"""
Export the parts of the woodexbaks dump that the preview needs, as JSON.

Reuses the parser in tools/db-audit/audit.py so the dump is read exactly once
with the same escaping rules, then normalises what it can:

  • strips the literal "SKU: " prefix and fills blank SKUs  (audit D33/D34)
  • adds is_active (the source table has no publish flag)   (audit S5)
  • parses the JSON columns once, so the loader does not have to
  • keeps HTML bodies intact for the product/category pages

Output: db-source/snapshot.json  (git-ignored — it contains customer PII)

Usage:
    python3 tools/db-import/export_snapshot.py db-source/woodexbaks.sql
"""
from __future__ import annotations

import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "db-audit"))
from audit import build_sqlite, parse_inserts, parse_schema, strip_line_comments  # noqa: E402


def as_float(value, default=0.0):
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def as_int(value, default=0):
    try:
        return int(float(value))
    except (TypeError, ValueError):
        return default


def parse_json(value, fallback):
    if value in (None, "", "null"):
        return fallback
    try:
        return json.loads(value)
    except (TypeError, ValueError):
        return fallback


def clean_html(value):
    if not value:
        return ""
    return str(value).replace("\r\n", "\n").strip()


def clean_sku(sku, product_id):
    text = (sku or "").strip()
    text = re.sub(r"^SKU\s*:\s*", "", text, flags=re.I).strip()
    return text or f"WD-{product_id}"


def rows(con, sql):
    con.row_factory = None
    return con.execute(sql).fetchall()


def main() -> int:
    dump = Path(sys.argv[1] if len(sys.argv) > 1 else "db-source/woodexbaks.sql")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "db-source/snapshot.json")
    if not dump.exists():
        print(f"dump not found: {dump}")
        return 1

    sql = dump.read_text(encoding="utf-8", errors="replace")
    clean = strip_line_comments(sql)
    tables = parse_schema(clean)
    data = parse_inserts(clean)
    con, _, errors = build_sqlite(tables, data, Path("/tmp/woodex-export.sqlite"))
    if errors:
        print(f"warning: {len(errors)} import errors", file=sys.stderr)

    # ---------------------------------------------------------------- categories
    categories = []
    for row in rows(con, "SELECT * FROM categories ORDER BY level, sort_order, id"):
        (cid, parent_id, level), rest = row[:3], row
        record = {
            "id": as_int(cid),
            "parentId": as_int(parent_id) if parent_id not in (None, 0, "0") else None,
            "level": as_int(level, 1),
            "path": row[10] if len(row) > 10 else str(cid),
            "name": row[12],
            "slug": row[13],
            "shortDescription": clean_html(row[14]),
            "longDescription": clean_html(row[15]),
            "backgroundImage": row[16] or None,
            "mainImage": row[17] or None,
            "isActive": bool(as_int(row[3], 1)),
            "sortOrder": as_int(row[11]),
            "showInHomeCarousel": bool(as_int(row[4])),
            "showInBestSellers": bool(as_int(row[8])),
        }
        categories.append(record)

    # ------------------------------------------------------------------ products
    products = []
    for row in rows(con, "SELECT * FROM products ORDER BY id"):
        mapped = dict(zip([d[0] for d in con.execute("SELECT * FROM products LIMIT 1").description], row))
        pid = as_int(mapped["id"])
        images = parse_json(mapped["images"], [])
        if isinstance(images, str):
            images = [images]
        specs = parse_json(mapped["specifications"], [])
        if isinstance(specs, dict):
            specs = [specs]
        products.append(
            {
                "id": pid,
                "name": (mapped["product_name"] or "").strip(),
                "slug": mapped["slug"] or f"product-{pid}",
                "sku": clean_sku(mapped["product_sku"], pid),
                "price": as_float(mapped["current_price"]),
                "oldPrice": as_float(mapped["old_price"]),
                "stock": as_int(mapped["quantity"]),
                "soldQuantity": as_int(mapped["sold_quantity"]),
                "categoryId": as_int(mapped["category"]) or None,
                "brand": "Woodex",
                "shortDescription": clean_html(mapped["short_description"]),
                "details": clean_html(mapped["product_details"]),
                "specifications": [s for s in specs if isinstance(s, dict)],
                "images": [i for i in images if isinstance(i, str)],
                "isBestSeller": bool(as_int(mapped["is_best_seller"])),
                "isSaleItem": bool(as_int(mapped["is_sale_item"])),
                "isActive": True,
                "ratingCount": as_int(mapped["rating_count"]),
                "ratingSum": as_int(mapped["rating_sum"]),
                "videoEmbed": mapped["video_embed"] or None,
            }
        )

    product_categories = [
        {"productId": as_int(r[0]), "categoryId": as_int(r[1])}
        for r in rows(con, "SELECT product_id, category_id FROM product_categories ORDER BY category_id, product_id")
    ]

    # ---------------------------------------------------------------------- faqs
    faqs = [
        {
            "id": as_int(r[0]),
            "scope": r[1],
            "itemId": as_int(r[2]),
            "question": r[3],
            "answer": clean_html(r[4]),
        }
        for r in rows(con, "SELECT id, faq_for, item_id, question, answer FROM faqs ORDER BY id")
    ]

    # -------------------------------------------------------------------- orders
    orders = []
    for r in rows(con, "SELECT * FROM orders ORDER BY order_date DESC, id DESC"):
        orders.append(
            {
                "id": as_int(r[0]),
                "orderNumber": r[1],
                "customerName": r[2],
                "customerEmail": r[3],
                "customerPhone": r[4],
                "shippingAddress": r[5],
                "orderDate": r[6],
                "total": as_float(r[7]),
                "status": r[8],
                "paymentStatus": r[9],
                "paymentIntentId": r[10],
                "paymentMethod": r[11],
                "couponCode": r[12],
                "discount": as_float(r[13]),
                "shipping": as_float(r[14]),
                "deliveryCompany": r[15],
                "trackingUrl": r[16],
                "trackingCode": r[17],
            }
        )

    order_items = [
        {
            "id": as_int(r[0]),
            "orderNumber": r[1],
            "productId": as_int(r[2]),
            "productName": r[4],
            "price": as_float(r[5]),
            "quantity": as_int(r[6]),
            "subtotal": as_float(r[7]),
            "productMissing": as_int(r[2]) not in {p["id"] for p in products},
        }
        for r in rows(con, "SELECT id, order_id, product_id, variation_id, product_name, price, quantity, subtotal FROM order_items ORDER BY id")
    ]

    # ---------------------------------------------------------------- quotations
    quotations = [
        {
            "id": as_int(r[0]),
            "quoteNo": r[1],
            "customerName": r[2],
            "customerEmail": r[3],
            "customerPhone": r[4],
            "customerAddress": r[5],
            "subtotal": as_float(r[6]),
            "total": as_float(r[7]),
            "status": r[8],
            "createdAt": r[9],
        }
        for r in rows(con, "SELECT * FROM quotation_requests ORDER BY created_at DESC, id DESC")
    ]

    quotation_items = [
        {
            "id": as_int(r[0]),
            "quoteId": as_int(r[1]),
            "productId": as_int(r[2]),
            "productName": r[4],
            "sku": r[5],
            "unitPrice": as_float(r[6]),
            "quantity": as_int(r[7]),
            "lineTotal": as_float(r[8]),
            "image": r[10] or None,
            "productMissing": as_int(r[2]) not in {p["id"] for p in products},
        }
        for r in rows(con, "SELECT id, request_id, product_id, variation_id, product_name, sku, unit_price, quantity, line_total, attributes_json, image FROM quotation_request_items ORDER BY id")
    ]

    # ---------------------------------------------------------------------- CMS
    # columns: id, slider_id, kicker, title, description, cta_label, cta_url,
    #          button_color, heading_color, description_color, image_path,
    #          image_alt, text_col, sort_order, is_published, ...
    hero_slides = [
        {
            "id": as_int(r[0]),
            "kicker": r[2],
            "title": (r[3] or "").replace("\r\n", "\n"),
            "description": r[4],
            "ctaLabel": r[5],
            "ctaUrl": r[6],
            "image": r[10],
            "alt": r[11],
            "published": bool(as_int(r[14], 1)),
        }
        for r in rows(con, "SELECT * FROM hero_slides ORDER BY sort_order, id")
    ]

    blogs = [
        {
            "id": as_int(r[0]),
            "title": r[1],
            "slug": r[2],
            "metaDescription": r[3],
            "image": r[4],
            "content": clean_html(r[5]),
            "createdAt": r[6],
        }
        for r in rows(con, "SELECT * FROM blogs ORDER BY created_at DESC, id DESC")
    ]

    footer_row = rows(con, "SELECT * FROM footer_settings LIMIT 1")
    footer = {}
    if footer_row:
        names = [d[0] for d in con.execute("SELECT * FROM footer_settings LIMIT 1").description]
        record = dict(zip(names, footer_row[0]))
        footer = {
            "tagline": record.get("about_tagline") or "Make your space work®",
            "about": clean_html(record.get("about_text")),
            "phone": record.get("phone_number"),
            "email": record.get("email_text"),
            "address": record.get("address_text"),
            "hours": record.get("hours_text"),
            "copyright": record.get("copyright_text"),
            "paymentLogos": parse_json(record.get("payment_logos_json"), []),
            "socialLinks": parse_json(record.get("social_links_json"), []),
        }

    footer_links = [
        {"section": r[0], "title": r[1], "url": r[2], "sort": as_int(r[3])}
        for r in rows(con, "SELECT section_key, link_title, link_url, sort_order FROM footer_links WHERE is_active = 1 ORDER BY section_key, sort_order")
    ]

    brands = [r[1] for r in rows(con, "SELECT id, brand_name FROM brands ORDER BY id")]

    snapshot = {
        "meta": {
            "source": dump.name,
            "database": (re.search(r"Database: `([^`]+)`", sql) or [None, None])[1],
            "server": (re.search(r"Server version: (\S+)", sql) or [None, None])[1],
            "php": (re.search(r"PHP Version: (\S+)", sql) or [None, None])[1],
            "importedAt": datetime.now(timezone.utc).isoformat(timespec="seconds"),
            "counts": {
                "categories": len(categories),
                "products": len(products),
                "productCategories": len(product_categories),
                "faqs": len(faqs),
                "orders": len(orders),
                "orderItems": len(order_items),
                "quotations": len(quotations),
                "quotationItems": len(quotation_items),
                "heroSlides": len(hero_slides),
                "blogs": len(blogs),
            },
            "imagesMissing": sum(1 for item in order_items if item["productMissing"]),
        },
        "categories": categories,
        "products": products,
        "productCategories": product_categories,
        "faqs": faqs,
        "orders": orders,
        "orderItems": order_items,
        "quotations": quotations,
        "quotationItems": quotation_items,
        "heroSlides": hero_slides,
        "blogs": blogs,
        "footer": footer,
        "footerLinks": footer_links,
        "brands": brands,
    }

    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(snapshot, ensure_ascii=False, indent=1))
    counts = snapshot["meta"]["counts"]
    print(f"snapshot written: {out} ({out.stat().st_size/1024:.0f} KB)")
    for key, value in counts.items():
        print(f"  {key:20s} {value}")
    print(f"  unresolved order lines {snapshot['meta']['imagesMissing']}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
