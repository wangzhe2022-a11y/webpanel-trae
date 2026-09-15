#!/bin/bash
# Full end-to-end drill for wp-backup.sh (as root):
# create -> verify archive -> corrupt -> restore -> verify -> list/download/
# delete -> invalid names -> concurrency lock -> prune.
set -u
WP=/workspace/bin/wp-backup.sh
B=/www/server/backup
P=/usr/local/webpanel/panel/data

die() { echo "FAIL: $*"; exit 1; }

job_field() { "$WP" status | sed -n "s/.*\"$1\":\"\{0,1\}\([^\",}]*\)\"\{0,1\}.*/\1/p"; }

wait_job() { # kind max_seconds  (waits for a NEW job of this kind to finish)
    local want="$1" max=$(( ${2:-60} * 2 )) n=0 line st kd
    while :; do
        line=$("$WP" status)
        st=$(sed -n 's/.*"state":"\([a-z]*\)".*/\1/p' <<<"$line")
        kd=$(sed -n 's/.*"kind":"\([a-z]*\)".*/\1/p' <<<"$line")
        if [ "$kd" = "$want" ]; then
            [ "$st" = "done" ] && return 0
            [ "$st" = "failed" ] && { echo "$line"; return 1; }
        fi
        n=$((n+1)); [ $n -gt $max ] && { echo TIMEOUT; echo "$line"; return 1; }
        sleep 0.5
    done
}

# ---------- setup fake production state ----------
rm -rf /www /usr/local/webpanel/panel/data
id webpanel &>/dev/null || useradd --system --no-create-home --shell /sbin/nologin webpanel
id shopdemo &>/dev/null || useradd --no-create-home --home-dir /www/wwwroot/shopdemo --shell /sbin/nologin --user-group shopdemo

mkdir -p /www/wwwroot/shopdemo/public /www/server/certs/example.com /www/server/panel/vhost "$P"
echo '<?php echo "original site";' > /www/wwwroot/shopdemo/public/index.php
echo "CERT-ORIGINAL" > /www/server/certs/example.com/fullchain.pem
echo "KEY-ORIGINAL" > /www/server/certs/example.com/privkey.pem
echo "server_name shopdemo;" > /www/server/panel/vhost/shopdemo.conf
chown -R shopdemo:shopdemo /www/wwwroot/shopdemo
sqlite3 "$P/panel.db" "CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT); INSERT INTO users VALUES (1,'admin');"
chmod 600 "$P/panel.db"

echo "=== 1. create files backup ==="
out=$("$WP" create files) || die "create failed: $out"
echo "$out"
name=$(sed -n 's/.*"name":"\([^"]*\)".*/\1/p' <<<"$out")
wait_job backup 60 || die "backup job did not finish"
[ -f "$B/$name" ] || die "archive missing: $name"
echo "archive OK: $name ($(stat -c %s "$B/$name") bytes)"
tar -tzf "$B/$name" | grep -q 'www/wwwroot/shopdemo/public/index.php' || die "site file not in archive"
tar -tzf "$B/$name" | grep -q '^\./panel.db' || die "panel.db not in archive"
tar -tzf "$B/$name" | grep -q 'www/server/certs/example.com/fullchain.pem' || die "cert not in archive"

echo "=== 2. corrupt current state ==="
echo '<?php echo "TAMPERED";' > /www/wwwroot/shopdemo/public/index.php
rm -f /www/server/panel/vhost/shopdemo.conf /www/server/certs/example.com/fullchain.pem
sqlite3 "$P/panel.db" "INSERT INTO users VALUES (2,'intruder');"
grep -q TAMPERED /www/wwwroot/shopdemo/public/index.php || die "tamper failed"

echo "=== 3. restore ==="
out=$("$WP" restore "$name") || die "restore failed: $out"
wait_job restore 60 || die "restore job did not finish"

echo "=== 4. verify restoration ==="
grep -q 'original site' /www/wwwroot/shopdemo/public/index.php || die "index.php NOT restored"
grep -qx 'CERT-ORIGINAL' /www/server/certs/example.com/fullchain.pem || die "cert NOT restored"
grep -q 'server_name shopdemo;' /www/server/panel/vhost/shopdemo.conf || die "vhost NOT restored"
n=$(sqlite3 "$P/panel.db" "SELECT COUNT(*) FROM users;")
[ "$n" = "1" ] || die "panel.db NOT restored (users=$n)"
[ "$(stat -c %U /www/wwwroot/shopdemo/public/index.php)" = "shopdemo" ] || die "file owner lost"
echo "restoration verified (files, certs, vhost, panel.db, ownership)"

echo "=== 5. list / download / delete ==="
"$WP" list
dl=/workspace/.tmp-download-test.tar.gz
"$WP" download "$name" > "$dl" || die "download failed"
cmp -s "$dl" "$B/$name" || die "downloaded archive differs"
echo "download byte-identical: $(stat -c %s "$dl") bytes"
"$WP" delete "$name" || die "delete failed"
[ -f "$B/$name" ] && die "archive not deleted"

echo "=== 6. invalid names rejected ==="
"$WP" delete '../../etc/passwd' 2>&1 | grep -q '"ok":false' || die "traversal name accepted"
"$WP" delete 'webpanel-full-99999999-999999.tar.gz' >/dev/null 2>&1; echo "missing-file rc=$? (nonzero expected)"

echo "=== 7. concurrency lock ==="
dd if=/dev/urandom of=/www/wwwroot/shopdemo/public/big.bin bs=1M count=80 2>/dev/null
out1=$("$WP" create files) || die "first create failed"
"$WP" create files >/workspace/.tmp-second.out 2>&1 && die "second create should have failed"
grep -q '"ok":false' /workspace/.tmp-second.out || die "unexpected second-create error"
echo "concurrent create correctly rejected: $(cat /workspace/.tmp-second.out)"
wait_job backup 120 || die "big backup job did not finish"

echo "=== 8. prune (keep newest BACKUP_KEEP) ==="
out2=$("$WP" create files) || die "second backup failed"
wait_job backup 60 || die "prune test backup did not finish"
echo "default keep=10 archives on disk: $(ls -1 "$B"/webpanel-*.tar.gz | wc -l)"
BACKUP_KEEP=1 "$WP" create files >/dev/null || die "third backup failed"
wait_job backup 60 || die "keep=1 backup did not finish"
cnt=$(ls -1 "$B"/webpanel-*.tar.gz 2>/dev/null | wc -l)
[ "$cnt" -le 1 ] || die "prune failed: $cnt archives remain"
echo "prune OK, remaining: $cnt"

echo "=== 9. stale-running detection ==="
printf 'STATE=running\nKIND=backup\nNAME=fake\nPHASE=x\nPROGRESS=1\nTOTAL=6\nSTARTED=1\nFINISHED=\nERROR=\n' > "$B/.job/state"
"$WP" status | grep -q '"state":"failed"' || die "stale running not detected as failed"
"$WP" status | grep -q '异常中断' || die "stale reason missing"
echo "stale running correctly reported as failed"

echo
echo "ALL DRILL TESTS PASSED"
