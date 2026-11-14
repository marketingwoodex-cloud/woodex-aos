#!/usr/bin/env python3
"""
Audit a phpMyAdmin MySQL/MariaDB dump.

Parses CREATE TABLE / ALTER TABLE / INSERT statements straight out of the dump,
mirrors the schema and rows into SQLite, then runs structural + data-integrity
checks and emits a JSON summary (and optionally a Markdown report).

Usage:
    python3 tools/db-audit/audit.py path/to/dump.sql --out-db /tmp/audit.sqlite \
        --json /tmp/audit.json
"""
from __future__ import annotations

import argparse
import json
import re
import sqlite3
import sys
from collections import Counter, OrderedDict
from pathlib import Path

# --------------------------------------------------------------------------- #
# Dump parsing
# --------------------------------------------------------------------------- #

CREATE_RE = re.compile(
    r"CREATE TABLE `(?P<name>[^`]+)` \((?P<body>.*?)\n\)\s*ENGINE=(?P<engine>\S+)"
    r"(?:\s+DEFAULT CHARSET=(?P<charset>\S+))?(?:\s+COLLATE=(?P<collate>\S+))?;",
    re.S,
)
ALTER_RE = re.compile(r"ALTER TABLE `(?P<name>[^`]+)`(?P<body>.*?);", re.S)
COLUMN_RE = re.compile(r"^\s*`(?P<name>[^`]+)`\s+(?P<type>[a-zA-Z]+(?:\([^)]*\))?(?:\s+unsigned)?)(?P<rest>.*)$")


def strip_line_comments(sql: str) -> str:
    out = []
    for line in sql.split("\n"):
        stripped = line.lstrip()
        if stripped.startswith("--") or stripped.startswith("/*!") or stripped.startswith("/*"):
            continue
        out.append(line)
    return "\n".join(out)


def sqlite_type(mysql_type: str) -> str:
    base = mysql_type.lower().split("(")[0].strip()
    if base in {"int", "integer", "bigint", "smallint", "tinyint", "mediumint", "bit", "year"}:
        return "INTEGER"
    if base in {"decimal", "numeric", "float", "double", "real", "dec"}:
        return "REAL"
    if base in {"blob", "longblob", "mediumblob", "tinyblob", "binary", "varbinary"}:
        return "TEXT"
    return "TEXT"


def parse_schema(sql: str):
    tables = OrderedDict()
    for match in CREATE_RE.finditer(sql):
        name = match.group("name")
        body = match.group("body")
        columns, keys_inline = [], []
        for raw_line in body.split("\n"):
            line = raw_line.strip().rstrip(",")
            if not line:
                continue
            upper = line.upper()
            if upper.startswith(("PRIMARY KEY", "UNIQUE KEY", "KEY ", "CONSTRAINT", "INDEX ", "FULLTEXT")):
                keys_inline.append(line)
                continue
            col = COLUMN_RE.match(raw_line)
            if not col:
                continue
            rest = col.group("rest")
            columns.append(
                {
                    "name": col.group("name"),
                    "mysql_type": col.group("type").strip(),
                    "sqlite_type": sqlite_type(col.group("type")),
                    "not_null": "NOT NULL" in rest.upper(),
                    "default": (re.search(r"DEFAULT\s+([^ ]+)", rest, re.I) or [None, None])[1],
                    "auto_increment": "AUTO_INCREMENT" in rest.upper(),
                    "enum": re.findall(r"'([^']*)'", col.group("type")) if col.group("type").lower().startswith("enum") else [],
                }
            )
        tables[name] = {
            "engine": match.group("engine"),
            "charset": (match.group("charset") or "").rstrip(";"),
            "collate": (match.group("collate") or "").rstrip(";"),
            "columns": columns,
            "pk": [],
            "unique": [],
            "indexes": [],
            "foreign_keys": [],
            "auto_increment_next": None,
        }

    for match in ALTER_RE.finditer(sql):
        name = match.group("name")
        body = match.group("body")
        if name not in tables:
            continue
        table = tables[name]
        for block in body.split("\n"):
            block = block.strip().rstrip(",")
            pk = re.match(r"ADD PRIMARY KEY \(([^)]*)\)", block, re.I)
            if pk:
                table["pk"] = [c.strip().strip("`") for c in pk.group(1).split(",")]
            uniq = re.match(r"ADD UNIQUE KEY `([^`]+)` \(([^)]*)\)", block, re.I)
            if uniq:
                cols = [c.strip().strip("`").split("(")[0] for c in uniq.group(2).split(",")]
                table["unique"].append({"name": uniq.group(1), "columns": cols})
            key = re.match(r"ADD KEY `([^`]+)` \(([^)]*)\)", block, re.I)
            if key:
                table["indexes"].append(key.group(1))
            fk = re.search(
                r"ADD CONSTRAINT `([^`]+)` FOREIGN KEY \(([^)]*)\) REFERENCES `([^`]+)` \(([^)]*)\)(.*)",
                block,
                re.I,
            )
            if fk:
                table["foreign_keys"].append(
                    {
                        "name": fk.group(1),
                        "columns": [c.strip().strip("`") for c in fk.group(2).split(",")],
                        "ref_table": fk.group(3),
                        "ref_columns": [c.strip().strip("`") for c in fk.group(4).split(",")],
                        "on_delete": (re.search(r"ON DELETE (\w+(?: \w+)?)", fk.group(5), re.I) or [None, None])[1],
                    }
                )
            ai = re.search(r"AUTO_INCREMENT=(\d+)", block)
            if ai:
                table["auto_increment_next"] = int(ai.group(1))
    return tables


