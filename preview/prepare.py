#!/usr/bin/env python3
"""Build a private, disposable local preview from the provided snapshot."""
import argparse
import base64
import hashlib
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
RUNTIME = HERE / 'runtime'
PUBLIC_TABLES = {
    'attributes', 'attribute_values', 'blogs', 'brands', 'categories', 'faqs',
    'footer_links', 'footer_settings', 'hero_sliders', 'hero_slides', 'hero_slides_alt',
    'products', 'product_attributes', 'product_attribute_terms', 'product_categories',
    'product_variations', 'projects', 'sale_page_settings', 'topbar_settings', 'url_redirects',
}

def filtered_sql(source):
    # phpMyAdmin emits one INSERT per statement. Scan quoted strings so that a
    # semicolon inside product HTML cannot accidentally expose a later record.
    pattern = re.compile(r'(?im)^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?')
    output = []
    pos = 0
    while match := pattern.search(source, pos):
        output.append(source[pos:match.start()])
        quote = None
        escaped = False
        i = match.end()
        while i < len(source):
            ch = source[i]
            if escaped:
                escaped = False
            elif quote and ch == '\\':
                escaped = True
            elif quote and ch == quote:
                if i + 1 < len(source) and source[i + 1] == quote:
                    i += 1
                else:
                    quote = None
            elif not quote and ch in "'\"`":
                quote = ch
            elif not quote and ch == ';':
                break
            i += 1
        if i == len(source):
            raise ValueError('Unterminated INSERT statement in SQL dump')
        if match.group(1).lower() in PUBLIC_TABLES:
            output.append(source[match.start():i + 1])
        else:
            output.append('-- Private data omitted from preview.\n')
        pos = i + 1
    output.append(source[pos:])
    return ''.join(output)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('sql', type=Path, help='Path to the supplied MySQL export (never committed)')
    args = parser.parse_args()
    if RUNTIME.exists():
        sys.exit('preview/runtime already exists. Back it up or remove it explicitly before preparing again.')
    if not args.sql.is_file():
        sys.exit('SQL export does not exist.')
    unrar = shutil.which('unrar')
    if not unrar:
        sys.exit('Install unrar (RAR 5 support required) and ensure it is on PATH.')
    for part in range(1, 5):
        if not (ROOT / f'public_html.part{part}.zip').is_file():
            sys.exit('Missing archive part ' + str(part))
    RUNTIME.mkdir(mode=0o700)
    try:
        archive = RUNTIME / 'archive'
        archive.mkdir()
        for part in range(1, 5):
            os.symlink(ROOT / f'public_html.part{part}.zip', archive / f'public_html.part{part}.rar')
        extract = RUNTIME / 'extract'
        extract.mkdir()
        subprocess.run([unrar, 'x', '-o+', '-idq', str(archive / 'public_html.part1.rar'), str(extract) + '/'], check=True)
        shutil.move(str(extract / 'public_html'), RUNTIME / 'app')
        shutil.rmtree(extract)
        shutil.rmtree(archive)
        app = RUNTIME / 'app'
        shutil.rmtree(app / 'sessions', ignore_errors=True)
        (app / 'sessions').mkdir(mode=0o777)
        (app / 'sessions').chmod(0o777)
        for path in app.rglob('error_log'):
            if path.is_file(): path.unlink()
        # This legacy upload handler has neither authentication nor validation.
        (app / 'lbpanel/includes/ckeditor/upload.php').unlink(missing_ok=True)
        for name in ['connection.php', 'config/connection.php', 'lbpanel/config/connection.php']:
            path = app / name
            text = path.read_text(encoding='utf-8')
            marker = '// Base URLs'
            if marker not in text:
                raise ValueError('Unexpected connection file layout: ' + name)
            # Retain URL helpers, replace all production/local credential fallbacks.
            path.write_text("""<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('db', 'woodex', 'woodex-local-only', 'woodex');
if ($conn->connect_error) { http_response_code(503); exit('Preview database unavailable'); }
$conn->set_charset('utf8mb4');
define('DB_HOST', 'db'); define('DB_USER', 'woodex');
define('DB_PASSWORD', 'woodex-local-only'); define('DB_NAME', 'woodex');
""" + text[text.index(marker):], encoding='utf-8')
        (RUNTIME / 'init').mkdir()
        sql = args.sql.read_text(encoding='utf-8-sig')
        (RUNTIME / 'init/01-schema-and-public-content.sql').write_text(filtered_sql(sql), encoding='utf-8')
        # Some catalog variation images point to the former developer's localhost.
        # Keep these relative so they resolve against whichever preview host is used.
        (RUNTIME / 'init/03-local-media-paths.sql').write_text(
            "UPDATE product_variations SET image = REPLACE(REPLACE(image, "
            "'https://localhost/woodex/assets/', 'assets/'), "
            "'http://localhost/woodex/assets/', 'assets/') "
            "WHERE image LIKE '%localhost/woodex/assets/%';\n", encoding='utf-8')
        # Create a new local admin account with a random password; no imported users.
        password = secrets.token_urlsafe(18)
        # Hash locally with Python crypt (bcrypt if supported), avoiding Docker startup dependencies.
        import crypt
        hashed = crypt.crypt(password, crypt.mksalt(crypt.METHOD_BLOWFISH))
        if not hashed or not hashed.startswith('$2'):
            raise RuntimeError('bcrypt unavailable; cannot create local admin password')
        (RUNTIME / 'init/02-preview-admin.sql').write_text("INSERT INTO users (username,name,password,role) VALUES ('previewadmin','Preview Admin','" + hashed + "','admin');\n", encoding='utf-8')
        auth = RUNTIME / 'auth'
        auth.mkdir(mode=0o700)
        web_password = secrets.token_urlsafe(18)
        sha = base64.b64encode(hashlib.sha1(web_password.encode()).digest()).decode()
        (auth / 'users').write_text('preview:{SHA}' + sha + '\n', encoding='utf-8')
        # Apache must be able to read this file in a container; directory stays local.
        auth.chmod(0o755)
        (auth / 'users').chmod(0o644)
        (RUNTIME / 'credentials.txt').write_text(f'HTTP basic login: preview / {web_password}\nAdmin login: previewadmin / {password}\n', encoding='utf-8')
        (RUNTIME / 'credentials.txt').chmod(0o600)
        print('Preview prepared. Credentials are in preview/runtime/credentials.txt (private; do not commit).')
    except Exception:
        shutil.rmtree(RUNTIME, ignore_errors=True)
        raise

if __name__ == '__main__':
    main()
