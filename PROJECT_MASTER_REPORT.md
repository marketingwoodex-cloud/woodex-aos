# Woodex AOS — Project Master Report

**Assessment:** static review of the four archive parts at repository base `efe79c9243240c7fe51ed11e31518e36821d5845`.  
**Scope:** frontend, backend, database, technology stack, business flows, security and recommended priorities. Paths below refer to the `public_html/` directory inside the archive, not extracted files in this repository.

## 1. Executive summary

Woodex is a PHP/MySQL furniture-commerce website with a server-rendered storefront, product catalog, shopping cart, cash-on-delivery checkout, quotation workflows, content pages, an administrative panel and a point-of-sale (POS) module. It is a traditional PHP application, not a separate JavaScript frontend plus API server. HTML, SQL and business logic are often mixed in individual PHP scripts. The repository itself contains a README and four archive parts, rather than an immediately runnable source tree.

**Overall assessment:** substantial business functionality is present, but the supplied snapshot is not a complete, verified deployment package. The base database schema is absent, dependency installation metadata is incomplete, and important security and order-integrity issues require urgent review before exposure or reuse. No running site or database was tested.

### Immediate priorities
1. Treat the credentials and session data in the archive as potentially exposed; rotate credentials, invalidate sessions, restrict repository access and remediate history/distribution according to the organization's incident process.
2. Enforce server-side authentication and authorization on every admin, POS and upload endpoint; audit web-server access rules and uploads.
3. Recalculate all checkout amounts on the server and make order creation atomic.
4. Obtain a sanitized, complete database schema and a reproducible dependency/runtime specification before attempting deployment.

## 2. Scope, method and limitations

- All four named `.zip` files are actually **RAR 5 multi-volume archives**. Individually they are not ZIP archives. They were inspected together using a RAR-capable extractor, without executing application code. Extraction succeeded, yielding approximately 111 MB, 1,407 files and 295 PHP files, including third-party code and session files.
- Reviewed the directory structure, configuration, representative storefront/admin/POS scripts, SQL update script, and bundled dependency metadata. Counts are snapshot counts, not estimates of unique application modules.
- This is a **static assessment**, not an exhaustive line-by-line audit or penetration test. No PHP runtime was available in the analysis environment; no live database, hosting settings, secrets, routes, integrations or user journeys were verified. A finding about a script cannot rule out external controls such as a firewall or hosting-level authentication.
- `database/live_schema_updates.sql` is an incremental update, **not a complete database dump**. Actual table definitions, constraints, production data, runtime versions, data quality and service availability remain unknown. Do not run the update against production without backup, review and testing.
- Observations about application behavior describe code present in the snapshot, not proof that the live site behaves identically.

## 3. Technology stack

| Layer | Observed technology | Evidence / qualification |
|---|---|---|
| Web server and routing | Apache-style `.htaccess`, `mod_rewrite` | `.htaccess` defines clean URLs, canonical host and HTTPS rules; actual hosting setup unverified. |
| Server | PHP, primarily procedural scripts | Root `.php` pages, `api/`, `lbpanel/`; exact deployed PHP version unknown. |
| Database | MySQL-compatible database through `mysqli` | Connection files and SQL syntax; exact server/version unknown. |
| Frontend | Server-rendered HTML, CSS, JavaScript, jQuery, Bootstrap, Owl Carousel | `index.php`, `shop.php`, `style.css`, `quick-view.js`; CDN dependencies include differing Bootstrap versions. |
| Admin editor | CKEditor 4.25.0-lts | Bundled `lbpanel/includes/ckeditor/ckeditor.js`; licensing/support status should be verified. |
| Email | PHPMailer 6.9.3 and configurable SMTP | `phpmailer/src/PHPMailer.php`, `lbpanel/includes/email_config.php`, `email_settings` SQL. |
| PDF | Dompdf code and fallback PDF implementation | PDF scripts look for `vendor/autoload.php`, which is absent in this snapshot; fallback paths exist. Dompdf package declares additional dependencies. |
| State | PHP sessions on filesystem | `lbpanel/config/session.php` selects root `sessions/` when writable. |
| Hosting hints | cPanel-style PHP configuration | `php.ini`, `.user.ini`; not proof of current host. |
| Build/dependencies | Bundled libraries; no root Composer or npm manifest/lock found | Third-party packages have their own manifests; installation and version reproducibility are incomplete. |

