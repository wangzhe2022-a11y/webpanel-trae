#!/bin/bash
# Filename search: jailed subtree, partial match, no symlink follow, caps.
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

export DRY_RUN_MARKER="/tmp/wp-fs-no-dryrun-search-$$"
export WEB_ROOT="$WEB"
export VDB_ROOT="$OUTSIDE"

fail() { echo "FAIL: $*" >&2; exit 1; }

run() { php "$WORKER" "$@"; }

run_err() {
    local out
    set +e
    out="$(php "$WORKER" "$@" 2>&1 >/dev/null)"
    local code=$?
    set -e
    [ "$code" -ne 0 ] || fail "expected failure for: $*"
    printf '%s' "$out"
}

echo "== seed site tree =="
mkdir -p "$WEB/$USER/public/wp-content/plugins/woocommerce-to-wechatapp/includes"
mkdir -p "$WEB/$USER/public/wp-content/plugins/akismet"
mkdir -p "$WEB/$USER/app/node_modules/express"
printf 'plugin\n' > "$WEB/$USER/public/wp-content/plugins/woocommerce-to-wechatapp/includes/class-w2w-auto.php"
printf 'idx\n' > "$WEB/$USER/public/wp-content/plugins/woocommerce-to-wechatapp/includes/index.php"
printf 'ask\n' > "$WEB/$USER/public/wp-content/plugins/akismet/akismet.php"
printf 'cfg\n' > "$WEB/$USER/public/wp-config.php"
printf 'hidden-in-nm\n' > "$WEB/$USER/app/node_modules/express/index.js"
printf 'outside-secret\n' > "$OUTSIDE/secret.txt"
ln -s "$OUTSIDE/secret.txt" "$WEB/$USER/public/escape-link"
ln -s "$OUTSIDE" "$WEB/$USER/public/escape-dir"

echo "== partial name from site root =="
out="$(run search "$USER" / w2w)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True, d
paths={h["path"] for h in d["hits"]}
assert "/public/wp-content/plugins/woocommerce-to-wechatapp/includes/class-w2w-auto.php" in paths, paths
assert all("secret" not in h["path"] for h in d["hits"]), paths
print("ok root partial", sorted(paths))
'

echo "== case-insensitive =="
out="$(run search "$USER" / "WP-CONFIG")"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
names={h["name"] for h in d["hits"]}
assert "wp-config.php" in names, names
print("ok case", names)
'

echo "== current-dir scope (not whole site) =="
out="$(run search "$USER" /public/wp-content/plugins/akismet php)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
paths={h["path"] for h in d["hits"]}
assert "/public/wp-content/plugins/akismet/akismet.php" in paths, paths
assert not any("w2w" in p for p in paths), paths
assert not any(p.endswith("/wp-config.php") for p in paths), paths
print("ok scoped", sorted(paths))
'

echo "== skip node_modules descendants =="
out="$(run search "$USER" / index)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
paths={h["path"] for h in d["hits"]}
assert "/app/node_modules/express/index.js" not in paths, paths
print("ok skip node_modules", sorted(paths))
'

echo "== symlink name can match; target not leaked =="
out="$(run search "$USER" / escape)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
paths={h["path"] for h in d["hits"]}
assert "/public/escape-link" in paths or "/public/escape-dir" in paths, paths
assert all(not h["path"].startswith("/tmp") for h in d["hits"]), paths
print("ok symlink names", sorted(paths))
'
out="$(run search "$USER" / secret)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
paths={h["path"] for h in d["hits"]}
assert not any("secret.txt" in p for p in paths), paths
print("ok no outside secret")
'

echo "== reject path-separator query =="
err="$(run_err search "$USER" / "../secret")"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "query" in d.get("error","") or "invalid" in d.get("error",""), d
print("ok bad query:", d.get("error"))
'

echo "== reject .. directory escape =="
err="$(run_err search "$USER" /.. php)"
echo "$err" | python3 -c '
import json,sys
d=json.loads(sys.stdin.read().strip().splitlines()[-1])
assert d.get("ok") is False
assert "jail" in d.get("error","") or "directory" in d.get("error","") or "parent" in d.get("error",""), d
print("ok dir escape:", d.get("error"))
'

echo "== vdb search is allowed =="
printf 'hello from vdb\n' > "$OUTSIDE/README.txt"
mkdir -p "$OUTSIDE/archives"
printf 'x\n' > "$OUTSIDE/archives/theme-export.zip"
out="$(run search __vdb / README)"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True, d
names={h["name"] for h in d["hits"]}
assert "README.txt" in names, names
print("ok vdb search", names)
'

echo "== dry-run search =="
export DRY_RUN_MARKER="/tmp/wp-fs-force-dry-search-$$"
touch "$DRY_RUN_MARKER"
out="$(php "$WORKER" search "$USER" / theme)"
rm -f "$DRY_RUN_MARKER"
echo "$out" | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") is True
names={h["name"] for h in d["hits"]}
assert "theme.zip" in names, names
print("ok dry-run search", names)
'

echo "ALL SEARCH TESTS PASSED"
