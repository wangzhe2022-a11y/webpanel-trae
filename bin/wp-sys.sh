#!/bin/bash
# ============================================================================
# wp-sys.sh - host status + service control (root only via sudo)
#   info
#   svc <nginx|mysql|phpfpm|php74fpm|...> <start|stop|restart|reload>
#   fpm-safe-restart
#   logins [limit]
#   access [limit]
#   deny|undeny <ipv4>
#   denylist
#   disk-usage
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-sys.sh {info|svc|fpm-safe-restart|logins|access|deny|undeny|denylist|disk-usage}" 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
# Fixture tests remap host paths via WP_DISK_USAGE_* and skip the root check.
if [ "$action" != "disk-usage" ] || [ -z "${WP_DISK_USAGE_FIXTURE:-}" ]; then
    require_root
fi

# PGDG (AL8 install.sh) uses postgresql-16; AlmaLinux 10 system PG uses postgresql
pg_unit() {
    if [ -f /usr/lib/systemd/system/postgresql-16.service ] \
        || [ -f /etc/systemd/system/postgresql-16.service ]; then
        echo postgresql-16
    elif [ -f /usr/lib/systemd/system/postgresql.service ] \
        || [ -f /etc/systemd/system/postgresql.service ]; then
        echo postgresql
    else
        echo postgresql-16
    fi
}

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
{"ok":true,"hostname":"demo-srv","os":"AlmaLinux 8.10","kernel":"4.18.x","uptime":"1 day","cpu_cores":4,"cpu_usage_pct":8.2,"loadavg":"0.10 0.05 0.01","load_1":0.10,"load_5":0.05,"load_15":0.01,"mem_total_kb":3880152,"mem_available_kb":2142200,"mem_used_kb":1737952,"mem_used_pct":44.8,"swap_total_kb":4194304,"swap_used_kb":102400,"swap_free_kb":4091904,"swap_used_pct":2.4,"disk":[{"fs":"/","size":"50G","used":"18G","avail":"32G","use_pct":36},{"fs":"/mnt/backup","size":"100G","used":"52G","avail":"48G","use_pct":52},{"fs":"/boot/efi","size":"511M","used":"9.1M","avail":"502M","use_pct":2}],"top":[{"name":"mysqld","rss_kb":412000},{"name":"php-fpm","rss_kb":186000},{"name":"nginx","rss_kb":42000},{"name":"postgres","rss_kb":38000},{"name":"node","rss_kb":28000},{"name":"sshd","rss_kb":12000}],"services":[{"name":"nginx","unit":"nginx","status":"active"},{"name":"mysql","unit":"mysqld","status":"active"}],"sites":1,"databases":1}
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
    done < <(ps -eo rss=,comm= --sort=-rss 2>/dev/null | head -n 6)
    top_json="${top_json%,}]"
    [ "$top_json" = "[" ] && top_json="[]"

    svcs="["
    svcs+="$(svc_json nginx nginx)"
    svcs+="$(svc_json mysql mysqld)"
    svcs+="$(svc_json postgres "$(pg_unit)")"
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
        postgres|postgresql|postgresql-16) unit="$(pg_unit)" ;;
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

