#!/bin/bash
# ============================================================================
# wp-installatron.sh - Installatron Server integration (root only via sudo)
#
#   status                  install state + version (json)
#   install                 start async install job (license key on STDIN)
#   upgrade                 start async upgrade job
#   uninstall [--purge]     remove Installatron (--purge also wipes app data)
#   login                   one-time GUI login url (json)
#   job                     async job state (json)
#
# Installatron Server is the standalone edition of Installatron built to hook
# into custom hosting systems (https://installatron.com/server):
#   installer : https://data.installatron.com/installatron-server.sh
#   programs  : /usr/local/installatron/
#   app data  : /var/installatron/  (kept on uninstall unless --purge)
#
# Defensive integration on this panel:
#   * /etc/nginx is tar-backed-up before install/upgrade and restored if
#     `nginx -t` fails afterwards (Installatron manages its own server block
#     inside /etc/nginx/nginx.conf).
#   * The panel's existing MySQL 8 is reused (--db-host=127.0.0.1) instead of
#     letting the installer provision a second database server.
#   * The license key travels on stdin (never in argv of this script) and is
#     wiped from disk as soon as the installer reads it.
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"
set -o pipefail

ITRON_BIN="/usr/local/installatron/installatron"
STATE_DIR="/usr/local/webpanel/installatron"
JOB_DIR="$STATE_DIR/.job"
JOB_STATE="$JOB_DIR/state"
JOB_LOCK="$JOB_DIR/lock"
NGINX_BACKUP_DIR="/www/server/backup"
SELF="$(readlink -f "$0")"

usage() { fail "usage: wp-installatron.sh {status|install|upgrade|uninstall [--purge]|login|job}" 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

valid_license_key() {
    [[ "$1" =~ ^[A-Za-z0-9][A-Za-z0-9-]{7,63}$ ]]
}

# ---- job state file (printf per line: values never re-expanded) ------------
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
    mkdir -p "$STATE_DIR" "$JOB_DIR"
    chmod 700 "$STATE_DIR" "$JOB_DIR" 2>/dev/null || true
    exec 9>"$JOB_LOCK"
    flock -n 9 || fail "已有安装/升级任务正在运行，请稍后再试"
    # write the initial "running" state BEFORE spawning so callers polling
    # right after install/upgrade can never observe a stale done/failed job
    JOB_P=0; JOB_PHASE="准备"; JOB_STARTED="$(date +%s)"; JOB_FINISHED=""; JOB_ERROR=""
    job_write running
    # setsid -f: always fork so the job survives its parent (sudo/php request)
    setsid -f "$SELF" "$@" >/dev/null 2>&1 </dev/null &
    disown 2>/dev/null || true
}

# ============================================================================
# status
# ============================================================================
cmd_status() {
    if is_dry_run; then
        # /tmp marker lets the dry-run UI flip between both page states
        if [ -f /tmp/wp-dry-installatron-installed ]; then
            ok "\"installed\":true,\"version\":\"5.0.1-dryrun\""
        fi
        ok "\"installed\":false"
    fi
    if [ ! -x "$ITRON_BIN" ]; then
        ok "\"installed\":false"
    fi
    local raw version
    raw="$("$ITRON_BIN" --GET /version 2>/dev/null || true)"
    version="$(printf '%s' "$raw" | sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)"
    [ -n "$version" ] || version=""
    ok "\"installed\":true,\"version\":\"$(json_escape "$version")\""
}

# ============================================================================
# install (license key on stdin)
# ============================================================================
cmd_install() {
    local key
    key="$(head -n1)"
    key="${key%%$'\r'}"
    valid_license_key "$key" || fail "无效的 License Key（installatron.com → My Account → License Key）"
    if is_dry_run; then
        # dry-run has no /usr/local/installatron binary: use the UI marker
        [ -f /tmp/wp-dry-installatron-installed ] \
            && fail "Installatron 已安装，如需重装请先卸载"
    elif [ -x "$ITRON_BIN" ]; then
        fail "Installatron 已安装，如需重装请先卸载"
    fi
    mkdir -p "$STATE_DIR" "$JOB_DIR"
    chmod 700 "$STATE_DIR" "$JOB_DIR" 2>/dev/null || true
    printf '%s' "$key" > "$JOB_DIR/.key"
    chmod 600 "$JOB_DIR/.key"
    JOB_KIND=install; JOB_NAME="Installatron Server"; JOB_T=6
    spawn_job _job_install
    ok
}

