#!/bin/bash
# ============================================================================
# wp-sys.sh - host status + service control (root only via sudo)
#   info
#   svc <nginx|mysql|phpfpm|php74fpm|...> <start|stop|restart|reload>
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-sys.sh {info|svc}" 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

svc_json() {
    # svc_json "label" "unit"
    local label="$1" unit="$2" state
    if is_dry_run; then state="active"; else state="$(systemctl is-active "$unit" 2>/dev/null || true)"; fi
    [ -z "$state" ] && state="unknown"
    printf '{"name":"%s","unit":"%s","status":"%s"},' "$label" "$unit" "$state"
}

if [ "$action" = "info" ]; then
    if is_dry_run; then
        cat <<'JSON'
{"ok":true,"hostname":"demo-srv","os":"AlmaLinux 8.10","kernel":"4.18.x","uptime":"1 day","cpu_cores":4,"loadavg":"0.10 0.05 0.01","mem_total_kb":8167020,"mem_available_kb":6125000,"disk":[{"fs":"/","size":"80G","used":"21G","avail":"59G","use_pct":27}],"services":[{"name":"nginx","unit":"nginx","status":"active"},{"name":"mysql","unit":"mysqld","status":"active"}],"sites":1,"databases":1}
JSON
        exit 0
    fi

    hostname="$(hostname)"
    os="$(. /etc/os-release 2>/dev/null; echo "${PRETTY_NAME:-unknown}")"
    kernel="$(uname -r)"
    uptime_s="$(cut -d. -f1 /proc/uptime)"
    up_d=$((uptime_s/86400)); up_h=$(((uptime_s%86400)/3600)); up_m=$(((uptime_s%3600)/60))
    uptime="${up_d}d ${up_h}h ${up_m}m"
    cpu_cores="$(nproc)"
    loadavg="$(cut -d' ' -f1-3 /proc/loadavg)"
    mem_total_kb="$(awk '/MemTotal/{print $2}' /proc/meminfo)"
    mem_avail_kb="$(awk '/MemAvailable/{print $2}' /proc/meminfo)"

    disk_json="["
    while read -r fs size used avail pct mount; do
        [ "$mount" = "/www" ] || [ "$mount" = "/" ] || continue
        disk_json+="$(printf '{"fs":"%s","size":"%s","used":"%s","avail":"%s","use_pct":%d},' "$mount" "$size" "$used" "$avail" "${pct%\%}")"
    done < <(df -hP 2>/dev/null | awk 'NR>1')
    disk_json="${disk_json%,}]"

    svcs="["
    svcs+="$(svc_json nginx nginx)"
    svcs+="$(svc_json mysql mysqld)"
    svcs+="$(svc_json panel-php php-fpm)"
    for v in 74 80 81 82 83; do
        unit="php${v}-php-fpm"
        [ -d "/etc/opt/remi/php$v" ] && svcs+="$(svc_json "php$v-fpm" "$unit")"
    done
    svcs="${svcs%,}]"

    sites=0; [ -d "$VHOST_DIR" ] && sites=$(find "$VHOST_DIR" -maxdepth 1 -name '*.conf' | wc -l)
    databases=0
    if [ -f /root/.my.cnf ] && mysql_cmd -e "SELECT 1" >/dev/null 2>&1; then
        databases=$(mysql_cmd -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys');" 2>/dev/null || echo 0)
    fi

    printf '{"ok":true,"hostname":"%s","os":"%s","kernel":"%s","uptime":"%s","cpu_cores":%s,"loadavg":"%s","mem_total_kb":%s,"mem_available_kb":%s,"disk":%s,"services":%s,"sites":%s,"databases":%s}\n' \
        "$hostname" "$os" "$kernel" "$uptime" "$cpu_cores" "$loadavg" \
        "$mem_total_kb" "$mem_avail_kb" "$disk_json" "$svcs" "$sites" "$databases"
    exit 0
fi

if [ "$action" = "svc" ]; then
    [ $# -eq 2 ] || fail "usage: svc <service> <start|stop|restart|reload>"
    svc="$1"; act="$2"
    case "$act" in start|stop|restart|reload) ;; *) fail "invalid action" ;; esac

    case "$svc" in
        nginx) unit=nginx ;;
        mysql) unit=mysqld ;;
        phpfpm) unit=php-fpm ;;
        php74fpm) unit=php74-php-fpm ;;
        php80fpm) unit=php80-php-fpm ;;
        php81fpm) unit=php81-php-fpm ;;
        php82fpm) unit=php82-php-fpm ;;
        php83fpm) unit=php83-php-fpm ;;
        *) fail "unknown service" ;;
    esac
    dr systemctl "$act" "$unit"
    ok
fi

usage