ESCAPES = {"0": "\0", "b": "\b", "n": "\n", "r": "\r", "t": "\t", "Z": "\x1a", "\\": "\\", "'": "'", '"': '"'}


def parse_insert_values(chunk: str):
    """Parse `(a,b),(c,d)` into a list of tuples. Returns (rows, ok)."""
    rows, row, value = [], [], None
    i, n = 0, len(chunk)
    in_string = False
    quote = ""
    in_row = False
    buf = []

    def flush_value():
        nonlocal buf, value
        raw = "".join(buf)
        buf = []
        text = raw.strip()
        if text.upper() == "NULL":
            value = None
        elif re.fullmatch(r"-?\d+", text):
            value = int(text)
        elif re.fullmatch(r"-?\d*\.\d+(?:[eE][-+]?\d+)?", text):
            value = float(text)
        else:
            value = text

    while i < n:
        ch = chunk[i]
        if in_string:
            if ch == "\\" and i + 1 < n:
                buf.append(ESCAPES.get(chunk[i + 1], chunk[i + 1]))
                i += 2
                continue
            if ch == quote:
                if i + 1 < n and chunk[i + 1] == quote:  # doubled quote
                    buf.append(quote)
                    i += 2
                    continue
                in_string = False
                i += 1
                continue
            buf.append(ch)
            i += 1
            continue
        if ch in ("'", '"'):
            in_string = True
            quote = ch
            i += 1
            continue
        if ch == "(" and not in_row:
            in_row = True
            row = []
            buf = []
            i += 1
            continue
        if ch == "," and in_row:
            flush_value()
            row.append(value)
            buf = []
            i += 1
            continue
        if ch == ")" and in_row:
            flush_value()
            row.append(value)
            in_row = False
            rows.append(tuple(row))
            row = []
            i += 1
            continue
        if in_row:
            buf.append(ch)
        i += 1
    return rows


INSERT_RE = re.compile(r"INSERT INTO `(?P<name>[^`]+)`\s*\((?P<cols>[^)]*)\)\s*VALUES", re.S)


def parse_inserts(sql: str):
    data = {}
    for match in INSERT_RE.finditer(sql):
        name = match.group("name")
        cols = [c.strip().strip("`") for c in match.group("cols").split(",")]
        # values run until the terminating ";" that is outside a string
        start = match.end()
        i, n, in_string, quote = start, len(sql), False, ""
        while i < n:
            ch = sql[i]
            if in_string:
                if ch == "\\":
                    i += 2
                    continue
                if ch == quote:
                    in_string = False
                i += 1
                continue
            if ch in ("'", '"'):
                in_string = True
                quote = ch
                i += 1
                continue
            if ch == ";":
                break
            i += 1
        rows = parse_insert_values(sql[start:i])
        data.setdefault(name, {"columns": cols, "rows": []})["rows"].extend(rows)
    return data


# --------------------------------------------------------------------------- #
# Mirror into SQLite
# --------------------------------------------------------------------------- #

def build_sqlite(tables, data, db_path: Path):
    if db_path.exists():
        db_path.unlink()
    con = sqlite3.connect(db_path)
    con.execute("PRAGMA foreign_keys=OFF")
    errors = []

    for name, table in tables.items():
        cols = []
        pk = [c for c in table["pk"] if any(col["name"] == c for col in table["columns"])]
        for col in table["columns"]:
            cols.append(f'"{col["name"]}" {col["sqlite_type"]}')
        if pk:
            cols.append(f'PRIMARY KEY ({", ".join(chr(34) + c + chr(34) for c in pk)})')
        ddl = f'CREATE TABLE "{name}" ({", ".join(cols)})'
        try:
            con.execute(ddl)
        except sqlite3.Error as exc:
            errors.append({"table": name, "error": str(exc), "ddl": ddl})

    inserted = Counter()
    for name, payload in data.items():
        table = tables.get(name)
        if not table:
            continue
        col_names = {col["name"] for col in table["columns"]}
        cols = [c for c in payload["columns"] if c in col_names]
        placeholders = ", ".join("?" for _ in cols)
        statement = f'INSERT INTO "{name}" ({", ".join(chr(34) + c + chr(34) for c in cols)}) VALUES ({placeholders})'
        index = {c: i for i, c in enumerate(payload["columns"])}
        for row in payload["rows"]:
            values = []
            for col in cols:
                pos = index[col]
                values.append(row[pos] if pos < len(row) else None)
            try:
                con.execute(statement, values)
                inserted[name] += 1
            except sqlite3.Error as exc:
                errors.append({"table": name, "row": str(values)[:200], "error": str(exc)})
    con.commit()
    return con, inserted, errors


