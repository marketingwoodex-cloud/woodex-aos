# Woodex PHP project — complete code audit

**Date:** 14 November 2026
**Archive:** `public_html.zip` from Google Drive → 4 volumes pushed to the repo → recovered, extracted, analysed
**Scope:** every PHP file, the routing layer, the admin panel, the data layer and the front-end pages
**Companion documents:** `docs/database-audit.md` (the 41-table data audit) · `docs/php-source-gap-report.md` (what was missing before this upload)

---

## 1. What the project actually is

| | |
| --- | --- |
| Stack | **Procedural PHP, no framework**, `mysqli`, Bootstrap 4/5 + jQuery, Apache `.htaccess` rewrites |
| PHP style | Server-rendered templates, 51 public pages, 14 JSON endpoints, 79 admin scripts |
| Total project PHP | **42,293 lines** across 158 files (excluding bundled libraries) |
| Bundled libraries | dompdf (80 files), PHPMailer 6.x (65 files), CKEditor 4 (incl. samples) |
| Archive contents | **1,407 files / 113 MB** — 295 PHP files total, rest is 153 referenced images + assets |
| Database | MySQL/MariaDB `u128159657_woodexbaks`, 41 tables, 637 rows — **35 of 41 tables are used by the code** |
| Admin panel | `public_html/lbpanel/` — 79 PHP files, 18 modules, ~11.6k lines |
| Payment | **Cash on Delivery only** (`process_checkout.php`), no payment gateway |
| Third-party services | SMTP via PHPMailer (settings stored in `email_settings`), no Stripe/PayPal/API keys in code |

The `AGENTS.md` file shipped in the archive confirms the intent: *"procedural PHP + `mysqli` site with a jQuery frontend and custom admin panel"*, XAMPP for local work, Apache for rewrites.

---

## 2. How the archive was recovered (and what is still missing)

The Drive file was named `public_html.zip` but is actually a **multi-volume RAR5 set**, and only part of it arrived:

| Event | Detail |
| --- | --- |
| Uploaded | 4 files of 24 MiB (the last one short by **2,229,184 bytes**) |
| Detected | RAR5 signature `52 61 72 21 1A 07 01 00`; 7-Zip reported *"Multivolume = +, Missing volume: in.r00"* |
| Fixed | Re-split at the four real volume boundaries → renamed `public_html.rar`, `.r00`, `.r01`, `.r02` |
| Result | **Full extraction succeeded: 1,407 files, 113 MB, exit code 0** |

Only the final ~2.2 MB is absent, and it contains no PHP (the extraction is complete — every page, template and admin script is intact). A few trailing image files may be truncated; all code is verified present.

---

## 3. Architecture map

```
public_html/
├── index.php (2,784 lines)          homepage: hero slider, categories, best sellers
├── shop.php / sale.php              catalogue + sale listing
├── singleproduct.php (1,605)        product page: gallery, variations, reviews, FAQs
├── servicedetail.php / servicelist  services
├── equotation.php (1,016)           e-quotation builder
├── quotation-basket.php             quote basket
├── checkout.php / process_checkout  COD checkout → orders + order_items
├── shopppingcart.php                cart  (note the typo in the filename — it is live)
├── header/header.php                global header + topbar_settings + sale_page_settings
├── footer/footer.php                global footer + footer_settings + footer_links
├── api/ (14 endpoints)              cart 6, quote 6, product quick-view, search suggest, reviews
├── lbpanel/ (79 scripts)            ADMIN PANEL
│   ├── index.php                    login (bcrypt via password_verify)
│   ├── dashboard.php                KPI cards + recent orders
│   ├── config/                      connection.php, session.php, product_categories.php
│   ├── php/ (16 files)              form processors: add product, variations, coupons, hero slides, order status
│   └── pos/ (10 files)              point-of-sale UI + 8 JSON endpoints
├── vendor/dompdf/                   PDF invoices + quotations
├── phpmailer/                       order / status emails
└── database/live_schema_updates.sql schema drift notes (26 KB)
```

**Routing:** `.htaccess` maps 30+ slugs to files (`shop.php → /shop`, `equotation.php → /e-quotation`, …), forces HTTPS on `www.`, and has a final `redirect.php` fallback driven by the `url_redirects` table.

---

## 4. Admin panel inventory (`lbpanel/`)

