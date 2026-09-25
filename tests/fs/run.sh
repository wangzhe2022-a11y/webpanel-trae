#!/bin/bash
# vdb host-root: list/cat/extract jailed to VDB_ROOT; mutating ops refused.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORKER="$ROOT/bin/fs-worker.php"
JAIL="$(mktemp -d)"
OUTSIDE="$(mktemp -d)"
trap 'rm -rf "$JAIL" "$OUTSIDE"' EXIT

export DRY_RUN_MARKER="/tmp/wp-fs-no-dryrun-$$"
export VDB_ROOT="$JAIL"

fail() { echo "FAIL: $*" >&2; exit 1; }

run() {
    php "$WORKER" "$@"
}

run_err() {
    # capture stderr JSON error; expect non-zero
    local out
    set +e
    out="$(php "$WORKER" "$@" 2>&1 >/dev/null)"
    local code=$?
    set -e
    [ "$code" -ne 0 ] || fail "expected failure for: $*"
    printf '%s' "$out"
}

echo "== seed fake /mnt/backup =="
mkdir -p "$JAIL/archives" "$JAIL/snapshots/2026-09"
printf 'hello from vdb\n' > "$JAIL/README.txt"
printf 'note\n' > "$JAIL/snapshots/notes.txt"
# zip for extract (zip-slip member must be rejected)
python3 - <<PY
import zipfile, os
jail = os.environ["VDB_ROOT"]
zpath = os.path.join(jail, "archives", "theme-export.zip")
with zipfile.ZipFile(zpath, "w") as z:
    z.writestr("theme/style.css", "body{}\n")
slip = os.path.join(jail, "archives", "slip.zip")
with zipfile.ZipFile(slip, "w") as z:
    z.writestr("../outside.txt", "escaped\n")
PY
printf 'outside\n' > "$OUTSIDE/secret.txt"
ln -s "$OUTSIDE/secret.txt" "$JAIL/escape-link"
ln -s "$OUTSIDE" "$JAIL/escape-dir"

echo "== list root =="
out="$(run list __vdb /)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
names={e["name"] for e in d["entries"]}
assert "archives" in names and "README.txt" in names, names
assert "escape-link" in names
print("ok list root", sorted(names))
'

echo "== list archives =="
out="$(run list __vdb /archives)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("path")=="/archives"
names={e["name"] for e in d["entries"]}
assert "theme-export.zip" in names, names
print("ok list archives")
'

echo "== cat README =="
out="$(run cat __vdb /README.txt)"
printf '%s' "$out" | python3 -c '
import sys
data=sys.stdin.read()
assert data.strip()=="hello from vdb", repr(data)
print("ok cat")
'

echo "== stat README =="
out="$(run stat __vdb /README.txt)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
assert d.get("name")=="README.txt"
assert int(d.get("size",0))==len("hello from vdb\n")
assert d.get("path") in ("/README.txt","README.txt")
print("ok stat", d)
'

echo "== stat directory refused =="
err="$(run_err stat __vdb /archives)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "regular" in d.get("error","") or "not a" in d.get("error",""), d
print("ok stat dir:", d.get("error"))
'

echo "== stat symlink refused =="
err="$(run_err stat __vdb /escape-link)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "symlink" in d.get("error","") or "jail" in d.get("error",""), d
print("ok stat symlink:", d.get("error"))
'

echo "== cat missing file =="
err="$(run_err cat __vdb /no-such-file.bin)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "not found" in d.get("error","") or "parent" in d.get("error",""), d
print("ok cat missing:", d.get("error"))
'

echo "== jail: .. escape =="
err="$(run_err list __vdb /../$(basename "$OUTSIDE"))"
echo "$err" | python3 -c '
import json,sys
raw=sys.stdin.read().strip().splitlines()[-1]
d=json.loads(raw)
assert d.get("ok") is False
assert "jail" in d.get("error","") or "not a directory" in d.get("error","") or "available" in d.get("error","")
print("ok .. escape:", d.get("error"))
'

echo "== jail: symlink file =="
err="$(run_err cat __vdb /escape-link)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "symlink" in d.get("error","") or "jail" in d.get("error","")
print("ok symlink file:", d.get("error"))
'

echo "== search README from vdb root =="
out="$(run search __vdb / README)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
names={h["name"] for h in d["hits"]}
assert "README.txt" in names, names
print("ok vdb search", names)
'

echo "== refuse write/mkdir/delete/compress/read =="
for act in write mkdir delete compress read upload chmod rename; do
    err="$(run_err "$act" __vdb /README.txt 2>/dev/null || true)"
    # run_err already requires non-zero; re-run for message
    err="$(php "$WORKER" "$act" __vdb /README.txt 2>&1 >/dev/null || true)"
    echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "read-only" in d.get("error",""), d
print("ok refuse '"$act"':", d.get("error"))
'
done

echo "== extract zip stays in jail =="
out="$(run extract __vdb /archives/theme-export.zip)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
assert d.get("extracted",0) >= 1
print("ok extract", d)
'
[ -f "$JAIL/archives/theme/style.css" ] || fail "extracted file missing"
[ ! -f "$OUTSIDE/theme/style.css" ] || fail "extract leaked outside"

echo "== extract zip-slip rejected =="
err="$(php "$WORKER" extract __vdb /archives/slip.zip 2>&1 >/dev/null || true)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "zip-slip" in d.get("error","") or "越界" in d.get("error",""), d
print("ok zip-slip:", d.get("error"))
'
[ ! -f "$OUTSIDE/outside.txt" ] || fail "zip-slip wrote outside jail"

echo "== missing backup disk =="
export VDB_ROOT="/tmp/wp-fs-missing-vdb-$$"
err="$(php "$WORKER" list __vdb / 2>&1 >/dev/null || true)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "not available" in d.get("error",""), d
print("ok missing disk:", d.get("error"))
'

echo "== dry-run vdb tree =="
export DRY_RUN_MARKER="/tmp/wp-fs-force-dry-$$"
touch "$DRY_RUN_MARKER"
out="$(php "$WORKER" list __vdb /)"
rm -f "$DRY_RUN_MARKER"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
names={e["name"] for e in d["entries"]}
assert "archives" in names and "snapshots" in names, names
print("ok dry-run vdb tree", sorted(names))
'

echo "ALL TESTS PASSED"
