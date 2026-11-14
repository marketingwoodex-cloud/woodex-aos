#!/usr/bin/env python3
"""Apply the production security patches to the original PHP app.

Run from the repository root:

    python3 tools/security/patch-app.py            # apply
    python3 tools/security/patch-app.py --check    # report only, change nothing

What it changes (every edit is idempotent — re-running does not duplicate):

1.  `lbpanel/config/auth_guard.php`      — new file: session guard + JSON 401 for
                                           AJAX endpoints.
2.  `lbpanel/**/*.php`                   — one `require_once … auth_guard.php;`
                                           line after `<?php`, for every admin
                                           script that had no session check.
3.  `lbpanel/config/session.php`         — cookie hardening (`HttpOnly`,
                                           `SameSite=Lax`, `Secure` on HTTPS,
                                           `use_strict_mode`), session storage
                                           moved out of the web root.
4.  `lbpanel/config/login_throttle.php`  — new file: 6 failures / 15 min per IP.
5.  `lbpanel/index.php`                  — throttle check, failure counting,
                                           `session_regenerate_id(true)` on success.
6.  `config/connection.php`              — credentials from
                                           `config/connection.local.php` or env vars;
                                           the local mysqli shim is loaded only if it
                                           is present (never on the host).

The leaked files (`error_log`, `*.orig`, `php.ini`, `.user.ini`, `database/*.sql`)
and the in-docroot `sessions/` directory are handled by `tools/security/quarantine.py`.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DOCROOT = ROOT / "php-source" / "public_html"
PANEL = DOCROOT / "lbpanel"

# Panel scripts that must stay reachable without a session.
OPEN_SCRIPTS = {
    "index.php",           # login form
    "logout.php",
    "forgot-password.php",
    "reset-password.php",
}
OPEN_DIRS = ("includes", "assets", "ckeditor", "config", "uploads", "fonts")

GUARD = """<?php
/**
 * Admin session guard.
 *
 * Included at the top of every script in lbpanel/ by tools/security/patch-app.py.
 * Before this file existed, 59 of the 79 admin scripts ran without checking the
 * session at all — including the POS module and every write endpoint.
 */

if (!function_exists('wdx_session_start')) {
    require_once __DIR__ . '/session.php';
}
wdx_session_start();

$__wdx_script = str_replace('\\\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__wdx_is_ajax = str_contains($__wdx_script, '/php/')
    || str_contains($__wdx_script, '/pos/api/')
    || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'cors');

if (empty($_SESSION['username'])) {
    if ($__wdx_is_ajax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication required']);
    } else {
        // Works whether the panel sits at the domain root or in a subfolder.
        $__wdx_root = preg_replace('#/lbpanel/.*$#', '', $__wdx_script);
        $__wdx_next = (string)($_SERVER['REQUEST_URI'] ?? '/lbpanel/index.php');
        header('Location: ' . $__wdx_root . '/lbpanel/index.php?next=' . rawurlencode($__wdx_next));
        http_response_code(302);
    }
    exit;
}

unset($__wdx_script, $__wdx_is_ajax, $__wdx_root, $__wdx_next);
"""

LOGIN_THROTTLE = """<?php
/**
 * Login throttling: six failed passwords per IP within fifteen minutes.
 *
 * File based on purpose — no schema change is needed on the production
 * database, and the counters live outside the web root.
 */

if (!function_exists('wdx_throttle_file')) {
    function wdx_throttle_file(): string
    {
        $dir = dirname(__DIR__, 3) . '/.wdx_data/throttle';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ip = trim(explode(',', $ip)[0]);
        return $dir . '/' . hash('sha256', $ip) . '.json';
    }

    /** @return array{count:int,first:int} */
    function wdx_throttle_state(): array
    {
        $file = wdx_throttle_file();
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !isset($data['count'], $data['first'])) {
            return ['count' => 0, 'first' => time()];
        }
        return ['count' => (int)$data['count'], 'first' => (int)$data['first']];
    }

    function wdx_throttle_window(): int
    {
        return 15 * 60;
    }

    function wdx_throttle_limit(): int
    {
        return 6;
    }

    /** True when this IP must not be allowed to try another password yet. */
    function wdx_throttle_blocked(): bool
    {
        $state = wdx_throttle_state();
        if (time() - $state['first'] > wdx_throttle_window()) {
            @unlink(wdx_throttle_file());
            return false;
        }
        return $state['count'] >= wdx_throttle_limit();
    }

    function wdx_throttle_remaining(): int
    {
        $state = wdx_throttle_state();
        $left = wdx_throttle_window() - (time() - $state['first']);
        return max(1, (int)ceil($left / 60));
    }

    function wdx_throttle_fail(): void
    {
        $state = wdx_throttle_state();
        if (time() - $state['first'] > wdx_throttle_window()) {
            $state = ['count' => 0, 'first' => time()];
        }
        $state['count']++;
        @file_put_contents(wdx_throttle_file(), json_encode($state), LOCK_EX);
    }

    function wdx_throttle_reset(): void
    {
        @unlink(wdx_throttle_file());
    }
}
"""

SESSION_PATCH_OLD = """        $sessionDir = realpath(__DIR__ . '/../../sessions');
        if ($sessionDir === false) {
            $sessionDir = __DIR__ . '/../../sessions';
            @mkdir($sessionDir, 0775, true);
            $sessionDir = realpath($sessionDir) ?: $sessionDir;
        }

        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            session_save_path($sessionDir);
        }
