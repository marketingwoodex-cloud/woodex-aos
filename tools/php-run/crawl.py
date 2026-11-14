#!/usr/bin/env python3
"""Crawl the running local copy of the app and report every broken/empty route.

Covers the whole project: static pages, shop, every category, every product,
blog, projects, cart/quote/checkout flow, and the complete admin panel — the
admin area is crawled with a logged-in session so guarded pages are included.

For each response it records status, size, PHP diagnostics (fatal/warning/
notice/SQLSTATE), and whether the page actually rendered content (e.g. a
category page with zero product cards is reported as empty even though it
returns 200).

Output: docs/route-audit.md and .tmp/route-audit.json

Usage:
  python3 tools/php-run/crawl.py [--base http://127.0.0.1:3000] [--limit 0]
"""

from __future__ import annotations

import argparse
import json
import re
import sqlite3
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter, defaultdict
from http.cookiejar import CookieJar
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PHP = ROOT / "php-source" / "public_html"
DB = ROOT / ".tmp" / "php-live.sqlite"

ERROR_PATTERNS = [
    ("fatal", re.compile(r"Fatal error|Uncaught (?:Error|Exception|TypeError)", re.I)),
    ("warning", re.compile(r"<b>Warning</b>|PHP Warning:|Warning:\s.*\son line \d+", re.I)),
    ("notice", re.compile(r"<b>Notice</b>|Notice:\s", re.I)),
    ("deprecated", re.compile(r"Deprecated:\s", re.I)),
    ("sql", re.compile(r"SQLSTATE|mysqli_sql_exception|There was an error|Invalid query", re.I)),
    ("blank", re.compile(r"^\s*$")),
]

# Files that are included by other pages and are not routable on their own.
PARTIALS = {"product-card.php", "blog-single.php", "project-detail.php", "servicedetail.php",
            "redirect.php", "singleproduct.php"}

STATIC_PAGES = [
    "/", "/shop", "/about-us", "/contact-us", "/projects", "/blog", "/faqs",
    "/cart", "/checkout", "/e-quotation", "/search?q=chair", "/b2b",
    "/factorydirect.php", "/projectbased.php", "/space_planing.php",
    "/deliveryandinstallation.php", "/privacy-policy", "/terms-and-conditions", "/warranty",
    "/showrooms", "/space-planning", "/robots.txt", "/sitemap.xml",
]


def build_urls(db_path: Path) -> list[tuple[str, str]]:
    """(group, path) pairs covering the public site."""
    urls = [("static", p) for p in STATIC_PAGES]
    if not db_path.exists():
        return urls
    db = sqlite3.connect(str(db_path))
    db.row_factory = sqlite3.Row
    for r in db.execute("select slug from categories where slug is not null and slug!='' order by sort_order"):
        urls.append(("category", f"/categories/{r['slug']}"))
    for r in db.execute("select slug,product_name from products where slug is not null and slug!='' order by id"):
        urls.append(("product", f"/shop/{urllib.parse.quote(r['slug'])}"))
    for r in db.execute("select slug from blogs where slug is not null and slug!=''"):
        urls.append(("blog", f"/blog/{r['slug']}"))
    for r in db.execute("select slug from projects where slug is not null and slug!=''"):
        urls.append(("project", f"/projects/{r['slug']}"))
    for p in sorted(PHP.glob("*.php")):
        if p.name in {"index.php"}:
            continue
        urls.append(("php-file", "/" + p.name))
    return urls


def admin_urls() -> list[tuple[str, str]]:
    urls = []
    for p in sorted((PHP / "lbpanel").rglob("*.php")):
        rel = p.relative_to(PHP).as_posix()
        if "/includes/" in rel or "/vendor/" in rel or "/ckeditor/" in rel:
            continue
        urls.append(("lbpanel", "/" + rel))
    return urls


def make_opener():
    cj = CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))


def fetch(opener, base: str, path: str, timeout: float = 60):
    url = base.rstrip("/") + path
    req = urllib.request.Request(url, headers={
        "User-Agent": "woodex-audit/1.0",
        "Accept": "text/html,application/xhtml+xml,*/*",
        "X-Forwarded-Proto": "http",
        "X-Forwarded-Host": urllib.parse.urlsplit(base).netloc,
    })
    try:
        with opener.open(req, timeout=timeout) as resp:
            body = resp.read()
            return resp.status, body, resp.headers.get("Content-Type", ""), ""
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read(), exc.headers.get("Content-Type", "") if exc.headers else "", ""
    except Exception as exc:  # noqa: BLE001
        return 0, b"", "", f"{type(exc).__name__}: {exc}"


