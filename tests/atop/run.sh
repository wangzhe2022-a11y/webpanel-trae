#!/bin/bash
# Reproduce the live dashboard bug: sample gauges exist for 00:02 on
# atop_20260923, but top_cpu/top_mem were empty because -b 00:02 remapped
# to the first record's date (Sep 23 RESET), not the post-midnight sample.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
LOGDIR="$(mktemp -d)"
trap 'rm -rf "$LOGDIR"' EXIT

printf 'dummy-atop-raw\n' > "$LOGDIR/atop_20260923"

export ATOP_TEST=1
export ATOP_BIN="$ROOT/tests/atop/mock-atop"
export ATOP_LOGPATH="$LOGDIR"
export ATOP_SYSCONFIG="/tmp/wp-atop-no-sysconfig"
export TIMEZONE="Asia/Shanghai"
export TZ="Asia/Shanghai"
# Force the real parser path even if a dry-run marker exists on this host.
export DRY_RUN_MARKER="/tmp/wp-atop-no-dryrun"

chmod +x "$ATOP_BIN"

fail() { echo "FAIL: $*" >&2; exit 1; }

run_info() {
    "$ROOT/bin/wp-atop.sh" info "$@"
}

echo "== mock remaps bare hh:mm 00:02 to Sep 23 RESET (old collector window) =="
"$ATOP_BIN" -r "$LOGDIR/atop_20260923" -Z -b "00:02" -e "00:03" -P PRC,PRM,PRD | python3 -c '
import sys
lines=[l for l in sys.stdin.read().splitlines() if l.startswith("PRC ")]
assert not lines, lines
print("ok mock: bare 00:02 window has no PRC lines")
'

echo "== mock absolute 2026092400:02 hits midnight processes =="
"$ATOP_BIN" -r "$LOGDIR/atop_20260923" -Z -b "2026092400:02" -e "2026092400:04" -P PRC,PRM,PRD | python3 -c '
import sys
names=[l.split()[7] for l in sys.stdin.read().splitlines() if l.startswith("PRC ")]
assert "mysqld" in names, names
print("ok mock: absolute window has", names)
'

json_field() {
    python3 -c 'import json,sys; d=json.load(sys.stdin); p=sys.argv[1].split(".");
v=d
for k in p:
    if k.isdigit():
        v=v[int(k)]
    else:
        v=v.get(k)
print("" if v is None else v if not isinstance(v, (dict,list)) else json.dumps(v,separators=(",",":")))' "$1"
}

echo "== latest (post-midnight 00:02 on atop_20260923) =="
out="$(run_info atop_20260923 latest 3 8)"
echo "$out" | python3 -m json.tool >/tmp/wp-atop-latest.json
time="$(printf '%s' "$out" | json_field time)"
[ "$time" = "00:02" ] || fail "expected time 00:02, got $time"
sample="$(printf '%s' "$out" | json_field sample)"
[ "$sample" != "null" ] && [ -n "$sample" ] || fail "sample missing"
top_cpu="$(printf '%s' "$out" | json_field top_cpu)"
top_mem="$(printf '%s' "$out" | json_field top_mem)"
echo "top_cpu=$top_cpu"
echo "top_mem=$top_mem"
printf '%s' "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
cpu=d.get("top_cpu") or []
mem=d.get("top_mem") or []
assert cpu, "top_cpu empty — midnight wrap still broken"
assert mem, "top_mem empty — midnight wrap still broken"
names={p.get("name") for p in cpu}
assert "mysqld" in names, names
assert cpu[0]["pid"]==1842
assert cpu[0]["rss_kb"]==412000, cpu[0]
print("ok latest: %s" % [p["name"] for p in cpu])
'

echo "== explicit 23:52 still returns that sample's processes =="
out="$(run_info atop_20260923 23:52 1 8)"
printf '%s' "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("time")=="23:52", d.get("time")
names={p.get("name") for p in (d.get("top_cpu") or [])}
assert "sshd" in names, names
print("ok 23:52:", sorted(names))
'

echo "== missing file still errors without 500-style crash =="
out="$(run_info atop_19990101 latest 1 8)"
printf '%s' "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
assert d.get("top_cpu")==[]
assert "不存在" in (d.get("error") or "")
print("ok missing file")
'

echo "ALL TESTS PASSED"
