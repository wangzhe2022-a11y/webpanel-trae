#!/bin/bash
# ============================================================================
# wp-node.sh - Node.js site lifecycle (root only via sudo)
#
#   create  <user> <domains_csv> <port> <start_cmd>
#   start|stop|restart <user>
#   npmi    <user>              run "npm install" in the app dir as the site user
#   remove  <user>              stop + disable + delete the systemd unit
#
# The app itself runs as the site's low-privilege system user, bound to
# 127.0.0.1:<port>; nginx proxies all site traffic to it. Start commands are
# executed by systemd directly (no shell), and only known runtimes are allowed.
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-node.sh {create|start|stop|restart|npmi|remove} ..." 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

cmd_create() {
    [ $# -eq 4 ] || fail "usage: create <user> <domains_csv> <port> <start_cmd>"
    local user="$1" domains="$2" port="$3" startcmd="$4"
    valid_user "$user" || fail "invalid system user name: $user"
    valid_port "$port" || fail "端口需为 1024-65535 之间的数字"
    valid_node_cmd "$startcmd" || fail "启动命令不合法（仅允许 node/npm/npx/yarn/pnpm/bun/deno 开头，参数仅限字母数字和 ._/=:@-）"

    local IFS=',' d
    for d in $domains; do
        d="${d,,}"
        valid_domain "$d" || fail "invalid domain: $d"
    done

    if ! is_dry_run; then
        id "$user" &>/dev/null && fail "system user already exists: $user"
        [ -e "$VHOST_DIR/$user.conf" ] && fail "vhost already exists: $user"
        [ -e "$NODE_UNIT_DIR/$(node_unit "$user")" ] && fail "node service already exists: $user"
        port_in_use "$port" && fail "端口 $port 已被其他站点占用，请换一个"
    fi

    # 1. system user + directories (app dir + a public dir kept for acme challenges)
    dr useradd --no-create-home --home-dir "$WEB_ROOT/$user" \
        --shell /sbin/nologin --user-group "$user" || fail "failed to create system user: $user"
    dr mkdir -p "$(appdir_of "$user")" "$(docroot_of "$user")" "$LOG_ROOT" || fail "cannot create app dir"
    dr chown -R "$user:$user" "$WEB_ROOT/$user" || fail "cannot chown app dir"
    dr chmod 750 "$WEB_ROOT/$user"
    dr touch "$LOG_ROOT/$user.log" "$LOG_ROOT/$user.error.log"
    dr chown nginx: "$LOG_ROOT/$user.log" "$LOG_ROOT/$user.error.log" 2>/dev/null || \
        dr chown root:root "$LOG_ROOT/$user.log" "$LOG_ROOT/$user.error.log"

    # 2. systemd unit for the app
    local unit="$NODE_UNIT_DIR/$(node_unit "$user")"
    if is_dry_run; then
        echo "[dry-run] write unit $unit (port=$port cmd=$startcmd)" >&2
    else
        sed -e "s|{{USER}}|$user|g" \
            -e "s|{{APPDIR}}|$(appdir_of "$user")|g" \
            -e "s|{{PORT}}|$port|g" \
            -e "s|{{STARTCMD}}|$startcmd|g" \
            "$NODE_TPL" > "$unit" || fail "cannot write systemd unit"
        chmod 644 "$unit"
    fi
    dr systemctl daemon-reload
    dr systemctl enable --now "$(node_unit "$user")"

    # 3. nginx reverse-proxy vhost
    render_vhost "$user" "$domains" 0 0 node "$port"
    reload_nginx

    ok "\"user\":\"$user\",\"appdir\":\"$(appdir_of "$user")\",\"port\":$port,\"unit\":\"$(node_unit "$user")\""
}

svc_ctl() {
    [ $# -eq 1 ] || fail "usage: ${action} <user>"
    local user="$1" unit
    valid_user "$user" || fail "invalid system user: $user"
    unit="$(node_unit "$user")"
    if ! is_dry_run && [ ! -f "$NODE_UNIT_DIR/$unit" ]; then
        fail "node service not found: $unit"
    fi
    dr systemctl "$action" "$unit"
    ok "\"unit\":\"$unit\",\"action\":\"$action\""
}

cmd_npmi() {
    [ $# -eq 1 ] || fail "usage: npmi <user>"
    local user="$1" appdir
    valid_user "$user" || fail "invalid system user: $user"
    appdir="$(appdir_of "$user")"

    if is_dry_run; then
        echo "[dry-run] npm install in $appdir as $user" >&2
        ok
    fi

    id "$user" &>/dev/null || fail "system user does not exist: $user"
    [ -f "$appdir/package.json" ] || fail "未找到 $appdir/package.json，请先在文件管理中上传项目代码"
    dr runuser -u "$user" -- npm install --prefix "$appdir" --no-audit --no-fund \
        || fail "npm install 失败（请检查 package.json / 网络）"
    dr systemctl restart "$(node_unit "$user")"
    ok
}

cmd_remove() {
    [ $# -eq 1 ] || fail "usage: remove <user>"
    local user="$1" unit
    valid_user "$user" || fail "invalid system user: $user"
    unit="$(node_unit "$user")"
    dr systemctl disable --now "$unit" 2>/dev/null
    dr rm -f "$NODE_UNIT_DIR/$unit"
    dr systemctl daemon-reload
    ok
}

case "$action" in
    create)  cmd_create "$@" ;;
    start)   svc_ctl "$@" ;;
    stop)    svc_ctl "$@" ;;
    restart) svc_ctl "$@" ;;
    npmi)    cmd_npmi "$@" ;;
    remove)  cmd_remove "$@" ;;
    *) usage ;;
esac
