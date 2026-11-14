# Woodex AOS

The four `public_html.part*.zip` files are historical RAR volumes. They contain sensitive historical configuration and must **not** be deployed to a web-accessible directory.

- `site/` — extracted application prepared for **staging only** at `staging.blackibex.net`.
- `deployment/HOSTINGER_STAGING.md` — Hostinger Git, isolated database, secret configuration and verification instructions.
- `preview/` — local preview setup and media audit.
- `PREVIEW_STATUS_AND_REMAINING_WORK.md` and `PROJECT_MASTER_REPORT.md` — findings and outstanding work.

This repository has known security and checkout-integrity issues and is **not production-ready**. The staging package fails closed without a private configuration file, does not import customer data, and must be protected by a site-wide password on the hosting account. No database credentials or SQL export should be committed.
