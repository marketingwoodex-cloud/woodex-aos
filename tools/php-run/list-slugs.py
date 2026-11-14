#!/usr/bin/env python3
"""Print the storefront slugs as JSON: {"categories": [...], "products": [...]}.

Used by the local runner to build /sitemap.xml and to know which category slugs
actually exist. Read-only.

    python3 tools/php-run/list-slugs.py .tmp/php-live.sqlite
"""
from __future__ import annotations

import json
import sqlite3
import sys


def main() -> None:
    db = sys.argv[1] if len(sys.argv) > 1 else ".tmp/php-live.sqlite"
    connection = sqlite3.connect(f"file:{db}?mode=ro", uri=True)

    def slugs(table: str, limit: int | None = None) -> list[str]:
        query = f"SELECT slug FROM {table} WHERE slug IS NOT NULL AND TRIM(slug) <> '' ORDER BY id"
        if limit:
            query += f" LIMIT {limit}"
        try:
            return [row[0] for row in connection.execute(query)]
        except sqlite3.Error:
            return []

    print(json.dumps({"categories": slugs("categories"), "products": slugs("products", 200)}))
    connection.close()


if __name__ == "__main__":
    main()
