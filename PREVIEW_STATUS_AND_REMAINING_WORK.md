# Woodex preview — status and remaining work

**Checked:** 14 November 2026  
**Scope:** isolated preview running from the supplied archives and filtered SQL export; representative HTTP, media, cart, database and admin checks. This is not a full penetration test, visual comparison or certification of every business flow.

## Executive status

The storefront, shop, a sanitized catalog database and lbpanel are running in the current preview. The current server uses native **PHP 8.4** with a local **MariaDB 10.8** database. It is not connected to the live database. The preview is disposable and is **not production-ready**.

| Area | Status | Evidence / limitation |
|---|---|---|
| Preview server | Running when checked | Homepage returned HTTP 200. Current native PHP, libraries and database live under temporary sandbox paths and are not a durable deployment. |
| Catalog / media | Mostly available | 171 product records; all 172 product image references checked exist. One classic hero image is missing, below. |
| Storefront pages | Representative pages available | Homepage, shop, category, product, blog, projects, quotation, contact and about pages returned 200. No full browser/device or visual comparison completed. |
| Cart and database writes | Basic smoke tests passed | A cart item was added and counted; a disposable subscriber was inserted and removed. A test checkout created one order and one item, then both were removed. This does not validate all business rules. |
| lbpanel / POS | Representative reads available | Demo login and dashboard, products, orders, POS screen and POS category/product endpoints responded successfully. Anonymous admin PHP requests redirected to login. POS sale/refund/replacement flows remain untested. |
| Email / PDF | Not complete | Real mail delivery is intentionally disabled; Composer's `vendor/autoload.php` is absent from the archive and PDF fallback output has not been validated. |
| Production readiness | Not ready | Security, checkout integrity, backup, dependencies, portability and comprehensive testing remain outstanding. |

## Fixes made during this check

1. **Seven broken homepage/footer destinations:** mapped old preview-only links to the closest existing pages: ergonomic chair → office chairs; executive desks → executive table; workstations → workstation; reception → reception desk; office sofa/storage → categories; planning ideas → space planning; workspace design → project based. These are approximate replacements, not claims about original intended content. All seven now resolve to HTTP 200 after redirect. The mapping is in `preview/wasm-router.php`; no production database was changed.
2. **Missing sitemap route:** added a preview-only `sitemap.xml` generated from the isolated active categories and catalog products, with a `noindex` response header. It now returns HTTP 200. This is not a substitute for a reviewed production SEO sitemap.
3. **Previous media fix retained:** five variation image values containing a developer `localhost` URL were rewritten in the isolated database, and `preview/prepare.py` generates an equivalent update on a fresh local import.

## What is still missing or needs work

### P0 — safety and data integrity

| Issue | Impact / evidence | Required work |
|---|---|---|
| Checkout trusts posted totals | `process_checkout.php` stores client-supplied total and shipping values. A successful test order does not establish correct pricing. | Compute product/variation prices, shipping and coupon discounts on the server; reject invalid quantities and tampering; cover with tests. |
| Checkout is not atomic | Order and item inserts are not enclosed in one verified transaction; errors may leave partial orders. | Use a transaction, rollback all failures, preserve cart on failure and add idempotency/reconciliation tests. |
| Legacy access control and CSRF | The preview router checks an admin session, but source endpoints do not consistently enforce authorization themselves; CSRF and roles were not comprehensively verified. | Implement centralized authorization per endpoint and role, CSRF protection, secure session settings and security tests before production. |
| Upload and execution risks | One unsafe legacy CKEditor upload handler was removed from the extracted preview. Other upload handlers and media execution protections need full review. | Validate content and size, generate names, store outside executable document roots, and review every upload endpoint. |
| Archived credentials and sessions | The original archives contain hardcoded database credential fallbacks and session files. The preview excludes archived sessions and overrides connection files. | Rotate potentially exposed credentials, invalidate sessions, review repository access and remove secrets from distribution/history using an incident process. |

### P1 — complete and reproducible preview

| Issue | Impact / evidence | Required work |
|---|---|---|
| One missing image | `assets/images/heroes/1772573373_Footer.jpg` is referenced in the classic hero table but absent from the archive. The active modern hero uses files that exist. | Provide the original file or approve an intentional replacement; confirm classic-slider rendering. |
| Ephemeral runtime | Native PHP, its compiled dependency and MariaDB are in `/tmp`; extracted application/runtime files are ignored by Git. A sandbox reset may stop the preview or erase demo changes. | Build and test a reproducible setup on an environment with Docker/Composer or provide a supported persistent runtime and restart procedure. The included `preview/README.md` Docker workflow has not been end-to-end tested here. |
| PHP version and extensions | The running binary is PHP 8.4, not the originally requested 8.2. GD is unavailable. | Pin and test the intended PHP/database versions and required extensions, including GD where needed. |
| Dependencies / PDFs | Archive does not contain a root Composer lockfile or `vendor/autoload.php`; fallback PDF code exists. | Restore verified dependency manifests/lockfiles, install dependencies and test both quotation PDFs, images, fonts and downloads. |
| Live data intentionally excluded | Original customer, order, POS, quotation, subscriber, SMTP and administrator records were omitted to protect privacy. | Keep using sanitized fixtures. Any real data use requires explicit authorization, access control and privacy safeguards. |
| Live-site parity | Original homepage was inspected, but archive, SQL export and current live release may differ. | Compare against a confirmed version of the site across desktop/mobile, dynamic states, content and all media; obtain missing source assets. |

### P2 — functional verification and operations

- Test full checkout variants, coupons, stock and concurrent requests; check status updates and financial reports.
- Test product/category CRUD, uploads, images, user roles, blogs, projects, reviews and settings in lbpanel.
- Test POS sale, refund, replacement, receipts and reporting with synthetic data; reconcile inventory and order records.
- Test quotation basket, submission, PDFs, notifications and disabled-email behavior. Configure a safe mail sandbox if delivery tests are required.
- Verify URL rewrites, redirect management, 404s, canonical URLs, accessibility, responsive layouts, browser JavaScript, and external CDN availability. The temporary sitemap and link aliases only cover the issues observed in this check.
- Add automated tests, logging with redaction, monitoring, backup/restore testing, database migrations, deployment rollback, rate limits and security headers.

## Verification performed

- Confirmed the homepage and shop are reachable and the native PHP/MariaDB processes are alive.
- Crawled 135 unique local homepage links and found eight failing routes; the seven legacy aliases and the sitemap now resolve.
- Checked local image/script/stylesheet references rendered by 11 representative storefront/admin-login pages; no missing local files were reported in that sample. This is not a scan of every dynamic image or external CDN.
- Confirmed demo admin session access to dashboard, products, orders and POS screens/API; anonymous requests to selected admin endpoints redirect to login. Session and SQL paths returned 403.
- Verified PHP syntax for the changed router and sitemap. No real email, payment, live database or production data was used.

**Previous detailed inventory:** `preview/MEDIA_AND_RUNTIME_AUDIT.md`  
**Local Docker setup notes:** `preview/README.md`