# ============================================================================
# upgrade
# ============================================================================
cmd_upgrade() {
    [ $# -eq 0 ] || usage
    if is_dry_run; then
        # dry-run has no /usr/local/installatron binary: use the UI marker
        [ -f /tmp/wp-dry-installatron-installed ] || fail "Installatron 未安装"
    else
        [ -x "$ITRON_BIN" ] || fail "Installatron 未安装"
    fi
    JOB_KIND=upgrade; JOB_NAME="Installatron Server"; JOB_T=4
    spawn_job _job_upgrade
    ok
}

# ============================================================================
# uninstall [--purge]
# ============================================================================
cmd_uninstall() {
    local purge=0
    if [ "${1:-}" = "--purge" ]; then purge=1; shift; fi
    [ $# -eq 0 ] || usage
    if is_dry_run; then ok; fi
    [ -x "$ITRON_BIN" ] || fail "Installatron 未安装"
    rpm -e installatron-server >/dev/null 2>&1 || true
    rm -rf /usr/local/installatron
    rm -f /etc/cron.d/installatron
    if [ "$purge" = "1" ]; then
        rm -rf /var/installatron
    fi
    if command -v nginx >/dev/null 2>&1; then
        if ! nginx -t >/dev/null 2>&1; then
            fail "卸载完成，但 /etc/nginx 中仍有 Installatron 残留配置导致校验失败，请手动检查"
        fi
        systemctl reload nginx >/dev/null 2>&1 || true
    fi
    ok
}

# ============================================================================
# login (one-time GUI session url)
# ============================================================================
cmd_login() {
    if is_dry_run; then
        # dry-run has no /usr/local/installatron binary: use the UI marker
        [ -f /tmp/wp-dry-installatron-installed ] || fail "Installatron 未安装"
        ok "\"url\":\"/installatron/demo\""
    fi
    [ -x "$ITRON_BIN" ] || fail "Installatron 未安装"
    local out url
    out="$("$ITRON_BIN" --POST /users/root/login 2>/dev/null || true)"
    if [ -z "$out" ]; then
        out="$("$ITRON_BIN" --GET /users/root/login 2>/dev/null || true)"
    fi
    url="$(printf '%s' "$out" | sed -n 's/.*"data"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)"
    [ -n "$url" ] || fail "无法创建控制台会话（Installatron 服务可能未就绪）"
    ok "\"url\":\"$(json_escape "$url")\""
}

# ============================================================================
# job (poll async task state)
# ============================================================================
cmd_job() {
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

# ============================================================================
# install job (runs detached as root)
# ============================================================================
_job_install() {
    local key ts stage dbpass JP=0
    key="$(cat "$JOB_DIR/.key" 2>/dev/null)"
    rm -f "$JOB_DIR/.key"   # wipe from disk as soon as it is read
    JOB_KIND=install; JOB_NAME="Installatron Server"; JOB_P=0; JOB_ERROR=""
    JOB_T=6   # nginx backup, db, download, install, nginx check, verify
    JOB_STARTED="$(date +%s)"; JOB_FINISHED=""
    JOB_PHASE="准备"
    job_write running
    nextstep() { JP=$((JP + 1)); JOB_P=$JP; JOB_PHASE="$1"; job_write running; }

    if is_dry_run; then
        JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"; job_write done; exit 0
    fi

    valid_license_key "$key" || jfail "License Key 无效"
    stage="$(mktemp -d /tmp/webpanel-itr.XXXXXX)" || jfail "无法创建临时目录"
    trap 'rm -rf "$stage"' EXIT
    ts="$(date +%Y%m%d-%H%M%S)"

    # 1. nginx safety net
    nextstep "备份 Nginx 配置"
    mkdir -p "$NGINX_BACKUP_DIR"
    tar -czf "$NGINX_BACKUP_DIR/nginx-pre-installatron-$ts.tar.gz" -C / etc/nginx \
        || jfail "Nginx 配置备份失败"

    # 2. reuse the panel's MySQL (installer would otherwise provision its own)
    nextstep "准备数据库"
    dbpass="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 28)"
    mysql_cmd <<SQL || jfail "MySQL 预建库失败（请确认 MySQL 正在运行）"
CREATE DATABASE IF NOT EXISTS installatron CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS 'installatron'@'127.0.0.1' IDENTIFIED BY '$dbpass';
ALTER USER 'installatron'@'127.0.0.1' IDENTIFIED BY '$dbpass';
CREATE USER IF NOT EXISTS 'installatron'@'localhost' IDENTIFIED BY '$dbpass';
ALTER USER 'installatron'@'localhost' IDENTIFIED BY '$dbpass';
GRANT ALL PRIVILEGES ON installatron.* TO 'installatron'@'127.0.0.1';
GRANT ALL PRIVILEGES ON installatron.* TO 'installatron'@'localhost';
FLUSH PRIVILEGES;
SQL
    printf 'db-host=127.0.0.1\ndb-user=installatron\ndb-name=installatron\n' > "$STATE_DIR/db.ini"
    chmod 600 "$STATE_DIR/db.ini"

    # 3. fetch the official installer
    nextstep "下载官方安装器"
    curl -fsSL -o "$stage/installatron-server.sh" \
        https://data.installatron.com/installatron-server.sh \
        || jfail "下载安装器失败（请检查服务器外网连接）"
    chmod +x "$stage/installatron-server.sh"

    # 4. run it
    nextstep "执行安装（约数分钟）"
    rpm -q httpd >/dev/null 2>&1 && yum -y remove httpd >/dev/null 2>&1
    "$stage/installatron-server.sh" -f --key "$key" \
        --db-host=127.0.0.1 --db-user=installatron --db-pass="$dbpass" --db-name=installatron \
        > "$stage/install.log" 2>&1 \
        || jfail "安装器执行失败：$(sanitize "$(tail -n 3 "$stage/install.log" | tr '\n' ' ' | cut -c1-200)")"
    [ -x "$ITRON_BIN" ] || jfail "安装器已结束但未找到程序（详见 $stage/install.log）"

    # 5. nginx config sanity - roll back to the pre-install backup on failure
    nextstep "校验 Nginx 配置"
    if command -v nginx >/dev/null 2>&1; then
        if ! nginx -t >/dev/null 2>&1; then
            tar -xzf "$NGINX_BACKUP_DIR/nginx-pre-installatron-$ts.tar.gz" -C / \
                || jfail "nginx 配置校验失败且自动回滚失败，请手动检查 /etc/nginx"
            nginx -t >/dev/null 2>&1 \
                || jfail "nginx 配置回滚后仍校验失败，请手动检查 /etc/nginx"
            systemctl reload nginx >/dev/null 2>&1 || true
            jfail "安装器修改的 nginx 配置未通过校验，已回滚安装前配置（详见 $stage/install.log）"
        fi
        systemctl reload nginx >/dev/null 2>&1 || true
    fi

    # 6. verify
    nextstep "验证安装"
    "$ITRON_BIN" --GET /version >/dev/null 2>&1 \
        || jfail "安装完成但服务未就绪（稍后在本页刷新重试）"

    JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"
    job_write done
    exit 0
}

# ============================================================================
# upgrade job (runs detached as root)
# ============================================================================
_job_upgrade() {
    local ts stage JP=0
    JOB_KIND=upgrade; JOB_NAME="Installatron Server"; JOB_P=0; JOB_ERROR=""
    JOB_T=4   # nginx backup, download, upgrade, check+verify
    JOB_STARTED="$(date +%s)"; JOB_FINISHED=""
    JOB_PHASE="准备"
    job_write running
    nextstep() { JP=$((JP + 1)); JOB_P=$JP; JOB_PHASE="$1"; job_write running; }

    if is_dry_run; then
        JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"; job_write done; exit 0
    fi

    [ -x "$ITRON_BIN" ] || jfail "Installatron 未安装"
    stage="$(mktemp -d /tmp/webpanel-itu.XXXXXX)" || jfail "无法创建临时目录"
    trap 'rm -rf "$stage"' EXIT
    ts="$(date +%Y%m%d-%H%M%S)"

    nextstep "备份 Nginx 配置"
    mkdir -p "$NGINX_BACKUP_DIR"
    tar -czf "$NGINX_BACKUP_DIR/nginx-pre-installatron-upg-$ts.tar.gz" -C / etc/nginx \
        || jfail "Nginx 配置备份失败"

    nextstep "下载官方安装器"
    curl -fsSL -o "$stage/installatron-server.sh" \
        https://data.installatron.com/installatron-server.sh \
        || jfail "下载安装器失败（请检查服务器外网连接）"
    chmod +x "$stage/installatron-server.sh"

    nextstep "执行升级"
    "$stage/installatron-server.sh" -f --quick \
        > "$stage/upgrade.log" 2>&1 \
        || jfail "升级失败：$(sanitize "$(tail -n 3 "$stage/upgrade.log" | tr '\n' ' ' | cut -c1-200)")"

    nextstep "校验与验证"
    if command -v nginx >/dev/null 2>&1; then
        if ! nginx -t >/dev/null 2>&1; then
            tar -xzf "$NGINX_BACKUP_DIR/nginx-pre-installatron-upg-$ts.tar.gz" -C / \
                || jfail "nginx 配置校验失败且自动回滚失败，请手动检查 /etc/nginx"
            nginx -t >/dev/null 2>&1 \
                || jfail "nginx 配置回滚后仍校验失败，请手动检查 /etc/nginx"
            systemctl reload nginx >/dev/null 2>&1 || true
            jfail "升级修改的 nginx 配置未通过校验，已回滚升级前配置"
        fi
        systemctl reload nginx >/dev/null 2>&1 || true
    fi
    "$ITRON_BIN" --GET /version >/dev/null 2>&1 \
        || jfail "升级完成但版本查询失败（服务可能仍在重启）"

    JOB_FINISHED="$(date +%s)"; JOB_PHASE="完成"
    job_write done
    exit 0
}

case "$action" in
    status)         cmd_status ;;
    install)        cmd_install ;;
    upgrade)        cmd_upgrade ;;
    uninstall)      cmd_uninstall "$@" ;;
    login)          cmd_login ;;
    job)            cmd_job ;;
    _job_install)   _job_install "$@" ;;
    _job_upgrade)   _job_upgrade "$@" ;;
    *) usage ;;
esac
