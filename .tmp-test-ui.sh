#!/bin/bash
# Panel UI end-to-end (dry-run) for the backup/restore page.
set -u
cd /workspace
DATA=/workspace/.tmp-panel-data
rm -rf "$DATA" "$DATA".http.log
mkdir -p "$DATA"

export PANEL_DATA_DIR="$DATA"
export PANEL_DRYRUN=1
export PANEL_BIN_DIR=/workspace/bin

PASS() { echo "PASS: $*"; }
DIE()  { echo "FAIL: $*"; kill "$SRV" 2>/dev/null; exit 1; }

PW='DemoPass12345'
echo "$PW" > "$DATA/admin-pass.txt"
php panel/tools/admin.php password admin < "$DATA/admin-pass.txt" >/dev/null || DIE "admin create"
rm -f "$DATA/admin-pass.txt"

php -S 127.0.0.1:8899 -t panel/public >"$DATA.http.log" 2>&1 &
SRV=$!
sleep 1

J="$DATA/cookies.txt"
BASE=http://127.0.0.1:8899

# --- login ---
csrf=$(curl -s -c "$J" "$BASE/login" | sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p')
[ -n "$csrf" ] || DIE "no csrf token on login page"
r=$(curl -s -b "$J" -c "$J" -d "username=admin&password=$PW&_csrf=$csrf" "$BASE/login")
grep -q '"ok":true' <<<"$r" || DIE "login failed: $r"
PASS "login"

# --- backup page renders ---
page=$(curl -s -b "$J" "$BASE/backup")
grep -q '一键全量备份'      <<<"$page" || DIE "page missing create button"
grep -q '备份文件与配置'    <<<"$page" || DIE "page missing files button"
grep -q '仅备份数据库'      <<<"$page" || DIE "page missing db button"
grep -q '备份记录'          <<<"$page" || DIE "page missing backup table"
grep -q 'webpanel-full-'    <<<"$page" || DIE "page missing demo backup entry"
grep -q '/www/server/backup' <<<"$page" || DIE "page missing backup dir info"
grep -q '任务进行中'        <<<"$page" || DIE "page missing job card"
grep -q 'btn-restore'       <<<"$page" || DIE "page missing restore buttons"
grep -q 'backup/create'     <<<"$page" || DIE "page missing create endpoint"
grep -q 'backup/restore'    <<<"$page" || DIE "page missing restore endpoint"
csrf2=$(sed -n 's/.*name="csrf" content="\([^"]*\)".*/\1/p' <<<"$page")
[ -n "$csrf2" ] || DIE "no csrf on backup page"
PASS "backup page renders (buttons, table, demo data, endpoints)"

# --- nav shows the new menu item ---
nav=$(curl -s -b "$J" "$BASE/")
grep -q '备份恢复' <<<"$nav" || DIE "nav missing backup entry"
grep -q '/backup'  <<<"$nav" || DIE "nav missing /backup href"
PASS "side nav contains 备份恢复"

# --- create (all scopes) ---
for s in full files db; do
    r=$(curl -s -b "$J" -H 'Accept: application/json' -d "scope=$s&_csrf=$csrf2" "$BASE/backup/create")
    grep -q "\"scope\":\"$s\"" <<<"$r" || DIE "create $s failed: $r"
done
r=$(curl -s -b "$J" -H 'Accept: application/json' -d "scope=evil&_csrf=$csrf2" "$BASE/backup/create")
grep -q '"ok":false' <<<"$r" || DIE "invalid scope accepted: $r"
PASS "create full/files/db ok, invalid scope rejected"

# --- status ---
r=$(curl -s -b "$J" -H 'Accept: application/json' "$BASE/backup/status")
grep -q '"state":"idle"' <<<"$r" || DIE "status not idle: $r"
PASS "status endpoint"

# --- restore: wrong confirm rejected, RESTORE accepted, bad name rejected ---
NAME="webpanel-full-$(date +%Y%m%d)-033000.tar.gz"
r=$(curl -s -b "$J" -d "name=$NAME&confirm=WRONG&_csrf=$csrf2" "$BASE/backup/restore")
grep -q 'RESTORE' <<<"$r" || DIE "wrong confirm not rejected: $r"
grep -q '"ok":false' <<<"$r" || DIE "wrong confirm should fail: $r"
r=$(curl -s -b "$J" -d "name=$NAME&confirm=RESTORE&_csrf=$csrf2" "$BASE/backup/restore")
grep -q '"ok":true' <<<"$r" || DIE "restore with RESTORE failed: $r"
r=$(curl -s -b "$J" -d "name=../../etc/passwd&confirm=RESTORE&_csrf=$csrf2" "$BASE/backup/restore")
grep -q '"ok":false' <<<"$r" || DIE "traversal name accepted by restore: $r"
PASS "restore confirm flow"

# --- delete: traversal rejected, valid name ok ---
r=$(curl -s -b "$J" -d "name=../../etc/passwd&_csrf=$csrf2" "$BASE/backup/delete")
grep -q '"ok":false' <<<"$r" || DIE "traversal name accepted by delete: $r"
r=$(curl -s -b "$J" -d "name=$NAME&_csrf=$csrf2" "$BASE/backup/delete")
grep -q '"ok":true' <<<"$r" || DIE "valid delete failed: $r"
PASS "delete flow"

# --- download (dry-run placeholder, csrf required) ---
r=$(curl -s -b "$J" "$BASE/backup/download?name=$NAME&_csrf=$csrf2")
grep -q 'dry-run placeholder' <<<"$r" || DIE "download placeholder missing: $r"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$J" "$BASE/backup/download?name=$NAME")
[ "$code" = "419" ] || DIE "download without csrf should be 419, got $code"
PASS "download flow (auth + csrf)"

# --- unauthenticated access rejected ---
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$BASE/backup/status")
[ "$code" = "401" ] || DIE "unauth ajax status should be 401, got $code"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/backup/status")
[ "$code" = "302" ] || DIE "unauth page status should redirect, got $code"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/backup")
[ "$code" = "302" ] || DIE "unauth page should redirect, got $code"
PASS "unauthenticated access blocked (401 ajax / 302 page)"

kill "$SRV" 2>/dev/null
echo
echo "ALL UI TESTS PASSED"
