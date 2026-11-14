# Running the original Woodex PHP app locally

This directory runs **the real cPanel application** (`php-source/public_html/`,
1,407 files) on this machine — no rewrites, no rebuild, the same PHP files the
client uploaded, talking to the same 41-table schema restored from
`db-source/woodexbaks.sql`.

```
browser ──► server.mjs  (PHP 8.2 compiled to WebAssembly, @php-wasm)
                 │  replays .htaccess rewrites, serves static files from disk
                 ▼
            mysqli  ──►  mysql_bridge.py  (MySQL wire protocol on 127.0.0.1:3306)
                 │        · translates MySQL → SQLite with sqlglot
                 │        · registers MySQL functions (NOW, CONCAT, DATE_FORMAT, IF, …)
                 ▼
            .tmp/php-live.sqlite   (derived copy of the cpanel dump — 41 tables, 171 products)
```

## Quick start

```bash
tools/php-run/dev.sh start          # bridge on 3306 + PHP runner on 3000
open http://localhost:3000/lbpanel/ # admin login
tools/php-run/dev.sh status
tools/php-run/dev.sh stop
```

Credentials created for this local run (the original hash is unknown):

| user      | password       | role    |
| --------- | -------------- | ------- |
| `admin`   | `Woodex@2026`  | admin   |
| `bilal`   | `Woodex@2026`  | admin   |
| `manager` | `Woodex@2026`  | manager |

Rebuild the database from the cpanel dump at any time (backup kept, accounts
re-seeded):

```bash
tools/php-run/rebuild-db.sh        # .tmp/php-live.sqlite from db-source/woodexbaks.sql
```

The storefront is at `http://localhost:3000/`, the admin panel at `/lbpanel/`.

## What is where

| path                            | role                                                        |
| ------------------------------- | ----------------------------------------------------------- |
| `server.mjs`                    | PHP-WASM runtime + HTTP router (`.htaccess` built in)       |
| `mysql_bridge.py`               | MySQL-protocol server over SQLite, plus the compat patches   |
| `shims/wdx_mysqli.php`          | mysqli shim used by the local run (see below)                |
| `apply-shim.sh`                 | installs/removes that shim in the extracted source           |
| `smoke.sh`                      | logs in and walks admin + storefront pages, reports failures |
| `trace_proxy.py`                | wire-level MySQL tracer (bridge on 3307, proxy on 3306)      |
| `dev.sh`                        | start/stop/status for both processes                         |
| `serve.sh`                      | foreground launcher (bridge + runner), used by the preview   |
| `rebuild-db.sh`                 | re-import the dump into `.tmp/php-live.sqlite`                |
| `seed_users.py`                 | create the local admin accounts (bcrypt, cost 10)             |
| `list-slugs.py`                 | category/product slugs for `/sitemap.xml`                     |
| `crawl.py`                      | crawls every route (storefront + admin) → `docs/route-audit.md` |
| `content-fixes.py`              | local-only content repairs (test hero slide, logo cards)      |

Runtime files live in `.tmp/` (git-ignored): `php-live.sqlite` (data),
`sql-trace.log` (every query, `OK`/`ERR`), `mysql-bridge.log`,
`php-runner.log`, `php-errors.log`, `php-sessions/` (PHP session files).

Environment switches: `PORT` (default 3000), `PHP_VERSION` (default 8.2 —
8.3/8.4/8.5 ship with `@php-wasm/node`), `BRIDGE_DEBUG=1` for protocol logs.

## Why a mysqli shim — the mysqlnd problem

PHP's native driver (`mysqlnd`) in the php-wasm build aborts the whole process
as soon as a **prepared statement** returns rows:

```
RuntimeError: null function or function signature mismatch
  at php.wasm.mysqlnd_read_header / mysqlnd_mempool_destroy
```

It reproduces on PHP 8.2.35, 8.3.36 and 8.4.27 (so not version-specific), with
and without `CLIENT_DEPRECATE_EOF`, on 1-column and 12-column selects, after 3
to 17 statements. Two independent reference clients (mysql-connector-python and
pymysql) read the exact same responses without complaint, so the bridge is not
the problem — the WASM mysqlnd build is.

A second, separate defect: a bare 7-byte OK packet makes mysqlnd read one byte
past the packet while parsing the optional "info" field, which fails the
response (`mysqli_query() === false`) and poisons the connection
(`errno 2014, Commands out of sync`). The bridge now appends an empty
length-encoded info field, which fixes every write path.

For prepared statements the app is served by `WdxMysqli` (`extends mysqli`),
which runs statements as escaped text queries over the same connection — the
text protocol is stable (hundreds of queries, zero crashes). Only three files
are touched, all of them the same connection bootstrap:

```
connection.php, config/connection.php, lbpanel/config/connection.php
```

