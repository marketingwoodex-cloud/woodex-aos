# Database audit — `u128159657_woodexbaks`

**Source:** `u128159657_woodexbaks (1).sql` — phpMyAdmin 5.2.2 dump
**Server:** MariaDB 11.8.9 · PHP 7.2.34 · cPanel database `u128159657_woodexbaks`
**Dump generated:** 14 Nov 2026, 05:14 UTC · 588 KB · UTF-8 clean
**Audited:** 14 Nov 2026 by the Woodex AOS workspace

---

## Verdict

The database **imports cleanly** (41 tables, 637 rows, **0 SQL errors**) and the **catalogue
layer is in good shape**: a consistent 3-level category tree, valid JSON in every structured
column, unique slugs, and 171 products that all resolve to real categories and images.

The **commerce layer is disconnected from the current catalogue**, and there is a single,
identifiable cause: product IDs were reassigned during a product re-import (current products
start at id 34), while every historical reference — order items, variations, quotation lines,
POS items, reviews, product FAQs — still points at the old id range (1–32). Six of six order
line items and all five product variations reference deleted products.

There is also **no operational safety net**: no audit log, no sessions table, no migrations
table, and only one foreign key in the entire schema.

| Severity | Count | Meaning |
| --- | --- | --- |
| High | 3 | Breaks a feature or loses data integrity today |
| Medium | 13 | Will cause wrong reports / bugs under normal use |
| Low | 3 | Cleanup and maintainability |
| Info | 4 | Commercial signals and verification results |

---

## 1. How this was tested

A live MySQL/MariaDB server is not reachable from this sandbox (network egress is limited to
npm/PyPI/GitHub, and no `mysqld`/`apt` is available), so the audit was done by:

1. **Parsing the dump exactly as written** — `CREATE TABLE`, `ALTER TABLE` (PK/unique/index/FK)
   and every `INSERT`, including MySQL string escaping — and importing it into a **SQLite mirror**
   with the same table and column names.
2. **Import result: 637 rows into 41 tables, 0 failed statements.** Every table got its primary
   key from the dump's `ALTER TABLE` block.
3. Running **structural checks** (keys, engines, charsets, FKs) and **data-integrity checks**
   (orphans, duplicates, JSON validity, totals reconciliation, tree consistency).
4. Executing **five representative application queries** (storefront listing, category tree,
   order + items join, POS day summary, quotation pipeline) — all five succeed.

Tool + machine-readable output: `tools/db-audit/audit.py` → `/tmp/woodex-audit.json`.

---

## 2. What the database actually is

A single-tenant furniture business system with four applications sharing one schema:

| Application | Tables | Rows | Notes |
| --- | --- | --- | --- |
| **Storefront / CMS** | categories, products, product_categories, product_variations, brands, attributes, attribute_values, blogs, faqs, projects, hero_sliders, hero_slides, hero_slides_alt, footer_settings, footer_links, topbar_settings, sale_page_settings, url_redirects, email_settings | 630+ | 53 categories (3 roots → 16 → 34), 171 products, 87 FAQs, 18 footer links |
| **Web commerce** | orders, order_items, customers, coupons, reviews, customer_reviews, subscribers, contact_forms | 20 | 5 orders (Rs 12,610,000), 6 line items |
| **Quotations** | quotation_requests, quotation_request_items | 36 | 11 requests, all `new`, Rs 1,793,301 |
| **POS (showroom)** | pos_transactions, pos_transaction_items, pos_refunds, pos_refund_items, pos_replacements, pos_replacement_items, pos_bank_accounts, pos_payment_apps | 10 | 3 transactions, Rs 826,500 |
| **Admin** | users | 1 | `bilal` / Bilal Khan / admin, bcrypt cost 10 |

Catalogue shape: 11 best-sellers, 1 sale item, 1 brand ("Woodex"), 171/171 products have image
arrays, average product body copy 1,303 characters of HTML.

---

## 3. What passed (verified good)

- **No duplicate slugs** — 171/171 product slugs and 53/53 category slugs are unique and non-blank.
- **Category tree is consistent** — 3 roots, 16 level-2, 34 level-3 nodes; **0** level mismatches,
  **0** missing parents, **0** `parent_id = 0` sentinels, all 53 active.
- **No orphan catalogue links** — `products.category`, and both sides of `product_categories`
  (204 mappings) all resolve; 0 duplicate product↔category pairs.
- **All structured columns are valid JSON** — `products.images`, `products.specifications`,
  `product_variations.variation_data`, `pos_transactions.items_sold` (0 invalid values).
