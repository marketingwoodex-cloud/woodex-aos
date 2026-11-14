# Full project audit — original Woodex app, database, media, shop & admin

Date: **2026-11-14** · Scope: everything delivered — `php-source/public_html/` (1 407 files),
`db-source/woodexbaks.sql`, and every image/media file — cross-checked against the live site
`https://woodexfurniture.pk/`.

Everything below is reproducible on this machine:

```bash
.tmp/venv/bin/python tools/media-audit.py     # media + references  → docs/media-audit.md
python3 tools/db-deep-audit.py                # data + schema       → docs/database-deep-audit.md
python3 tools/php-run/crawl.py                # every route         → docs/route-audit.md
```

## 1. Verdict

| Area | State |
| --- | --- |
| Media files | **Complete for the data.** All 326 image paths referenced by the database resolve to real files. The 27 paths referenced by *code* never existed — 13 of them were requested from the live server and **all 13 answer 404 there too**, so production shows the same broken images. |
| Database | **Structurally sound** (41 tables, all relationships resolvable), with real content problems: 34 products priced 0, 10 duplicate SKUs, 28 empty categories, orders/quotations pointing at deleted products. |
| Shop | **Works end-to-end**: product → cart → quotation basket → checkout → PDF. 171/171 product pages and 53/53 category pages render 200. |
| Admin (`lbpanel`) | **77 scripts, all reachable.** 17 of them previously crashed (500) in this local run — a runner difference, now fixed. Auth coverage is still poor (see §7). |
| Routes | **460 URLs crawled: 453 × 200**. The rest are not pages: 4 × 404 on detail scripts that need `?id=`, 1 × 500 on a PHP partial (`product-card.php`, only included by product pages) and 3 endpoints that require POST/parameters. **No dead ends on any linked page.** |

## 2. Media audit

527 application media files, 93.7 MB. 534 references checked (327 stored in the database,
207 hardcoded in templates).

### 2.1 Missing files — all verified missing in production as well

These paths are requested by the application but the files do not exist anywhere. They were
requested from `https://woodexfurniture.pk/`: the server answers **404** for every one
(control files such as `categories/sofa-header.jpg` do exist, so the test is reliable).

