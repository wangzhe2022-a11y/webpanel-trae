#!/bin/bash
# Prove the old download helper deadlocks on large stdout, and the new
# streamer copies the file without blocking on stderr.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORKER="$ROOT/bin/fs-worker.php"
JAIL="$(mktemp -d)"
OUT="$(mktemp -d)"
trap 'rm -rf "$JAIL" "$OUT"' EXIT

export DRY_RUN_MARKER="/tmp/wp-fs-no-dryrun-$$"
export VDB_ROOT="$JAIL"

fail() { echo "FAIL: $*" >&2; exit 1; }

echo "== seed 2MiB vdb file (larger than a typical pipe buffer) =="
dd if=/dev/urandom of="$JAIL/big.bin" bs=1024 count=2048 status=none
python3 - <<'PY' "$JAIL/big.bin" > "$OUT/expect.sha"
import hashlib, sys
h = hashlib.sha256()
with open(sys.argv[1], "rb") as f:
    for chunk in iter(lambda: f.read(1024 * 1024), b""):
        h.update(chunk)
print(h.hexdigest())
PY

echo "== old pattern deadlocks (must time out) =="
set +e
timeout 3 php "$ROOT/tests/download/old-deadlock.php" "$WORKER" "$JAIL/big.bin" >"$OUT/old.bin" 2>"$OUT/old.err"
old_rc=$?
set -e
# 124 = timeout(1) killed a hung process — that is the expected outcome.
[ "$old_rc" -eq 124 ] || fail "old pattern was expected to hang (timeout 124), got exit $old_rc"
echo "ok old pattern hung as expected"

echo "== new streamer copies the file =="
php "$ROOT/tests/download/new-stream.php" "$WORKER" >"$OUT/new.bin"
python3 - <<'PY' "$OUT/new.bin" "$OUT/expect.sha"
import hashlib, sys
h = hashlib.sha256()
with open(sys.argv[1], "rb") as f:
    for chunk in iter(lambda: f.read(1024 * 1024), b""):
        h.update(chunk)
expect = open(sys.argv[2]).read().strip()
got = h.hexdigest()
assert got == expect, (got, expect)
print("ok new stream sha256")
PY
[ "$(wc -c < "$OUT/new.bin")" -eq "$(wc -c < "$JAIL/big.bin")" ] || fail "size mismatch"

echo "== new streamer reports worker errors without hanging =="
set +e
php "$ROOT/tests/download/new-stream.php" "$WORKER" /no-such.bin >"$OUT/missing.bin" 2>"$OUT/missing.err"
miss_rc=$?
set -e
[ "$miss_rc" -ne 0 ] || fail "missing file should fail"
grep -q "file not found" "$OUT/missing.bin" "$OUT/missing.err" || fail "missing file error not surfaced"
echo "ok missing file error"

echo "== reap kills a long-running child =="
php "$ROOT/tests/download/reap.php"
echo "ok reap"

echo "ALL TESTS PASSED"
