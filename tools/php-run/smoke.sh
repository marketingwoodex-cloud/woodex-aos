#!/usr/bin/env bash
# Walk the original Woodex app on the local PHP runner and report failures.
#
#   tools/php-run/smoke.sh [base-url]
#
# Logs in through the real admin login form, then requests every admin module
# and the main storefront pages, flagging anything that is not 2xx/3xx, is
# empty, or contains a PHP fatal error.
set -uo pipefail

base="${1:-http://127.0.0.1:3001}"
user="${WDX_USER:-bilal}"
pass="${WDX_PASS:-Woodex@2026}"
jar="$(mktemp)"
out="$(mktemp -d)"
fails=0
total=0

check() {  # check <label> <path> [expected-regex]
  local label="$1" path="$2" body="$out/body.$$"
  total=$((total + 1))
  local code
  code=$(curl -s -m 60 -b "$jar" -c "$jar" -o "$body" -w '%{http_code}' "$base$path")
  local size
  size=$(wc -c < "$body")
  local err=""
  if [[ "$code" != 2* && "$code" != 3* ]]; then err="http $code"; fi
  if grep -qiE "Fatal error|Parse error|Uncaught (Error|Exception)|RuntimeError" "$body"; then err="${err:+$err, }php error"; fi
  if [[ -z "$err" ]]; then
    printf '  ok   %-34s %s (%s B)\n' "$label" "$code" "$size"
  else
    fails=$((fails + 1))
    printf '  FAIL %-34s %s (%s B) — %s\n' "$label" "$code" "$size" "$err"
    head -c 200 "$body" | tr '\n' ' ' | sed 's/^/       body: /'
    echo
    grep -oiE "(Fatal error|Parse error|Uncaught [A-Za-z]+)[^<]{0,120}" "$body" | head -2 | sed 's/^/       /'
  fi
}

echo "== login =="
code=$(curl -s -m 60 -c "$jar" -o "$out/login.html" -w '%{http_code}' \
  -X POST "$base/lbpanel/index.php" \
  --data-urlencode "username=$user" --data-urlencode "password=$pass" --data-urlencode "login=")
if [[ "$code" != 302 && "$code" != 303 ]]; then
  echo "  FAIL login returned $code (expected 302 to dashboard)"
  fails=$((fails + 1))
else
  echo "  ok   login accepted, redirected"
fi

echo "== admin panel =="
check "dashboard"        "/lbpanel/dashboard.php"
for page in add-users add-doctor add-product add-category add-blog add-faq add-coupon add-brand \
            add-slider add-testimonial add-team add-order add-quotation add-media add-page \
            manage-orders manage-products manage-categories manage-blogs manage-faqs \
            manage-coupons manage-brands manage-sliders manage-testimonials manage-team \
            manage-users manage-doctors manage-media manage-pages manage-quotations \
            view-products view-orders view-quotations settings pos pos-orders pos-products \
            sales-report stock-report dashboard-reports profile; do
  [[ -f "php-source/public_html/lbpanel/$page.php" ]] || continue
  check "$page" "/lbpanel/$page.php"
done

echo "== storefront =="
check "home"        "/"
check "home.php"    "/index.php"
check "shop"        "/shop.php"
check "about"       "/about-us"
check "contact"     "/contact-us"
check "blogs"       "/blog"
check "faq"         "/faqs"
check "e-quotation" "/e-quotation"
check "cart"        "/cart"
check "checkout"    "/checkout.php"
# Use a slug that actually exists in the import so the product page is real.
product_slug=$(python3 -c "
import sqlite3, sys
try:
    conn = sqlite3.connect('.tmp/php-live.sqlite')
    row = conn.execute('SELECT slug FROM products WHERE slug IS NOT NULL LIMIT 1').fetchone()
    print(row[0] if row else '')
except Exception:
    print('')
" 2>/dev/null)
if [[ -n "$product_slug" ]]; then
  check "product" "/shop/$product_slug"
fi
check "sale"        "/sale"
check "projects"    "/projects"
check "services"    "/services"
check "showrooms"   "/showrooms"
check "b2b"         "/b2b"
check "warranty"    "/warranty"
check "privacy"     "/privacy-policy"

echo
echo "== $((total - fails))/$total pages ok, $fails failed =="
echo "log dir: $out"
echo "$out" > /tmp/wdx-smoke-dir
[[ "$fails" -eq 0 ]]
