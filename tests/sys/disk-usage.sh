#!/bin/bash
# Fixture test for wp-sys.sh `disk-usage`: usage string, dry-run JSON, live tree.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fail() { echo "FAIL: $*" >&2; exit 1; }

grep -n 'disk-usage' "$ROOT/bin/wp-sys.sh" | grep -vq '^[[:space:]]*#' \
    || fail "wp-sys.sh missing disk-usage action"
grep -F 'disk-usage' "$ROOT/bin/wp-sys.sh" | head -n 5 >/dev/null
grep -F '{info|svc|fpm-safe-restart|logins|access|deny|undeny|denylist|disk-usage}' "$ROOT/bin/wp-sys.sh" >/dev/null \
    || fail "usage() missing disk-usage"

dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT
touch "$dir/dry"

out="$(DRY_RUN_MARKER="$dir/dry" "$ROOT/bin/wp-sys.sh" disk-usage)"
echo "$out" | grep -q '"ok":true' || fail "dry-run JSON missing ok:true: $out"
echo "$out" | grep -q '"total":"18.4 GB"' || fail "dry-run JSON missing demo total: $out"
echo "$out" | grep -q '"Files in home directory"' || fail "dry-run JSON missing home category: $out"
echo "$out" | grep -q '"icon":"home"' || fail "dry-run JSON missing home icon: $out"
echo "$out" | grep -q '"largest_dirs"' || fail "dry-run JSON missing largest_dirs: $out"
echo "$out" | grep -q '"application_backups"' || fail "dry-run JSON missing demo dir: $out"
echo "$out" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d["ok"] and len(d["categories"])==9 and d["categories"][0]["icon"]=="home"' \
    || fail "dry-run JSON schema invalid: $out"

# Live path computation against a tiny fake host tree (no root required).
fix="$dir/host"
mkdir -p \
    "$fix/www/wwwroot/support-local" \
    "$fix/www/wwwroot/zmerch" \
    "$fix/www/wwwroot/.trash" \
    "$fix/www/wwwlogs" \
    "$fix/www/server/backup/full" \
    "$fix/www/server/data" \
    "$fix/www/server/panel" \
    "$fix/www/server/tmp" \
    "$fix/www/application_backups" \
    "$fix/tmp" \
    "$fix/var/mail"
dd if=/dev/zero of="$fix/www/wwwroot/support-local/site.bin" bs=1024 count=200 status=none
dd if=/dev/zero of="$fix/www/wwwroot/zmerch/shop.bin" bs=1024 count=80 status=none
dd if=/dev/zero of="$fix/www/wwwroot/.trash/old.bin" bs=1024 count=40 status=none
dd if=/dev/zero of="$fix/www/wwwlogs/access.log" bs=1024 count=30 status=none
dd if=/dev/zero of="$fix/www/server/backup/full/a.tar" bs=1024 count=120 status=none
dd if=/dev/zero of="$fix/www/application_backups/dump.tar" bs=1024 count=300 status=none
dd if=/dev/zero of="$fix/www/server/data/ibdata1" bs=1024 count=60 status=none
dd if=/dev/zero of="$fix/www/server/panel/app.conf" bs=1024 count=4 status=none
dd if=/dev/zero of="$fix/tmp/tmp.bin" bs=1024 count=16 status=none
dd if=/dev/zero of="$fix/var/mail/nobody" bs=1024 count=2 status=none

live="$(
    WP_DISK_USAGE_FIXTURE=1 \
    WWW_ROOT="$fix/www" \
    WEB_ROOT="$fix/www/wwwroot" \
    LOG_ROOT="$fix/www/wwwlogs" \
    BACKUP_ROOT="$fix/www/server/backup" \
    WP_HOME_DIR="$fix/www/server/panel" \
    WP_MYSQL_DATADIR="$fix/www/server/data" \
    WP_PG_DATADIR="" \
    WP_TMP_DIRS="$fix/tmp:$fix/www/server/tmp" \
    WP_MAIL_DIRS="$fix/var/mail" \
    WP_VAR_LOG_DIRS="" \
    "$ROOT/bin/wp-sys.sh" disk-usage
)"
echo "$live" | python3 -c '
import json, sys
d = json.load(sys.stdin)
assert d.get("ok") is True, d
cats = {c["name"]: c for c in d["categories"]}
assert set(cats) == {
    "Files in home directory",
    "Files in hidden subdirectories",
    "Databases",
    "Mailing Lists",
    "Email",
    "Website Files",
    "Logs",
    "Temporary Files",
    "Other",
}, cats
assert cats["Mailing Lists"]["size"] == "0 B"
assert cats["Website Files"]["size"] != "0 B"
assert cats["Files in hidden subdirectories"]["size"] != "0 B"
assert cats["Databases"]["size"] != "0 B"
assert cats["Other"]["size"] != "0 B"
assert cats["Website Files"]["icon"] == "globe"
names = [x["name"] for x in d["largest_dirs"]]
assert "application_backups" in names, names
assert "support-local" in names, names
assert d["total"] and d["total"] != "0 B"
print("live total", d["total"])
print("live categories", [(c["name"], c["size"], c["icon"]) for c in d["categories"]])
print("live dirs", d["largest_dirs"])
' || fail "live fixture JSON invalid: $live"

echo "PASS tests/sys/disk-usage.sh"
