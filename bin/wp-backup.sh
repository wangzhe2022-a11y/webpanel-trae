#!/bin/bash
# ============================================================================
# wp-backup.sh - one-click backup / restore (root only via sudo)
#
#   create <full|files|db>    start async backup job (returns immediately)
#   restore <name>            start async restore job (DANGEROUS - overwrites)
#   status                    current job state (json)
#   list                      backup archives (json)
#   delete <name>             delete one archive
#   download <name>           stream archive to stdout
#
# Archives live in /www/server/backup and are named:
#   webpanel-<scope>-YYYYmmdd-HHMMSS[-N].tar.gz
# Archive layout:
#   ./manifest.txt                  scope / host / date
#   ./panel.db                      panel sqlite snapshot (VACUUM INTO / .backup / cp)
#   ./mysql/<db>.sql.gz             per-database MySQL dumps
#   ./postgres/<db>.sql.gz          per-database PostgreSQL dumps
#   ./fpm/phpXX/<user>.conf         site PHP-FPM pools
#   ./node-units/wp-node-*.service  Node.js site units
#   www/...                         site files, certs, vhosts (paths from /)
#
# Jobs run detached (setsid) and report progress through $JOB_STATE; only one
# backup/restore job may run at a time (flock). Database users/passwords are
# NOT part of a backup: on the same machine they already exist, on a new
# machine re-create them via the panel afterwards.
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"
set -o pipefail

BACKUP_ROOT="${BACKUP_ROOT:-/www/server/backup}"
BACKUP_KEEP="${BACKUP_KEEP:-10}"
PANEL_DATA_DIR="${PANEL_DATA_DIR:-/usr/local/webpanel/panel/data}"
PANEL_DB="$PANEL_DATA_DIR/panel.db"
JOB_DIR="$BACKUP_ROOT/.job"
JOB_STATE="$JOB_DIR/state"
JOB_LOCK="$JOB_DIR/lock"
SELF="$(readlink -f "$0")"

# PostgreSQL binaries (PGDG layout first, PATH fallback)
PG_PSQL="${PG_PSQL:-$([ -x /usr/pgsql-16/bin/psql ] && echo /usr/pgsql-16/bin/psql || command -v psql)}"
PG_PSQL="${PG_PSQL:-/usr/pgsql-16/bin/psql}"
PG_DUMP="${PG_DUMP:-$([ -x /usr/pgsql-16/bin/pg_dump ] && echo /usr/pgsql-16/bin/pg_dump || command -v pg_dump)}"
PG_DUMP="${PG_DUMP:-/usr/pgsql-16/bin/pg_dump}"

