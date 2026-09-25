#!/bin/bash
# Fixture test for wp-sys.sh `fpm-safe-restart` dry-run JSON + usage wiring.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fail() { echo "FAIL: $*" >&2; exit 1; }

grep -F 'fpm-safe-restart' "$ROOT/bin/wp-sys.sh" >/dev/null \
    || fail "wp-sys.sh missing fpm-safe-restart"
grep -nE 'systemctl[[:space:]]+restart[[:space:]]+php-fpm' "$ROOT/bin/wp-sys.sh" | grep -vq '^[[:space:]]*#' \
    || fail "wp-sys.sh missing panel php-fpm restart"
grep -F 'systemctl try-restart' "$ROOT/bin/wp-sys.sh" >/dev/null \
    || fail "wp-sys.sh missing Remi try-restart"
for u in php74-php-fpm php80-php-fpm php81-php-fpm php82-php-fpm php83-php-fpm; do
    grep -F "$u" "$ROOT/bin/wp-sys.sh" >/dev/null || fail "wp-sys.sh missing $u"
done
awk '/action" = "fpm-safe-restart"/,/^fi$/' "$ROOT/bin/wp-sys.sh" | grep -q nginx \
    && fail "fpm-safe-restart must not touch nginx" || true

dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT
touch "$dir/dry"

out="$(DRY_RUN_MARKER="$dir/dry" "$ROOT/bin/wp-sys.sh" fpm-safe-restart)"
echo "$out" | grep -q '"ok":true' || fail "dry-run JSON missing ok:true: $out"
echo "$out" | grep -q '"php-fpm"' || fail "dry-run JSON missing php-fpm: $out"
echo "$out" | grep -q '"restarted"' || fail "dry-run JSON missing restarted: $out"
echo "$out" | grep -q '"skipped"' || fail "dry-run JSON missing skipped: $out"
echo "$out" | grep -q '"failed"' || fail "dry-run JSON missing failed: $out"

echo "PASS tests/sys/fpm-safe-restart.sh"