## 4. Architecture and data flow

```text
Browser
  ├─ Storefront PHP pages ── mysqli ── MySQL-compatible database
  ├─ JS requests → api/cart, api/quote, api/product, api/search, api/reviews
  │                      └─ PHP sessions and database
  └─ Admin /lbpanel/ ── PHP pages and endpoints ── database
                         ├─ POS transaction/refund APIs
                         ├─ uploaded files / assets
                         └─ SMTP via PHPMailer
Checkout/quotation ── database + session ── email / PDF output
```

This is a monolithic deployment with shared database and filesystem. `connection.php`, `config/connection.php` and `lbpanel/config/connection.php` provide overlapping connection configuration; maintainability and configuration drift should be assessed.

## 5. Frontend report

### Storefront
- **Homepage and merchandising:** `index.php`, hero slides, featured/best-selling products, category carousel and sale content. Homepage settings are partly database-driven.
- **Catalog:** `shop.php`, `fetch_products.php`, `filters_meta.php`, `singleproduct.php`, `product-card.php` and `api/product/quick-view.php`; categories, product variations, filtering, search suggestions and quick view are represented in the code.
- **Commerce:** `shopppingcart.php`, `checkout.php`, `order_success.php`, coupon handling and cart endpoints. The spelling of `shopppingcart.php` is part of the current implementation and routes should not be renamed without compatibility planning.
- **Quotations:** `equotation.php`, `quotation-basket.php`, `submit_quote_request.php` and PDF generators; a separate quotation basket is present.
- **Marketing/content:** about, services, projects, blog, showrooms, contact, FAQ, warranty, shipping, privacy and terms pages.
- **Assets and presentation:** `assets/`, root `style.css`, inline styles/scripts and external CDNs. No root frontend build pipeline was found. Mixed CDN versions and large page files increase regression and maintenance risk; responsiveness and accessibility require browser testing.

### Admin/POS interface
`lbpanel/` contains dashboard, product/category/brand/attribute management, users, orders, coupons, content, reviews, projects, settings, reports and quotation requests. `lbpanel/pos/` contains a POS interface and transaction/refund/replacement endpoints. Presence of a screen does not establish that its access control, workflow or calculations work correctly.

## 6. Backend report

- **Routing:** Apache rewrite rules map human-friendly storefront URLs to PHP scripts; `redirect.php` and `url_redirects` support configured redirects. Host canonicalization is specific to the configured production domain and must be reviewed in staging.
- **HTTP endpoints:** `api/cart/{add,count,get,remove,save_totals,update}.php`, equivalent quotation endpoints, quick view, search suggestions and review submission. These are PHP endpoints in the same application, not a separately versioned service.
- **Sessions:** cart, quotation and administrative login use PHP sessions. The helper stores sessions in a project-root directory when writable. This makes web-server protections and exclusion of runtime files from backups/repositories especially important.
- **Login:** `lbpanel/index.php` queries users with a prepared statement, verifies passwords using `password_verify` and stores user ID, name and role in session. Individual endpoints still need their own enforced authorization; a login screen alone does not protect them.
- **Checkout:** `process_checkout.php` inserts orders and items and sends notifications. Its payment method is set to cash on delivery and status to pending; do not infer an online payment gateway from this snapshot.
- **Email and documents:** PHPMailer sends configured SMTP messages; quotation generators attempt Dompdf and include fallback code. Confirm real output quality and dependency availability in staging.
- **POS:** transaction and refund code is present; `lbpanel/pos/api/save_transaction.php` uses a database transaction. POS authentication, price validation, reconciliation and refund controls need a dedicated audit.

## 7. Database report

### Confirmed database artifacts
`database/live_schema_updates.sql` adds columns conditionally to existing tables, creates several newer tables and backfills `product_categories` from the legacy `products.category` column. It assumes an existing selected database. It cannot independently reconstruct the original application database.