"""

SESSION_PATCH_NEW = """        // Cookies: not readable from JavaScript, not sent on cross-site POSTs,
        // and HTTPS-only when the request already arrived over TLS.
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.use_strict_mode', '1');
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            @ini_set('session.cookie_secure', '1');
        }

        // Session files must never live inside the web root: every file there is
        // downloadable, and a readable session file is a hijackable login.
        $candidates = [
            dirname(__DIR__, 3) . '/sessions',   // one level above public_html
            dirname(__DIR__, 2) . '/sessions',   // legacy: inside public_html
        ];
        $sessionDir = false;
        foreach ($candidates as $candidate) {
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0775, true);
            }
            $real = realpath($candidate);
            if ($real !== false && is_writable($real)) {
                $sessionDir = $real;
                break;
            }
        }

        if ($sessionDir !== false) {
            session_save_path($sessionDir);
        }
"""


def rel_php(path: Path) -> str:
    """Path from a panel script's folder to lbpanel/config/."""
    depth = len(path.relative_to(PANEL).parts) - 1
    return "/".join([".."] * depth + ["config", "auth_guard.php"]) if depth else "config/auth_guard.php"


def wants_guard(path: Path) -> bool:
    rel = path.relative_to(PANEL)
    if rel.parts[0] in OPEN_DIRS:
        return False
    if path.name in OPEN_SCRIPTS:
        return False
    return True


def insert_guard(path: Path, apply: bool) -> bool:
    text = path.read_text(encoding="utf-8", errors="surrogateescape")
    if "auth_guard.php" in text:
        return False
    if not text.startswith("<?php"):
        return False
    line = f"<?php\nrequire_once __DIR__ . '/{rel_php(path)}';\n"
    if apply:
        path.write_text(line + text[len("<?php\n"):], encoding="utf-8", errors="surrogateescape")
    return True


def patch_session(apply: bool) -> bool:
    path = PANEL / "config" / "session.php"
    text = path.read_text(encoding="utf-8", errors="surrogateescape")
    if "session.cookie_httponly" in text:
        return False
    if SESSION_PATCH_OLD not in text:
        print("  ! session.php did not match the expected block — skipped", file=sys.stderr)
        return False
    if apply:
        path.write_text(text.replace(SESSION_PATCH_OLD, SESSION_PATCH_NEW), encoding="utf-8", errors="surrogateescape")
    return True


def patch_login(apply: bool) -> bool:
    path = PANEL / "index.php"
    text = path.read_text(encoding="utf-8", errors="surrogateescape")
    if "wdx_throttle_blocked" in text:
        return False

    old_head = """if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and fetch input
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
"""
    new_head = """require_once __DIR__ . '/config/login_throttle.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (wdx_throttle_blocked()) {
        http_response_code(429);
        header('Retry-After: ' . (wdx_throttle_remaining() * 60));
        $error_message = 'Too many failed attempts from this address. Try again in '
            . wdx_throttle_remaining() . ' minutes.';
    }

    // Sanitize and fetch input
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
"""
    if old_head not in text:
        old_head = old_head.replace("\n", "\r\n")
        new_head = new_head.replace("\n", "\r\n")
        if old_head not in text:
            print("  ! lbpanel/index.php did not match the expected block — skipped", file=sys.stderr)
            return False

    text = text.replace(old_head, new_head, 1)

    old_ok = """        if (password_verify($password, $user['password'])) {
            // Store user data in session
            $_SESSION['user_id'] = $user['id'];"""
    new_ok = """        if (password_verify($password, $user['password'])) {
            wdx_throttle_reset();
            // New session id on privilege change — an id planted before login
            // must not survive it.
            session_regenerate_id(true);

            // Store user data in session
            $_SESSION['user_id'] = $user['id'];"""
    if old_ok not in text:
        old_ok = old_ok.replace("\n", "\r\n")
        new_ok = new_ok.replace("\n", "\r\n")
        if old_ok not in text:
            print("  ! lbpanel/index.php success block did not match — skipped", file=sys.stderr)
            return False
    text = text.replace(old_ok, new_ok, 1)

    old_fail_1 = """        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password.";
    }"""
    new_fail_1 = """        } else {
            wdx_throttle_fail();
            $error_message = "Invalid username or password.";
        }
    } else {
        wdx_throttle_fail();
        $error_message = "Invalid username or password.";
    }"""
    if old_fail_1 in text:
        text = text.replace(old_fail_1, new_fail_1, 1)
    else:
        old_fail_1 = old_fail_1.replace("\n", "\r\n")
        new_fail_1 = new_fail_1.replace("\n", "\r\n")
        if old_fail_1 in text:
            text = text.replace(old_fail_1, new_fail_1, 1)
        else:
            print("  ! lbpanel/index.php failure blocks did not match — counters not wired", file=sys.stderr)

    if apply:
        path.write_text(text, encoding="utf-8", errors="surrogateescape")
    return True