- **Order totals reconcile exactly** — for all 5 orders, `total_amount = Σ items + shipping − discount`.
- **Order items match by order number** — 0 orphans when joined on `orders.order_number`.
- **Quotation totals reconcile** — 11/11; no orphan quote lines against `quotation_requests`.
- **POS receipts reconcile** — items match transactions; refunds point at real transactions.
- **Passwords are hashed properly** — bcrypt `$2y$` cost 10, no MD5/SHA1 or plaintext.
- **5/5 representative app queries succeed** against the mirror.

---

## 4. Findings

### 🔴 High

#### H1 · Every historical product reference points at deleted products (D5, D6, D28, D29, D30, D36)
Current product IDs run **34–207**. Every reference created before the re-import uses **1–32**:

| Table | Orphan rows | Missing product IDs |
| --- | --- | --- |
| `order_items` | 6 of 6 | 25, 1, 29, 2, 17 |
| `product_variations` | 5 of 5 | 31, 1 |
| `quotation_request_items` | 15 of 25 | 1, 31, 4, 29, 17, 2, 13, 26, 18, 175, 86 |
| `pos_transaction_items` | 5 of 5 | 24, 1, 17, 27 |
| `reviews` | 3 of 3 | 32, 15, 25 |
| `faqs` (product FAQs) | 13 of 13 | 20 |

Because `order_items`, `quotation_request_items` and `pos_transaction_items` all store
`product_name` and `price` at time of sale, **receipts and invoices still render correctly**.
What breaks is anything that joins to `products`: product links on past orders, "customers also
bought", stock reconciliation, review stars on product pages, and variable-product pricing — the
5 variations are all dead, and every product is now `product_type = 'simple'`.

**Cause:** the catalogue was re-imported (IDs shifted by +33) without remapping references.
**Fix (P0):** map old→new by SKU or name, then backfill; details in §7.

#### H2 · No audit, session or migration tables (A4)
There is no `activity_log`, `sessions`, `login_attempts` or `migrations` table. Admin edits,
deletions, price changes and logins leave **no trace**, and nothing records which schema version
the PHP code expects. For a system holding Rs 15M of order/quotation value, that is the biggest
operational risk after H1.

#### H3 · Single administrator, no recovery path (A1)
Exactly one account (`bilal`, role `admin`) and no password-reset table. If that credential is
lost or the person is unavailable, nobody can administer the panel; there is also no separation
between "who changed the price" and "who approved it".

### 🟠 Medium

| ID | Finding | Impact |
| --- | --- | --- |
| M1 (D32) | `order_items.order_id` is a `varchar` holding `orders.order_number`, not `orders.id` | Any new query/ORM that joins on `orders.id` silently returns **zero rows**. This is the trap that made the first pass of this audit report false "orphan" orders before it was corrected. |
| M2 (S2) | Only **1 foreign key** in 41 tables (`hero_slides → hero_sliders`) | Orphans accumulate silently — exactly how H1 went unnoticed. |
| M3 (S3) | Mixed charsets: **25 latin1 + 16 utf8mb4** | Mojibake and collation errors as soon as Urdu, emoji or curly quotes cross tables; `JOIN` between mixed columns can fail with "illegal mix of collations". |
| M4 (D13/D33/D34) | SKU hygiene: **10 duplicate SKU values**, **26 blank SKUs**, **82 SKUs stored as `SKU: XX-10001`** | Duplicate/blank SKUs break purchasing, barcodes and any future unique index; the literal `SKU:` prefix ends up printed in exports. |
| M5 (D20) | **34 products priced Rs 0.00** with 0 stock | They render as "Free" on the storefront and corrupt average-price / discount maths. |
| M6 (S5) | `products` has **no publish flag and no timestamps** | A product cannot be hidden without deleting it; no "newest first" sort; no edit auditing. |
| M7 (A2) | PII in plain text: customer names, emails, phones, addresses, plus `contact_forms.ip` and `user_agent` | No retention policy or encryption; a dump leak exposes customers directly. |

### 🟡 Low

- **L1 (S4/S6)** — 10 empty tables, including `doctors` (0 rows, leftover from a **medical
  template**) and three duplicated concepts: `reviews` vs `customer_reviews`,
  `hero_slides` vs `hero_slides_alt`, and two attribute systems (`attributes`/`attribute_values`
  vs `product_attributes`/`product_attribute_terms`). `customers` is never filled because orders
  store customer details inline.
- **L2 (I2)** — 28 of 53 categories contain no products.
- **L3** — `faqs` is polymorphic (`faq_for` + `item_id`) with no referential check.

