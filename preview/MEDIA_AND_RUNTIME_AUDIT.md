# Preview media and runtime audit

## Scope

Reviewed the extracted four-part archive, the provided SQL export after excluding private records, the current isolated preview, and the original site's publicly accessible homepage. The preview is not a production deployment and is not a byte-for-byte copy of the live database. This audit is a targeted inventory and functional smoke test, not an exhaustive visual, accessibility or security audit.

## Media findings

- The extracted archive contains **468 files in `assets/`** (about 87 MB), plus about 7.7 MB under `lbpanel/uploads/`. These assets are included in the temporary extracted application, not committed a second time.
- The local database contains **171 products**. All 172 image references extracted from product image arrays were found in the archive. Category main/background references and project cover/gallery references checked were also present.
- Five product-variation records referenced images under `https://localhost/woodex/assets/...`. The actual files exist in the archive, but a browser outside the server cannot use that hostname. The isolated preview database now uses relative `assets/...` paths. `prepare.py` also adds a preview-only SQL update for future imports. The original SQL attachment and production database were not modified.
- A broader scan of media paths in text fields found 215 unique present image paths and **one unresolved path**: `assets/images/heroes/1772573373_Footer.jpg`, referenced by the classic hero slides table. That file is not in the supplied archive. The configured active hero design in this dump is `modern`, whose four referenced backgrounds are present. To restore the classic slide exactly, supply the missing original file; no substitute has been passed off as the original.
- HTTP checks of rendered local image references on the home, shop, category, product, projects and quotation pages found no missing local images in the checked markup. This does not cover every dynamic state, browser, CSS file or possible catalog combination. Some external/CDN resources remain outside this local inventory.
- The live homepage was consulted for comparison, but the live website may have changed since the supplied archive and database were produced. Full visual equivalence has not been verified.

## Runtime findings and changes

- The earlier WebAssembly runtime could read data but failed on MySQL write responses. The current running preview instead uses native PHP 8.4 and an isolated MariaDB 10.8 database. The preview is separate from production; SMTP settings and private customers, orders, original admin users and related transactional records were excluded.
- Rewrote the three extracted connection files to connect only to the sandbox database. The PHP router applies friendly routes, denies direct access to sensitive files and requires the demo admin session for admin PHP endpoints. Public preview authentication was removed at the user's request; lbpanel authentication remains in place.
- Enabled writes on the isolated preview. Smoke tests successfully added a product to the session cart, inserted a test subscriber, and created a test order with an order item. The test subscriber and order were then removed. Admin login and representative dashboard, product, category, order, content, settings and POS pages returned HTTP 200 with an authenticated session.
- The setup script, Apache configuration and Docker Compose workflow are retained for a separate local installation. The currently running native preview depends on temporary tools under `/tmp`; it is not a tested, persistent deployment workflow. The router filename retains its historical `wasm-router.php` name even though the active runtime is native PHP.

## Remaining limitations

- Checkout trusts client-submitted totals and does not atomically save all order items; these are existing application defects, not validated payment processing. The preview database is disposable; do not use it for real customers, orders or payments.
- Legacy admin authorization, CSRF defenses, upload security, payment/POS operations and all business flows need a full security and integration review. The router is containment for this preview, not production hardening.
- No real email delivery is enabled; checkout email and external integrations have not been verified. The available native runtime lacks the GD extension; image-processing features requiring GD have not been validated. Dompdf's Composer autoloader is missing from the archive, so PDF generation may use a fallback.
- Only the available archived files and supplied SQL were checked; the single missing image cannot be reconstructed exactly without a source copy. The database import is intentionally filtered for privacy and does not reproduce production transactions or customer data.