`apply-shim.sh` keeps `.orig` copies and can revert. Nothing else in
`public_html/` is modified.

## Translation layer (mysql_bridge.py)

Notable MySQL → SQLite handling, all driven by the queries the app actually
issues:

* `GROUP_CONCAT(x ORDER BY y SEPARATOR ', ')` → `GROUP_CONCAT(x, ', ')`
* `CREATE TABLE IF NOT EXISTS` for a table that already exists in the import is
  skipped entirely (otherwise `AUTO_INCREMENT`, `ENUM`, named `UNIQUE`
  constraints and `KEY`/`INDEX` clauses all fail in SQLite)
* `ALTER TABLE … ADD COLUMN … AFTER col` → `AFTER` removed; "duplicate column
  name" is treated as success because the imported schema already has the column
* `SHOW COLUMNS FROM t LIKE 'c'` is answered from `PRAGMA table_info`
* MySQL functions registered locally: `NOW`, `SYSDATE`, `CURDATE`,
  `DATE_FORMAT`, `CONCAT`, `IF`, `IFNULL`, `UNIX_TIMESTAMP`, …

## Verified state

`tools/php-run/smoke.sh` on this machine:

```
== login ==              ok (302 → dashboard.php)
== admin panel ==        dashboard, add-users, add-blog, add-faq, add-coupon,
                         manage-orders, view-products, sales-report — all 200
== storefront ==         home, shop, about, contact, blog, faqs, cart, sale,
                         projects, services, showrooms, b2b, warranty, privacy,
                         product page (/shop/<slug>), blog post — all 200
== 26/26 pages ok ==
```

Writes are verified end-to-end: posting the "add FAQ" form as `bilal` inserts a
row (faqs 89 → 90) and the panel reports "FAQ added successfully!".

## Browser-facing behaviour of the runner

The preview is served through a proxy, so the runner has to be careful about the
origin the application sees:

* **Origin pass-through.** The PHP request is built with the browser's real
  `x-forwarded-proto`/`Host`. The app derives every absolute URL from
  `$_SERVER['HTTP_HOST']`, so pages now emit `https://<preview-host>/assets/...`
  instead of `http://127.0.0.1:3000/assets/...` — the latter was blocked as mixed
  content, which is why images appeared broken.
* **Hardcoded production domain.** A few templates/footers hardcode
  `https://woodexfurniture.pk/...`; those URLs are rewritten in the HTML response
  to the request origin (mail addresses are left untouched).
* **Missing images.** Images absent from the upload get a neutral SVG placeholder
  (200, `image/svg+xml`) instead of a broken-image icon.
* **Link aliases.** The delivered header/footer defaults point at pages and
  category slugs that do not exist in the database (`/categories/ergonomic-chair`,
  `/portfolio`, `/careers`, `/sitemap.xml`, …). They 404 on the live host too.
  For the preview, `LINK_ALIASES` in `server.mjs` redirects them to the closest
  real page, and any other unknown `/categories|/blog|/projects|/shop/<slug>`
  redirects to its listing page.
* **`/sitemap.xml`** is generated from the database (pages + categories + 200
  products) instead of 404-ing.

Verified after these changes: **184 links/assets on the home page, 0 broken**,
and the same 26/26 smoke pages.

## Audits

Three re-runnable audits back the "everything works" claim; each writes a document under `docs/`:

```bash
.tmp/venv/bin/python tools/media-audit.py   # every media file vs every reference  → docs/media-audit.md
python3 tools/db-deep-audit.py              # data integrity + schema vs code      → docs/database-deep-audit.md
python3 tools/php-run/crawl.py              # all routes, logged-in admin included  → docs/route-audit.md
```

`docs/deep-audit.md` is the consolidated report: what is present, what is missing (and whether it
is missing on the live site too), what was fixed in the runner, and what only the site owner can fix.

## Running each script from its own directory

Apache and php-fpm set the working directory to the script's own folder, and the POS module plus
the category manager rely on it (`require '../config/connection.php'` — PHP resolves a leading
`../` against the cwd only). PHP-WASM keeps a single cwd, so the runner writes
`.tmp/php-prepend.php` and registers it as `auto_prepend_file`:

```php
if (@is_file($_SERVER['SCRIPT_FILENAME'])) @chdir(dirname($_SERVER['SCRIPT_FILENAME']));
```

## Limits of this harness (all local-run only)

* It is a **development harness**: MySQL is emulated over SQLite, so
  transactional/stored-procedure behaviour differs from the cPanel host.
* `affected_rows`/`insert_id` for writes come back as 0/-1 through the shim;
  the app only uses them for "record saved" messages, which still work.
* Full-text search and `MATCH … AGAINST` are not emulated.
* Production still needs the P0 security work from `docs/php-code-audit.md`
  (rotated DB password, auth guard on 59 unguarded admin scripts, CSRF, session
  regeneration, login throttling).