### ℹ️ Info / commercial signals

- **135 of 171 products (79%) are out of stock**, and `sold_quantity` is **0 on every row**, so
  "best seller" ranking is currently driven by a manual flag (11 flagged), not by real sales.
- Order pipeline: **4 Pending + 1 Processing**, **all payments Pending** — no order has ever
  reached Delivered/Completed, which suggests checkout completes without payment confirmation.
- POS test data is present: two identical Rs 350,000 baskets 4 seconds apart, customer name
  `zxgzxdvzx`, and Rs 2,500,000 tendered for a Rs 350,000 sale.
- Quotations: 11 requests, all status `new`, Rs 1,793,301 — nothing has been progressed.
- SEO redirects are working (`url_redirects` row has 22 hits) — the 301 module is in use.

---

## 5. Security notes

| Area | Status |
| --- | --- |
| Password storage | ✅ bcrypt cost 10 |
| Session storage | ⚠️ no session table — likely PHP native sessions on disk, so logins cannot be audited or revoked per-device |
| Admin actions | ❌ no trail |
| PII | ⚠️ plaintext emails/phones/addresses/IP/UA |
| Secrets | ℹ️ `email_settings.smtp_password` exists (currently empty) — ensure it never lands in a dump like this one |
| Payment data | ✅ only Stripe intent IDs are stored, no card data |
| Least privilege | ⚠️ roles are `admin/editor/viewer`, but with one user the model is untested |

Recommendations: rotate the `bilal` password, add a second admin, stop dumping
`email_settings`/`contact_forms` to shared locations, and add `activity_log` + `sessions`.

---

## 6. Fix order

**P0 — before the next deploy**
1. Backfill product references (§7) and verify each table resolves.
2. Add the audit log and turn on logging for price/stock/order changes.
3. Create a second admin account and a password-reset flow.

**P1 — next sprint**
4. Rename `order_items.order_id` → `order_number` (or add a real `order_id` FK), then add FKs
   where data already allows: `order_items.product_id`, `quotation_request_items.request_id`,
   `pos_transaction_items.transaction_id`, `products.category`, `product_categories.*`.
5. Convert all `latin1` tables to `utf8mb4` / `utf8mb4_unicode_ci` in one migration.
6. Normalise SKUs (strip `SKU:`, fill blanks, add a unique index after de-duplication).
7. Add `products.is_active`, `created_at`, `updated_at`.

**P2 — cleanup**
8. Drop or archive `doctors` and the duplicate hero/attribute/review tables once the PHP code is
   confirmed not to reference them.
9. Decide one category model (`products.category` **or** `product_categories`) and enforce it.
10. Reconcile the 34 zero-price products and 135 zero-stock products.

---

## 7. Repair SQL (review on a copy first)

```sql
-- 0) Work on a copy. Take a fresh dump before running anything.
-- SELECT ... INTO OUTFILE is disabled on most cPanel hosts; use phpMyAdmin export instead.

-- 1) Prove the old→new product mapping before touching data.
--    Old ids are 1–32; current products are 34–207. Match on the name/SKU captured in history.
SELECT DISTINCT i.product_id, i.product_name, p.id AS new_id, p.product_sku
FROM order_items i
LEFT JOIN products p ON p.product_name = i.product_name
WHERE i.product_id NOT IN (SELECT id FROM products);

-- 2) Backfill order items (repeat the pattern for quotation_request_items,
--    pos_transaction_items, reviews, product_variations and faqs).
UPDATE order_items i
JOIN products p ON p.product_name = i.product_name
SET i.product_id = p.id
WHERE i.product_id NOT IN (SELECT id FROM products);

-- 3) Anything still unresolved: keep the row for reporting, but mark it.
ALTER TABLE order_items ADD COLUMN product_missing TINYINT(1) NOT NULL DEFAULT 0;
UPDATE order_items SET product_missing = 1 WHERE product_id NOT IN (SELECT id FROM products);

-- 4) Rename the misleading key column.
ALTER TABLE order_items CHANGE order_id order_number VARCHAR(255) NOT NULL;
-- then update the PHP queries that used order_id.

-- 5) SKU clean-up + uniqueness.
UPDATE products SET product_sku = TRIM(REPLACE(product_sku, 'SKU:', '')) WHERE product_sku LIKE 'SKU:%';
UPDATE products SET product_sku = CONCAT('WD-', id) WHERE TRIM(COALESCE(product_sku, '')) = '';
-- resolve the 10 duplicate values first, then:
-- ALTER TABLE products ADD UNIQUE KEY uq_products_sku (product_sku);

-- 6) Lifecycle columns.
ALTER TABLE products
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 7) Audit log.
CREATE TABLE activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id VARCHAR(64) NULL,
  summary VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) Charset normalisation (run table by table, off-peak).
ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- …repeat for the other 24 latin1 tables.
```