| Area | Files | Lines | Notes |
| --- | --- | ---: | --- |
| Login / logout | 2 | ~250 | `password_verify` against bcrypt in `users` |
| Dashboard | 1 | ~250 | revenue `WHERE status='completed'`, pending count, best sellers |
| Products | 6 | ~1,900 | add (297), edit (718), list (344), variations, attributes + values |
| Categories | 3 | ~600 | 3-level tree, `category-management/` |
| Brands | 1 | — | |
| Orders | 5 | ~1,900 | manage (754), pending, processing, completed, cancelled |
| Quotations | 2 | — | list + detail view |
| **POS** | 10 | ~3,500 | `index.php` (1,727) till UI, `manage_pos.php` (1,341), 8 APIs (save transaction, refund, replacement, receipt, details) |
| Blogs | 4 | ~950 | add/edit/list + CKEditor image upload |
| FAQs | 3 | — | |
| Hero slider | 2 + 4 procs | ~600 | two parallel schemas (`hero_slides`, `hero_slides_alt`) |
| Footer / topbar / sale page | 3 | ~1,000 | settings builders writing JSON-ish rows |
| Projects | 3 | ~600 | portfolio CRUD + image uploads |
| Reviews | 2 | ~590 | approve/reject (`manage_reviews`, `customer-reviews`) |
| Coupons | 3 | — | |
| Customers / subscribers | 2 | — | read-only lists |
| Sales report / export | 2 | — | `sales-report.php`, `php/export_sales.php` (CSV) |
| SEO redirects | 1 | 286 | writes `url_redirects` |
| Email settings | 1 | — | SMTP host/user/pass/from stored in DB — **plaintext** |
| Users | 3 | — | add/edit/list, bcrypt on create |

---

## 5. Data layer

* `mysqli` with **310 prepared statements** and **209 direct `query()` calls**.
* Only **38 interpolated queries**, and they are schema DDL guarded by column/table allow-lists (`ALTER TABLE` self-healing, `SHOW COLUMNS`), plus one `WHERE id = $productId` after an `intval`. No `$_GET`/`$_POST` value reaches a query string directly.
* `utf8mb4` set on connect; errors surfaced as `$stmt->error` in a few admin screens.
* **35 of 41 tables used.** Six are dead weight: `product_attributes`, `product_attribute_terms`, `customers` (orders carry the customer), `pos_bank_accounts`, `pos_payment_apps`, `doctors` (template leftover).
* Two competing schemas coexist: `reviews` vs `customer_reviews`, `hero_slides` vs `hero_slides_alt`, and the site reads both.
* Three copies of the same connection file (`/connection.php`, `/config/connection.php`, `/lbpanel/config/connection.php`), two copies of the footer, two of `api/` helpers — classic copy-paste drift.

---

## 6. Security findings

### Critical

| # | Finding | Evidence | Impact |
| --- | --- | --- | --- |
| **C1** | **Live database credentials hardcoded in source** | `connection.php:23-24` and `lbpanel/config/connection.php:23-24` contain the production user `u128159657_woodbaks` and its password | Anyone who obtains one file (backup, error page, misconfigured handler, the archive itself) owns the database. The credential also appears in `error_log` (see C2) and is now inside the uploaded archive |
| **C2** | **`error_log` is publicly readable and contains credentials + server paths** | `/error_log` (3.1 KB) holds a mysqli stack trace with the old DB user, its password and `/home2/furnitur/baksorbit.woodexfurniture.pk/…` | `.htaccess` blocks `\.log` but `error_log` has **no extension**, so no `<FilesMatch>` rule matches it. Direct information disclosure, plus a working DB credential |
| **C3** | **Admin panel is unauthenticated by default** | Only **20 of 79** admin scripts redirect anonymous visitors. `lbpanel/.htaccess` contains a comment and no access rule. Unprotected: `dashboard.php`, `view-products.php`, `view-customers.php`, `view-subscribers.php`, `add-users.php`, `sales-report.php`, all `php/*` processors, all `pos/api/*` | Anonymous visitors can read revenue and customer PII, **create a new admin account** (`POST add-users.php`), **delete products** (`view-products.php?delete_id=N`), **create POS sales**, **issue refunds**, and **change order status** (which sends customer emails) |

### High

| # | Finding | Evidence | Impact |
| --- | --- | --- | --- |
| **H1** | No RBAC despite a `role` column | `$_SESSION['role']` referenced once in the whole panel | Every logged-in user is effectively super-admin |
| **H2** | No `session_regenerate_id()` on login; no throttling | `lbpanel/index.php` sets session vars and redirects | Session fixation; unlimited password guessing |
| **H3** | CSRF protection only on a handful of pages | 27 CSRF references, concentrated in `customer-reviews.php`; destructive actions are GET (`?delete_id=`) | Any admin session can be driven from a third-party page |
| **H4** | Unprotected state-changing JSON endpoints | `lbpanel/pos/api/save_transaction.php`, `process_refund.php`, `process_replacement.php`, `php/update_order_status.php`, `php/hero_save_slide.php` — none start a session or check one | Money-affecting writes without authentication |

### Medium

| # | Finding | Evidence |
| --- | --- | --- |
| M1 | Debug artefacts in the web root | `session_test.php` prints the session id; `database/live_schema_updates.sql` (26 KB) is blocked by `.htaccess` only because it ends in `.sql`; `AGENTS.md` publicly lists the repo map |
| M2 | SMTP password stored in plaintext in `email_settings` and rendered into admin forms | `includes/email_config.php`, `email-settings.php` |
| M3 | Upload limits set very high | `.user.ini`: `upload_max_filesize = 9999M`, `post_max_size = 512M` |
| M4 | Database schema self-heals at runtime | Pages run `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE` on request | hides drift, needs DDL rights in production |
| M5 | Public endpoints trust input shape | `api/cart/*`, `api/quote/*` accept JSON and update session carts without CSRF tokens | acceptable for anonymous carts, but no rate limiting |
| M6 | Quote numbers are predictable | `QR-` + date + first 6 hex of `md5(microtime())` |

