# Deploying to Hostinger (staging first)

The site that ships is **`php-source/public_html/`** from the `main` branch — the original application,
with the P0 security patches already applied to the source (not just to the local runner).

What is *in* the repo and what is *not*:

| in git | not in git (by design) |
| --- | --- |
| every `.php`, `.css`, `.js`, `.htaccess`, `assets/`, `lbpanel/uploads/` | `config/connection.local.php` — the DB credentials |
| the security patches (`lbpanel/config/auth_guard.php`, `login_throttle.php`, patched `session.php`, `index.php`, both `connection.php`) | `sessions/`, `.wdx_data/`, `wdx_mysqli.php` (local shim), `error_log`, `*.orig`, `*.sql` |

Current bundle: 1 187 files, 111 MB (≈ 94 MB zipped).

---

## 1. Create the staging site (hPanel)

1. **hPanel → Websites → Subdomains** → create `staging.woodexfurniture.pk` (or any name).
   Leave “custom folder” default: the document root becomes
   `~/domains/staging.woodexfurniture.pk/public_html`.
2. **hPanel → Advanced → PHP Configuration** → PHP **8.2** or 8.3, extensions `mysqli`, `mbstring`,
   `openssl`, `gd` enabled (they are by default on Hostinger).
3. Leave the production site alone until the staging checklist at the end of this file passes.

## 2. Put the code in the document root

Pick one path. The **git route** keeps staging in sync with `main`; the **zip route** needs no SSH.

### A — git deploy (recommended, Business/Cloud plans with SSH)

hPanel → Advanced → **GIT** (or SSH):

```bash
cd ~ && mkdir -p repos && cd repos
git clone https://github.com/marketingwoodex-cloud/woodex-aos.git
cd woodex-aos && git checkout main
```

Then publish the app folder into the staging document root and keep it in sync:

```bash
rsync -a --delete ~/repos/woodex-aos/php-source/public_html/ \
      ~/domains/staging.woodexfurniture.pk/public_html/
```

Every later deploy is `git pull && rsync …` (add `--exclude config/connection.local.php` **only** if you
keep that file inside the docroot — see step 3; by default it is not part of the tree, so plain rsync
is safe).

> Hostinger's “Git deploy” button clones the whole repository, so do **not** point it at `public_html`
> directly — the repo root also holds `tools/` and `docs/`. Clone to `~/repos/` and rsync the app
> folder, as above.

### B — zip upload (no SSH)

```bash
tools/deploy/make-bundle.sh          # → .tmp/public_html.zip (+ manifest)
```

hPanel → Files → **File Manager** → open the staging document root → **Upload** the zip → right-click →
**Extract** → delete the zip. `.tmp/public_html.zip` is git-ignored; it is only an upload vehicle.

## 3. Database credentials (the site will not start without this)

Staging points at the **existing production database**, so nothing needs importing. Create the
credential file *on the server* — it is deliberately not in git:

`~/…/public_html/config/connection.local.php`

```php
<?php
define('LIVE_DB_HOST', 'localhost');
define('LIVE_DB_USER', 'u123456_woodex');      // your Hostinger DB user
define('LIVE_DB_PASSWORD', '••••••••');        // hPanel → Databases → Manage
define('LIVE_DB_NAME', 'u123456_woodex');
```

Then:

```bash
chmod 600 config/connection.local.php
```

`lbpanel/config/connection.php` reads the same file (they share `config/connection.local.php` only for
the app root copy — if you prefer, place a second copy at `lbpanel/config/connection.local.php` with
the same four `define()` lines; the admin panel includes that path).

Sanity check: `php -r 'var_dump(function_exists("mysqli_connect"));'` → `true`.

### ⚠️ Staging writes to live data

Pointing staging at the production database means orders, POS transactions, refunds and product edits
made on staging are **real**. Two ways to make that safe:

* give staging a **read-only** user (recommended until the checkout/POS flows are verified):

  ```sql
  CREATE USER 'u123456_stg'@'localhost' IDENTIFIED BY '…';
  GRANT SELECT ON `u123456_woodex`.* TO 'u123456_stg'@'localhost';
  ```

  Login, browsing and reporting work; save actions fail loudly. Swap in the read-write user when you
  move to the production cutover.
* or take a fresh dump and import it into a separate `u123456_woodex_staging` database, which is the
  cleaner option if you plan to keep staging around.

## 4. Sessions and writable paths

`lbpanel/config/session.php` puts session files in the first writable directory of:

1. `dirname(public_html, 1)` → `~/domains/staging.woodexfurniture.pk/sessions`
2. `public_html/sessions` (legacy fallback)

Make sure the first one exists and is writable (PHP-FPM usually creates it; if not):

```bash
mkdir -p ~/domains/staging.woodexfurniture.pk/sessions && chmod 775 ~/domains/staging.woodexfurniture.pk/sessions
```

Session files must never be inside the document root again — that was one of the P0 findings.
`tools/security/quarantine.py` removed the 239 files that used to sit there.

## 5. Verify the deployment

From this repository (both scripts take a base URL):

```bash
tools/security/verify.sh https://staging.woodexfurniture.pk     # 34 checks, expect 34/34
tools/php-run/smoke.sh   https://staging.woodexfurniture.pk     # 26 pages, expect 26/26
```

Manual checks that matter most:

```bash
# leaked files must be 404 (not 403 — 403 confirms they exist)
for p in /connection.php.orig /config/connection.php.orig /error_log /php.ini \
         /sessions/ /database/live_schema_updates.sql /wdx_mysqli.php; do
  printf '%-38s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code}' https://staging.woodexfurniture.pk$p)"
done

# admin area: logged out → redirect, AJAX → 401 JSON, logged in → 200
curl -s -o /dev/null -w '%{http_code}\n' https://staging.woodexfurniture.pk/lbpanel/manage-orders.php
curl -s https://staging.woodexfurniture.pk/lbpanel/pos/api/get_products.php
```

Then in a browser: log in at `/lbpanel/`, open Dashboard, Orders, Products, POS, and one storefront
checkout. The session id must change at login (the URL never shows it, but the cookie does) and the
panel must keep working across pages.

> On the host, the guard is the application's own `lbpanel/config/auth_guard.php` — the runner's
> `tools/security/guard.php` is local-only. Both implement the same rules (session required, AJAX 401,
> `?next=` redirect), and `verify.sh` proves the behaviour end-to-end.

## 6. Cutover to production (when staging passes)

1. **Rotate the DB password** in hPanel → Databases, update `connection.local.php` on production,
   then delete the old `connection.php.orig`, `error_log`, `php.ini`, `.user.ini`,
   `database/*.sql` and the `sessions/` folder from production `public_html` (the bundle no longer
   contains them, but the live server still does).
2. `rsync -a --delete --exclude config/connection.local.php` the app over the production document
   root — the first deploy that includes `lbpanel/config/auth_guard.php` will start redirecting
   logged-out admin requests.
3. Re-run `tools/security/verify.sh https://woodexfurniture.pk` and spot-check checkout + POS.
4. Keep the previous `public_html` in a tarball for rollback (`tar czf ~/backup-$(date +%F).tgz public_html`
   before step 2).

## Rollback

```bash
# staging: put the previous tree back
rsync -a --delete ~/backups/public_html-YYYY-MM-DD/ ~/domains/staging.woodexfurniture.pk/public_html/
```

Nothing in the security patches changes data, so a rollback is a file copy — no database migration
was introduced (the login throttle is file-based, the session path is a directory).
