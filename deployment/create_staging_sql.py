#!/usr/bin/env python3
"""Create a staging-only schema/catalog import outside the repository."""
import argparse
import importlib.util
import os
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('preview_prepare', ROOT / 'preview' / 'prepare.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('source', type=Path, help='Original export; remains unchanged')
parser.add_argument('output', type=Path, help='New private output path outside this repository')
args = parser.parse_args()
source = args.source.resolve(strict=True)
out = args.output.resolve()
if out == source or out.is_relative_to(ROOT):
    parser.error('Output must be a different path outside the repository.')
if out.exists():
    parser.error('Output already exists; choose a new filename.')
sql = source.read_text(encoding='utf-8-sig')
# This filter is designed for the supplied phpMyAdmin dump, not arbitrary SQL.
# Refuse common data-bearing or executable statement forms other than INSERT.
if re.search(r'(?im)^\s*(?:REPLACE\s+INTO|UPDATE\s+|DELETE\s+FROM|LOAD\s+DATA|CREATE\s+(?:TRIGGER|EVENT|FUNCTION|PROCEDURE)|CALL\s+|SOURCE\s+|USE\s+)', sql):
    parser.error('Unsupported statements detected; do not import without a manual security review.')
filtered = module.filtered_sql(sql)
# The export uses a MariaDB-specific collation unavailable on some hosts.
# This is an approximate compatibility mapping, not byte-for-byte parity.
filtered = re.sub(r'\butf8mb4_uca1400_ai_ci\b', 'utf8mb4_unicode_ci', filtered, flags=re.IGNORECASE)
filtered += "\n-- Staging-only normalization of archived developer-local media URLs.\n"
filtered += "UPDATE product_variations SET image=REPLACE(REPLACE(image, 'https://localhost/woodex/assets/', 'assets/'), 'http://localhost/woodex/assets/', 'assets/') WHERE image LIKE '%localhost/woodex/assets/%';\n"
# Never import original users or SMTP settings; the allowlist omits these rows.
fd = os.open(out, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
with os.fdopen(fd, 'w', encoding='utf-8') as f:
    f.write(filtered)
print('Created private staging SQL:', out)
print('Review catalog/content for personal data before importing. Original users and SMTP settings are not imported.')
