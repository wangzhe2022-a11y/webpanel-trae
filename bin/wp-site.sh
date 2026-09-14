#!/bin/bash
# ============================================================================
# wp-site.sh - website / vhost / PHP-FPM pool lifecycle (root only via sudo)
#
#   create   <user> <phpver> <domains_csv> [max_children]
#   delete   <user> [purge]
#   php-set  <user> <phpver>
#   render   <user> <domains_csv> <ssl> <hsts>
#
# Passwords never pass through here. Secrets are read only by sibling scripts.
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-site.sh {create|delete|php-set|render} ..." 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

cmd_create() {
    [ $# -ge 3 ] || fail "usage: create <user> <phpver> <domains_csv> [max_children]"
    local user="$1" phpver="$2" domains="$3" maxchildren="${4:-20}"
    valid_user "$user" || fail "invalid system user name: $user"
    valid_phpver "$phpver" || fail "unsupported php version: $phpver"

    local IFS=',' d
    for d in $domains; do
        d="${d,,}"
        valid_domain "$d" || fail "invalid domain: $d"
    done

    if ! is_dry_run; then
        if id "$user" &>/dev/null; then fail "system user already exists: $user"; fi
        [ -e "$VHOST_DIR/$user.conf" ] && fail "vhost already exists: $user"
    fi

    # 1. system user (no login, home = site dir)
    dr useradd --no-create-home --home-dir "$WEB_ROOT/$user" \
        --shell /sbin/nologin --user-group "$user" || fail "failed to create system user: $user"

    # 2. directories
    dr mkdir -p "$(docroot_of "$user")" "$LOG_ROOT" || fail "cannot create docroot"
    dr chown -R "$user:$user" "$WEB_ROOT/$user" || fail "cannot chown docroot"
    dr chmod 750 "$WEB_ROOT/$user"
    dr touch "$LOG_ROOT/$user.log" "$LOG_ROOT/$user.error.log"
    dr chown nginx: "$LOG_ROOT/$user.log" "$LOG_ROOT/$user.error.log" 2>/dev/null || \
        dr chown root:root "$LOG_ROOT/$user.log" "$LOG_ROOT/$user.error.log"

    # 3. fpm pool + vhost
    write_pool "$user" "$phpver" "$maxchildren"
    render_vhost "$user" "$domains" 0 0

    # 4. apply
    restart_fpm "$phpver"
    reload_nginx

    ok "\"user\":\"$user\",\"docroot\":\"$(docroot_of "$user")\",\"php\":\"$phpver\""
}

cmd_delete() {
    [ $# -ge 1 ] || fail "usage: delete <user> [purge]"
    local user="$1" purge="${2:-}"
    valid_user "$user" || fail "invalid system user: $user"

    dr rm -f "$VHOST_DIR/$user.conf"
    local oldver
    oldver="$(remove_pool "$user")"
    reload_nginx
    [ -n "${oldver:-}" ] && dr systemctl restart "${PHP_SERVICE[$oldver]}"

    if [ "$purge" = "purge" ]; then
        dr rm -rf "$WEB_ROOT/$user"
    fi
    dr userdel "$user" 2>/dev/null || true
    dr groupdel "$user" 2>/dev/null || true
    ok
}

cmd_php_set() {
    [ $# -eq 2 ] || fail "usage: php-set <user> <phpver>"
    local user="$1" phpver="$2"
    valid_user "$user" || fail "invalid system user: $user"
    valid_phpver "$phpver" || fail "unsupported php version: $phpver"

    local oldver
    oldver="$(remove_pool "$user")"
    write_pool "$user" "$phpver"
    [ -n "${oldver:-}" ] && dr systemctl restart "${PHP_SERVICE[$oldver]}"
    restart_fpm "$phpver"
    ok "\"php\":\"$phpver\""
}

cmd_render() {
    [ $# -eq 4 ] || fail "usage: render <user> <domains_csv> <ssl> <hsts>"
    local user="$1" domains="$2" ssl="$3" hsts="$4"
    [[ "$ssl" =~ ^[01]$ && "$hsts" =~ ^[01]$ ]] || fail "ssl/hsts must be 0 or 1"
    render_vhost "$user" "$domains" "$ssl" "$hsts"
    reload_nginx
    ok
}

case "$action" in
    create)  cmd_create "$@" ;;
    delete)  cmd_delete "$@" ;;
    php-set) cmd_php_set "$@" ;;
    render)  cmd_render "$@" ;;
    *) usage ;;
esac