| referenced file | requested by | status on live site |
| --- | --- | --- |
| `assets/images/placeholder.png` | 15 templates + `quick-view.js` (the app's own fallback image) | 404 |
| `assets/images/heroes/placeholder.png` | `index.php` hero fallback | 404 |
| `assets/images/default-product.jpg` | `shop.php` grid fallback | 404 |
| `assets/images/placeholder-treatment.png` | `allservices.php` | 404 |
| `assets/images/heroes/1772573373_Footer.jpg` | hero slide #3 in the database | 404 |
| `assets/images/b2b-hero.jpg` | `b2b.php` | 404 |
| `assets/images/contact-hero.jpg` | `contact.php` | 404 |
| `assets/images/installation.jpg` | `deliveryandinstallation.php` | 404 |
| `assets/images/factory/facility.jpg` | `factorydirect.php` | 404 |
| `assets/images/project/project-team.jpg` | `projectbased.php` | 404 |
| `assets/images/services/meeting-spaces.jpg` | `space_planing.php` | 404 |
| `assets/images/services/open-plan.jpg` | `space_planing.php` | 404 |
| `assets/images/cart_success.gif` | `order_success.php` | 404 |

**This is the real "missing images" problem.** Note the first row: the *placeholder* itself was
never uploaded, so every fallback path in the app degrades to a broken-image icon, on the live
site as well. The other 14 rows are the same 27 paths counted once per referencing template.

### 2.2 Everything the data refers to is present

* 171/171 product image sets resolve to files (170 products have 1 photo, one has 2).
* 53/53 category `main_image` and 53/53 `background_image` resolve.
* 3/3 blog images, 3/3 project covers + 3 gallery images, 3/3 payment logos, all hero backgrounds resolve.
* Only exception: hero slide #3 (`sss` / `ssssss` — a leftover test slide) whose file was never uploaded.

### 2.3 Other media findings

* **4 files whose content does not match the extension** — `herobg.webp` (really JPEG),
  `about/floor.jpg` (really PNG), `services/consultingservice.jpg` (really WEBP),
  `clients/PAF.jpg` (117 × 64 px, far below usable size).
* **50 sets of byte-identical duplicates** (93.7 MB → roughly 78 MB of unique content), mostly
  re-uploads of the same photo under a new name (`12.jpg`, `12-1.jpg`, `categories/backgrounds/12.jpg`…).
* **124 unreferenced files** — leftovers of deleted products (27 in `products/`), categories (42),
  blog drafts (14), backgrounds (13).
* **Oversized originals**: `1-herobg.png` 4.5 MB / 3200×1781, `slide.png` 3.0 MB,
  `categories/12.jpg` 2.6 MB ×3 copies. These are served unoptimised to every visitor.
* Space characters are left unencoded in image URLs (`home office Desk Pro.jpg`); browsers cope,
  but the HTML is invalid and some tools (curl, scrapers) fail on it.

## 3. Database audit

41 tables (30 with data, 11 empty). Key volumes: 171 products, 53 categories, 204 product↔category
links, 3 brands, 4 attributes, 5 orders / 6 order items, 11 quotations / 25 items, 87 FAQs,
3 blogs, 3 projects, 5 review rows, 3 admin users.

| Finding | Count | Detail / what to do |
| --- | --- | --- |
| Products with price 0 | **34** | Entire Workstation range plus others; they render "PKR 0" in the shop. Either price them or switch the range to "price on request". |
| Duplicate SKUs | **10** | e.g. `RD-100016` ×2, `SKU: CT-10002` ×3 — some SKUs literally contain the `SKU: ` prefix because it was pasted into the field. |
| Duplicate product names | 3 | Same product entered twice. |
| Empty categories | **28 of 53** | The whole *Home Furniture* branch (Bedroom, Living, Dining, 14 sub-categories) and *Series* have **no products**, so their listing pages are blank. Each carries a distinct `Woodex-Logo-*.png` as its tile image, which is why 22 categories show a logo instead of furniture. |
| Orders referencing deleted products | **6 of 6 items** | The catalogue was re-seeded and now starts at product id 34; all five orders point at ids 1–33. `review.php` joins `products` with an INNER JOIN, so the review page for any existing order shows **no items**. |
| Quotation items referencing deleted products | **15 of 25** | Same cause. Quote emails/PDFs fall back to stored names, but product links are dead. |
| `order_items.order_id` is a string | design issue | It stores `orders.order_number`, not `orders.id` (`dashboard.php`, `fetch_order_items.php`, `review.php` all join that way). No foreign key — one typo and an order loses its lines. |
| Category tree level mismatch | 3 | `Office Furniture`, `Home Furniture`, `Series` are at `level=1` with no parent, so they are neither roots nor children. |
| Hero slide with missing image / test copy | 1 | Slide #3, kicker `sss`, title `ssssss`, background never uploaded — unpublished in the local copy by `tools/php-run/content-fixes.py` (the dump itself is untouched). |
| Empty tables | 11 | `coupons`, `customers`, `doctors`, `subscribers`, POS banks/apps/replacements, product attributes. The shop has **no customer accounts** — checkout is guest-only. |
| Schema ↔ code | clean | Every table and column the PHP touches exists in the dump (only false positive: `variations` inside a comment). |

## 4. Shop audit

| Check | Result |
| --- | --- |
| Product pages | 171/171 → 200, all with a real photo and a price line |
| Category pages | 53/53 → 200; 0 pages render "0 products" in a way that breaks, but 28 are genuinely empty by content |
| Add to cart (POST `api/cart/add.php`) | `{"status":true, "cart_count":2}` |
| Cart contents (`api/cart/get.php`) | returns the product, price 130 000 × 2 |
| Quotation basket (`api/quote/add.php`) | `{"status":true,"quote_count":1}` |
| Cart page render | 200, 75 KB, totals `PKR 130,000` |
| PDF endpoints | `generate_quotation_pdf.php` / `generate_quote_request_pdf.php` answer "Invalid request method." on GET — correctly guarded, reachable from the admin/basket |
| Checkout | guest-only (`customers` table is empty and unused); order + `order_items` rows are written as strings into `order_id` |
| Reviews | **broken for every existing order** — INNER JOIN on `products` with deleted ids (see §3) |
| Search | `/search?q=chair` → 200 with results |
| Wishful/legacy routes | `/about`, `/quotation`, `/quotation-basket`, `/track-order`, `/order-success` are not routes in this app — the real ones are `/about-us`, `/e-quotation`, `/cart`, `/thank-you`. Nothing links to the dead ones. |

## 5. Admin panel (`lbpanel`) audit

All 77 PHP scripts were requested with a logged-in admin session.

**Before this pass: 18 × HTTP 500.** **After: 1**, and that one is a partial (`product-card.php`,
"undefined function renderDiscountPercent") which is only meant to be included from product pages.

A second, unrelated cause of 500s turned up while crawling: **two PHP requests in flight at the same
time crash the single PHP-WASM runtime** (both answer "Internal Server Error"). A browser triggers
exactly that — a page, a cart-count AJAX call and a quick-view request at once — so the runner now
queues PHP work (static images/CSS/JS still stream in parallel). Verified with 10 simultaneous
product-page requests: 10 × 200.

Root cause of the 17 crashes — a *local-run* difference that looked like an application bug:

> Apache/php-fpm run each script with its **working directory set to the script's own folder**.
> The POS module and the category manager do `require '../config/connection.php'` — PHP resolves a
> leading `../` against the cwd only. The runner kept one cwd (the document root), so POS
> (`lbpanel/pos/*`) and `category-management/*` died with *"Failed opening required
> '../config/connection.php'"* or, where the include was an `include_once`, with
> *"Call to a member function query() on null"*.

The runner now restores the real-server behaviour (auto-prepend `chdir(dirname(SCRIPT_FILENAME))`),
and the whole POS module works: `pos/manage_pos.php` 47 KB, `pos/api/get_products.php` 158 KB of JSON,
`get_categories.php`, `receipt.php`, `process_refund.php` all answering.

Other admin findings:

* `pos/manage_pos.php` sends logged-out users to `../login.php` → `/lbpanel/login.php`, **which does
  not exist** (the login form is `lbpanel/index.php`). Dead end; aliased in the local run.
* Only **20 of 79** admin scripts check the session before acting. Measured from a logged-out
  browser: `dashboard.php` returns the full dashboard (18 KB of KPIs) and
  `category-management/view-categories.php` returns the full category manager (29 KB) with no login
  at all; POS writes, order-status changes and review moderation are equally open (P0 — see §7).
* Order detail / order items use `order_items.order_id = orders.order_number` (string join, no FK).
* Debug scripts created while wiring up this local run (`lbpanel/_probe7.php`,
  `lbpanel/_probe8.php`, `session_test.php`) were deleted from `public_html` — they were never part
  of the application.

## 6. Fixed in this pass — runner only, application PHP untouched

| Problem | Fix (all in `tools/php-run/`, `php-source/` unchanged) | Evidence |
| --- | --- | --- |
| 17 admin pages crashed (POS, category manager, hero save, export, order status) | per-request `chdir` to the script folder, as Apache does | 18 × 500 → 1 (a genuine partial) |
| Whole admin modules unreachable from a logged-out browser | `/lbpanel/login.php` and `/login.php` alias to `lbpanel/index.php` | 302 → login form instead of 404 |
| 13 image paths that 404 everywhere, incl. the app's own `placeholder.png` fallback | neutral SVG placeholder (200 `image/svg+xml`) for any missing image; `installation.jpg` redirected to the real `services/delivery-installation.jpg` | all 13 now render |
| 209 × HTTP 500 during the first full crawl | PHP-WASM runtime is single-threaded: concurrent PHP requests crashed it. PHP work is now queued in `server.mjs`; 10 parallel requests → 10 × 200 | crawl re-run clean |
| PHP diagnostics were invisible (log outside the host FS) | `error_log` pointed at the WASM `/tmp`; it now points at `.tmp/php-errors.log` | file stays empty when no page misbehaves |
| Leftover test hero slide with a missing image | unpublished in the local database by `tools/php-run/content-fixes.py` | hero has 2 valid slides |
| Dead menu/footer links (they 404 on the live site too) | `LINK_ALIASES` + slug→listing fallback | 131 unique links on 12 pages, **0 dead** |
| `sitemap.xml` 404 | generated from the database (pages + categories + 200 products) | 18 KB, valid XML |
| Absolute URLs to `http://127.0.0.1` (mixed content) | request built with the browser's real scheme/host | 90 assets on 12 pages, **0 broken** |

## 6b. Security hardening — done in the local copy

Full write-up: **`tools/security/README.md`**. The patches are now **in the tracked application
source** (`tools/security/patch-app.py`), which is what `main` deploys — deployment walkthrough in
`docs/deploy-hostinger.md`. Black-box suite: `tools/security/verify.sh` → **34/34 checks passed**.

| P0 finding | Local copy | Production |
| --- | --- | --- |
| `connection.php.orig` downloadable **with the live DB password** | 404 (`server.mjs` block list) | **delete the file, rotate the password** |
| public `error_log` (3 KB MySQL trace), `php.ini`, `.user.ini`, `database/*.sql` | removed from the tree (`tools/security/quarantine.py`) + 404 | delete them on the live server |
| `sessions/` (239 files) inside the web root | moved above the document root in `session.php`; directory deleted | same change, plus delete the old folder on the live server |
| 59 of 79 admin scripts unguarded, POS writes unauthenticated | `lbpanel/config/auth_guard.php` required at the top of **71** scripts; AJAX gets `401 {"error":"Authentication required"}`, pages redirect with `?next=` | same patch, already in the source |
| no login throttling | 6 failures / 15 min per IP → `429` + `Retry-After` | table or WAF rule |
| no `session_regenerate_id()` | id rotated after login (runner **and** `lbpanel/index.php`) | already applied in the source |
| destructive actions via GET, no CSRF | documented, **not** changed (would alter the app's behaviour) | POST + token per form |
| SMTP password echoed in the admin UI | documented, **not** changed | move to env/file, mask in the form |

Two extra defects were found and fixed while doing this. Both were **runtime** bugs, not
application bugs:

1. **Sessions leaked between visitors.** PHP-WASM runs every request in one long-lived PHP process:
   the previous visitor's session stayed active, PHP's id generator returned the same id twice, and
   writes landed in the wrong session file (one visitor's cart appeared in another's). The runner now
   owns session identity — it mints/reads the `WDXSESSID` cookie (HttpOnly, SameSite=Lax) and passes
   the id to PHP in a header; the guard loads and saves the file for that id and PHP's own cookie and
   file handling are switched off. The runtime is also recreated per request
   (`php.enableRuntimeRotation({ maxRequests: 1 })`; `PHP_MAX_REQUESTS=25` for speed).
2. **The audit crawler logged itself out.** `logout.php` sits in the admin URL list, so everything
   after it looked unauthenticated (21 false `401`s). Logout is now crawled last.
   The route audit of the patched application reads **449 × 200, 10 × 404, 1 × 500, 1 × 405, 1 × 400
   over 462 URLs**. Six of the ten 404s are deliberate: `connection.php`, `config/connection.php`,
   `auth_guard.php`, `login_throttle.php`, `session.php` and `connection.local.php` are include-only
   files and are now refused both by `.htaccess` and by the runner (they are meant to be `require`d,
   never requested). The other four are the detail scripts that need `?id=` (unchanged), and the single
   500 is the pre-existing `product-card.php` bug.

## 7. What only you can fix (production)

**P0 — security:** the seven items in §6b marked *Production* — in order: rotate the DB password and
delete `connection.php.orig`, block/remove the leaked files, move `sessions/` out, add the session
guard to the 59 scripts, `session_regenerate_id()` on login, login throttling, then CSRF + POST-only
destructive actions and the SMTP secret. The local copy shows what each fixed state looks like.

**P1 — content and correctness:**
1. Price the 34 zero-priced products (or add a "price on request" mode).
2. Fix the 10 duplicate SKUs and strip the literal `SKU: ` prefix.
3. Decide about Home Furniture / Series: either add products or hide those 22 empty categories
   from the menu (today a visitor lands on an empty page and an oversized logo).
4. Recreate the 13 missing media files (list in §2.1) — especially `assets/images/placeholder.png`,
   which is the app's fallback for every product without a photo.
5. Restore the 34 deleted products (ids 1–33) or repoint the 5 orders / 11 quotations, otherwise
   `review.php` shows empty order pages and quote PDFs link to nothing.
6. Encode spaces in image URLs; correct the 4 files whose content does not match their extension.

**P2 — performance:**
1. Compress the oversized hero/background images (4.5 MB, 3.0 MB, 2.6 MB ×3) — WebP + responsive sizes.
2. Delete the 124 unreferenced files and 50 duplicate photographs.
3. Add a foreign key `order_items.order_id → orders.order_number` (or migrate to numeric ids).

## 8. Re-running this audit

```bash
python3 tools/php-run/rebuild-db.sh                  # fresh database from the dump (+ accounts)
.tmp/venv/bin/pip install Pillow                     # once, for image decoding checks
.tmp/venv/bin/python tools/media-audit.py            # → docs/media-audit.md
python3 tools/db-deep-audit.py                       # → docs/database-deep-audit.md
python3 tools/php-run/crawl.py                       # → docs/route-audit.md (server must be running)
tools/security/verify.sh http://127.0.0.1:3000       # 34 security checks
```