### Low / informational

* `md5()` used only for cart/quote keys and URL hashes — not for passwords (bcrypt is used correctly).
* Bootstrap, Font Awesome and jQuery loaded from public CDNs without SRI.
* No tests, no `composer.json`, no `.env`; configuration is code.
* `display_errors = Off` in `.user.ini` (good), security headers set in `.htaccess` (good).

### What is done **right**

`password_hash`/`password_verify` with bcrypt · prepared statements dominate (310) · `getimagesize()` + MIME allow-list on every image upload · `.htaccess` denies dotfiles, `.sql`, `.log`, `.ini`, `.bak` · security headers (`X-Frame-Options`, `nosniff`, `X-XSS-Protection`) · HTTPS + `www` canonicalisation · custom session directory outside the docroot · sanitised output with `htmlspecialchars` (731 uses) · no `eval`, no dynamic includes, no SQL built from request data.

---

## 7. Code ↔ data: where the audit's data problems will surface

| Data problem (from the DB audit) | Where it shows up in this code |
| --- | --- |
| 21 orphaned line items (6 order, 15 quotation) | `manage-orders.php`, `quotation-request-view.php` join `order_items` → `products`; deleted ids leave blank product names/links |
| 34 products priced 0 | `singleproduct.php` and the POS both read `price` directly → "Rs 0" on the product page and in the till |
| 135 of 171 out of stock | no stock guard in `checkout.php` or `pos/api/save_transaction.php` — orders can be placed for unbuyable items |
| 82 `SKU:` prefixed, 10 duplicate SKUs | search and admin list sort on the raw field |
| Dashboard revenue | `WHERE status='completed'` matches `'Completed'` only because MySQL collations are case-insensitive — it would silently return 0 on a binary-collation server |

---

## 8. Can this run in the preview sandbox?

| Requirement | Status |
| --- | --- |
| PHP interpreter | ✗ none installed; `php-wasm` (npm) can execute it |
| `mysqli` extension | ✗ not in php-wasm's default build — needs a shim or a MySQL-protocol server |
| MySQL/MariaDB | ✗ no server available; your data is in the imported SQLite preview DB |
| Apache rewrites | ✗ no Apache — would need a router that replays the 30+ `.htaccess` rules |
| Uploads / CKEditor / dompdf / PHPMailer | ✓ all present in the archive |
| Config override | ✓ `LIVE_DB_*` env vars are already supported by `connection.php` — the cleanest hook for a shim |

**Conclusion:** the code is complete and portable enough to run, but only with a database adapter. The shortest credible path is: run PHP under `php-wasm`, provide a `mysqli`-compatible class backed by the imported SQLite data, and map URLs with a small router. That is a real port, not a one-liner — but it is now unblocked because the source is complete.

---

## 9. Fix order

**P0 — today, before anything else is exposed publicly**
1. Rotate the database password; remove the hardcoded fallbacks from the three `connection.php` copies and load `LIVE_DB_*` from environment only.
2. Delete `/error_log` from the server, and add `error_log` (no extension) plus `*.log`/`*.sql` to the denial list; rotate the leaked old credential.
3. Add a single auth guard (`lbpanel/includes/auth.php` → `if (empty($_SESSION['username'])) { header('Location: index.php'); exit; }`) and `require` it at the top of all 79 admin scripts; add `Require all denied`-style protection or an IP allow-list for `lbpanel/` while that lands.

**P1 — this week**
4. Stop destructive GETs: `view-products.php?delete_id=` → POST + CSRF token.
5. `session_regenerate_id(true)` on login; add login throttling; enforce `role` on admin-only screens; `session_destroy()` + cookie clear on logout.
6. Move SMTP credentials to environment variables; stop rendering the password into forms.
7. Remove `session_test.php`, `AGENTS.md`, `database/live_schema_updates.sql` from the web root.

**P2 — data**
8. Apply the repair SQL from `docs/database-audit.md` (re-link 21 orphaned lines, set the 34 prices, de-duplicate SKUs) — the admin will look broken until this is done.
9. Drop the six unused tables and pick one schema for reviews and hero slides.

**P3 — engineering**
10. Introduce `composer.json`, a `.env`, and one shared connection/bootstrap; delete the duplicate `connection.php` and `footer.php` copies.
11. Add stock guards in checkout and POS; add tests for the money paths.

---

## 10. Reproducing this audit

```bash
# 1. assemble the four uploaded volumes (RAR5, not zip)
cp public_html.part1.zip public_html.rar
for i in 2 3 4; do cp public_html.part$i.zip "public_html.r$(printf '%02d' $((i-2)))"; done
# 2. extract (7z handles the multivolume set)
npx 7z-wasm x public_html.rar -o php-source
# 3. verify
find php-source -name '*.php' | wc -l     # 295
```

Evidence files: `/tmp/rar/` (reconstructed volumes), `php-source/public_html/` (extracted tree, git-ignored), `docs/database-audit.md` (data audit), `/tmp/wa.sqlite` (41-table mirror).
