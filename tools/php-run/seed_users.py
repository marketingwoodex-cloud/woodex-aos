#!/usr/bin/env python3
"""Create the local admin accounts in the imported SQLite database.

    python3 tools/php-run/seed_users.py --db .tmp/php-live.sqlite

The cpanel dump only contains a `users` row whose password hash nobody knows, so
the local run needs accounts with known passwords. These are **local only** —
they exist in `.tmp/php-live.sqlite`, never on the live host.
"""
from __future__ import annotations

import argparse
import sqlite3
from datetime import datetime

import bcrypt

ACCOUNTS = [
    # username,  name,             role,     password
    ("bilal", "Bilal Khan", "admin", "Woodex@2026"),
    ("admin", "Woodex Admin", "admin", "Woodex@2026"),
    ("manager", "Sales Manager", "manager", "Woodex@2026"),
]


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--db", default=".tmp/php-live.sqlite")
    parser.add_argument("--password", default="Woodex@2026")
    args = parser.parse_args()

    connection = sqlite3.connect(args.db)
    connection.execute(
        """CREATE TABLE IF NOT EXISTS users (
               id INTEGER PRIMARY KEY AUTOINCREMENT,
               username TEXT NOT NULL UNIQUE,
               name TEXT NOT NULL,
               password TEXT NOT NULL,
               role TEXT NOT NULL DEFAULT 'admin',
               created_at TEXT,
               updated_at TEXT
           )"""
    )

    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    for username, name, role, password in ACCOUNTS:
        # bcrypt with a cost of 10, exactly what PHP's password_hash() produces,
        # so password_verify() in lbpanel/index.php accepts it.
        hashed = bcrypt.hashpw(password.encode(), bcrypt.gensalt(rounds=10, prefix=b"2b")).decode()
        existing = connection.execute(
            "SELECT id FROM users WHERE username = ?", (username,)
        ).fetchone()
        if existing:
            connection.execute(
                "UPDATE users SET name = ?, password = ?, role = ?, updated_at = ? WHERE username = ?",
                (name, hashed, role, now, username),
            )
            action = "updated"
        else:
            connection.execute(
                "INSERT INTO users (username, name, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
                (username, name, hashed, role, now, now),
            )
            action = "created"
        print(f"  {action:8} {username:10} role={role:8} password={password}")

    connection.commit()
    rows = connection.execute("SELECT username, role FROM users ORDER BY id").fetchall()
    connection.close()
    print("users now:", ", ".join(f"{u}({r})" for u, r in rows))


if __name__ == "__main__":
    main()