| Business domain | Tables observed in SQL or application queries | Evidence / caveat |
|---|---|---|
| Catalog | `products`, `categories`, `product_categories`, `product_variations`, `attributes`, `attribute_values`, `brands` | Product/category join table is created by the update script; other base definitions are not fully supplied. |
| Sales | `orders`, `order_items`, `coupons` | Checkout references these; item `order_id` uses order number in the code. Verify actual foreign keys. |
| Quotations | `quotation_requests`, `quotation_request_items` | Created in the update script; item `request_id` links logically to request ID, but the supplied definition does not declare a foreign key. |
| POS | `pos_transactions`, `pos_transaction_items`, `pos_refunds`, `pos_refund_items` | Referenced in POS code; base schemas not established by the update script. |
| Content and marketing | `blogs`, `projects`, `faqs`, `hero_sliders`, `hero_slides`, `hero_slides_alt`, `customer_reviews`, `reviews` | Some newer tables are created in the update script; others are existing dependencies. |
| Configuration and leads | `users`, `contact_forms`, `subscribers`, `footer_settings`, `footer_links`, `topbar_settings`, `sale_page_settings`, `url_redirects`, `email_settings` | Authentication, content settings, contacts, redirects and SMTP settings. |

**Likely relationships:** products ↔ categories through `product_categories`; products → variations; orders → order items; quotation requests → quotation items; POS transactions → items and refunds. These are based on code/schema usage, not a verified entity-relationship diagram. Inspect the real schema for keys, uniqueness, indexes, collation and delete behavior.

**Recovery requirements:** request a sanitized full schema-only export and migration history; document MySQL/MariaDB version and extensions; set up restricted accounts and encrypted backups; test restore; then compare actual production structure to the update script before running migrations. The update script uses stored procedures, `INFORMATION_SCHEMA`, `DELIMITER` and DDL, so check permissions and client compatibility.

## 8. Security and reliability findings

| Priority | Finding and evidence | Recommended action |
|---|---|---|
| Critical | Database connection files include non-empty hardcoded live credential fallbacks (`connection.php`, `config/connection.php`, `lbpanel/config/connection.php`). The archives are tracked in Git. | Treat credentials as compromised, rotate and audit use; move secrets to a secret manager/environment; remove sensitive archives/history with a coordinated incident process. Do not reproduce values in reports. |
| Critical | Archive includes 216 files in `sessions/`. They are runtime session artifacts and may contain sensitive state. | Invalidate active sessions, inspect exposure under responsible access controls, move session storage outside web root, exclude it from version control/backups intended for distribution, and review retention. |
| Critical | `lbpanel/pos/api/save_transaction.php` and `lbpanel/php/update_order_status.php` do not visibly enforce session authentication or role checks in their own code; nearby `.htaccess` files contain only comments. External protection was not tested. | Restrict access immediately at the server/network layer and add centralized authentication and per-operation authorization to every endpoint; test anonymous and low-privilege access. |
| Critical | `lbpanel/includes/ckeditor/upload.php` accepts an uploaded filename and moves it without an evident login check or file-type validation; it targets a document-root path. Whether that target exists or is executable is unknown. | Disable/deny this legacy endpoint until reviewed; require authorization, allowlist content types, generate filenames, impose limits and store outside executable web roots. |
| High | `process_checkout.php:49–50, 72–96` accepts `total_amount` and `shipping_cost` from POST and stores them in the order. | Recompute item prices, discounts, shipping and totals from trusted server-side data; reject mismatches and validate coupons and quantities. |
| High | `process_checkout.php:99–161` inserts an order and then items without an evident encompassing transaction; item failures are logged while processing continues and the cart is cleared. | Use a database transaction, rollback on any item failure, preserve the cart on error, and add idempotency and reconciliation tests. |
| High | The login script does not visibly regenerate the session ID after login; the reviewed state-changing endpoints do not consistently demonstrate CSRF protection. | Regenerate sessions on authentication, configure secure/HttpOnly/SameSite cookies, add CSRF protections and login throttling, and audit all routes. Do not assume every route lacks these controls. |
| Medium | `email_settings.smtp_password` is a database column; `php.ini` specifies a 512 MB POST limit and a much larger upload limit. | Protect SMTP secrets with restricted access/secret storage; set practical upload limits and validate uploads server-side. Actual effective limits depend on server configuration. |
| Medium | `vendor/autoload.php` is missing despite Dompdf integration; root dependency manifests and lockfiles are absent. | Restore a reproducible Composer installation and lockfile, verify required extensions and fallback PDF behavior; inventory and update third-party libraries. |