# Restart panel php-fpm, then try-restart every installed Remi site pool.
# A lone `svc phpfpm restart` can wipe /run/php-fpm (RuntimeDirectory) and
# leave Remi sockets gone (nginx 502). This action always follows the panel
# unit with try-restart of php74/80/81/82/83 when those units exist.
# Does not touch nginx or RuntimeDirectoryPreserve.
if [ "$action" = "fpm-safe-restart" ]; then
    REMI_FPM_UNITS=(php74-php-fpm php80-php-fpm php81-php-fpm php82-php-fpm php83-php-fpm)
    self="$SCRIPT_DIR/wp-sys.sh"

    json_named_array() {
        local -n _items=$1
        local i first=1
        printf '['
        for ((i=0; i<${#_items[@]}; i++)); do
            [ "$first" = 1 ] || printf ','
            first=0
            printf '"%s"' "$(jesc "${_items[i]}")"
        done
        printf ']'
    }

    fpm_unit_exists() {
        local u="$1"
        case "$u" in
            php74-php-fpm|php80-php-fpm|php81-php-fpm|php82-php-fpm|php83-php-fpm) ;;
            *) return 1 ;;
        esac
        [ -f "/usr/lib/systemd/system/${u}.service" ] && return 0
        [ -f "/etc/systemd/system/${u}.service" ] && return 0
        systemctl cat "${u}.service" >/dev/null 2>&1
    }

    emit_fpm_safe_json() {
        printf '{"ok":true,"restarted":%s,"skipped":%s,"failed":%s}\n' \
            "$(json_named_array "$1")" \
            "$(json_named_array "$2")" \
            "$(json_named_array "$3")"
    }

    classify_remi_units() {
        will_restart=("php-fpm")
        skipped=()
        local u
        for u in "${REMI_FPM_UNITS[@]}"; do
            if fpm_unit_exists "$u"; then
                will_restart+=("$u")
            else
                skipped+=("$u")
            fi
        done
    }

    run_fpm_safe_restart() {
        local u
        restarted=()
        skipped=()
        failed=()

        if ! systemctl restart php-fpm; then
            fail "php-fpm restart failed"
        fi
        restarted+=("php-fpm")

        for u in "${REMI_FPM_UNITS[@]}"; do
            if ! fpm_unit_exists "$u"; then
                skipped+=("$u")
                continue
            fi
            if systemctl try-restart "$u"; then
                restarted+=("$u")
            else
                failed+=("$u")
            fi
        done

        emit_fpm_safe_json restarted skipped failed
        exit 0
    }

    if is_dry_run; then
        printf '%s\n' '{"ok":true,"restarted":["php-fpm","php74-php-fpm","php83-php-fpm"],"skipped":["php80-php-fpm","php81-php-fpm","php82-php-fpm"],"failed":[]}'
        exit 0
    fi

    # Hidden worker flag: actual restart sequence, already outside php-fpm.
    if [ "${1:-}" = "--inner" ]; then
        run_fpm_safe_restart
    fi

    in_php_fpm_cgroup() {
        grep -q 'php-fpm' /proc/self/cgroup 2>/dev/null
    }

    # Invoked from the panel worker: detaching avoids deadlock / cgroup kill
    # when this script restarts the same php-fpm unit that is waiting on us.
    # CLI / tests run the sequence synchronously and can report real failures.
    if in_php_fpm_cgroup; then
        classify_remi_units
        failed=()
        if command -v systemd-run >/dev/null 2>&1; then
            systemd-run --quiet --collect \
                --description="WebPanel safe PHP-FPM restart" \
                -- "$self" fpm-safe-restart --inner \
                || fail "failed to schedule php-fpm safe restart"
        else
            nohup bash -c 'sleep 0.8; exec "$1" fpm-safe-restart --inner' _ "$self" >/dev/null 2>&1 &
        fi
        emit_fpm_safe_json will_restart skipped failed
        exit 0
    fi

    run_fpm_safe_restart
fi

if [ "$action" = "logins" ]; then
    # Recent SSH authentication successes from /var/log/secure (newest first)
    n="${1:-10}"
    case "$n" in ''|*[!0-9]*) fail "usage: logins [limit]" ;; esac
    [ "$n" -gt 100 ] && n=100
    if is_dry_run; then
        cat <<'JSON'
{"ok":true,"logins":[{"time":"Sep 23 13:51:08","user":"root","ip":"203.0.113.10","method":"publickey"},{"time":"Sep 23 12:08:41","user":"trae_solo","ip":"203.0.113.10","method":"publickey"},{"time":"Sep 23 09:22:15","user":"demo","ip":"198.51.100.24","method":"password"},{"time":"Sep 22 22:14:03","user":"root","ip":"203.0.113.10","method":"publickey"},{"time":"Sep 22 18:02:11","user":"root","ip":"198.51.100.24","method":"password"},{"time":"Sep 22 11:45:30","user":"trae_solo","ip":"203.0.113.10","method":"publickey"},{"time":"Sep 21 20:17:44","user":"demo","ip":"203.0.113.55","method":"password"},{"time":"Sep 21 14:03:09","user":"root","ip":"203.0.113.10","method":"publickey"},{"time":"Sep 20 23:58:01","user":"trae_solo","ip":"198.51.100.24","method":"publickey"},{"time":"Sep 20 16:21:37","user":"root","ip":"203.0.113.10","method":"password"},{"time":"Sep 19 08:40:12","user":"demo","ip":"203.0.113.55","method":"publickey"},{"time":"Sep 18 19:05:55","user":"root","ip":"203.0.113.10","method":"publickey"}]}
JSON
        exit 0
    fi
    # AlmaLinux / newer OpenSSH log "sshd-session[PID]" (and similar sshd*
    # tags) instead of classic "sshd[PID]". Also read rotated
    # /var/log/secure-YYYYMMDD oldest-first so tail still sees chronological
    # lines when the current log is short.
    files=()
    for f in ${SECURE_LOG_ROTATED:-/var/log/secure-[0-9]*}; do
        [ -f "$f" ] && files+=("$f")
    done
    f="${SECURE_LOG:-/var/log/secure}"
    [ -f "$f" ] && files+=("$f")

    printf '{"ok":true,"logins":['
    if [ "${#files[@]}" -gt 0 ]; then
        grep -hE 'sshd[^[:space:]]*\[[0-9]+\]: Accepted (publickey|password)' "${files[@]}" 2>/dev/null \
            | tail -n "$n" | tac | awk '
            /sshd[^[:space:]]*\[[0-9]+\]: Accepted (publickey|password) for / {
                method=""; user=""; ip="";
                for (i=1; i<=NF; i++) {
                    if ($i=="Accepted" && method=="") method=$(i+1);
                    if ($i=="for" && user=="") user=$(i+1);
                    if ($i=="from" && ip=="") ip=$(i+1);
                }
                if (method!="" && user!="" && ip!="") {
                    gsub(/[\\"]/,"",method); gsub(/[\\"]/,"",user); gsub(/[\\"]/,"",ip);
                    printf "%s{\"time\":\"%s %s %s\",\"user\":\"%s\",\"ip\":\"%s\",\"method\":\"%s\"}",
                        sep, $1, $2, $3, user, ip, method;
                    sep=",";
                }
            }'
    fi
    printf ']}\n'
    exit 0
fi

if [ "$action" = "access" ]; then
    # Recent access to the WebPanel management UI from /www/wwwlogs/panel.log
    # (nginx combined format). Also flags suspicious IPs: many failed logins,
    # many requests, or scans for sensitive paths (.env, wp-admin, etc.).
    n="${1:-30}"
    case "$n" in ''|*[!0-9]*) fail "usage: access [limit]" ;; esac
    [ "$n" -gt 200 ] && n=200

    if is_dry_run; then
        cat <<'JSON'
{"ok":true,"total":42,"unique_ips":5,"recent":[{"time":"23/Sep/2026:14:32:01 +0800","ip":"203.0.113.10","method":"GET","uri":"/","status":200,"ua":"Mozilla/5.0 (Windows NT 10.0; Win64; x64)"},{"time":"23/Sep/2026:14:31:58 +0800","ip":"203.0.113.10","method":"POST","uri":"/login","status":200,"ua":"Mozilla/5.0 (Windows NT 10.0; Win64; x64)"},{"time":"23/Sep/2026:14:31:40 +0800","ip":"198.51.100.24","method":"POST","uri":"/login","status":401,"ua":"python-requests/2.31"},{"time":"23/Sep/2026:14:31:38 +0800","ip":"198.51.100.24","method":"POST","uri":"/login","status":401,"ua":"python-requests/2.31"},{"time":"23/Sep/2026:14:30:12 +0800","ip":"45.33.22.11","method":"GET","uri":"/.env","status":404,"ua":"Mozilla/5.0 (compatible; Nmap Scripting Engine)"},{"time":"23/Sep/2026:14:29:55 +0800","ip":"45.33.22.11","method":"GET","uri":"/wp-admin/","status":404,"ua":"Mozilla/5.0 (compatible; Nmap Scripting Engine)"}],"failed_logins":[{"ip":"198.51.100.24","count":12}],"suspicious":[{"ip":"198.51.100.24","reason":"12 次登录失败","count":12,"level":"high"},{"ip":"45.33.22.11","reason":"扫描敏感路径（/.env, /wp-admin/）","count":2,"level":"medium"}]}
JSON
        exit 0
    fi

    log="${PANEL_ACCESS_LOG:-/www/wwwlogs/panel.log}"
    if [ ! -f "$log" ]; then
        # Try rotated logs too, but if none exist, return empty.
        printf '{"ok":true,"total":0,"unique_ips":0,"recent":[],"failed_logins":[],"suspicious":[]}\n'
        exit 0
    fi

    # Parse combined log. Output one record per line for the last $n entries,
    # then aggregate failed-logins and suspicious IPs in awk.
    # Loopback traffic (local testing / health checks) is excluded from all stats.
    tmp="$(mktemp)"
    grep -v -E '^(127\.0\.0\.1|::1) ' "$log" | tail -n 2000 > "$tmp" || true

    total=$(wc -l < "$tmp" | tr -d ' ')
    unique_ips=$(awk '{print $1}' "$tmp" | sort -u | wc -l | tr -d ' ')

    # Recent entries (newest first)
    recent_json="["
    recent_json+=$(tail -n "$n" "$tmp" | tac | awk '
    {
        # combined format: ip - user [time] "request" status bytes "referer" "ua"
        ip=$1;
        # time is between [ and ]
        s=index($0,"["); e=index($0,"]");
        time=(s&&e)?substr($0,s+1,e-s-1):"";
        # request is between first pair of quotes after ]
        rest=substr($0,e+1);
        q1=index(rest,"\""); q2=index(substr(rest,q1+1),"\"");
        req=(q1&&q2)?substr(rest,q1+1,q2-1):"";
        split(req,rq," "); method=rq[1]; uri=rq[2];
        # status is the token after the closing quote of request
        after=substr(rest,q1+q2+1);
        # remove leading spaces
        gsub(/^[ \t]+/,"",after);
        split(after,st," "); status=st[1];
        # user agent is the last quoted field
        n=split($0,parts,"\"");
        ua=parts[n-1];
        gsub(/[\\"]/,"",ua); gsub(/[\\"]/,"",uri); gsub(/[\\"]/,"",method);
        if (ip!="" && time!="") {
            printf "%s{\"time\":\"%s\",\"ip\":\"%s\",\"method\":\"%s\",\"uri\":\"%s\",\"status\":%s,\"ua\":\"%s\"}",
                (NR>1?",":""), time, ip, method, uri, (status+0), ua;
        }
    }')
    recent_json+="]"

    # Failed logins (401 on /login) grouped by IP
    failed_json="["
    failed_json+=$(grep -E '"(POST|GET) /login' "$tmp" 2>/dev/null | awk '$0 ~ / 401 / {print $1}' | sort | uniq -c | sort -rn | awk '
    { printf "%s{\"ip\":\"%s\",\"count\":%d}", (NR>1?",":""), $2, $1 }')
    failed_json+="]"

    # Suspicious IPs: >=3 failed logins OR scanned sensitive paths OR >=20 requests
    # Sensitive path patterns (no lookahead — awk ERE doesn't support it).
    # .well-known/acme-challenge is legitimate (Let's Encrypt), so not flagged.
    sens='\.(env|git|svn|htaccess|htpasswd|sql|bak|old|zip|tar|gz|rar|7z)$|/(wp-admin|wp-login|phpmyadmin|pma|manager|console|xmlrpc|actuator|debug)(/|$)|^/env$|^/admin(\?|/)'
    susp_json="["
    susp_json+=$(awk -v sens="$sens" '
    {
        ip=$1;
        # parse status and uri
        s=index($0,"["); e=index($0,"]");
        rest=substr($0,e+1);
        q1=index(rest,"\""); q2=index(substr(rest,q1+1),"\"");
        req=substr(rest,q1+1,q2-1);
        split(req,rq," "); uri=rq[2];
        after=substr(rest,q1+q2+1); gsub(/^[ \t]+/,"",after);
        split(after,st," "); status=st[1]+0;
        count[ip]++;
        if (status==401 && uri ~ /^\/login/) fail[ip]++;
        if (uri ~ sens) scan[ip]++;
    }
    END {
        for (ip in count) {
            reason=""; level="low"; cnt=0;
            if ((fail[ip]+0) >= 3) { reason=fail[ip]" 次登录失败"; cnt=fail[ip]; level="high"; }
            else if ((scan[ip]+0) >= 1) { reason="扫描敏感路径"; cnt=scan[ip]; level="medium"; }
            else if ((count[ip]+0) >= 20) { reason="高频访问 "count[ip]" 次"; cnt=count[ip]; level="low"; }
            if (reason != "") {
                printf "%s{\"ip\":\"%s\",\"reason\":\"%s\",\"count\":%d,\"level\":\"%s\"}",
                    (NR_seen++?",":""), ip, reason, cnt, level;
            }
        }
    }' "$tmp")
    susp_json+="]"

    rm -f "$tmp"

    printf '{"ok":true,"total":%s,"unique_ips":%s,"recent":%s,"failed_logins":%s,"suspicious":%s}\n' \
        "$total" "$unique_ips" "$recent_json" "$failed_json" "$susp_json"
    exit 0
fi

if [ "$action" = "denylist" ]; then
    # List currently blocked IPs in /www/server/panel/deny-ips.conf
    deny_file="${PANEL_DENY_FILE:-/www/server/panel/deny-ips.conf}"
    if [ ! -f "$deny_file" ]; then
        printf '{"ok":true,"denied":[]}\n'
        exit 0
    fi
    # Extract deny directives, ignore comments and blank lines
    ips="["
    ips+=$(grep -E '^[[:space:]]*deny[[:space:]]' "$deny_file" 2>/dev/null \
        | sed -E 's/^[[:space:]]*deny[[:space:]]+([0-9.]+).*/\1/' \
        | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' \
        | awk '{ printf "%s\"%s\"", (NR>1?",":""), $1 }')
    ips+="]"
    printf '{"ok":true,"denied":%s}\n' "$ips"
    exit 0
fi

if [ "$action" = "deny" ] || [ "$action" = "undeny" ]; then
    # Block / unblock an IP from the panel via /www/server/panel/deny-ips.conf
    ip="$1"
    case "$ip" in
        ''|*[!0-9.]*) fail "usage: $action <ipv4>" ;;
    esac
    # Validate IPv4 octets
    IFS='.' read -r o1 o2 o3 o4 <<< "$ip"
    for o in "$o1" "$o2" "$o3" "$o4"; do
        [ -n "$o" ] && [ "$o" -ge 0 ] 2>/dev/null && [ "$o" -le 255 ] 2>/dev/null || fail "invalid IPv4: $ip"
    done

    deny_file="${PANEL_DENY_FILE:-/www/server/panel/deny-ips.conf}"
    allow_file="${PANEL_ALLOW_FILE:-/www/server/panel/allow-ips.conf}"

    if is_dry_run; then
        printf '{"ok":true,"action":"%s","ip":"%s"}\n' "$action" "$ip"
        exit 0
    fi

    [ -d "$(dirname "$deny_file")" ] || mkdir -p "$(dirname "$deny_file")"

    if [ "$action" = "deny" ]; then
        # Safety: refuse to block an IP that is in the allow list (would lock
        # out the legitimate admin if allow list is enforced).
        if grep -qE "^[[:space:]]*allow[[:space:]]+${ip}[[:space:]]*;" "$allow_file" 2>/dev/null; then
            fail "refuse to deny an IP that is in the allow list: $ip"
        fi
        # Idempotent: skip if already denied
        if grep -qE "^[[:space:]]*deny[[:space:]]+${ip}[[:space:]]*;" "$deny_file" 2>/dev/null; then
            printf '{"ok":true,"action":"deny","ip":"%s","already":true}\n' "$ip"
            exit 0
        fi
        # Append deny directive with timestamp comment
        printf '# blocked %s\ndeny %s;\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$ip" >> "$deny_file"
    else
        # undeny: remove all deny lines for this IP (and their preceding comment)
        if [ -f "$deny_file" ]; then
            # Use a temp file: drop comment lines immediately before a matching deny
            awk -v ip="$ip" '
                /^[[:space:]]*#[[:space:]]*blocked/ { prev=$0; prev_is_comment=1; next }
                $0 ~ "^[[:space:]]*deny[[:space:]]+" ip "[[:space:]]*;" { prev=""; prev_is_comment=0; next }
                { if (prev_is_comment) print prev; print $0; prev=""; prev_is_comment=0 }
            ' "$deny_file" > "${deny_file}.tmp" && mv "${deny_file}.tmp" "$deny_file"
        fi
    fi

    # Validate and reload nginx
    if ! nginx -t >/dev/null 2>&1; then
        fail "nginx config test failed after modifying deny list"
    fi
    systemctl reload nginx >/dev/null 2>&1 || fail "failed to reload nginx"

    printf '{"ok":true,"action":"%s","ip":"%s"}\n' "$action" "$ip"
    exit 0
fi

# Disk usage breakdown for the dashboard widget.
# total = sum of the 9 category byte counts (not df used on /). Categories are
# disjoint path groups so the header total matches the left-hand list.
# Largest directories are a separate top-N listing and may overlap categories.
if [ "$action" = "disk-usage" ]; then
    if is_dry_run; then
        cat <<'JSON'
{"ok":true,"total":"18.4 GB","categories":[{"name":"Files in home directory","size":"4.25 MB","icon":"home"},{"name":"Files in hidden subdirectories","size":"1.58 GB","icon":"hidden"},{"name":"Databases","size":"315.48 MB","icon":"database"},{"name":"Mailing Lists","size":"0 B","icon":"mailing"},{"name":"Email","size":"127.41 MB","icon":"email"},{"name":"Website Files","size":"3.16 GB","icon":"globe"},{"name":"Logs","size":"227.3 MB","icon":"doc"},{"name":"Temporary Files","size":"780.64 MB","icon":"clock"},{"name":"Other","size":"5.29 GB","icon":"dots"}],"largest_dirs":[{"name":"application_backups","size":"9.28 GB"},{"name":"public_html","size":"3.16 GB"},{"name":"support-local","size":"2.75 GB"},{"name":"zmerch","size":"1.6 GB"},{"name":"Email","size":"1.58 GB"},{"name":".trash","size":"1.58 GB"},{"name":"tmp","size":"780.64 MB"},{"name":"nuki","size":"631.88 MB"},{"name":"old","size":"306.1 MB"},{"name":"catalog","size":"304.07 MB"},{"name":"logs","size":"227.3 MB"}]}
JSON
        exit 0
    fi

    # Global budget: keep dashboard refresh snappy even on large trees.
    disk_deadline=$((SECONDS + 8))

    du_run() {
        local remain=$((disk_deadline - SECONDS))
        [ "$remain" -gt 8 ] && remain=2
        [ "$remain" -lt 1 ] && return 1
        [ "$remain" -gt 3 ] && remain=3
        if command -v timeout >/dev/null 2>&1; then
            timeout "$remain" "$@"
        else
            "$@"
        fi
    }

    # Bytes of one path. Skip missing paths, remote /mnt/backup, and over-budget.
    du_bytes() {
        local path="$1" out
        [ -n "$path" ] || { echo 0; return; }
        [ -e "$path" ] || { echo 0; return; }
        case "$path" in
            /mnt/backup|/mnt/backup/*)
                echo 0
                return
                ;;
        esac
        [ $((disk_deadline - SECONDS)) -lt 1 ] && { echo 0; return; }
        out="$(du_run du -sbx "$path" 2>/dev/null | awk '{print $1; exit}')"
        [[ "${out:-}" =~ ^[0-9]+$ ]] && echo "$out" || echo 0
    }

    sum_bytes() {
        local t=0 p n
        for p in "$@"; do
            n="$(du_bytes "$p")"
            t=$((t + n))
        done
        echo "$t"
    }

    # Human-readable like dry-run / cPanel: "3.16 GB", "780.64 MB", "4.25 KB", "0 B".
    human_bytes() {
        awk -v b="${1:-0}" 'BEGIN{
            if (b < 1) { print "0 B"; exit }
            if (b < 1024) { printf "%d B\n", b; exit }
            split("KB MB GB TB", u, " ")
            n = b + 0
            i = 0
            while (n >= 1024 && i < 4) { n /= 1024; i++ }
            s = sprintf("%.2f", n)
            sub(/0+$/, "", s)
            sub(/\.$/, "", s)
            printf "%s %s\n", s, u[i]
        }'
    }

    is_hidden_name() {
        case "$1" in
            .* ) return 0 ;;
            *) return 1 ;;
        esac
    }

    # Depth-1 hidden directories (skip . and ..).
    sum_hidden_dirs() {
        local root="$1" t=0 d base
        [ -d "$root" ] || { echo 0; return; }
        shopt -s nullglob dotglob
        for d in "$root"/.*; do
            base="$(basename "$d")"
            [ "$base" = "." ] || [ "$base" = ".." ] && continue
            is_hidden_name "$base" || continue
            [ -d "$d" ] || continue
            [ -L "$d" ] && continue
            t=$((t + $(du_bytes "$d")))
        done
        shopt -u nullglob dotglob
        echo "$t"
    }

    # Depth-1 children for the largest-dir list: "bytes<TAB>basename".
    list_child_dirs() {
        local root="$1" bytes path name
        [ -d "$root" ] || return
        [ $((disk_deadline - SECONDS)) -lt 1 ] && return
        while read -r bytes path; do
            [ -n "${path:-}" ] || continue
            [ "$path" = "$root" ] && continue
            [ -d "$path" ] || continue
            [[ "$bytes" =~ ^[0-9]+$ ]] || continue
            [ "$bytes" -gt 0 ] || continue
            name="$(basename "$path")"
            [ "$name" = "." ] || [ "$name" = ".." ] && continue
            printf '%s\t%s\n' "$bytes" "$name"
        done < <(du_run du -bx --max-depth=1 "$root" 2>/dev/null)
    }

    add_largest() {
        local path="$1" bytes name
        [ -e "$path" ] || return
        name="$(basename "$path")"
        [ -n "$name" ] || return
        bytes="$(du_bytes "$path")"
        [ "$bytes" -gt 0 ] || return
        printf '%s\t%s\n' "$bytes" "$name"
    }

    first_existing_dir() {
        local p
        for p in "$@"; do
            [ -n "$p" ] && [ -d "$p" ] && { printf '%s' "$p"; return 0; }
        done
        return 1
    }

    www_root="${WWW_ROOT:-/www}"
    web_root="${WEB_ROOT:-/www/wwwroot}"
    log_root="${LOG_ROOT:-/www/wwwlogs}"
    backup_root="${BACKUP_ROOT:-/www/server/backup}"
    panel_home="${WP_HOME_DIR:-/www/server/panel}"

    mysql_dir="${WP_MYSQL_DATADIR:-}"
    if [ -z "$mysql_dir" ]; then
        mysql_dir="$(first_existing_dir /www/server/data /var/lib/mysql || true)"
    fi
    pg_dir="${WP_PG_DATADIR:-}"
    if [ -z "$pg_dir" ]; then
        pg_dir="$(first_existing_dir \
            /var/lib/pgsql/16/data \
            /var/lib/pgsql/data \
            /var/lib/postgresql \
            /www/server/pgsql \
            || true)"
    fi

    IFS=':' read -r -a tmp_dirs <<< "${WP_TMP_DIRS:-/tmp:/www/server/tmp}"
    IFS=':' read -r -a mail_dirs <<< "${WP_MAIL_DIRS:-/var/mail:/var/spool/mail}"
    IFS=':' read -r -a extra_log_dirs <<< "${WP_VAR_LOG_DIRS:-/var/log/nginx:/var/log/httpd:/var/log/mysql:/var/log/mysqld:/var/log/mariadb:/var/log/php-fpm:/var/log/atop}"

    web_total="$(du_bytes "$web_root")"
    web_hidden="$(sum_hidden_dirs "$web_root")"
    website_bytes=$((web_total - web_hidden))
    [ "$website_bytes" -lt 0 ] && website_bytes=0

    hidden_bytes="$web_hidden"
    hidden_bytes=$((hidden_bytes + $(sum_hidden_dirs "$www_root")))
    hidden_bytes=$((hidden_bytes + $(sum_hidden_dirs "$www_root/server")))
    if [ -z "${WP_DISK_USAGE_FIXTURE:-}" ] && [ -d /home ]; then
        shopt -s nullglob
        for d in /home/*; do
            [ -d "$d" ] || continue
            hidden_bytes=$((hidden_bytes + $(sum_hidden_dirs "$d")))
        done
        shopt -u nullglob
    fi

    home_bytes="$(du_bytes "$panel_home")"
    if [ -z "${WP_DISK_USAGE_FIXTURE:-}" ] && [ -d /home ]; then
        shopt -s nullglob
        for d in /home/*; do
            [ -d "$d" ] || continue
            # Non-hidden children only; hidden already counted above.
            for c in "$d"/*; do
                [ -e "$c" ] || continue
                home_bytes=$((home_bytes + $(du_bytes "$c")))
            done
        done
        shopt -u nullglob
    fi

    db_bytes=0
    [ -n "$mysql_dir" ] && db_bytes=$((db_bytes + $(du_bytes "$mysql_dir")))
    [ -n "$pg_dir" ] && db_bytes=$((db_bytes + $(du_bytes "$pg_dir")))

    mail_bytes="$(sum_bytes "${mail_dirs[@]}")"
    mailing_bytes=0

    log_bytes="$(du_bytes "$log_root")"
    for d in "${extra_log_dirs[@]}"; do
        [ -e "$d" ] || continue
        log_bytes=$((log_bytes + $(du_bytes "$d")))
    done
    # Cheap file sizes for common host logs (no tree walk).
    for f in /var/log/messages /var/log/secure /var/log/cron; do
        [ -f "$f" ] || continue
        n="$(stat -c '%s' "$f" 2>/dev/null || echo 0)"
        [[ "$n" =~ ^[0-9]+$ ]] && log_bytes=$((log_bytes + n))
    done

    tmp_bytes="$(sum_bytes "${tmp_dirs[@]}")"

    other_bytes=0
    other_paths=(
        "$backup_root"
        "$www_root/backup"
        "$www_root/application_backups"
        "$www_root/server/application_backups"
        "$www_root/server/certs"
        "$www_root/server/acme.sh"
    )
    if [ -d "$www_root" ]; then
        shopt -s nullglob
        for d in "$www_root"/*; do
            [ -d "$d" ] || continue
            base="$(basename "$d")"
            case "$base" in
                wwwroot|wwwlogs|server|backup|application_backups) continue ;;
            esac
            other_paths+=("$d")
        done
        shopt -u nullglob
    fi
    other_bytes="$(sum_bytes "${other_paths[@]}")"

    total_bytes=$((home_bytes + hidden_bytes + db_bytes + mailing_bytes + mail_bytes + website_bytes + log_bytes + tmp_bytes + other_bytes))

    largest_tmp="$(mktemp)"
    {
        list_child_dirs "$web_root"
        list_child_dirs "$backup_root"
        list_child_dirs "$www_root/backup"
        add_largest "$www_root/application_backups"
        add_largest "$www_root/server/application_backups"
        add_largest "$www_root/.trash"
        add_largest "$web_root/.trash"
        for d in "${tmp_dirs[@]}"; do
            add_largest "$d"
        done
        add_largest "$log_root"
        add_largest "$mysql_dir"
        # Named leftovers that often show up in cPanel-style lists.
        add_largest "$www_root/server/backup"
    } > "$largest_tmp"

    largest_json="["
    largest_sep=""
    while IFS=$'\t' read -r bytes name; do
        [ -n "${name:-}" ] || continue
        largest_json+="${largest_sep}$(printf '{"name":"%s","size":"%s"}' "$(jesc "$name")" "$(jesc "$(human_bytes "$bytes")")")"
        largest_sep=","
    done < <(sort -nr -k1,1 "$largest_tmp" | awk -F '\t' '!seen[$2]++ {print}' | head -n 10)
    largest_json+="]"
    rm -f "$largest_tmp"

    cat_json="["
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Files in home directory" "$(jesc "$(human_bytes "$home_bytes")")" "home"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Files in hidden subdirectories" "$(jesc "$(human_bytes "$hidden_bytes")")" "hidden"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Databases" "$(jesc "$(human_bytes "$db_bytes")")" "database"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Mailing Lists" "$(jesc "$(human_bytes "$mailing_bytes")")" "mailing"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Email" "$(jesc "$(human_bytes "$mail_bytes")")" "email"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Website Files" "$(jesc "$(human_bytes "$website_bytes")")" "globe"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Logs" "$(jesc "$(human_bytes "$log_bytes")")" "doc"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Temporary Files" "$(jesc "$(human_bytes "$tmp_bytes")")" "clock"),"
    cat_json+="$(printf '{"name":"%s","size":"%s","icon":"%s"}' "Other" "$(jesc "$(human_bytes "$other_bytes")")" "dots")"
    cat_json+="]"

    printf '{"ok":true,"total":"%s","categories":%s,"largest_dirs":%s}\n' \
        "$(jesc "$(human_bytes "$total_bytes")")" "$cat_json" "$largest_json"
    exit 0
fi
usage