usage() { fail "usage: wp-backup.sh {create|restore|status|list|delete|download} ..." 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

# only our own generated names (no path separators, no traversal possible)
valid_bkp_name() {
    [[ "$1" =~ ^webpanel-(full|files|db)-[0-9]{8}-[0-9]{6}(-[0-9]{1,3})?\.tar\.gz$ ]]
}

archive_path() { # name -> absolute path, validated
    local n="$1"
    valid_bkp_name "$n" || fail "invalid backup name"
    local f="$BACKUP_ROOT/$n"
    case "$(realpath -m "$f" 2>/dev/null)" in
        "$BACKUP_ROOT"/*) printf '%s' "$f" ;;
        *) fail "invalid backup name" ;;
    esac
}

# ---- job state file (printf per line: values never re-expanded) -----------
job_write() { # STATE
    local st="$1"
    mkdir -p "$JOB_DIR" 2>/dev/null || true
    printf 'STATE=%s\nKIND=%s\nNAME=%s\nPHASE=%s\nPROGRESS=%s\nTOTAL=%s\nSTARTED=%s\nFINISHED=%s\nERROR=%s\n' \
        "$st" "$JOB_KIND" "$JOB_NAME" "$JOB_PHASE" "$JOB_P" "$JOB_T" "$JOB_STARTED" "$JOB_FINISHED" "$JOB_ERROR" \
        > "$JOB_STATE"
    chmod 600 "$JOB_STATE" 2>/dev/null || true
}

sanitize() { # keep state file single-line and quote-free
    local s="$1"
    s="${s//$'\n'/ }"; s="${s//$'\r'/ }"; s="${s//\"/\'}"; s="${s//\$/}"
    printf '%s' "$s"
}

jfail() {
    JOB_ERROR="$(sanitize "$1")"
    JOB_FINISHED="$(date +%s)"
    JOB_PHASE="失败"
    job_write failed
    exit 1
}

json_escape() { printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'; }

fmt_ts() { # epoch -> "YYYY-mm-dd HH:MM:SS" (or empty)
    [ -n "$1" ] && date -d "@$1" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || true
}

# ---- background job launcher (child inherits fd 9 -> holds the flock) ------
spawn_job() { # jobfunc args...   (JOB_KIND/JOB_NAME/JOB_T must be preset)
    mkdir -p "$BACKUP_ROOT" "$JOB_DIR"
    chmod 700 "$BACKUP_ROOT" "$JOB_DIR" 2>/dev/null || true
    exec 9>"$JOB_LOCK"
    flock -n 9 || fail "已有备份/恢复任务正在运行，请稍后再试"
    # write the initial "running" state BEFORE spawning so callers polling
    # right after create/restore can never observe a stale done/failed job
    JOB_P=0; JOB_PHASE="准备"; JOB_STARTED="$(date +%s)"; JOB_FINISHED=""; JOB_ERROR=""
    job_write running
    # setsid -f: always fork so the job survives its parent (sudo/php request)
    setsid -f "$SELF" "$@" >/dev/null 2>&1 </dev/null &
    disown 2>/dev/null || true
}

# ============================================================================
# create
# ============================================================================
cmd_create() {
    [ $# -eq 1 ] || usage
    local scope="$1" name ts n=0
    case "$scope" in
        full|files|db) ;;
        *) fail "invalid scope: $scope（允许 full / files / db）" ;;
    esac
    ts="$(date +%Y%m%d-%H%M%S)"
    name="webpanel-$scope-$ts.tar.gz"
    while [ -e "$BACKUP_ROOT/$name" ]; do
        n=$((n + 1)); [ "$n" -gt 99 ] && fail "cannot allocate backup name"
        name="webpanel-$scope-$ts-$n.tar.gz"
    done

    if is_dry_run; then ok "\"name\":\"$name\""; fi
    JOB_KIND=backup; JOB_NAME="$name"
    case "$scope" in
        full)  JOB_T=6 ;;
        files) JOB_T=4 ;;
        db)    JOB_T=5 ;;
    esac
    spawn_job _job_backup "$name" "$scope"
    ok "\"name\":\"$name\",\"scope\":\"$scope\""
}

# ============================================================================
# restore
# ============================================================================
cmd_restore() {
    [ $# -eq 1 ] || usage
    local name="$1"
    archive_path "$name" >/dev/null
    [ -f "$BACKUP_ROOT/$name" ] || fail "备份文件不存在"
    if is_dry_run; then ok; fi
    JOB_KIND=restore; JOB_NAME="$name"; JOB_T=7
    spawn_job _job_restore "$name"
    ok "\"name\":\"$name\""
}

# ============================================================================
# status / list / delete / download
# ============================================================================
cmd_status() {
    if is_dry_run && [ ! -f "$JOB_STATE" ]; then
        printf '{"ok":true,"job":{"state":"idle"}}\n'; exit 0
    fi
    if [ ! -f "$JOB_STATE" ]; then
        printf '{"ok":true,"job":{"state":"idle"}}\n'; exit 0
    fi
    local STATE KIND NAME PHASE PROGRESS TOTAL STARTED FINISHED ERROR
    while IFS='=' read -r k v; do
        case "$k" in
            STATE) STATE=$v ;; KIND) KIND=$v ;; NAME) NAME=$v ;;
            PHASE) PHASE=$v ;; PROGRESS) PROGRESS=$v ;; TOTAL) TOTAL=$v ;;
            STARTED) STARTED=$v ;; FINISHED) FINISHED=$v ;; ERROR) ERROR=$v ;;
        esac
    done < "$JOB_STATE"

    # stale "running" (job crashed / server rebooted): lock is the truth
    if [ "${STATE:-}" = "running" ]; then
        if exec 9>"$JOB_LOCK" 2>/dev/null && flock -n 9; then
            STATE=failed
            ERROR="任务异常中断（服务器重启或脚本被杀）"
        fi
    fi

    printf '{"ok":true,"job":{"state":"%s","kind":"%s","name":"%s","phase":"%s","progress":%s,"total":%s,"started":"%s","finished":"%s","error":"%s"}}\n' \
        "${STATE:-idle}" "$(json_escape "${KIND:-}")" "$(json_escape "${NAME:-}")" \
        "$(json_escape "${PHASE:-}")" "${PROGRESS:-0}" "${TOTAL:-0}" \
        "$(json_escape "$(fmt_ts "${STARTED:-}")")" "$(json_escape "$(fmt_ts "${FINISHED:-}")")" \
        "$(json_escape "${ERROR:-}")"
}

cmd_list() {
    if is_dry_run; then
        printf '{"ok":true,"dir":"%s","keep":%s,"backups":[{"name":"webpanel-full-20260915-033000.tar.gz","scope":"full","size":284569907,"mtime":"2026-09-15 03:30:00"}]}\n' \
            "$BACKUP_ROOT" "$BACKUP_KEEP"
        exit 0
    fi
    local out="[" first=1 name scope size mtime
    while IFS='|' read -r name size mtime; do
        scope="${name#webpanel-}"; scope="${scope%%-*}"
        [ $first -eq 0 ] && out+=","
        out+="$(printf '{"name":"%s","scope":"%s","size":%s,"mtime":"%s"}' "$name" "$scope" "$size" "$mtime")"
        first=0
    done < <(for f in "$BACKUP_ROOT"/webpanel-*.tar.gz; do
        [ -f "$f" ] || continue
        printf '%s|%s|%s\n' "$(basename "$f")" "$(stat -c %s "$f")" "$(stat -c %Y "$f")"
    done | sort -t'|' -k3,3nr | while IFS='|' read -r n s m; do
        printf '%s|%s|%s\n' "$n" "$s" "$(date -d "@$m" '+%Y-%m-%d %H:%M:%S')"
    done)
    out+="]"
    printf '{"ok":true,"dir":"%s","keep":%s,"backups":%s}\n' "$BACKUP_ROOT" "$BACKUP_KEEP" "$out"
}

cmd_delete() {
    [ $# -eq 1 ] || usage
    local f
    f="$(archive_path "$1")"
    [ -f "$f" ] || fail "备份文件不存在"
    if is_dry_run; then ok; fi
    rm -f "$f" || fail "删除失败"
    ok
}

cmd_download() {
    [ $# -eq 1 ] || usage
    local f
    f="$(archive_path "$1")"
    [ -f "$f" ] || fail "备份文件不存在"
    cat "$f"
}

# ============================================================================
# backup job (runs detached as root)
# ============================================================================
_job_backup() { # name scope
    local name="$1" scope="$2" rc JP=0
    JOB_KIND=backup; JOB_NAME="$name"; JOB_P=0; JOB_ERROR=""
    case "$scope" in
        full)  JOB_T=6 ;;   # panel, fpm/node, mysql, pg, tar, prune
        files) JOB_T=4 ;;   # panel, fpm/node, tar, prune
        db)    JOB_T=5 ;;   # panel, mysql, pg, tar, prune
    esac
    JOB_STARTED="$(date +%s)"; JOB_FINISHED=""
    JOB_PHASE="准备"
    job_write running
    nextstep() { JP=$((JP + 1)); JOB_P=$JP; JOB_PHASE="$1"; job_write running; }

    if is_dry_run; then
        JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"; job_write done; exit 0
    fi

    local stage; stage="$(mktemp -d /tmp/webpanel-bkp.XXXXXX)" || jfail "无法创建临时目录"
    trap 'rm -rf "$stage"' EXIT
    mkdir -p "$stage/mysql" "$stage/postgres" "$stage/fpm" "$stage/node-units"

    # 1. panel sqlite (atomic snapshot, safe while panel is running)
    # VACUUM INTO needs SQLite >= 3.27; AlmaLinux 8 ships 3.26.0 ("near INTO:
    # syntax error"), so fall back to sqlite3 .backup, then cp -a.
    nextstep "备份面板数据"
    if [ -f "$PANEL_DB" ]; then
        local snap="$stage/panel.db" snap_ok=0
        if command -v php >/dev/null 2>&1; then
            if php -r 'try { $d = new PDO("sqlite:" . $argv[1]); $d->exec("VACUUM INTO " . $d->quote($argv[2])); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()); exit(1); }' \
                "$PANEL_DB" "$snap" && [ -s "$snap" ]; then
                snap_ok=1
            fi
        fi
        if [ "$snap_ok" -eq 0 ] && command -v sqlite3 >/dev/null 2>&1; then
            rm -f "$snap"
            if sqlite3 "$PANEL_DB" ".backup '$snap'" && [ -s "$snap" ]; then
                snap_ok=1
            fi
        fi
        if [ "$snap_ok" -eq 0 ]; then
            rm -f "$snap"
            cp -a "$PANEL_DB" "$snap" || jfail "面板数据库快照失败"
        fi
    fi

    # 2. php-fpm pools + node systemd units -> stage
    if [ "$scope" != "db" ]; then
        nextstep "备份 PHP-FPM 与 Node 配置"
        local pd v cf
        for pd in /etc/opt/remi/php*/php-fpm.d; do
            [ -d "$pd" ] || continue
            v="${pd#/etc/opt/remi/php}"; v="${v%%/*}"
            mkdir -p "$stage/fpm/php$v"
            for cf in "$pd"/*.conf; do
                [ -f "$cf" ] && cp -a "$cf" "$stage/fpm/php$v/" || true
            done
        done
        local nu
        for nu in /etc/systemd/system/wp-node-*.service; do
            [ -f "$nu" ] && cp -a "$nu" "$stage/node-units/" || true
        done
    fi

    # 3. mysql dumps (skip cleanly when the server is stopped)
    if [ "$scope" != "files" ]; then
        nextstep "备份 MySQL 数据库"
        if mysql_cmd -e "SELECT 1" >/dev/null 2>&1; then
            local db
            while read -r db; do
                [ -n "$db" ] || continue
                valid_dbname "$db" || continue
                mysqldump --defaults-file=/root/.my.cnf --single-transaction \
                    --routines --triggers --events --databases "$db" 2>/dev/null \
                    | gzip > "$stage/mysql/$db.sql.gz" || jfail "MySQL 备份失败: $db"
            done < <(mysql_cmd -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys');")
        fi
    fi

    # 4. postgres dumps
    if [ "$scope" != "files" ] && [ -x "$PG_DUMP" ] \
        && runuser -u postgres -- "$PG_PSQL" -qAt -c "SELECT 1" >/dev/null 2>&1; then
        nextstep "备份 PostgreSQL 数据库"
        local pdb
        while read -r pdb; do
            [ -n "$pdb" ] || continue
            valid_dbname "$pdb" || continue
            runuser -u postgres -- "$PG_DUMP" --format=plain "$pdb" 2>/dev/null \
                | gzip > "$stage/postgres/$pdb.sql.gz" || jfail "PostgreSQL 备份失败: $pdb"
        done < <(runuser -u postgres -- "$PG_PSQL" -qAt -c \
            "SELECT datname FROM pg_database WHERE NOT datistemplate AND datname <> 'postgres';" 2>/dev/null)
    fi

    # manifest
    {
        echo "scope=$scope"
        echo "created=$(date '+%Y-%m-%d %H:%M:%S')"
        echo "host=$(hostname)"
        echo "panel=webpanel"
    } > "$stage/manifest.txt"

    # 5. tar: stage + www tree (site files / certs / vhosts)
    if [ "$scope" != "db" ]; then
        nextstep "打包压缩（含站点文件与证书）"
    else
        nextstep "打包压缩"
    fi
    local -a wwwsrc=()
    if [ "$scope" != "db" ]; then
        [ -d "$WEB_ROOT" ] && wwwsrc+=("www/wwwroot")
        [ -d "$CERT_ROOT" ] && wwwsrc+=("www/server/certs")
        [ -d "$VHOST_DIR" ] && wwwsrc+=("www/server/panel/vhost")
    fi
    rc=0
    tar -czf "$BACKUP_ROOT/$name" -C / ${wwwsrc+"${wwwsrc[@]}"} -C "$stage" . || rc=$?
    [ "$rc" -le 1 ] || jfail "打包失败（tar 退出码 $rc）"

    # 6. prune old archives (keep newest BACKUP_KEEP)
    nextstep "轮转清理"
    local old
    while read -r old; do
        [ -n "$old" ] && rm -f "$old"
    done < <(ls -1t "$BACKUP_ROOT"/webpanel-*.tar.gz 2>/dev/null | tail -n +$((BACKUP_KEEP + 1)))

    JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"
    job_write done
    exit 0
}

# ============================================================================
# restore job (runs detached as root)
# ============================================================================
_job_restore() { # name
    local name="$1" rc
    JOB_KIND=restore; JOB_NAME="$name"; JOB_P=0; JOB_T=7; JOB_ERROR=""
    JOB_STARTED="$(date +%s)"; JOB_FINISHED=""
    JOB_PHASE="准备"
    job_write running

    if is_dry_run; then
        JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"; job_write done; exit 0
    fi

    local archive="$BACKUP_ROOT/$name"

    # 1. validate
    JOB_P=1; JOB_PHASE="校验备份文件"; job_write running
    [ -f "$archive" ] || jfail "备份文件不存在"
    tar -tzf "$archive" >/dev/null 2>&1 || jfail "备份文件损坏（无法解包）"

    local stage; stage="$(mktemp -d /tmp/webpanel-rst.XXXXXX)" || jfail "无法创建临时目录"
    trap 'rm -rf "$stage"' EXIT
    # everything except the www tree goes to stage
    rc=0
    tar -xzf "$archive" -C "$stage" --exclude=www || rc=$?
    [ "$rc" -le 1 ] || jfail "解包失败（tar 退出码 $rc）"

    # 2. www tree directly back into /
    JOB_P=2; JOB_PHASE="恢复站点文件"; job_write running
    # NB: never use `tar -tzf | grep -q` here - grep -q closes the pipe early,
    # tar dies of SIGPIPE and pipefail turns that into a false negative.
    local listing
    listing="$(tar -tzf "$archive" 2>/dev/null || true)"
    if grep -q '^www/' <<<"$listing"; then
        rc=0
        tar -xzf "$archive" -C / www || rc=$?
        [ "$rc" -le 1 ] || jfail "站点文件恢复失败"
        # re-create missing site users and fix ownership
        local d u
        for d in "$WEB_ROOT"/*; do
            [ -d "$d" ] || continue
            u="$(basename "$d")"
            valid_user "$u" || continue
            if ! id "$u" >/dev/null 2>&1; then
                useradd --no-create-home --home-dir "$d" --shell /sbin/nologin \
                    --user-group "$u" || jfail "重建系统用户失败: $u"
                chown -R "$u:$u" "$d" || jfail "修正目录属主失败: $u"
            fi
        done
    fi

    # 3. panel database (current one is kept aside)
    JOB_P=3; JOB_PHASE="恢复面板数据库"; job_write running
    if [ -f "$stage/panel.db" ]; then
        [ -f "$PANEL_DB" ] && cp -a "$PANEL_DB" "$PANEL_DB.pre-restore.$(date +%s)" || true
        mkdir -p "$PANEL_DATA_DIR"
        install -o webpanel -g webpanel -m 600 "$stage/panel.db" "$PANEL_DB" \
            || jfail "面板数据库恢复失败"
    fi

    # 4. mysql imports (dumps contain CREATE DATABASE + USE)
    JOB_P=4; JOB_PHASE="恢复 MySQL 数据库"; job_write running
    if [ -d "$stage/mysql" ] && mysql_cmd -e "SELECT 1" >/dev/null 2>&1; then
        local f db
        for f in "$stage"/mysql/*.sql.gz; do
            [ -f "$f" ] || continue
            db="$(basename "$f" .sql.gz)"
            valid_dbname "$db" || continue
            gunzip -c "$f" | mysql --defaults-file=/root/.my.cnf \
                || jfail "MySQL 恢复失败: $db"
        done
    fi

    # 5. postgres imports (drop+recreate keeping current owner, then reload)
    JOB_P=5; JOB_PHASE="恢复 PostgreSQL 数据库"; job_write running
    if [ -d "$stage/postgres" ] && [ -x "$PG_DUMP" ] \
        && runuser -u postgres -- "$PG_PSQL" -qAt -c "SELECT 1" >/dev/null 2>&1; then
        local f db owner
        for f in "$stage"/postgres/*.sql.gz; do
            [ -f "$f" ] || continue
            db="$(basename "$f" .sql.gz)"
            valid_dbname "$db" || continue
            owner="$(runuser -u postgres -- "$PG_PSQL" -qAt -c \
                "SELECT pg_get_userbyid(datdba) FROM pg_database WHERE datname='$db';" 2>/dev/null)"
            [ -n "$owner" ] || owner=postgres
            runuser -u postgres -- "$PG_PSQL" -qAt -c \
                "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$db';" >/dev/null 2>&1 || true
            runuser -u postgres -- "$PG_PSQL" -qAt -c "DROP DATABASE IF EXISTS \"$db\";" \
                || jfail "PostgreSQL 删除旧库失败: $db"
            runuser -u postgres -- "$PG_PSQL" -qAt -c "CREATE DATABASE \"$db\" OWNER \"$owner\";" \
                || jfail "PostgreSQL 建库失败: $db"
            gunzip -c "$f" | runuser -u postgres -- "$PG_PSQL" -d "$db" -v ON_ERROR_STOP=1 -q \
                || jfail "PostgreSQL 恢复失败: $db"
        done
    fi

    # 6. fpm pools + node units
    JOB_P=6; JOB_PHASE="恢复 FPM / Node 配置"; job_write running
    local pv svc nu un v
    if command -v systemctl >/dev/null 2>&1; then
        for pv in "$stage"/fpm/*; do
            [ -d "$pv" ] || continue
            v="$(basename "$pv")"
            mkdir -p "/etc/opt/remi/php$v/php-fpm.d"
            cp -a "$pv"/*.conf "/etc/opt/remi/php$v/php-fpm.d/" 2>/dev/null || true
            svc="php$v-php-fpm"
            if systemctl is-enabled "$svc" >/dev/null 2>&1; then
                systemctl restart "$svc" || jfail "重启 $svc 失败"
            fi
        done
        for nu in "$stage"/node-units/wp-node-*.service; do
            [ -f "$nu" ] || continue
            cp -a "$nu" /etc/systemd/system/
        done
        if [ -d "$stage/node-units" ]; then
            systemctl daemon-reload
            for nu in "$stage"/node-units/wp-node-*.service; do
                [ -f "$nu" ] || continue
                un="$(basename "$nu")"
                systemctl enable "$un" >/dev/null 2>&1 || true
                systemctl restart "$un" || jfail "重启 Node 服务失败: $un"
            done
        fi
    fi

    # 7. reload web stack
    JOB_P=7; JOB_PHASE="重载服务"; job_write running
    if command -v nginx >/dev/null 2>&1; then
        nginx -t 2>/dev/null || jfail "nginx 配置校验失败（站点配置可能损坏）"
        if command -v systemctl >/dev/null 2>&1; then
            systemctl reload nginx || jfail "nginx 重载失败"
        fi
    fi

    JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"
    job_write done
    exit 0
}

case "$action" in
    create)   cmd_create "$@" ;;
    restore)  cmd_restore "$@" ;;
    status)   cmd_status ;;
    list)     cmd_list ;;
    delete)   cmd_delete "$@" ;;
    download) cmd_download "$@" ;;
    _job_backup)  _job_backup "$@" ;;
    _job_restore) _job_restore "$@" ;;
    *) usage ;;
esac
