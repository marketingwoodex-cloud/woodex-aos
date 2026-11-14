# Staging preparation and test results

## Prepared

- Extracted the application into `site/` so that the website files can be published separately from Git metadata and historical archives.
- Removed archived sessions, error logs, old domain validation files, PHP configuration overrides and an unsafe legacy CKEditor upload endpoint from the publishable tree.
- Replaced the three archived database connection files with a shared staging-only bootstrap. It requires private configuration outside the document root, rejects non-staging HTTP hosts, and fails closed when configuration or the database is missing.
- Added direct-entry authentication guards for admin PHP endpoints, secure session cookie settings and login session ID regeneration. These controls are not a substitute for a full security review.
- Added staging no-index signals, upload-directory PHP execution restrictions, disabled email in the shared mail helper and provided private staging SQL/admin preparation and manual publishing tools.
- Preserved archived product/media files and the SQL schema update script; the provided database export and generated sanitized database are not checked in.

## Local tests

- PHP 8.4 syntax-checked 294 PHP files with zero syntax errors. PHP 8.2 compatibility still needs testing.
- Imported the supplied export into a separate local MariaDB 10.8 test database after filtering customer/order/user/SMTP data. Catalog-backed homepage and shop returned HTTP 200; cart add succeeded.
- Admin login, product page and POS product API succeeded with a synthetic admin account. Anonymous dashboard access redirected and anonymous POS API returned 401. Direct session and bootstrap requests returned 403.
- A missing private config fails closed; an HTTP request with a non-staging Host is rejected. The staging mail helper returned disabled.
- Tested the local SQL filtering tool, admin creation tool and file-copy helper with disposable data and a temporary target. The publish helper refused a target inside the repository and did not publish the original archives.

## Not yet verified

- No connection to Hostinger hPanel, DNS, SSL, GitHub OAuth or the staging host was made; no deployment was performed. The hosting plan's document root, PHP version, rewrite rules, filesystem permissions and Git checkout capabilities remain unverified.
- The setup requires a separate staging database and private host configuration. Review the filtered content for private data before import. Establish password protection for the entire site before exposure.
- Checkout totals and atomicity, CSRF, role-based permissions, all upload paths, full POS operations, PDFs, responsive design and email integrations are not fully tested. The site is not production-ready.
- The exact classic hero image `assets/images/heroes/1772573373_Footer.jpg` was absent from the supplied archive. The current active modern hero images are present.
- The historical Git archives still contain secrets and sessions. Rotate exposed credentials, invalidate sessions and follow a coordinated history/distribution cleanup process. Never clone the repository into a public web directory.

See `deployment/HOSTINGER_STAGING.md` for setup and the required manual review steps.