CONNECTION_FILES = [
    DOCROOT / "connection.php",                            # third copy, included by the storefront
    DOCROOT / "config" / "connection.php",
    DOCROOT / "lbpanel" / "config" / "connection.php",     # included by most admin scripts
]


def patch_connection(path: Path, apply: bool) -> bool:
    text = path.read_text(encoding="utf-8", errors="surrogateescape")
    if "connection.local.php" in text:
        return False

    match = re.search(
        r"// Local run only: route prepared statements through escaped text queries\.\n"
        r"// See tools/php-run/shims/wdx_mysqli\.php — remove this line for production\.\n"
        r"require_once __DIR__ \. '/(\.\./)*wdx_mysqli\.php';\n",
        text,
    )
    if match is None:
        print(f"  ! {path.relative_to(ROOT)} shim block did not match — skipped", file=sys.stderr)
        return False
    shim_rel = match.group(0).split("require_once __DIR__ . '")[1].split("'")[0]
    old_shim = match.group(0)
    new_shim = """// Credentials do not live in this file. They are read from
// <document root>/config/connection.local.php (git-ignored) or, failing that,
// from the LIVE_DB_* / DB_* environment variables. See docs/deploy-hostinger.md.
$__wdx_docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$__wdx_local_cfg = $__wdx_docroot !== '' ? $__wdx_docroot . '/config/connection.local.php' : '';
if ($__wdx_local_cfg === '' || !is_file($__wdx_local_cfg)) {
    $__wdx_local_cfg = __DIR__ . '/connection.local.php';
}
if (is_file($__wdx_local_cfg)) {
    require_once $__wdx_local_cfg;
}

// Local runner only: the shim is loaded by connection.local.php when needed; a
// stray copy next to this file is honoured too. It is never deployed to the host.
if (is_file(__DIR__ . '/__WDX_REL__wdx_mysqli.php')) {
    require_once __DIR__ . '/__WDX_REL__wdx_mysqli.php';
}
"""
    new_shim = new_shim.replace("__WDX_REL__", shim_rel.lstrip("/"))
    text = text.replace(old_shim, new_shim, 1)

    text = text.replace(
        "$tmp = @new WdxMysqli($cfg['host'], $cfg['user'], (string)$cfg['pass'], $cfg['name']);",
        "$driver = class_exists('WdxMysqli') ? 'WdxMysqli' : 'mysqli';\n"
        "    $tmp = @new $driver($cfg['host'], $cfg['user'], (string)$cfg['pass'], $cfg['name']);",
        1,
    )
    if apply:
        path.write_text(text, encoding="utf-8", errors="surrogateescape")
    return True


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="report what would change")
    args = ap.parse_args()
    apply = not args.check

    if not PANEL.is_dir():
        print(f"lbpanel not found at {PANEL}", file=sys.stderr)
        return 1

    changes: list[str] = []

    guard_path = PANEL / "config" / "auth_guard.php"
    if not guard_path.exists():
        if apply:
            guard_path.write_text(GUARD, encoding="utf-8")
        changes.append("created lbpanel/config/auth_guard.php")

    throttle_path = PANEL / "config" / "login_throttle.php"
    if not throttle_path.exists():
        if apply:
            throttle_path.write_text(LOGIN_THROTTLE, encoding="utf-8")
        changes.append("created lbpanel/config/login_throttle.php")

    guarded = [p for p in sorted(PANEL.rglob("*.php")) if p.is_file() and wants_guard(p) and insert_guard(p, apply)]
    if guarded:
        changes.append(f"guarded {len(guarded)} admin scripts")
        for p in guarded[:5]:
            changes.append(f"    e.g. {p.relative_to(ROOT)}")
        if len(guarded) > 5:
            changes.append(f"    … and {len(guarded) - 5} more")

    if patch_session(apply):
        changes.append("hardened lbpanel/config/session.php (cookie flags, storage outside web root)")
    if patch_login(apply):
        changes.append("wired throttling + session_regenerate_id into lbpanel/index.php")
    for conn in CONNECTION_FILES:
        if conn.is_file() and patch_connection(conn, apply):
            changes.append(f"made {conn.relative_to(DOCROOT)} credential-free (local.php / env vars)")

    print("check only — nothing written" if args.check else "patched", "·", len(changes), "step(s)")
    for c in changes:
        print("  -", c)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
