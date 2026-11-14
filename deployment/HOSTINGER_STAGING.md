# Hostinger staging deployment: Woodex

**Target:** `https://staging.blackibex.net/` only.  
**Status:** preparation and local smoke tests; no Hostinger deployment or connection has been performed from this environment.

## Critical safety rules

- Do **not** deploy the repository root into any web-accessible directory. The existing repository history and four archive files contain historical credentials and session data. A Git checkout also contains `.git`. Rotate exposed credentials and invalidate old sessions; restricting the new `site/` tree does not remove secrets from old Git history or copies.
- Only publish the contents of `site/` to the **confirmed staging document root**. Do not target the existing production directory or database. Back up the staging target before each copy; the publish script overwrites matching files and does not remove old files.
- Use Hostinger's directory password protection or another verified access control for the **entire staging site** before making it reachable. The included admin checks are defense in depth, not a full security audit. Do not put real customer data in staging.
- Checkout integrity, CSRF, roles, uploads and other legacy security issues remain open. This package is for staging testing, not production approval. Real email is disabled in the shared SMTP helper; test integrations only with dedicated sandbox services.
- Back up the live site and database independently. Do not configure automatic production deployment from the testing branch.

## 1. Prepare the database privately

Create a **new separate** database and database user in Hostinger. Do not reuse the production database or account. The supplied SQL export is sensitive and must not be committed or placed in the web root. On a trusted machine with Python 3.9+ and this repository checked out, generate a separate staging import:

```sh
python3 deployment/create_staging_sql.py /private/path/original-export.sql /private/path/woodex-staging.sql
```

The output is created with mode 0600 and cannot overwrite an existing file or be written within the repository. It includes schema and an allowlist of catalog/content inserts, excludes original users, customer transactions and SMTP settings, and changes the MariaDB-specific `utf8mb4_uca1400_ai_ci` collation to `utf8mb4_unicode_ci` for compatibility. This may change comparison/uniqueness semantics; review any import conflicts. Catalog, blogs and other retained content are **not guaranteed anonymous**: review before importing. The script is designed for the supplied phpMyAdmin dump and rejects certain unsupported SQL statements; it is not a general-purpose anonymizer.

Import the reviewed output into the new staging database through Hostinger's phpMyAdmin or another authorized tool. Verify 41 tables, catalog products/categories and absence of real users and orders. Avoid storing the original or filtered SQL in the Git checkout or document root. Protect and delete temporary exports when no longer needed.

## 2. Git checkout and document root

Hostinger documents GitHub OAuth through **Websites → Dashboard → Advanced → Git**. Configure it for `marketingwoodex-cloud/woodex-aos` and the reviewed branch. Hostinger's Git root directory is the **checkout destination**, not necessarily a way to deploy only a repository subfolder. Never assume that choosing a folder there filters the repository contents.

1. Determine the actual document root for `staging.blackibex.net` in hPanel and take a backup. Verify it is not the live site's root.
2. Configure the Git checkout in a **private directory outside every web document root**, if your Hostinger plan permits it. The checkout contains the historical archive files, which must remain private.
3. Copy only `site/` into the staging document root. From a trusted SSH shell, after verifying the exact path and backup, you may run:

   ```sh
   WOODEX_CONFIRM_STAGING=staging.blackibex.net bash deployment/publish-staging.sh /absolute/path/to/staging/document-root
   ```

   This is a manual copy, not automatic Git deployment. The script rejects a target inside/containing the checkout, a Git target, or a target containing symbolic links, but it **cannot verify that a given path belongs to the staging domain**. It copies and overwrites matching files, leaves stale files and user uploads in place, and does not manage database migrations. Re-run only after review and backup. Manage uploaded media separately so updates and restores do not overwrite or lose it.
4. If hPanel cannot place the checkout outside the document root, or SSH/copy access is unavailable, **stop** and choose a safe deployment method with Hostinger support. Do not point the Git checkout at `public_html` as a workaround.

Hostinger's [Git deployment guide](https://support.hostinger.com/en/articles/1583302-how-to-deploy-a-git-repository-in-hostinger) describes the GitHub connection. Some Hostinger plans do not permit changing the document root; see its [root-directory guidance](https://support.hostinger.com/en/articles/1583494-what-is-the-path-to-your-website-s-root-home-directory-and-how-to-change-it). Actual hPanel features and paths vary by plan and must be confirmed in the account.

## 3. Configure PHP and secrets

- Select a supported PHP 8.2+ runtime with `mysqli`/mysqlnd and `mbstring`; verify DOM, GD, cURL and other extensions for PDF/media features. Enable Apache rewrite and `.htaccess` overrides. Test with the actual Hostinger PHP/database versions.
- `site/config/bootstrap.php` looks for `.woodex-staging.php` **two directory levels above the published document root** by default. For a document root `/example/domain/public_html`, the default is `/example/.woodex-staging.php`. Alternatively set `WDX_CONFIG_FILE` to an absolute private path via server configuration. The file must be outside the web document root; never commit it.
- Copy `deployment/staging-config.example.php` to that private path on the host, fill it with the dedicated staging database details, and restrict file permissions so only the PHP process/account can read it. The application fails closed with HTTP 503 when config or DB is unavailable. Do not send credentials, API tokens or passwords through chat.
- Confirm the PHP session save path is writable and outside web-accessible directories. Session cookies require HTTPS. Turn off `display_errors` on the host and log errors privately. `robots.txt` and the `X-Robots-Tag` header request no indexing; neither is access control.
- Create a new staging administrator only **after** importing the filtered database. From the private checkout on the host, explicitly point the CLI at the same private config used by the published site:

  ```sh
  WDX_CONFIG_FILE=/absolute/private/path/.woodex-staging.php php deployment/create-staging-admin.php
  ```

  The random password is printed once in your trusted terminal; store it securely, change it after login and do not share it in chat. The script refuses to overwrite an existing `stagingadmin` account. The staging guard currently permits only `admin` role accounts to access admin scripts.

## 4. Verify the protected staging site

After configuring password protection and HTTPS, check homepage, hero/category/product images, shop search/filter, product variations, cart and a synthetic checkout. Verify admin login, product/category views, orders, quotation basket, POS screens, uploads and reports using **synthetic** data. Confirm anonymous access to admin and POS endpoints is denied, session and configuration files are not accessible, and no real email is sent. Check that links and writes do not go to production. Test backups/restore and inspect logs. The classic hero reference `assets/images/heroes/1772573373_Footer.jpg` is missing from the supplied archive.

The archive lacks `vendor/autoload.php`; PDF fallback or installed Composer dependencies and the generated documents must be tested explicitly. The local CLI smoke tests do not validate Hostinger's Apache rewrites, permissions, email, HTTPS, full UI, or PHP 8.2 compatibility.

## 5. Main branch and release

This session can only change its current Arena branch. After code review, push this branch and open a pull request to `main`; a repository maintainer must review and merge it. Connect Hostinger to the reviewed branch or update hPanel after the merge. **Do not merge or deploy to production solely because a smoke test succeeds.**
