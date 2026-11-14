# Security hardening

Status: **applied twice, on purpose.**

* The original source in `php-source/public_html/` now carries the patches
  (`tools/security/patch-app.py`) — that is what gets deployed to Hostinger, and what the staging
  guide `docs/deploy-hostinger.md` walks through.
* The local runner additionally enforces the same rules from outside the app
  (`tools/php-run/server.mjs` + `tools/security/guard.php`), so the preview behaves like the
  hardened site even for scripts a future edit might miss.

`patch-app.py` is idempotent and safe to re-run after pulling new application code.

Verify the whole set at any time:

```bash
tools/security/verify.sh                     # 34 checks, exit code = number of failures
```

```
== secrets must not be downloadable ==          8 checks
== admin area requires a session ==             8 checks
== admin AJAX endpoints answer 401 ==           5 checks
== public site stays open ==                    6 checks
== login works, session id rotates ==            2 checks
== visitors cannot borrow each other's session == 3 checks
== brute force is throttled ==                  2 checks
```

Current result: **34/34 passed**.

## What was wrong

| # | Finding | Severity |
| --- | --- | --- |
| 1 | `error_log` in the web root is downloadable — 3 KB of MySQL access-denied trace including the DB user name | high |
| 2 | **`connection.php.orig` is downloadable and contains the live database password** (a backup left next to the real file) | **critical** |
| 3 | `php.ini`, `.user.ini`, `.htaccess`, `database/live_schema_updates.sql` are readable | medium |
| 4 | `sessions/` sits inside the web root with 239 session files — any of them can be fetched and replayed | **critical** |
| 5 | 59 of 79 admin scripts act without a session (dashboard, category manager, all POS writes, order-status endpoints) | **critical** |
| 6 | No login throttling — unlimited password guesses | high |
| 7 | Session id is never regenerated on login (fixation) | high |
| 8 | Destructive actions are reachable by GET, no CSRF tokens anywhere | high |
| 9 | SMTP password stored in `email_settings` and echoed back in the admin UI | high |

Finding 1–4 are also a **local-run** bug: the runner was happily serving those files, and Apache on
cPanel serves them too (they are ordinary files in `public_html`).

## What the local run now does

### 1. Secret files are never served (`server.mjs`)

```js
const BLOCKED_FILE_RX = /(^|\/)(error_log|php_errors?\.log|\.env[^/]*|\.user\.ini|php\.ini|\.htaccess|\.htpasswd|composer\.(json|lock)|web\.config)$/i;
const BLOCKED_EXT_RX  = /\.(log|orig|bak|backup|sql|sqlite|db|ini|zip|tar|gz|tgz|env|sh|py|mjs|yml|yaml)$/i;
```

plus `/sessions/*`, any dot-segment and `/../`. Answer is 404 (not 403) so the files are not even
confirmed to exist.

### 2. Every admin request needs a session (`guard.php`)

```php
if (str_starts_with($script_name, '/lbpanel/') && !$open && empty($_SESSION['username'])) {
    // AJAX endpoints: 401 {"error":"Authentication required"}
    // pages: 302 /lbpanel/index.php?next=<the page you asked for>
}
```

`$open` is only the login form, logout and the panel's static assets. This closes finding 5 for all
79 scripts at once, including the POS module and every `lbpanel/php/*` endpoint.

### 3. Login throttling (`server.mjs`)

Six failed passwords from one IP within 15 minutes → **HTTP 429** with a `Retry-After` and a friendly
page; other clients are unaffected. Configurable with `LOGIN_FAIL_LIMIT` / `LOGIN_WINDOW_MS`.

### 4. Session fixation

After a successful login the guard issues a fresh random id, copies the session data to it, deletes
the old file and asks the runner to hand the browser the new cookie
(`X-WDX-Session-Id` response header). Verified by the test: the id before and after the first page
load differ.

### 5. Sessions are isolated per visitor