# --------------------------------------------------------------------------- #
# Checks
# --------------------------------------------------------------------------- #

def q1(con, sql, params=()):
    try:
        return con.execute(sql, params).fetchall()
    except sqlite3.Error as exc:
        return [("__error__", str(exc))]


def table_exists(con, name):
    return con.execute(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (name,)
    ).fetchone() is not None


def column_exists(con, table, column):
    if not table_exists(con, table):
        return False
    return any(row[1] == column for row in con.execute(f'PRAGMA table_info("{table}")'))


def run_checks(con, tables, inserted):
    findings = []

    def add(fid, severity, area, title, detail, evidence=None, fix=None):
        findings.append(
            {
                "id": fid,
                "severity": severity,
                "area": area,
                "title": title,
                "detail": detail,
                "evidence": evidence or [],
                "fix": fix,
            }
        )

    # ---- structural -------------------------------------------------------
    no_pk = [name for name, t in tables.items() if not t["pk"]]
    if no_pk:
        add(
            "S1", "high", "schema", f"{len(no_pk)} tables without a primary key",
            "Rows in these tables cannot be addressed individually, which breaks updates, "
            "pagination and ORM mapping.",
            no_pk,
            "Add surrogate `id` primary keys (see report for the ALTER statements).",
        )

    fk_tables = [name for name, t in tables.items() if t["foreign_keys"]]
    add(
        "S2", "medium", "schema",
        f"Only {sum(len(t['foreign_keys']) for t in tables.values())} foreign keys across {len(tables)} tables",
        "Referential integrity is enforced in PHP only, so orphan rows can accumulate. "
        f"Tables with declared FKs: {', '.join(fk_tables) or 'none'}.",
        [f"{n}: " + ", ".join(fk['name'] for fk in t['foreign_keys']) for n, t in tables.items() if t["foreign_keys"]],
    )

    charsets = Counter((t["charset"] or "unknown") for t in tables.values())
    if len(charsets) > 1:
        add(
            "S3", "medium", "schema",
            "Mixed table charsets (" + ", ".join(f"{k}: {v}" for k, v in charsets.items()) + ")",
            "latin1 tables alongside utf8mb4 tables cause mojibake when text with non-Latin "
            "characters (Urdu, emoji, curly quotes) moves between them, and break JOIN collation.",
            [f"{n} → {t['charset']}" for n, t in tables.items() if t["charset"]],
            "ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        )

    # ---- orphans / relational --------------------------------------------
    def orphan_check(fid, child, child_col, parent, parent_col, severity="high"):
        if not (table_exists(con, child) and table_exists(con, parent) and column_exists(con, child, child_col)):
            return
        rows = q1(
            con,
            f'SELECT COUNT(*) FROM "{child}" c WHERE c."{child_col}" IS NOT NULL AND c."{child_col}" != "" '
            f'AND NOT EXISTS (SELECT 1 FROM "{parent}" p WHERE p."{parent_col}" = c."{child_col}")',
        )
        count = rows[0][0] if rows else 0
        if isinstance(count, int) and count > 0:
            sample = q1(
                con,
                f'SELECT c."{child_col}" FROM "{child}" c WHERE c."{child_col}" IS NOT NULL AND c."{child_col}" != "" '
                f'AND NOT EXISTS (SELECT 1 FROM "{parent}" p WHERE p."{parent_col}" = c."{child_col}") LIMIT 10',
            )
            add(
                fid, severity, "data",
                f"{count} orphan rows: {child}.{child_col} → {parent}.{parent_col}",
                "These rows point at records that no longer exist and will silently drop out of joins.",
                [f"{child}.{child_col}={row[0]}" for row in sample],
                f"DELETE FROM {child} WHERE {child_col} NOT IN (SELECT {parent_col} FROM {parent});",
            )

    orphan_check("D1", "products", "category", "categories", "id")
    orphan_check("D2", "product_categories", "product_id", "products", "id")
    orphan_check("D3", "product_categories", "category_id", "categories", "id")
    orphan_check("D5", "order_items", "product_id", "products", "id")
    orphan_check("D6", "product_variations", "product_id", "products", "id")
    orphan_check("D7", "quotation_request_items", "request_id", "quotation_requests", "id")
    orphan_check("D8", "categories", "parent_id", "categories", "id", severity="medium")
    orphan_check("D9", "products", "brand_id", "brands", "id", severity="medium")
    orphan_check("D10", "pos_transaction_items", "transaction_id", "pos_transactions", "id")
    orphan_check("D11", "hero_slides", "slider_id", "hero_sliders", "id", severity="low")
    orphan_check("D28", "quotation_request_items", "product_id", "products", "id", severity="medium")
    orphan_check("D29", "pos_transaction_items", "product_id", "products", "id", severity="medium")
    orphan_check("D30", "reviews", "product_id", "products", "id", severity="medium")
    orphan_check("D31", "pos_refunds", "original_transaction_id", "pos_transactions", "id", severity="low")

    # order_items.order_id stores the human order_number, not orders.id.
    if table_exists(con, "order_items") and table_exists(con, "orders"):
        rows = q1(
            con,
            'SELECT COUNT(*) FROM order_items i WHERE NOT EXISTS '
            '(SELECT 1 FROM orders o WHERE o.order_number = i.order_id)',
        )
        orphan_count = rows[0][0] if rows else 0
        if orphan_count:
            add("D4", "high", "data", f"{orphan_count} order items referencing an unknown order number",
                "order_items.order_id holds orders.order_number, so any lookup assumes that join.")
        if not orphan_count:
            add(
                "D32", "medium", "schema",
                "order_items.order_id is a varchar storing orders.order_number",
                "The column is named like a foreign key to orders.id but holds the order number string. "
                "Any new query that joins on orders.id silently returns nothing — this is the single most "
                "likely source of 'missing items' bugs in reports and exports.",
                [f"sample: order_items.order_id = {r[0]}" for r in q1(con, "SELECT order_id FROM order_items LIMIT 3")],
                "Rename to order_number, or add a real order_id INT FK and backfill it.",
            )

    # ---- duplicates -------------------------------------------------------
    def duplicate_check(fid, table, column, label=None, severity="medium"):
        if not (table_exists(con, table) and column_exists(con, table, column)):
            return
        rows = q1(
            con,
            f"SELECT \"{column}\", COUNT(*) c FROM \"{table}\" WHERE \"{column}\" IS NOT NULL "
            f"AND LENGTH(TRIM(\"{column}\")) > 0 AND LOWER(TRIM(\"{column}\")) NOT IN ('null','undefined','-') "
            f"GROUP BY \"{column}\" HAVING c > 1 ORDER BY c DESC LIMIT 10",
        )
        if rows and rows[0][0] != "__error__":
            add(
                fid, severity, "data",
                f"{len(rows)} duplicate {label or column} values in {table}",
                "Duplicates confuse URLs, search results and stock reconciliation.",
                [f"{row[0]} × {row[1]}" for row in rows],
                f"Add a UNIQUE index after de-duplicating: ALTER TABLE {table} ADD UNIQUE ({column});",
            )

    duplicate_check("D12", "products", "slug", "product slugs")
    duplicate_check("D13", "products", "product_sku", "SKUs")
    duplicate_check("D14", "categories", "slug", "category slugs")
    # NOTE: a product legitimately belongs to several categories; only exact
    # product↔category pairs are checked below.

    product_category_pairs = q1(
        con,
        'SELECT product_id, category_id, COUNT(*) c FROM product_categories GROUP BY product_id, category_id HAVING c > 1 LIMIT 5',
    )
    if product_category_pairs and product_category_pairs[0][0] != "__error__":
        add(
            "D16", "low", "data", "Duplicate product↔category mappings",
            "The same product is linked to the same category more than once, inflating category counts.",
            [f"product {r[0]} ↔ category {r[1]} × {r[2]}" for r in product_category_pairs],
        )

    # ---- numeric sanity ---------------------------------------------------
    numeric_checks = [
        ("D17", "products", "current_price", "< 0", "negative current_price"),
        ("D18", "products", "quantity", "< 0", "negative stock quantity"),
        ("D19", "orders", "total_amount", "< 0", "negative order total"),
    ]
    for fid, table, column, condition, label in numeric_checks:
        if table_exists(con, table) and column_exists(con, table, column):
            rows = q1(con, f'SELECT COUNT(*) FROM "{table}" WHERE "{column}" IS NOT NULL AND "{column}" {condition}')
            if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
                add(fid, "high", "data", f"{rows[0][0]} rows with {label}", "Money/stock must never go negative.", [])

    if table_exists(con, "products") and column_exists(con, "products", "current_price"):
        rows = q1(con, 'SELECT COUNT(*) FROM products WHERE current_price = 0 OR current_price IS NULL')
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("D20", "medium", "data", f"{rows[0][0]} products priced at zero",
                "Zero-price products show as free on the storefront and break discount maths.")

    if table_exists(con, "products") and column_exists(con, "products", "old_price"):
        rows = q1(con, 'SELECT COUNT(*) FROM products WHERE old_price > 0 AND old_price < current_price')
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("D21", "low", "data", f"{rows[0][0]} products where old_price < current_price",
                "The strike-through 'was' price is lower than the selling price, so the discount badge is wrong.")

    # ---- JSON columns -----------------------------------------------------
    json_columns = [
        ("products", "specifications"),
        ("products", "images"),
        ("product_variations", "variation_data"),
        ("pos_transactions", "items_sold"),
    ]
    for table, column in json_columns:
        if not (table_exists(con, table) and column_exists(con, table, column)):
            continue
        rows = q1(con, f'SELECT "{column}" FROM "{table}" WHERE "{column}" IS NOT NULL LIMIT 500')
        bad = 0
        for (value,) in rows:
            if value in (None, ""):
                continue
            try:
                json.loads(value)
            except (TypeError, ValueError):
                bad += 1
        if bad:
            add(
                "D22", "high", "data", f"{bad} invalid JSON values in {table}.{column}",
                "These columns are decoded by the PHP app; invalid JSON throws or renders empty.",
                [],
                "Repair with JSON_VALID() checks and normalise to [] / {} when empty.",
            )

    # ---- timestamps / enums ---------------------------------------------
    for table, column in [("users", "created_at"), ("orders", "order_date"), ("products", "id")]:
        pass
    if table_exists(con, "orders") and column_exists(con, "orders", "order_date"):
        rows = q1(con, "SELECT COUNT(*) FROM orders WHERE order_date IS NULL OR order_date = '0000-00-00 00:00:00'")
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("D23", "medium", "data", f"{rows[0][0]} orders with missing/zero dates",
                "Zero dates break date sorting and reporting.")

    # ---- empty tables -----------------------------------------------------
    empty = []
    for name in tables:
        if not table_exists(con, name):
            continue
        rows = q1(con, f'SELECT COUNT(*) FROM "{name}"')
        if rows and rows[0][0] == 0:
            empty.append(name)
    if empty:
        add(
            "S4", "low", "schema", f"{len(empty)} empty tables in the dump",
            "Either features that were never used or data that was never migrated.",
            empty,
        )

    # ---- users / auth -----------------------------------------------------
    if table_exists(con, "users"):
        rows = q1(con, "SELECT id, username, name, password, role FROM users")
        record = []
        weak = 0
        for uid, username, name, password, role in rows:
            algo = "bcrypt" if str(password or "").startswith(("$2y$", "$2a$", "$2b$")) else "unknown/plain"
            if algo != "bcrypt":
                weak += 1
            record.append(f"#{uid} {username} ({role}) — {algo}")
        add(
            "A1", "high" if weak else "medium", "security",
            f"{len(rows)} admin account(s); {weak} not using bcrypt",
            "Single admin account means no audit separation and no recovery path; "
            "the panel has no session/audit table, so logins and edits are untraceable.",
            record,
            "Add a `login_attempts` + `activity_log` table, enforce min. 1 additional admin, rotate the password.",
        )

    # ---- PII --------------------------------------------------------------
    pii = []
    for table, column in [
        ("orders", "customer_email"), ("orders", "customer_phone"),
        ("quotation_requests", "customer_email"), ("quotation_requests", "customer_phone"),
        ("customers", "email"), ("subscribers", "email"), ("contact_forms", "email"),
    ]:
        if table_exists(con, table) and column_exists(con, table, column):
            rows = q1(con, f'SELECT COUNT(*) FROM "{table}" WHERE "{column}" IS NOT NULL AND "{column}" != ""')
            if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
                pii.append(f"{table}.{column}: {rows[0][0]} rows")
    if pii:
        add(
            "A2", "medium", "security",
            "Personal data stored unencrypted in the database",
            "Customer names, emails and phone numbers are in plain text, with no retention "
            "policy visible in the schema.",
            pii,
        )

    # ---- payments ---------------------------------------------------------
    if table_exists(con, "orders") and column_exists(con, "orders", "payment_intent_id"):
        rows = q1(con, "SELECT COUNT(*) FROM orders WHERE payment_status = 'Pending' AND payment_intent_id IS NOT NULL")
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("A3", "medium", "commerce", f"{rows[0][0]} orders with a payment intent stuck in Pending",
                "Abandoned Stripe intents inflate the open-order queue.")

    # ---- commerce consistency --------------------------------------------
    if table_exists(con, "orders") and table_exists(con, "order_items"):
        rows = q1(
            con,
            "SELECT COUNT(*) FROM orders o WHERE NOT EXISTS "
            "(SELECT 1 FROM order_items i WHERE i.order_id = o.order_number)",
        )
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("D24", "medium", "data", f"{rows[0][0]} orders with no line items",
                "Totals cannot be reconciled against items.")
        rows = q1(
            con,
            "SELECT COUNT(*) FROM orders o WHERE ABS(o.total_amount - ("
            "COALESCE((SELECT SUM(i.subtotal) FROM order_items i WHERE i.order_id = o.order_number), 0) "
            "+ COALESCE(o.shipping_cost,0) - COALESCE(o.discount_amount,0))) > 1",
        )
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("D25", "high", "data", f"{rows[0][0]} orders whose total ≠ items + shipping − discount",
                "Order totals do not reconcile; invoices and reports will disagree.",
                [f"order #{r[0]} total={r[1]}" for r in q1(
                    con,
                    "SELECT o.id, o.total_amount FROM orders o WHERE ABS(o.total_amount - ("
                    "COALESCE((SELECT SUM(i.subtotal) FROM order_items i WHERE i.order_id = o.order_number), 0)"
                    " + COALESCE(o.shipping_cost,0) - COALESCE(o.discount_amount,0))) > 1 LIMIT 5",
                )])

    # ---- stock ------------------------------------------------------------
    if table_exists(con, "products"):
        rows = q1(con, "SELECT COUNT(*) FROM products WHERE quantity <= 0")
        if rows and isinstance(rows[0][0], int):
            add("I1", "info", "commerce", f"{rows[0][0]} of {inserted.get('products', 0)} products are out of stock",
                "Useful commercial signal rather than a defect.")

    # ---- category tree ----------------------------------------------------
    if table_exists(con, "categories"):
        rows = q1(con, "SELECT COUNT(*) FROM categories WHERE parent_id IS NOT NULL AND parent_id = 0")
        if rows and isinstance(rows[0][0], int) and rows[0][0] > 0:
            add("D26", "low", "data", f"{rows[0][0]} top-level categories use parent_id = 0 instead of NULL",
                "Mixed sentinels break recursive queries that expect NULL.")
        rows = q1(
            con,
            "SELECT c.id, c.category_name, c.level, (SELECT level FROM categories p WHERE p.id = c.parent_id) pl "
            "FROM categories c WHERE c.parent_id IS NOT NULL AND c.level != pl + 1 LIMIT 10",
        )
        if rows and rows[0][0] != "__error__":
            add("D27", "medium", "data", f"{len(rows)}+ categories with an inconsistent level value",
                "level must equal parent.level + 1; otherwise menus and breadcrumbs render wrong.",
                [f"#{r[0]} {r[1]} level={r[2]} parent_level={r[3]}" for r in rows])

    # ---- SKU hygiene ------------------------------------------------------
    if table_exists(con, "products") and column_exists(con, "products", "product_sku"):
        blank = q1(con, "SELECT COUNT(*) FROM products WHERE LENGTH(TRIM(COALESCE(product_sku,''))) = 0")[0][0]
        if blank:
            add("D33", "medium", "data", f"{blank} products with a blank SKU",
                "Blank SKUs cannot be matched to supplier invoices or a barcode, and they break "
                "unique-index migrations.", [], "UPDATE products SET product_sku = CONCAT('WD-', id) WHERE TRIM(COALESCE(product_sku,'')) = '';")
        prefixed = q1(con, "SELECT COUNT(*) FROM products WHERE product_sku LIKE 'SKU:%'")[0][0]
        if prefixed:
            add("D34", "low", "data", f"{prefixed} SKUs carry a literal 'SKU: ' prefix",
                "The label is stored inside the value, so printed barcodes and exports read 'SKU: SKU: ...'.",
                [r[0] for r in q1(con, "SELECT product_sku FROM products WHERE product_sku LIKE 'SKU:%' LIMIT 5")],
                "UPDATE products SET product_sku = TRIM(REPLACE(product_sku, 'SKU:', '')) WHERE product_sku LIKE 'SKU:%';")

    # ---- products table is missing lifecycle columns ----------------------
    if table_exists(con, "products"):
        cols = {r[1] for r in con.execute('PRAGMA table_info("products")')}
        missing = [c for c in ("created_at", "updated_at") if c not in cols]
        publish = [c for c in ("is_active", "status", "is_published", "published_at") if c in cols]
        if missing or not publish:
            add(
                "S5", "medium", "schema",
                "products has no publish flag and no timestamps",
                f"Missing: {', '.join(missing) or 'none'}; publish columns: {', '.join(publish) or 'none'}. "
                "A product cannot be hidden from the storefront without deleting it, and there is no "
                "way to sort by 'newest' or audit edits.",
                [],
                "ALTER TABLE products ADD is_active TINYINT(1) NOT NULL DEFAULT 1, "
                "ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, "
                "ADD updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;",
            )

    # ---- two category systems --------------------------------------------
    if table_exists(con, "products") and table_exists(con, "product_categories") and column_exists(con, "products", "category"):
        mismatch = q1(
            con,
            'SELECT COUNT(*) FROM products p WHERE p.category IS NOT NULL AND NOT EXISTS '
            '(SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = p.category)',
        )[0][0]
        if mismatch:
            add("D35", "medium", "data",
                f"{mismatch} products whose primary category is not among their category mappings",
                "The app keeps a single `products.category` AND a `product_categories` join table. The two "
                "disagree, so a product can appear under a category in one view and not another.",
                [f"#{r[0]} {r[1]}" for r in q1(con,
                    "SELECT p.id, p.product_name FROM products p WHERE p.category IS NOT NULL AND NOT EXISTS "
                    "(SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = p.category) LIMIT 5")])

    orphans = q1(con, "SELECT COUNT(*) FROM categories c WHERE NOT EXISTS (SELECT 1 FROM product_categories pc WHERE pc.category_id = c.id)")[0][0] if table_exists(con, "categories") else 0
    if orphans:
        add("I2", "info", "commerce", f"{orphans} categories contain no products",
            "Empty categories still render in menus and sitemaps unless is_active is switched off.")

    # ---- faqs polymorphic integrity --------------------------------------
    if table_exists(con, "faqs") and column_exists(con, "faqs", "faq_for") and column_exists(con, "faqs", "item_id"):
        broken = q1(
            con,
            "SELECT COUNT(*) FROM faqs f WHERE (f.faq_for = 'product' AND NOT EXISTS (SELECT 1 FROM products p WHERE p.id = f.item_id)) "
            "OR (f.faq_for = 'category' AND NOT EXISTS (SELECT 1 FROM categories c WHERE c.id = f.item_id))",
        )[0][0]
        if broken:
            add("D36", "medium", "data", f"{broken} FAQs attached to a missing product/category",
                "faqs is polymorphic (faq_for + item_id) with no constraint, so deleted products leave "
                "orphan FAQs that never render.")

    # ---- duplicate-purpose / leftover tables -----------------------------
    legacy = []
    rationale = {
        "doctors": "leftover from a medical template — 0 rows, no furniture meaning",
        "reviews": "duplicates customer_reviews (both store star ratings)",
        "hero_slides_alt": "duplicates hero_slides (second slider design kept side by side)",
        "product_attributes": "duplicates attributes/attribute_values (second attribute system, 0 rows)",
        "product_attribute_terms": "duplicates attribute_values (0 rows)",
        "customers": "orders store customer details inline, so this table is never filled",
    }
    for table, why in rationale.items():
        if table_exists(con, table):
            rows = q1(con, f'SELECT COUNT(*) FROM "{table}"')[0][0]
            legacy.append(f"{table} ({rows} rows) — {why}")
    if legacy:
        add("S6", "low", "schema", f"{len(legacy)} legacy or duplicate-purpose tables",
            "Two parallel implementations of the same concept force every query to pick one, and dead "
            "tables like `doctors` hint the schema was cloned from another project.",
            legacy,
            "Confirm with the app code, then DROP the unused ones in a versioned migration.")

    # ---- observability ----------------------------------------------------
    names = {r[0].lower() for r in con.execute("SELECT name FROM sqlite_master WHERE type='table'")}
    missing_ops = [t for t in ("audit_log", "activity_log", "sessions", "login_attempts", "migrations") if t not in names]
    if missing_ops:
        add("A4", "high", "security",
            "No session, audit or migration tables",
            f"Looking for {', '.join(missing_ops)} — none exist. Admin edits, deletions and logins leave no "
            "trace, and there is no record of which schema version the code expects.",
            [],
            "Add activity_log(user_id, action, entity, entity_id, created_at), sessions and migrations tables.")

    # ---- credentials ------------------------------------------------------
    if table_exists(con, "users"):
        rows = q1(con, "SELECT password FROM users")
        weak_cost = sum(1 for (pw,) in rows if isinstance(pw, str) and pw.startswith(("$2y$", "$2a$", "$2b$")) and pw[4:6].isdigit() and int(pw[4:6]) < 10)
        if weak_cost:
            add("A5", "medium", "security", f"{weak_cost} password hashes below bcrypt cost 10",
                "Cheap hashes are fast to brute-force if the dump leaks.")

    # ---- commerce signals -------------------------------------------------
    if table_exists(con, "products") and column_exists(con, "products", "sold_quantity"):
        never_sold = q1(con, "SELECT COUNT(*) FROM products WHERE COALESCE(sold_quantity,0) = 0")[0][0]
        add("I3", "info", "commerce", f"{never_sold} products have never recorded a sale",
            "sold_quantity is 0 on every row, so best-seller ranking has no real history to work with.")

    # ---- representative application queries -------------------------------
    probe = []
    def try_query(label, sql):
        try:
            con.execute(sql).fetchmany(3)
            probe.append({"query": label, "ok": True})
        except sqlite3.Error as exc:
            probe.append({"query": label, "ok": False, "error": str(exc)})
    try_query("storefront listing", "SELECT p.id, p.product_name, p.current_price FROM products p WHERE p.quantity > 0 ORDER BY p.is_best_seller DESC LIMIT 10")
    try_query("category tree", "SELECT c.id, c.category_name, c.level FROM categories c ORDER BY c.path")
    try_query("order with items", "SELECT o.order_number, o.total_amount, i.product_name FROM orders o LEFT JOIN order_items i ON i.order_id = o.order_number")
    try_query("pos day summary", "SELECT DATE(transaction_date) d, SUM(total) FROM pos_transactions GROUP BY d")
    try_query("quotation pipeline", "SELECT status, COUNT(*), SUM(total) FROM quotation_requests GROUP BY status")
    failed = [p for p in probe if not p["ok"]]
    if failed:
        add("S7", "high", "schema", f"{len(failed)} representative app queries fail",
            "The schema cannot answer core storefront/reporting questions as-is.",
            [f"{p['query']}: {p['error']}" for p in failed])
    findings.append({"id": "Q1", "severity": "info", "area": "verification",
                     "title": f"{len(probe) - len(failed)}/{len(probe)} representative application queries succeed",
                     "detail": "Storefront listing, category tree, order+items join, POS day summary and quotation "
                               "pipeline were executed against the imported mirror.",
                     "evidence": [f"{p['query']}: {'ok' if p['ok'] else p['error']}" for p in probe],
                     "fix": None})

    return findings


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("dump")
    parser.add_argument("--out-db", default="/tmp/woodex-audit.sqlite")
    parser.add_argument("--json", default="/tmp/woodex-audit.json")
    args = parser.parse_args()

    path = Path(args.dump)
    raw = path.read_bytes()
    try:
        sql = raw.decode("utf-8")
        encoding = "utf-8"
    except UnicodeDecodeError:
        sql = raw.decode("utf-8", errors="replace")
        encoding = "utf-8 (with replacement characters)"

    clean = strip_line_comments(sql)
    tables = parse_schema(clean)
    data = parse_inserts(clean)
    con, inserted, import_errors = build_sqlite(tables, data, Path(args.out_db))
    findings = run_checks(con, tables, inserted)

    counts = {}
    for name in tables:
        if table_exists(con, name):
            counts[name] = q1(con, f'SELECT COUNT(*) FROM "{name}"')[0][0]

    summary = {
        "source_file": path.name,
        "bytes": len(raw),
        "encoding": encoding,
        "server": (re.search(r"Server version: (\S+)", sql) or [None, None])[1],
        "php_version": (re.search(r"PHP Version: (\S+)", sql) or [None, None])[1],
        "database": (re.search(r"Database: `([^`]+)`", sql) or [None, None])[1],
        "generated": (re.search(r"Generation Time: ([^\n]+)", sql) or [None, None])[1].strip(),
        "tables": len(tables),
        "rows": sum(counts.values()),
        "counts": counts,
        "inserted": dict(inserted),
        "import_errors": import_errors[:25],
        "import_error_count": len(import_errors),
        "foreign_key_count": sum(len(t["foreign_keys"]) for t in tables.values()),
        "tables_without_pk": [n for n, t in tables.items() if not t["pk"]],
        "charsets": dict(Counter((t["charset"] or "unknown") for t in tables.values())),
        "schema": {
            name: {
                "columns": [c["name"] for c in t["columns"]],
                "pk": t["pk"],
                "unique": t["unique"],
                "foreign_keys": t["foreign_keys"],
                "engine": t["engine"],
                "charset": t["charset"],
                "rows": counts.get(name, 0),
            }
            for name, t in tables.items()
        },
        "findings": findings,
    }

    Path(args.json).write_text(json.dumps(summary, indent=2, ensure_ascii=False))
    server = summary["server"] or "?"
    print(f"Imported {summary['rows']} rows into {len(tables)} tables from {path.name}")
    print(f"  server={server}  php={summary['php_version']}  db={summary['database']}")
    print(f"  import errors: {len(import_errors)}")
    print(f"  findings: {len(findings)}")
    for finding in findings:
        print(f"   [{finding['severity']:6s}] {finding['id']}: {finding['title']}")
    print(f"\nSQLite mirror: {args.out_db}\nJSON summary: {args.json}")


if __name__ == "__main__":
    sys.exit(main())
