#!/bin/bash
# Fixture test for wp-sys.sh `access`: loopback traffic (127.0.0.1 / ::1)
# from local testing must be excluded from total / unique_ips / recent /
# failed_logins / suspicious, while real threats are still flagged.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fail() { echo "FAIL: $*" >&2; exit 1; }

dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT

cat >"$dir/panel.log" <<'LOG'
127.0.0.1 - - [26/Sep/2026:10:00:01 +0800] "GET / HTTP/1.1" 200 1234 "-" "Mozilla/5.0"
127.0.0.1 - - [26/Sep/2026:10:00:02 +0800] "POST /login HTTP/1.1" 401 89 "-" "python-requests/2.31"
127.0.0.1 - - [26/Sep/2026:10:00:03 +0800] "POST /login HTTP/1.1" 401 89 "-" "python-requests/2.31"
127.0.0.1 - - [26/Sep/2026:10:00:04 +0800] "GET /.env HTTP/1.1" 404 0 "-" "Nmap"
::1 - - [26/Sep/2026:10:00:05 +0800] "GET / HTTP/1.1" 200 1234 "-" "curl/8.0"
203.0.113.10 - - [26/Sep/2026:10:00:06 +0800] "GET / HTTP/1.1" 200 1234 "-" "Mozilla/5.0"
198.51.100.24 - - [26/Sep/2026:10:00:07 +0800] "POST /login HTTP/1.1" 401 89 "-" "python-requests/2.31"
198.51.100.24 - - [26/Sep/2026:10:00:08 +0800] "POST /login HTTP/1.1" 401 89 "-" "python-requests/2.31"
198.51.100.24 - - [26/Sep/2026:10:00:09 +0800] "POST /login HTTP/1.1" 401 89 "-" "python-requests/2.31"
45.33.22.11 - - [26/Sep/2026:10:00:10 +0800] "GET /.env HTTP/1.1" 404 0 "-" "Nmap"
45.33.22.11 - - [26/Sep/2026:10:00:11 +0800] "GET /wp-admin/ HTTP/1.1" 404 0 "-" "Nmap"
LOG

# Guard: wp-sys.sh must filter loopback before aggregating stats.
grep -F 'grep -v -E ^(127\.0\.0\.1|::1) ' "$ROOT/bin/wp-sys.sh" >/dev/null 2>&1 \
    || grep -F "'^(127\\.0\\.0\\.1|::1) '" "$ROOT/bin/wp-sys.sh" >/dev/null \
    || fail "wp-sys.sh missing loopback filter for access"

if [ "$(id -u)" -ne 0 ]; then
    # Behavior assertions need root (require_root); guards above still ran.
    echo "SKIP: behavior test needs root (guards passed)"
    echo "PASS tests/sys/access.sh"
    exit 0
fi

echo "== access excludes loopback from all stats =="
out="$(PANEL_ACCESS_LOG="$dir/panel.log" "$ROOT/bin/wp-sys.sh" access 20)"
echo "$out" | python3 -c '
import json, sys
d = json.loads(sys.stdin.read())
assert d["ok"] is True, d
# 11 lines total, 5 loopback (4x127.0.0.1 + 1x::1) -> 6 kept, 3 unique IPs
assert d["total"] == 6, d["total"]
assert d["unique_ips"] == 3, d["unique_ips"]
ips = [r["ip"] for r in d["recent"]]
assert "127.0.0.1" not in ips and "::1" not in ips, ips
assert len(ips) == 6, ips
fl = {r["ip"]: r["count"] for r in d["failed_logins"]}
assert fl == {"198.51.100.24": 3}, fl
susp = {r["ip"]: r for r in d["suspicious"]}
assert "127.0.0.1" not in susp and "::1" not in susp, susp
assert susp["198.51.100.24"]["level"] == "high", susp
assert susp["198.51.100.24"]["count"] == 3, susp
assert susp["45.33.22.11"]["level"] == "medium", susp
print("ok loopback excluded, threats kept")
'

echo "== loopback-only log returns empty stats, not an error =="
cat >"$dir/loopback-only.log" <<'LOG'
127.0.0.1 - - [26/Sep/2026:11:00:01 +0800] "GET / HTTP/1.1" 200 1234 "-" "Mozilla/5.0"
127.0.0.1 - - [26/Sep/2026:11:00:02 +0800] "POST /login HTTP/1.1" 401 89 "-" "python-requests/2.31"
::1 - - [26/Sep/2026:11:00:03 +0800] "GET /.env HTTP/1.1" 404 0 "-" "Nmap"
LOG
out="$(PANEL_ACCESS_LOG="$dir/loopback-only.log" "$ROOT/bin/wp-sys.sh" access 20)"
echo "$out" | python3 -c '
import json, sys
d = json.loads(sys.stdin.read())
assert d["ok"] is True and d["total"] == 0 and d["suspicious"] == [], d
print("ok empty log")
'

echo "PASS tests/sys/access.sh"