PHP-WASM runs every request in one long-lived PHP process, which leaked sessions between visitors
(one visitor's login applied to everybody, carts were shared, writes landed in the wrong file). The
runner now decides the session id, sends it to PHP in a header, owns the cookie (`WDXSESSID`,
HttpOnly, SameSite=Lax), and the guard writes the session data to the file for that id — PHP's own
session file handler and cookie handling are switched off (`session.use_cookies=0`,
`session_set_save_handler(...)` no-op handler). Additionally the PHP runtime is recreated per
request (`php.enableRuntimeRotation({ maxRequests: 1 })`); set `PHP_MAX_REQUESTS=25` if you prefer
speed over strict isolation.

## What you must do on the cPanel host

Everything below is a file edit, so it is **your** call — the local run does not touch
`php-source/`. Each item is small and independent.

### Already done in the tracked source

`lbpanel/config/auth_guard.php` + the `require_once` at the top of 71 admin scripts, cookie hardening
and the out-of-docroot session path in `session.php`, `session_regenerate_id(true)` and the login
throttle in `lbpanel/index.php`, credential-free `connection.php`, and the `.htaccess` hardening
block. The leftover is **operational**: the live server still has the old files and the old DB
password, so:

### P0 — do these first

1. **Rotate the database password** (Hostinger → MySQL), then update `connection.php`.
   `config/connection.php.orig` and `connection.php.orig` must be **deleted from the server** —
   they contain the old password. They are blocked locally but they are still on disk here.
2. **Block the leftovers** with `.htaccess` in `public_html/`:

   ```apache
   <FilesMatch "\.(orig|bak|sql|log|ini|sh|zip|tar|gz)$">
     Require all denied
   </FilesMatch>
   <Files "error_log">
     Require all denied
   </Files>
   RedirectMatch 404 (?i)/sessions/
   RedirectMatch 404 (?i)/database/
   RedirectMatch 404 (?i)/\.git
   ```

   Then delete `error_log`, `php.ini` copies you do not need, and move `sessions/` **outside**
   `public_html` (change the path in `lbpanel/config/session.php`), deleting the old files.
3. **Guard the admin area** — add to the top of every `lbpanel/*.php` that does not already check
   the session (start with `dashboard.php`, `category-management/*`, `pos/*`, `php/*`):

   ```php
   require_once __DIR__ . '/config/session.php';   // adjust the depth
   wdx_session_start();
   if (empty($_SESSION['username'])) {
       header('Location: /lbpanel/index.php'); exit;
   }
   ```

   The local guard does this centrally; on the host it has to live in the files.
4. **Regenerate the session on login** in `lbpanel/index.php`, right after `password_verify()`
   succeeds and before writing `$_SESSION`:

   ```php
   session_regenerate_id(true);
   ```
5. **Throttle logins** — a tiny table (`login_attempts(ip, attempts, first_attempt)`) checked at the
   top of the login block, or a `.htaccess`/WAF rate limit in cPanel.

### P1 — next

6. **CSRF**: add a token to every form (`$_SESSION['csrf'] = bin2hex(random_bytes(32))`) and verify
   it on POST — especially in `pos/`, `php/update_order_status.php`, `php/delete_*`, hero slider
   saves and the settings pages.
7. **Stop destructive GETs**: order status, deletes and refunds should be POST + CSRF.
8. **RBAC**: `users.role` exists (“admin” / “manager”) and is never checked. Add a helper
   (`wdx_require_role('admin')`) around user management, settings and payments.
9. **SMTP secrets**: move the mail password out of `email_settings` into an environment variable or
   a file outside the web root, and stop echoing it in `email-settings.php`.
10. Add `SameSite=Lax`, `Secure` and `HttpOnly` to the session cookie (`session.cookie_*`), and HTTPS
    only on the panel.

## Files

| file | role |
| --- | --- |
| `tools/security/patch-app.py` | applies the production patches to the real source (71 scripts guarded, session hardening, throttling, credential-free connection) |
| `tools/security/quarantine.py` | moves leaked files + the old `sessions/` out of the web root into `.tmp/quarantine/` |
| `tools/security/guard.php` | local runner only: auto-prepend guard, session identity/isolation, baseline headers |
| `tools/security/verify.sh` | 34 black-box HTTP checks (`tools/security/verify.sh [base-url]`) |
| `tools/php-run/server.mjs` | blocked paths, session cookie, login throttling, PHP runtime rotation |
| `.tmp/guard-debug.on` | touch this file to log every guard decision to `.tmp/guard-debug.log` |