def login(opener, base: str, user: str, password: str) -> tuple[bool, str]:
    data = urllib.parse.urlencode({"username": user, "password": password, "login": "1"}).encode()
    req = urllib.request.Request(base.rstrip("/") + "/lbpanel/index.php", data=data, headers={
        "Content-Type": "application/x-www-form-urlencoded",
        "User-Agent": "woodex-audit/1.0",
        "X-Forwarded-Proto": "http",
        "X-Forwarded-Host": urllib.parse.urlsplit(base).netloc,
    })
    try:
        with opener.open(req, timeout=90) as resp:
            body = resp.read().decode("utf-8", "replace")
            ok = "Invalid username or password" not in body
            return ok, f"status={resp.status}"
    except urllib.error.HTTPError as exc:
        return False, f"HTTPError {exc.code}"
    except Exception as exc:  # noqa: BLE001
        return False, f"{type(exc).__name__}: {exc}"


def classify(group: str, path: str, status: int, body: bytes, ctype: str) -> dict:
    text = body.decode("utf-8", "replace")
    rec = {"group": group, "path": path, "status": status, "bytes": len(body), "type": ctype.split(";")[0]}
    hits = []
    if status >= 400:
        hits.append(f"http-{status}")
    for name, rx in ERROR_PATTERNS:
        if name == "blank":
            continue
        if rx.search(text):
            hits.append(name)
    if len(body) < 800 and "html" in ctype:
        hits.append("suspiciously-small")
    if hits:
        rec["problems"] = sorted(set(hits))
        snippet = re.sub(r"<[^>]+>", " ", text)
        snippet = re.sub(r"\s+", " ", snippet).strip()
        for name, rx in ERROR_PATTERNS:
            if name in ("blank",):
                continue
            m = rx.search(text)
            if m:
                rec["evidence"] = re.sub(r"\s+", " ", text[max(0, m.start() - 80): m.start() + 240])
                break
        if "evidence" not in rec:
            rec["evidence"] = snippet[:240]
    if group in ("category", "shop", "static") and status == 200:
        cards = len(re.findall(r"class=\"[^\"]*product-card", text))
        rec["product_cards"] = cards
    if group == "lbpanel" and status == 200:
        rec["admin_ok"] = bool(re.search(r"(logout|Logout|lbpanel)", text, re.I))
    return rec


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="http://127.0.0.1:3000")
    ap.add_argument("--user", default="admin")
    ap.add_argument("--password", default="Woodex@2026")
    ap.add_argument("--limit", type=int, default=0, help="cap number of public URLs (debug)")
    args = ap.parse_args()

    base = args.base
    print(f"crawling {base}")
    opener = make_opener()
    ok, detail = login(opener, base, args.user, args.password)
    print(f"admin login as {args.user}: {'OK' if ok else 'FAILED'} ({detail})")

    rows = []
    db_urls = {p: g for g, p in build_urls(DB)}
    queue: list[str] = ["/"] + [p for p in db_urls if p != "/"]
    seen: set[str] = set()
    cap = args.limit or 700
    link_rx = re.compile(r"""(?:href|action)\s*=\s*["']([^"'#]+)["']""", re.I)
    while queue and len(seen) < cap:
        path = queue.pop(0)
        path = path.split("?")[0] if path.startswith("/search") else path
        if path in seen:
            continue
        seen.add(path)
        group = db_urls.get(path.split("?")[0]) or ("static" if path.count("/") == 1 else "linked")
        status, body, ctype, err = fetch(opener, base, path)
        rec = classify(group, path, status, body, ctype)
        # Direct hits on .php entry points that need POST/query parameters are
        # expected to answer 404/405/"No id specified". Only real PHP errors count.
        if group == "php-file":
            hard = {"fatal", "warning", "notice", "sql", "transport"}
            if not (hard & set(rec.get("problems", []))):
                rec.pop("problems", None)
                rec.pop("evidence", None)
            elif path.lstrip("/") in PARTIALS:
                # a partial that is only meant to be included from another page
                rec["expected"] = "partial — included by product/blog pages, not routable"
                rec.pop("problems", None)
        if err:
            rec["problems"] = sorted(set(rec.get("problems", []) + ["transport"]))
            rec["evidence"] = err
        rows.append(rec)
        if rec.get("problems"):
            print(f"  {rec['status']} {path} → {','.join(rec['problems'])}")
            rec["source"] = "harvested from home page links" if group == "linked" else group
        sys.stdout.flush()
        if "html" not in ctype or status >= 400:
            continue
        text = body.decode("utf-8", "replace")
        for m in link_rx.finditer(text):
            href = m.group(1).strip()
            if not href or href.startswith(("mailto:", "tel:", "javascript:", "data:", "#")):
                continue
            if "://" in href or href.startswith("//"):
                parts = urllib.parse.urlsplit(href)
                if parts.netloc and parts.netloc not in (urllib.parse.urlsplit(base).netloc, "woodexfurniture.pk"):
                    continue
                href = parts.path + (("?" + parts.query) if parts.query else "")
            if not href.startswith("/"):
                continue
            if href.startswith(("/lbpanel", "/api/", "/assets/", "/vendor/", "/sessions/")):
                continue
            if re.search(r"\.(jpg|jpeg|png|webp|gif|svg|css|js|woff2?|ttf|pdf)$", href, re.I):
                continue
            href = href.split("#")[0]
            if href and href not in seen:
                queue.append(href)

    for group, path in admin_urls():
        status, body, ctype, err = fetch(opener, base, path)
        rec = classify(group, path, status, body, ctype)
        # Direct hits on .php entry points that need POST/query parameters are
        # expected to answer 404/405/"No id specified". Only real PHP errors count.
        if group == "php-file":
            hard = {"fatal", "warning", "notice", "sql", "transport"}
            if not (hard & set(rec.get("problems", []))):
                rec.pop("problems", None)
                rec.pop("evidence", None)
            elif path.lstrip("/") in PARTIALS:
                # a partial that is only meant to be included from another page
                rec["expected"] = "partial — included by product/blog pages, not routable"
                rec.pop("problems", None)
        if err:
            rec["problems"] = sorted(set(rec.get("problems", []) + ["transport"]))
            rec["evidence"] = err
        # a guarded page that bounced us to the login form is not a server error
        rows.append(rec)

    problems = [r for r in rows if r.get("problems")]
    empty_listings = [r for r in rows if r.get("product_cards") == 0 and r["group"] in ("category", "shop")]
    summary = Counter(r["status"] for r in rows)

    payload = {
        "base": base,
        "total": len(rows),
        "by_status": dict(summary),
        "by_group": dict(Counter(r["group"] for r in rows)),
        "problem_count": len(problems),
        "problems": problems,
        "empty_listings": [r["path"] for r in empty_listings],
        "rows": rows,
    }
    (ROOT / ".tmp" / "route-audit.json").write_text(json.dumps(payload, indent=1))

    md = ["# Route audit — every page of the local run\n",
          f"Crawled `{args.base}` with a logged-in admin session (`{args.user}`). Generated by "
          "`python3 tools/php-run/crawl.py`.\n",
          "| Metric | Value |\n| --- | --- |",
          f"| URLs crawled | {len(rows)} |"]
    for status, n in sorted(summary.items()):
        md.append(f"| HTTP {status} | {n} |")
    md.append(f"| Pages with PHP errors or empty bodies | {len(problems)} |")
    md.append(f"| Category/shop pages rendering 0 product cards | {len(empty_listings)} |")
    md.append("")
    for group in ("static", "php-file", "category", "product", "blog", "project", "linked", "lbpanel"):
        grp = [r for r in rows if r["group"] == group]
        if not grp:
            continue
        bad = [r for r in grp if r.get("problems")]
        md.append(f"## {group}: {len(grp)} pages, {len(bad)} with problems\n")
        if bad:
            md.append("| page | status | problem | evidence |\n| --- | --- | --- | --- |")
            for r in bad[:80]:
                ev = (r.get("evidence") or "").replace("|", "\\|")[:180]
                md.append(f"| `{r['path']}` | {r['status']} | {', '.join(r['problems'])} | {ev} |")
            if len(bad) > 80:
                md.append(f"\n… {len(bad) - 80} more in `.tmp/route-audit.json`")
            md.append("")
        else:
            md.append("No problems.\n")
    if empty_listings:
        md.append("## Category/shop pages with zero products\n")
        for r in empty_listings[:80]:
            md.append(f"- `{r['path']}` ({r['bytes']} bytes)")
        md.append("")
    (ROOT / "docs" / "route-audit.md").write_text("\n".join(md) + "\n")

    print(f"\n{len(rows)} URLs | status {dict(summary)} | {len(problems)} with problems | "
          f"{len(empty_listings)} empty listings")
    print("wrote docs/route-audit.md and .tmp/route-audit.json")
    return 0


if __name__ == "__main__":
    sys.exit(main())
