#!/bin/bash
# Fixture test for wp-sys.sh `logins`: AlmaLinux OpenSSH logs
# `sshd-session[PID]: Accepted ...` which the old sshd[PID]-only awk dropped.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fail() { echo "FAIL: $*" >&2; exit 1; }

# Keep this pipeline aligned with bin/wp-sys.sh action=logins.
parse_logins() {
    local n="$1"
    shift
    grep -hE 'sshd[^[:space:]]*\[[0-9]+\]: Accepted (publickey|password)' "$@" 2>/dev/null \
        | tail -n "$n" | tac | awk '
        /sshd[^[:space:]]*\[[0-9]+\]: Accepted (publickey|password) for / {
            method=""; user=""; ip="";
            for (i=1; i<=NF; i++) {
                if ($i=="Accepted" && method=="") method=$(i+1);
                if ($i=="for" && user=="") user=$(i+1);
                if ($i=="from" && ip=="") ip=$(i+1);
            }
            if (method!="" && user!="" && ip!="") {
                gsub(/[\\"]/,"",method); gsub(/[\\"]/,"",user); gsub(/[\\"]/,"",ip);
                printf "%s{\"time\":\"%s %s %s\",\"user\":\"%s\",\"ip\":\"%s\",\"method\":\"%s\"}",
                    sep, $1, $2, $3, user, ip, method;
                sep=",";
            }
        }'
}

dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT

# Rotated older file + current file (chronological concat, then tail).
cat >"$dir/secure-20260920" <<'LOG'
Sep 20 08:00:01 host sshd[100]: Accepted publickey for oldroot from 203.0.113.9 port 22 ssh2
Sep 20 09:00:01 host systemd[1]: Started sshd.service
LOG
cat >"$dir/secure" <<'LOG'
Sep 24 12:00:00 host systemd[1]: Starting sshd.service...
Sep 24 12:08:41 host sshd[991]: Accepted password for demo from 198.51.100.24 port 22 ssh2
Sep 24 13:51:08 host sshd-session[1842]: Accepted publickey for grokbot from 203.0.113.10 port 55112 ssh2
Sep 24 14:02:11 host sshd-session[2201]: Accepted publickey for trae_solo from 203.0.113.10 port 22 ssh2
Sep 24 14:10:00 host CROND[9]: (root) CMD (/usr/lib64/sa/sa1)
LOG

echo "== old sshd[PID]-only awk drops sshd-session lines =="
old=$(grep -hE 'Accepted (publickey|password)' "$dir/secure" \
    | awk '/sshd\[[0-9]+\]: Accepted (publickey|password) for / { c++ } END { print c+0 }')
[ "$old" = "1" ] || fail "expected 1 classic sshd match, got $old"

echo "== new parser keeps sshd and sshd-session, newest first =="
out=$(parse_logins 12 "$dir/secure-20260920" "$dir/secure")
echo "$out" | python3 -c '
import json, sys
raw = "[" + sys.stdin.read() + "]"
rows = json.loads(raw)
assert [r["user"] for r in rows] == ["trae_solo", "grokbot", "demo", "oldroot"], rows
assert rows[0] == {"time": "Sep 24 14:02:11", "user": "trae_solo", "ip": "203.0.113.10", "method": "publickey"}, rows[0]
assert rows[1]["user"] == "grokbot" and rows[1]["method"] == "publickey", rows[1]
assert rows[2] == {"time": "Sep 24 12:08:41", "user": "demo", "ip": "198.51.100.24", "method": "password"}, rows[2]
print("ok", len(rows), "rows")
'

echo "== limit 2 returns the two newest sshd-session lines =="
out=$(parse_logins 2 "$dir/secure")
echo "$out" | python3 -c '
import json, sys
rows = json.loads("[" + sys.stdin.read() + "]")
assert [r["user"] for r in rows] == ["trae_solo", "grokbot"], rows
print("ok limit")
'

# Guard: the live wrapper uses the broadened sshd* PID regex, not sshd[PID] only.
if grep -n 'sshd\\\[[0-9\]+\\\]: Accepted' "$ROOT/bin/wp-sys.sh" | grep -vq '^[[:space:]]*#'; then
    fail "wp-sys.sh still has sshd[PID]-only awk"
fi
grep -F 'sshd[^[:space:]]*\[[0-9]+\]: Accepted' "$ROOT/bin/wp-sys.sh" >/dev/null \
    || fail "wp-sys.sh missing sshd* PID regex"

echo "PASS tests/sys/logins.sh"