These are static findings and risk indications, not proof of exploitation. Admin authorization should be audited comprehensively, including reads, exports, uploads, POS, role enforcement and direct endpoint access. Check for SQL injection, output escaping/XSS, insecure direct object references and log exposure throughout the codebase.

## 9. Deployment and operations checklist

1. **Quarantine and secure:** do not publish the archive or extracted sessions/configuration. Rotate credentials and limit access before further use.
2. **Recover source:** use a RAR 5 multi-volume extractor on all four ordered parts in an isolated directory. The `.zip` suffix is misleading; use appropriate names/tooling. Do not deploy extracted session files, error logs or configuration secrets.
3. **Recover database:** obtain a complete sanitized schema and authorized data; provision a non-production MySQL-compatible instance and least-privilege credentials. Do not assume the included SQL update creates the base schema.
4. **Define runtime:** determine supported PHP, Apache, database and extension versions; `mysqli` is required by the code, while Dompdf's package metadata lists `dom` and `mbstring` among its requirements. Verify SMTP and file permissions.
5. **Dependency management:** rebuild/install Composer dependencies from a reviewed project manifest and lockfile; verify PHPMailer and editor versions, licenses and vulnerability status. Avoid relying on partial `vendor/` contents.
6. **Configure:** use environment-based secrets and an explicit trusted base URL, remove hardcoded fallbacks, set Apache rewrite rules and protect sensitive paths. Ensure staging cannot send real mail or connect to production.
7. **Test:** perform security tests, catalog/cart/coupon/checkout/quote/POS/refund flows, database rollback, PDF and email delivery, accessibility and mobile checks. Add monitoring, logging with sensitive-data redaction, backup/restore and deployment rollback.

## 10. Roadmap and acceptance criteria

| Phase | Work | Acceptance criteria |
|---|---|---|
| P0: contain | Credential rotation, session invalidation, access restrictions, disable unsafe upload endpoints | Old credentials and sessions no longer work; anonymous admin/POS requests are denied; incident owner verifies exposure. |
| P1: correctness | Server-side pricing, atomic checkout, endpoint authorization, CSRF and upload hardening | Automated tests reject tampered totals and unauthorized requests; a failed item insert rolls back the whole order. |
| P2: reproducibility | Full schema/migrations, documented PHP stack, Composer lock, staging and deployment guide | A clean staging environment can be built and restored without production credentials. |
| P3: maintainability | Central configuration/auth/database access, separate presentation from business logic, dependency and frontend cleanup | Critical flows covered by tests; duplicated configuration reduced; dependencies tracked and upgradeable. |
| P4: product quality | Accessibility, performance, SEO, analytics and POS reconciliation review | Measured against agreed baselines and verified with realistic data and users. |

## 11. Outstanding questions

- What are the actual PHP, database and Apache versions, and what external authentication/firewall rules protect the live site?
- Where is the complete database schema and migration history? Are the archived files representative of the current live release?
- Which credentials and sessions were valid when the archives were shared, and who had access?
- Which POS, quotation, shipping, tax and discount rules are authoritative? Are online payments planned or used elsewhere?
- Who owns dependency updates, backup restoration, incident response and acceptance testing?

**Conclusion:** The snapshot describes a feature-rich legacy PHP commerce and administration system. Its business scope is clear enough to plan modernization, but security containment, a complete database and a reproducible staging environment are prerequisites for a reliable deployment or deeper validation.