---

## 8. Limitations

- **No live connection to your cPanel database.** This sandbox can only reach npm/PyPI/GitHub,
  and no MySQL server can be installed, so the audit ran against an exact mirror of the dump.
  It validates structure and data, not the PHP code's use of it.
- **No application source.** Findings about *intent* (e.g. which attribute system is live) are
  inferred from schema, row counts and naming; the PHP code decides the rest.
- **Snapshot only.** The dump is from 14 Nov 2026 — anything written since then is invisible.
- Image paths (`assets/images/products/...`) are stored as relative paths; whether the files
  exist can only be checked on the server.

---

## 9. Reproduce

```bash
python3 tools/db-audit/audit.py db-source/woodexbaks.sql \
  --out-db /tmp/woodex-audit.sqlite --json /tmp/woodex-audit.json
```

Prints the import summary and all findings; the JSON contains the full schema map, per-table
counts and evidence rows, and the SQLite mirror is queryable with any SQLite client.

---

## Appendix — table inventory

| Table | Rows | Engine | Charset | Notes |
| --- | --- | --- | --- | --- |
| attributes | 4 | InnoDB | latin1 | attribute definitions |
| attribute_values | 8 | InnoDB | latin1 | |
| blogs | 3 | InnoDB | latin1 | 1 is "This is test blog" |
| brands | 1 | InnoDB | latin1 | "Woodex" |
| categories | 53 | InnoDB | latin1 | 3-level tree, all active |
| contact_forms | 1 | InnoDB | latin1 | stores IP + user agent |
| coupons | 0 | InnoDB | latin1 | feature unused |
| customer_reviews | 5 | InnoDB | latin1 | duplicates `reviews` |
| customers | 0 | InnoDB | latin1 | orders store customer inline |
| doctors | 0 | InnoDB | latin1 | leftover medical template |
| email_settings | 1 | InnoDB | latin1 | SMTP config |
| faqs | 87 | InnoDB | latin1 | polymorphic; 13 orphans |
| footer_links | 18 | InnoDB | latin1 | |
| footer_settings | 1 | InnoDB | latin1 | |
| hero_sliders | 1 | InnoDB | latin1 | |
| hero_slides | 3 | InnoDB | latin1 | only table with an FK |
| hero_slides_alt | 4 | InnoDB | latin1 | duplicate design |
| orders | 5 | InnoDB | latin1 | all payment Pending |
| order_items | 6 | InnoDB | latin1 | `order_id` = order number |
| pos_bank_accounts | 0 | InnoDB | latin1 | |
| pos_payment_apps | 0 | InnoDB | latin1 | |
| pos_refunds | 1 | InnoDB | latin1 | |
| pos_refund_items | 1 | InnoDB | latin1 | |
| pos_replacements | 0 | InnoDB | latin1 | |
| pos_replacement_items | 0 | InnoDB | latin1 | |
| pos_transactions | 3 | InnoDB | latin1 | test data present |
| pos_transaction_items | 5 | InnoDB | latin1 | orphans reference old product ids |
| products | 171 | InnoDB | latin1 | ids 34–207; no publish flag |
| product_attributes | 0 | InnoDB | latin1 | unused duplicate system |
| product_attribute_terms | 0 | InnoDB | latin1 | unused |
| product_categories | 204 | InnoDB | utf8mb4 | mapping table |
| product_variations | 5 | InnoDB | latin1 | all reference deleted products |
| projects | 3 | InnoDB | latin1 | 1 placeholder title |
| quotation_requests | 11 | InnoDB | utf8mb4 | all `new` |
| quotation_request_items | 25 | InnoDB | utf8mb4 | 15 reference deleted products |
| reviews | 3 | InnoDB | latin1 | duplicates `customer_reviews` |
| sale_page_settings | 1 | InnoDB | latin1 | |
| subscribers | 0 | InnoDB | latin1 | |
| topbar_settings | 1 | InnoDB | latin1 | |
| url_redirects | 1 | InnoDB | latin1 | 22 hits — in use |
| users | 1 | InnoDB | latin1 | single admin |

*41 tables · 637 rows · 1 foreign key · 25 latin1 + 16 utf8mb4.*
