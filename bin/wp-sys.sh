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

# Escape a string for inclusion in a JSON double-quoted value.
jesc() { printf '%s' "$1" | sed 's/[\\"]/\\&/g'; }

# Two /proc/stat snapshots ~120ms apart — cheap enough for dashboard refresh.
cpu_usage_pct() {
    local a b
    a="$(awk '/^cpu /{print $2,$3,$4,$5,$6,$7,$8,$9}' /proc/stat)"
    sleep 0.12
    b="$(awk '/^cpu /{print $2,$3,$4,$5,$6,$7,$8,$9}' /proc/stat)"
    awk -v a="$a" -v b="$b" 'BEGIN{
        n=split(a,x," "); split(b,y," ");
        idle1=x[4]+x[5]; idle2=y[4]+y[5];
        tot1=0; tot2=0;
        for(i=1;i<=n;i++){ tot1+=x[i]; tot2+=y[i] }
        dt=tot2-tot1; di=idle2-idle1;
        if(dt<=0){ print 0; exit }
        p=(dt-di)*100/dt;
        if(p<0)p=0; if(p>100)p=100;
        printf "%.1f", p
    }'
}

pct_of() {
    awk -v n="${1:-0}" -v d="${2:-0}" 'BEGIN{
        if(d<=0){ print 0; exit }
        p=n*100/d; if(p<0)p=0; if(p>100)p=100;
        printf "%.1f", p
    }'
}

