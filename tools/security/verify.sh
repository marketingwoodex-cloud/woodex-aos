#!/usr/bin/env bash
# Security checks for the local run of the Woodex application.
#
#   tools/security/verify.sh [base-url]
#
# Every check is a black-box HTTP request against the running preview, so it
# verifies what an attacker could actually do — not what the code intends.
# Exit status is the number of failed checks.
set -uo pipefail

base="${1:-http://127.0.0.1:${PORT:-3000}}"
jar_a="$(mktemp)"; jar_b="$(mktemp)"; jar_c="$(mktemp)"
fails=0; total=0

pass() { total=$((total + 1)); printf '  ok    %s\n' "$1"; }
fail() { total=$((total + 1)); fails=$((fails + 1)); printf '  FAIL  %s — %s\n' "$1" "$2"; }
code() { curl -s -o /dev/null -w '%{http_code}' -m 60 "$@"; }
body() { curl -s -m 60 "$@"; }
sid_of() { awk '/WDXSESSID/{print $7}' "$1" | tail -1; }

echo "== secrets must not be downloadable =="
for path in /error_log /connection.php.orig /config/connection.php.orig /php.ini /.user.ini \
            /.htaccess /database/live_schema_updates.sql; do
  c=$(code "$base$path")
  if [[ "$c" == "404" || "$c" == "403" ]]; then pass "blocked $path"; else fail "blocked $path" "HTTP $c"; fi
done
sample_session=$(ls ../php-source/public_html/sessions 2>/dev/null | head -1 || true)
if [[ -n "${sample_session:-}" ]]; then
  c=$(code "$base/sessions/$sample_session")
  if [[ "$c" == "404" || "$c" == "403" ]]; then pass "blocked /sessions/<file>"; else fail "blocked /sessions/<file>" "HTTP $c"; fi
else
  pass "blocked /sessions/<file> (no sample file)"
fi

echo "== admin area requires a session =="
for path in /lbpanel/dashboard.php /lbpanel/view-users.php /lbpanel/view-products.php \
            /lbpanel/category-management/view-categories.php /lbpanel/pos/manage_pos.php \
            /lbpanel/manage-orders.php /lbpanel/sales-report.php /lbpanel/email-settings.php; do
  out=$(curl -s -m 60 -o /dev/null -w '%{http_code} %{redirect_url}' "$base$path")
  c="${out%% *}"; loc="${out#* }"
  if [[ "$c" == "302" && "$loc" == *"/lbpanel/index.php"* ]]; then
    pass "login required: $path"
  else
    fail "login required: $path" "HTTP $c → ${loc:-no redirect}"
  fi
done

echo "== admin AJAX endpoints answer 401, not data =="
for path in /lbpanel/php/update_order_status.php /lbpanel/php/fetch_order_items.php \
            /lbpanel/php/export_sales.php /lbpanel/pos/api/get_products.php \
            /lbpanel/pos/api/save_transaction.php; do
  out=$(curl -s -m 60 -w '|%{http_code}' "$base$path")
  c="${out##*|}"; b="${out%|*}"
  if [[ "$c" == "401" && "$b" == *Authentication* ]]; then pass "401 for $path"; else fail "401 for $path" "HTTP $c body=${b:0:40}"; fi
done

echo "== public site stays open =="
for path in / /shop /categories/office-chairs /cart /blog /assets/images/header-logo.png; do
  c=$(code "$base$path")
  if [[ "$c" == "200" ]]; then pass "public $path"; else fail "public $path" "HTTP $c"; fi
done

echo "== login still works and rotates the session id =="
curl -s -m 60 -c "$jar_a" -o /dev/null -X POST "$base/lbpanel/index.php" \
  --data-urlencode 'username=admin' --data-urlencode 'password=Woodex@2026' --data-urlencode 'login=1'
sid_before=$(sid_of "$jar_a")
c=$(code -b "$jar_a" -c "$jar_a" "$base/lbpanel/dashboard.php")
sid_after=$(sid_of "$jar_a")
if [[ "$c" == "200" ]]; then pass "admin can log in"; else fail "admin can log in" "dashboard HTTP $c"; fi
if [[ -n "$sid_before" && -n "$sid_after" && "$sid_before" != "$sid_after" ]]; then
  pass "session id rotated after login"
else
  fail "session id rotated after login" "before=${sid_before:-none} after=${sid_after:-none}"
fi

echo "== one visitor cannot borrow another visitor's session =="
c=$(code -c "$jar_b" "$base/lbpanel/dashboard.php")
if [[ "$c" == "302" ]]; then pass "second browser is not logged in"; else fail "second browser is not logged in" "HTTP $c"; fi
curl -s -m 60 -b "$jar_a" -c "$jar_a" -X POST -d 'product_id=40&quantity=2' \
  -o /dev/null "$base/api/cart/add.php"
count_a=$(body -b "$jar_a" "$base/api/cart/count.php")
count_b=$(body -b "$jar_b" "$base/api/cart/count.php" | tr -d '\n')
count_c=$(body -c "$jar_c" "$base/api/cart/count.php" | tr -d '\n')
if [[ "$count_a" == *'"cart_count":2'* ]]; then pass "owner keeps own cart ($count_a)"; else fail "owner keeps own cart" "$count_a"; fi
if [[ "$count_b" == *'"cart_count":0'* && "$count_c" == *'"cart_count":0'* ]]; then
  pass "other visitors get an empty cart"
else
  fail "other visitors get an empty cart" "b=$count_b c=$count_c"
fi

echo "== brute force is throttled =="
fake_ip="203.0.113.$((RANDOM % 250 + 2))"
locked=""
for i in $(seq 1 8); do
  c=$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "X-Forwarded-For: $fake_ip" \
    -X POST "$base/lbpanel/index.php" \
    --data-urlencode 'username=admin' --data-urlencode 'password=wrong-on-purpose' --data-urlencode 'login=1')
  [[ "$c" == "429" ]] && { locked=$i; break; }
done
if [[ -n "$locked" ]]; then pass "locked out after $locked wrong passwords"; else fail "locked out after repeated failures" "never returned 429"; fi
c=$(code -X POST "$base/lbpanel/index.php" --data-urlencode 'username=admin' \
  --data-urlencode 'password=Woodex@2026' --data-urlencode 'login=1')
if [[ "$c" == "302" ]]; then pass "other clients are not affected by the lockout"; else fail "other clients unaffected" "HTTP $c"; fi

rm -f "$jar_a" "$jar_b" "$jar_c"
echo
echo "== $((total - fails))/$total checks passed, $fails failed =="
exit "$fails"
