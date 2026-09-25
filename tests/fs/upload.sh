#!/bin/bash
# Site-jail upload: same-name files are overwritten; dirs/symlinks/vdb stay refused.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORKER="$ROOT/bin/fs-worker.php"
WEB="$(mktemp -d)"
OUTSIDE="$(mktemp -d)"
trap 'rm -rf "$WEB" "$OUTSIDE"' EXIT

USER="$(id -un)"
if ! [[ "$USER" =~ ^[a-z][a-z0-9_]{2,30}$ ]]; then
    echo "SKIP: current user '$USER' is not a valid site-user name" >&2
    exit 0
fi

export DRY_RUN_MARKER="/tmp/wp-fs-no-dryrun-upload-$$"
export WEB_ROOT="$WEB"
export VDB_ROOT="$OUTSIDE"

fail() { echo "FAIL: $*" >&2; exit 1; }

run() {
    php "$WORKER" "$@"
}

run_err() {
    local out
    set +e
    out="$(php "$WORKER" "$@" 2>&1 >/dev/null)"
    local code=$?
    set -e
    [ "$code" -ne 0 ] || fail "expected failure for: $*"
    printf '%s' "$out"
}

echo "== seed site jail =="
mkdir -p "$WEB/$USER/public/includes" "$WEB/$USER/public/plugins"
printf 'OLD PLUGIN BODY\n' > "$WEB/$USER/public/includes/class-w2w-auto.php"
printf 'keep me\n' > "$WEB/$USER/public/includes/other.php"
mkdir -p "$WEB/$USER/public/includes/keepdir"
ln -s "$OUTSIDE/secret.txt" "$WEB/$USER/public/includes/escape-link"
printf 'outside-secret\n' > "$OUTSIDE/secret.txt"

echo "== first upload (new name) =="
printf 'brand new\n' > "$OUTSIDE/new-upload.txt"
out="$(run upload "$USER" /public/includes "$OUTSIDE/new-upload.txt" brand-new.txt)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
assert d.get("name")=="brand-new.txt"
assert int(d.get("size",0))==len("brand new\n")
print("ok first upload", d)
'
[ -f "$WEB/$USER/public/includes/brand-new.txt" ] || fail "new file missing"
[ "$(cat "$WEB/$USER/public/includes/brand-new.txt")" = "brand new" ] || fail "new file content"
[ ! -f "$OUTSIDE/new-upload.txt" ] || fail "temp file should be consumed"

echo "== overwrite same-name file =="
printf 'NEW PLUGIN BODY v2\n' > "$OUTSIDE/replace.txt"
out="$(run upload "$USER" /public/includes "$OUTSIDE/replace.txt" class-w2w-auto.php)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True, d
assert d.get("name")=="class-w2w-auto.php"
assert int(d.get("size",0))==len("NEW PLUGIN BODY v2\n")
print("ok overwrite", d)
'
got="$(cat "$WEB/$USER/public/includes/class-w2w-auto.php")"
[ "$got" = "NEW PLUGIN BODY v2" ] || fail "overwrite did not replace contents: $got"
[ "$(cat "$WEB/$USER/public/includes/other.php")" = "keep me" ] || fail "sibling file was touched"
[ -d "$WEB/$USER/public/includes/keepdir" ] || fail "sibling dir missing"
[ ! -f "$OUTSIDE/replace.txt" ] || fail "overwrite temp should be consumed"

echo "== refuse overwrite of a directory =="
printf 'nope\n' > "$OUTSIDE/dir-clash.txt"
err="$(run_err upload "$USER" /public/includes "$OUTSIDE/dir-clash.txt" keepdir)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "directory" in d.get("error",""), d
print("ok dir clash:", d.get("error"))
'
[ -d "$WEB/$USER/public/includes/keepdir" ] || fail "directory was replaced"
[ -f "$OUTSIDE/dir-clash.txt" ] || fail "failed upload should leave temp file"

echo "== refuse overwrite of a symlink =="
printf 'hijack\n' > "$OUTSIDE/link-clash.txt"
err="$(run_err upload "$USER" /public/includes "$OUTSIDE/link-clash.txt" escape-link)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "symlink" in d.get("error","") or "jail" in d.get("error",""), d
print("ok symlink clash:", d.get("error"))
'
[ -L "$WEB/$USER/public/includes/escape-link" ] || fail "symlink was replaced"
[ "$(cat "$OUTSIDE/secret.txt")" = "outside-secret" ] || fail "symlink target was written"

echo "== refuse path-traversal name =="
printf 'x\n' > "$OUTSIDE/trav.txt"
err="$(run_err upload "$USER" /public/includes "$OUTSIDE/trav.txt" '../secret.txt')"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "invalid" in d.get("error",""), d
print("ok traversal name:", d.get("error"))
'
[ ! -f "$WEB/secret.txt" ] || fail "traversal wrote outside site user"

echo "== refuse .. directory escape =="
printf 'x\n' > "$OUTSIDE/esc.txt"
err="$(run_err upload "$USER" /.. "$OUTSIDE/esc.txt" leaked.txt)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "jail" in d.get("error","") or "directory" in d.get("error","") or "parent" in d.get("error",""), d
print("ok dir escape:", d.get("error"))
'
[ ! -f "$WEB/leaked.txt" ] || fail "upload escaped site jail"

echo "== vdb upload still read-only =="
err="$(php "$WORKER" upload __vdb / "$OUTSIDE/esc.txt" leaked.txt 2>&1 >/dev/null || true)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "read-only" in d.get("error",""), d
print("ok vdb refuse:", d.get("error"))
'

echo "ALL UPLOAD TESTS PASSED"