if [ "$action" = "info" ]; then
    if is_dry_run; then
        cat <<'JSON'
{"ok":true,"hostname":"demo-srv","os":"AlmaLinux 8.10","kernel":"4.18.x","uptime":"1 day","cpu_cores":4,"cpu_usage_pct":8.2,"loadavg":"0.10 0.05 0.01","load_1":0.10,"load_5":0.05,"load_15":0.01,"mem_total_kb":3880152,"mem_available_kb":2142200,"mem_used_kb":1737952,"mem_used_pct":44.8,"swap_total_kb":4194304,"swap_used_kb":102400,"swap_free_kb":4091904,"swap_used_pct":2.4,"disk":[{"fs":"/","size":"40G","used":"21G","avail":"19G","use_pct":53},{"fs":"/mnt/backup","size":"50G","used":"8.1G","avail":"42G","use_pct":16}],"top":[{"name":"mysqld","rss_kb":412000},{"name":"php-fpm","rss_kb":186000},{"name":"nginx","rss_kb":42000}],"services":[{"name":"nginx","unit":"nginx","status":"active"},{"name":"mysql","unit":"mysqld","status":"active"}],"sites":1,"databases":1}
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
    load_1="$(awk '{print $1}' /proc/loadavg)"
    load_5="$(awk '{print $2}' /proc/loadavg)"
    load_15="$(awk '{print $3}' /proc/loadavg)"
    loadavg="${load_1} ${load_5} ${load_15}"
    cpu_pct="$(cpu_usage_pct)"
    mem_total_kb="$(awk '/MemTotal/{print $2}' /proc/meminfo)"
    mem_avail_kb="$(awk '/MemAvailable/{print $2}' /proc/meminfo)"
    mem_total_kb="${mem_total_kb:-0}"
    mem_avail_kb="${mem_avail_kb:-0}"
    mem_used_kb=$((mem_total_kb - mem_avail_kb))
    [ "$mem_used_kb" -lt 0 ] && mem_used_kb=0
    mem_used_pct="$(pct_of "$mem_used_kb" "$mem_total_kb")"
    swap_total_kb="$(awk '/SwapTotal/{print $2}' /proc/meminfo)"
    swap_free_kb="$(awk '/SwapFree/{print $2}' /proc/meminfo)"
    swap_total_kb="${swap_total_kb:-0}"
    swap_free_kb="${swap_free_kb:-0}"
    swap_used_kb=$((swap_total_kb - swap_free_kb))
    [ "$swap_used_kb" -lt 0 ] && swap_used_kb=0
    swap_used_pct="$(pct_of "$swap_used_kb" "$swap_total_kb")"

    disk_json="["
    seen_mounts="|"
    add_disk_row() {
        local fs="$1" size="$2" used="$3" avail="$4" pct="$5" mount="$6"
        [ -n "$mount" ] || return
        [[ "$seen_mounts" == *"|$mount|"* ]] && return
        seen_mounts+="$mount|"
        disk_json+="$(printf '{"fs":"%s","size":"%s","used":"%s","avail":"%s","use_pct":%d},' \
            "$(jesc "$mount")" "$(jesc "$size")" "$(jesc "$used")" "$(jesc "$avail")" "${pct%\%}")"
    }
    # Always include / ; include /mnt/backup when it is a distinct mount
    # (NFS/bind or extra disk). Then add every other real block device.
    while read -r fs size used avail pct mount; do
        add_disk_row "$fs" "$size" "$used" "$avail" "$pct" "$mount"
    done < <(df -hP / 2>/dev/null | awk 'NR>1')
    if mountpoint -q /mnt/backup 2>/dev/null; then
        while read -r fs size used avail pct mount; do
            add_disk_row "$fs" "$size" "$used" "$avail" "$pct" "$mount"
        done < <(df -hP /mnt/backup 2>/dev/null | awk 'NR>1')
    fi
    while read -r fs size used avail pct mount; do
        # Real block devices only (/dev/vda1, /dev/vdb1, /dev/mapper/..., etc.).
        # Skip tmpfs, overlay, proc, and other virtual filesystems.
        [[ "$fs" == /dev/* ]] || continue
        add_disk_row "$fs" "$size" "$used" "$avail" "$pct" "$mount"
    done < <(df -hP 2>/dev/null | awk 'NR>1')
    disk_json="${disk_json%,}]"

    top_json="["
    while read -r rss comm; do
        [ -n "${rss:-}" ] || continue
        [[ "$rss" =~ ^[0-9]+$ ]] || continue
        [ -n "${comm:-}" ] || continue
        top_json+="$(printf '{"name":"%s","rss_kb":%s},' "$(jesc "$comm")" "$rss")"
    done < <(ps -eo rss=,comm= --sort=-rss 2>/dev/null | head -n 5)
    top_json="${top_json%,}]"
    [ "$top_json" = "[" ] && top_json="[]"

    svcs="["
    svcs+="$(svc_json nginx nginx)"
    svcs+="$(svc_json mysql mysqld)"
    svcs+="$(svc_json postgres postgresql-16)"
    svcs+="$(svc_json panel-php php-fpm)"
    for v in 74 80 81 82 83; do
        unit="php${v}-php-fpm"
        [ -d "/etc/opt/remi/php$v" ] && svcs+="$(svc_json "php$v-fpm" "$unit")"
    done
    # one service per node site
    for nu in "$NODE_UNIT_DIR"/wp-node-*.service; do
        [ -f "$nu" ] || continue
        nuser="$(basename "$nu" .service)"
        nuser="${nuser#wp-node-}"
        svcs+="$(svc_json "node:$nuser" "$(basename "$nu" .service)")"
    done
    svcs="${svcs%,}]"

    sites=0; [ -d "$VHOST_DIR" ] && sites=$(find "$VHOST_DIR" -maxdepth 1 -name '*.conf' | wc -l)
    databases=0
    if [ -f /root/.my.cnf ] && mysql_cmd -e "SELECT 1" >/dev/null 2>&1; then
        databases=$(mysql_cmd -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys');" 2>/dev/null || echo 0)
    fi
    # + postgres user databases
    if [ -x /usr/pgsql-16/bin/psql ] && runuser -u postgres -- /usr/pgsql-16/bin/psql -qAt -c "SELECT 1" >/dev/null 2>&1; then
        databases=$((databases + $(runuser -u postgres -- /usr/pgsql-16/bin/psql -qAt -c "SELECT COUNT(*) FROM pg_database WHERE NOT datistemplate AND datname <> 'postgres';" 2>/dev/null || echo 0)))
    elif command -v psql >/dev/null 2>&1 && runuser -u postgres -- psql -qAt -c "SELECT 1" >/dev/null 2>&1; then
        databases=$((databases + $(runuser -u postgres -- psql -qAt -c "SELECT COUNT(*) FROM pg_database WHERE NOT datistemplate AND datname <> 'postgres';" 2>/dev/null || echo 0)))
    fi

    printf '{"ok":true,"hostname":"%s","os":"%s","kernel":"%s","uptime":"%s","cpu_cores":%s,"cpu_usage_pct":%s,"loadavg":"%s","load_1":%s,"load_5":%s,"load_15":%s,"mem_total_kb":%s,"mem_available_kb":%s,"mem_used_kb":%s,"mem_used_pct":%s,"swap_total_kb":%s,"swap_used_kb":%s,"swap_free_kb":%s,"swap_used_pct":%s,"disk":%s,"top":%s,"services":%s,"sites":%s,"databases":%s}\n' \
        "$(jesc "$hostname")" "$(jesc "$os")" "$(jesc "$kernel")" "$(jesc "$uptime")" \
        "${cpu_cores:-0}" "${cpu_pct:-0}" "$(jesc "$loadavg")" \
        "${load_1:-0}" "${load_5:-0}" "${load_15:-0}" \
        "$mem_total_kb" "$mem_avail_kb" "$mem_used_kb" "$mem_used_pct" \
        "$swap_total_kb" "$swap_used_kb" "$swap_free_kb" "$swap_used_pct" \
        "$disk_json" "$top_json" "$svcs" "$sites" "$databases"
    exit 0
fi

if [ "$action" = "svc" ]; then
    [ $# -eq 2 ] || fail "usage: svc <service> <start|stop|restart|reload>"
    svc="$1"; act="$2"
    case "$act" in start|stop|restart|reload) ;; *) fail "invalid action" ;; esac

    case "$svc" in
        nginx) unit=nginx ;;
        mysql) unit=mysqld ;;
        postgres) unit=postgresql-16 ;;
        phpfpm) unit=php-fpm ;;
        php74fpm) unit=php74-php-fpm ;;
        php80fpm) unit=php80-php-fpm ;;
        php81fpm) unit=php81-php-fpm ;;
        php82fpm) unit=php82-php-fpm ;;
        php83fpm) unit=php83-php-fpm ;;
        node-*)
            nuser="${svc#node-}"
            valid_user "$nuser" || fail "invalid node service"
            unit="wp-node-$nuser"
            ;;
        *) fail "unknown service" ;;
    esac
    dr systemctl "$act" "$unit"
    ok
fi

usage
