#!/bin/bash
# ============================================================================
# wp-lib.sh - shared helpers for WebPanel root wrapper scripts
# Wrappers are invoked by the webpanel user through sudo; every argument must
# be validated here before touching the system.
# ============================================================================

set -u

WEB_ROOT="${WEB_ROOT:-/www/wwwroot}"
LOG_ROOT="${LOG_ROOT:-/www/wwwlogs}"
CERT_ROOT="${CERT_ROOT:-/www/server/certs}"
VHOST_DIR="${VHOST_DIR:-/www/server/panel/vhost}"
VHOST_TPL_DIR="${VHOST_TPL_DIR:-/usr/local/webpanel/config/nginx}"
FPM_TPL="${FPM_TPL:-/usr/local/webpanel/config/php-fpm/site.conf.tmpl}"
ACME_HOME="${ACME_HOME:-/www/server/acme.sh}"
ACME_BIN="$ACME_HOME/acme.sh"
TIMEZONE="${TIMEZONE:-Asia/Shanghai}"
DRY_RUN_MARKER="${DRY_RUN_MARKER:-/usr/local/webpanel/.dryrun}"

# supported PHP versions -> Remi service name / pool dir / socket dir
declare -A PHP_SERVICE=(
    [74]="php74-php-fpm"
    [80]="php80-php-fpm"
    [81]="php81-php-fpm"
    [82]="php82-php-fpm"
    [83]="php83-php-fpm"
)
declare -A PHP_POOLDIR=(
    [74]="/etc/opt/remi/php74/php-fpm.d"
    [80]="/etc/opt/remi/php80/php-fpm.d"
    [81]="/etc/opt/remi/php81/php-fpm.d"
    [82]="/etc/opt/remi/php82/php-fpm.d"
    [83]="/etc/opt/remi/php83/php-fpm.d"
)
DEFAULT_PHP="82"

is_dry_run() { [ -f "$DRY_RUN_MARKER" ]; }

ok()   { printf '{"ok":true%s}\n' "${1:+,$1}"; exit 0; }
fail() { local msg="$1" code="${2:-1}"; printf '{"ok":false,"error":"%s","code":%s}\n' "${msg//\"/\'}" "$code" >&2; exit "$code"; }

require_root() {
    if is_dry_run; then return 0; fi
    [ "$(id -u)" -eq 0 ] || fail "must run as root" 2
}

# validators ----------------------------------------------------------------
valid_user()   { [[ "$1" =~ ^[a-z][a-z0-9_]{2,30}$ ]]; }
valid_dbname() { [[ "$1" =~ ^[a-zA-Z0-9_]{2,64}$ ]]; }
valid_dbuser() { [[ "$1" =~ ^[a-zA-Z0-9_]{2,32}$ ]]; }
valid_phpver() { [ -n "${PHP_SERVICE[$1]:-}" ]; }

valid_domain() {
    local d="$1"
    [ ${#d} -le 253 ] || return 1
    [[ "$d" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]]
}

# docroot for a site user
docroot_of() { echo "$WEB_ROOT/$1/public"; }

# resolve a relative path inside the site jail; prints absolute path or fails
jail_path() {
    local user="$1" rel="${2:-/}"
    valid_user "$user" || fail "invalid site user"
    local base="$WEB_ROOT/$user"
    local abs
    abs="$(realpath -m -- "$base/$rel" 2>/dev/null)" || fail "invalid path"
    case "$abs" in
        "$base"|"$base"/*) printf '%s' "$abs" ;;
        *) fail "path escapes site jail" ;;
    esac
}

# run a command unless dry-run (failures are non-fatal by design; callers
# that cannot tolerate a failure append `|| fail ...` explicitly)
dr() {
    if is_dry_run; then echo "[dry-run] $*" >&2; return 0; fi
    "$@"
}

# render the nginx vhost for a site
# args: user primary_domains_csv ssl(0|1) hsts(0|1)
render_vhost() {
    local user="$1" domains_csv="$2" ssl="${3:-0}" hsts="${4:-0}"
    valid_user "$user" || fail "invalid site user"
    local primary first
    primary="${domains_csv%%,*}"
    valid_domain "$primary" || fail "invalid domain: $primary"
    local docroot
    docroot="$(docroot_of "$user")"

    local tpl target hsts_line=""
    if [ "$ssl" = "1" ]; then
        tpl="$VHOST_TPL_DIR/vhost-https.conf.tmpl"
        [ "$hsts" = "1" ] && hsts_line='    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;'
    else
        tpl="$VHOST_TPL_DIR/vhost-http.conf.tmpl"
    fi
    target="$VHOST_DIR/$user.conf"

    if is_dry_run; then
        echo "[dry-run] render $target from $tpl ($domains_csv ssl=$ssl)" >&2
        return 0
    fi
    [ -f "$tpl" ] || fail "vhost template missing: $tpl"
    mkdir -p "$VHOST_DIR"
    sed -e "s|{{USER}}|$user|g" \
        -e "s|{{PRIMARY}}|$primary|g" \
        -e "s|{{DOMAINS}}|${domains_csv//,/ }|g" \
        -e "s|{{DOCROOT}}|$docroot|g" \
        -e "s|{{HSTS}}|$hsts_line|g" \
        "$tpl" > "$target"
}

# write / refresh the per-site PHP-FPM pool
# args: user phpver [max_children]
write_pool() {
    local user="$1" phpver="$2" maxchildren="${3:-20}"
    valid_user "$user" || fail "invalid site user"
    valid_phpver "$phpver" || fail "unsupported php version: $phpver"
    local pooldir docroot target
    pooldir="${PHP_POOLDIR[$phpver]}"
    docroot="$(docroot_of "$user")"
    target="$pooldir/$user.conf"

    if is_dry_run; then
        echo "[dry-run] write pool $target (php$phpver)" >&2
        return 0
    fi
    mkdir -p "$pooldir" /run/php-fpm
    sed -e "s|{{USER}}|$user|g" \
        -e "s|{{PHPVER}}|$phpver|g" \
        -e "s|{{DOCROOT}}|$docroot|g" \
        -e "s|{{MAXCHILDREN}}|$maxchildren|g" \
        -e "s|{{TIMEZONE}}|$TIMEZONE|g" \
        "$FPM_TPL" > "$target"
}

# remove a pool file from whatever version dir it lives in; prints removed "x"
remove_pool() {
    local user="$1" v
    for v in "${!PHP_POOLDIR[@]}"; do
        if [ -f "${PHP_POOLDIR[$v]}/$user.conf" ]; then
            dr rm -f "${PHP_POOLDIR[$v]}/$user.conf"
            echo "$v"
            return 0
        fi
    done
}

reload_nginx() {
    if is_dry_run; then echo "[dry-run] nginx -t && reload" >&2; return 0; fi
    nginx -t || fail "nginx config test failed"
    systemctl reload nginx
}

restart_fpm() {
    local phpver="$1"
    valid_phpver "$phpver" || fail "unsupported php version: $phpver"
    dr systemctl restart "${PHP_SERVICE[$phpver]}"
    if ! is_dry_run; then
        systemctl is-active --quiet "${PHP_SERVICE[$phpver]}" || fail "fpm php$phpver failed to start"
    fi
}

mysql_cmd() {
    if is_dry_run; then return 0; fi
    mysql --defaults-file=/root/.my.cnf -N -B "$@"
}
