# Woodex AOS

The staging application is in the repository root for `staging.blackibex.net`. `public_html/` contains a deployable snapshot of the application only, with `index.php` directly inside it; this snapshot must be refreshed when application files change. See `deployment/HOSTINGER_STAGING.md` for safe deployment and isolated database setup. Hostinger Git deploys the entire repository, not just this subfolder.

Historical archive files were removed from the current tree, but remain in Git history and previously deployed checkouts. They contain sensitive credentials and sessions: rotate credentials, invalidate sessions, and keep Git metadata outside the public document root.

This application has known security and checkout-integrity issues and is not production-ready. Protect the entire staging site and never commit database credentials or the SQL export.
