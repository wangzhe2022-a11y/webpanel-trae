#!/bin/bash
# ============================================================================
# wp-atop.sh - read-only atop log history for the dashboard (root via sudo)
#
#   info [file] [time] [latest_n] [top_n]
#
# file      atop_YYYYMMDD | auto | -     (default: today's log, else newest)
# time      HH:MM | latest | -           (default: last non-RESET sample)
# latest_n  1..24                        (how many trailing system samples)
# top_n     3..20                        (top processes by CPU and by RSS)
#
# Parseable atop 2.7.1 commands (never the interactive TUI):
#   atop -r FILE -Z -P CPU,CPL,MEM,SWP,DSK
#   atop -r FILE -Z -b YYYYMMDDHH:MM -e YYYYMMDDHH:MM -P PRC,PRM,PRD
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

ATOP_BIN="${ATOP_BIN:-atop}"
ATOP_LOGPATH="${ATOP_LOGPATH:-/var/log/atop}"
ATOP_SYSCONFIG="${ATOP_SYSCONFIG:-/etc/sysconfig/atop}"
ATOP_TEST="${ATOP_TEST:-0}"

usage() { fail "usage: wp-atop.sh info [file] [time] [latest_n] [top_n]" 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
[ "$action" = "info" ] || usage

if [ "$ATOP_TEST" != "1" ]; then
    require_root
fi

jesc() { printf '%s' "$1" | sed 's/[\\"]/\\&/g'; }

emit() { # remaining JSON body (no wrapping braces)
    printf '{"ok":true,%s}\n' "$1"
    exit 0
}

# ---- dry-run demo ----------------------------------------------------------
if is_dry_run && [ "$ATOP_TEST" != "1" ]; then
    today="$(date +%Y%m%d)"
    yday="$(date -d 'yesterday' +%Y%m%d 2>/dev/null || date +%Y%m%d)"
    now="$(date +%H:%M)"
    emit "\"installed\":true,\"version\":\"2.7.1-dryrun\",\"service\":\"active\",\"enabled\":\"enabled\",\"log_path\":\"/var/log/atop\",\"interval_s\":600,\"last_log_mtime\":\"$(date '+%Y-%m-%d %H:%M:%S')\",\"logs\":[{\"name\":\"atop_${today}\",\"size\":1843200,\"mtime\":\"$(date '+%Y-%m-%d %H:%M:%S')\"},{\"name\":\"atop_${yday}\",\"size\":2105344,\"mtime\":\"$(date -d yesterday '+%Y-%m-%d 23:50:00' 2>/dev/null || echo '2026-01-01 23:50:00')\"}],\"file\":\"atop_${today}\",\"time\":\"${now}\",\"times\":[\"00:00\",\"06:00\",\"12:00\",\"18:00\",\"${now}\"],\"sample\":{\"time\":\"${now}\",\"epoch\":$(date +%s),\"interval_s\":600,\"nrcpu\":4,\"cpu_busy_pct\":12.4,\"cpu_user_pct\":8.1,\"cpu_sys_pct\":3.2,\"cpu_wait_pct\":1.1,\"load_1\":0.42,\"load_5\":0.31,\"load_15\":0.22,\"loadavg\":\"0.42 0.31 0.22\",\"mem_total_kb\":8048576,\"mem_used_kb\":3211264,\"mem_avail_kb\":4837312,\"mem_used_pct\":39.9,\"cache_kb\":1048576,\"swap_total_kb\":4194304,\"swap_used_kb\":102400,\"swap_used_pct\":2.4,\"disk\":[{\"name\":\"vda\",\"busy_pct\":4.2,\"reads\":120,\"writes\":48}]},\"recent\":[{\"time\":\"${now}\",\"epoch\":$(date +%s),\"interval_s\":600,\"nrcpu\":4,\"cpu_busy_pct\":12.4,\"cpu_user_pct\":8.1,\"cpu_sys_pct\":3.2,\"cpu_wait_pct\":1.1,\"load_1\":0.42,\"load_5\":0.31,\"load_15\":0.22,\"loadavg\":\"0.42 0.31 0.22\",\"mem_total_kb\":8048576,\"mem_used_kb\":3211264,\"mem_avail_kb\":4837312,\"mem_used_pct\":39.9,\"cache_kb\":1048576,\"swap_total_kb\":4194304,\"swap_used_kb\":102400,\"swap_used_pct\":2.4,\"disk\":[{\"name\":\"vda\",\"busy_pct\":4.2,\"reads\":120,\"writes\":48}]}],\"top_cpu\":[{\"pid\":1842,\"name\":\"mysqld\",\"cpu_pct\":6.2,\"rss_kb\":412000,\"disk_kb\":8192},{\"pid\":2201,\"name\":\"php-fpm\",\"cpu_pct\":3.1,\"rss_kb\":186000,\"disk_kb\":1024},{\"pid\":991,\"name\":\"nginx\",\"cpu_pct\":0.8,\"rss_kb\":42000,\"disk_kb\":256}],\"top_mem\":[{\"pid\":1842,\"name\":\"mysqld\",\"cpu_pct\":6.2,\"rss_kb\":412000,\"disk_kb\":8192},{\"pid\":2201,\"name\":\"php-fpm\",\"cpu_pct\":3.1,\"rss_kb\":186000,\"disk_kb\":1024},{\"pid\":1,\"name\":\"systemd\",\"cpu_pct\":0.1,\"rss_kb\":9800,\"disk_kb\":0}],\"error\":\"\""
fi

# ---- args ------------------------------------------------------------------
file_arg="${1:-auto}"
time_arg="${2:-latest}"
latest_n="${3:-1}"
top_n="${4:-8}"

[[ "$file_arg" =~ ^(auto|-)?$ ]] && file_arg="auto"
[[ "$time_arg" =~ ^(latest|-)?$ ]] && time_arg="latest"

if [ "$file_arg" != "auto" ] && ! [[ "$file_arg" =~ ^atop_[0-9]{8}$ ]]; then
    fail "invalid atop log name"
fi
if [ "$time_arg" != "latest" ] && ! [[ "$time_arg" =~ ^[0-9]{2}:[0-9]{2}$ ]]; then
    fail "invalid sample time (HH:MM)"
fi
[[ "$latest_n" =~ ^[0-9]+$ ]] && [ "$latest_n" -ge 1 ] && [ "$latest_n" -le 24 ] || latest_n=1
[[ "$top_n" =~ ^[0-9]+$ ]] && [ "$top_n" -ge 3 ] && [ "$top_n" -le 20 ] || top_n=8

# ---- config / status -------------------------------------------------------
interval_s=600
if [ -f "$ATOP_SYSCONFIG" ]; then
    # shellcheck disable=SC1090
    . "$ATOP_SYSCONFIG" 2>/dev/null || true
    [[ "${LOGINTERVAL:-}" =~ ^[0-9]+$ ]] && [ "$LOGINTERVAL" -gt 0 ] && interval_s="$LOGINTERVAL"
    [ -n "${LOGPATH:-}" ] && [ -d "$LOGPATH" ] && ATOP_LOGPATH="$LOGPATH"
fi

installed=false
version=""
if command -v "$ATOP_BIN" >/dev/null 2>&1; then
    installed=true
    version="$("$ATOP_BIN" -V 2>&1 | head -n1 | grep -oE '[0-9]+\.[0-9]+(\.[0-9]+)?' | head -n1 || true)"
    [ -n "$version" ] || version="unknown"
fi

if [ "$ATOP_TEST" = "1" ]; then
    svc_state="active"
    svc_enabled="enabled"
else
    svc_state="$(systemctl is-active atop 2>/dev/null || true)"
    [ -z "$svc_state" ] && svc_state="unknown"
    svc_enabled="$(systemctl is-enabled atop 2>/dev/null || true)"
    [ -z "$svc_enabled" ] && svc_enabled="unknown"
fi

logs_json="["
last_mtime=""
newest_name=""
today_name="atop_$(date +%Y%m%d)"
if [ -d "$ATOP_LOGPATH" ]; then
    while IFS= read -r path; do
        [ -f "$path" ] || continue
        bn="$(basename "$path")"
        [[ "$bn" =~ ^atop_[0-9]{8}$ ]] || continue
        sz="$(stat -c %s "$path" 2>/dev/null || echo 0)"
        mt="$(stat -c %Y "$path" 2>/dev/null || echo 0)"
        mt_h="$(date -d "@${mt}" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo "")"
        logs_json+="$(printf '{"name":"%s","size":%s,"mtime":"%s"},' "$(jesc "$bn")" "$sz" "$(jesc "$mt_h")")"
        last_mtime="$mt_h"
        newest_name="$bn"
    done < <(ls -1 "$ATOP_LOGPATH"/atop_[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9] 2>/dev/null | sort -r)
fi
logs_json="${logs_json%,}]"
[ "$logs_json" = "[" ] && logs_json="[]"

status_prefix="$(printf '"installed":%s,"version":"%s","service":"%s","enabled":"%s","log_path":"%s","interval_s":%s,"last_log_mtime":"%s","logs":%s' \
    "$installed" "$(jesc "$version")" "$(jesc "$svc_state")" "$(jesc "$svc_enabled")" \
    "$(jesc "$ATOP_LOGPATH")" "$interval_s" "$(jesc "$last_mtime")" "$logs_json")"

empty_tail='"file":"","time":"","times":[],"sample":null,"recent":[],"top_cpu":[],"top_mem":[]'

if [ "$installed" != "true" ]; then
    emit "${status_prefix},${empty_tail},\"error\":\"未安装 atop\""
fi

# pick log file
if [ "$file_arg" = "auto" ]; then
    if [ -f "$ATOP_LOGPATH/$today_name" ]; then
        file_name="$today_name"
    else
        file_name="$newest_name"
    fi
else
    file_name="$file_arg"
fi

if [ -z "$file_name" ]; then
    emit "${status_prefix},${empty_tail},\"error\":\"$(jesc "$ATOP_LOGPATH") 下没有 atop_YYYYMMDD 日志\""
fi

log_file="$ATOP_LOGPATH/$file_name"
# refuse path tricks even though the name is already validated
case "$(realpath -m "$log_file" 2>/dev/null || echo "$log_file")" in
    "$ATOP_LOGPATH"/*) ;;
    *) emit "${status_prefix},${empty_tail},\"error\":\"非法日志路径\"" ;;
esac

if [ ! -f "$log_file" ]; then
    emit "${status_prefix},\"file\":\"$(jesc "$file_name")\",\"time\":\"\",\"times\":[],\"sample\":null,\"recent\":[],\"top_cpu\":[],\"top_mem\":[],\"error\":\"日志文件不存在：${file_name}\""
fi
if [ ! -s "$log_file" ]; then
    emit "${status_prefix},\"file\":\"$(jesc "$file_name")\",\"time\":\"\",\"times\":[],\"sample\":null,\"recent\":[],\"top_cpu\":[],\"top_mem\":[],\"error\":\"日志为空：${file_name}\""
fi

run_atop() {
    local timeout_bin=""
    command -v timeout >/dev/null 2>&1 && timeout_bin="timeout 25"
    # shellcheck disable=SC2086
    $timeout_bin "$ATOP_BIN" "$@"
}

# ---- system samples (cheap: no per-process labels) -------------------------
tmp_sys="$(mktemp)"
tmp_err="$(mktemp)"
tmp_prc=""; tmp_times=""; tmp_recent=""; tmp_sample=""; tmp_cpu=""; tmp_mem=""
trap 'rm -f "$tmp_sys" "$tmp_err" "$tmp_prc" "$tmp_times" "$tmp_recent" "$tmp_sample" "$tmp_cpu" "$tmp_mem"' EXIT

if ! run_atop -r "$log_file" -Z -P CPU,CPL,MEM,SWP,DSK >"$tmp_sys" 2>"$tmp_err"; then
    err="$(head -n1 "$tmp_err" | tr -d '\r')"
    [ -n "$err" ] || err="读取 atop 日志失败"
    emit "${status_prefix},\"file\":\"$(jesc "$file_name")\",\"time\":\"\",\"times\":[],\"sample\":null,\"recent\":[],\"top_cpu\":[],\"top_mem\":[],\"error\":\"$(jesc "$err")\""
fi

tmp_times="$(mktemp)"
tmp_recent="$(mktemp)"
tmp_sample="$(mktemp)"

awk -v target="$time_arg" -v latest_n="$latest_n" \
    -v times_file="$tmp_times" -v recent_file="$tmp_recent" -v sample_file="$tmp_sample" '
function jesc(s,    t) { t=s; gsub(/\\/, "\\\\", t); gsub(/"/, "\\\"", t); return t }
function num(x) { x=x+0; return x }
function pct(n, d) {
    if (d <= 0) return 0
    p = n * 100 / d
    if (p < 0) p = 0
    if (p > 100) p = 100
    return p
}
function r1(x) { return sprintf("%.1f", x+0) }
function r2(x) { return sprintf("%.2f", x+0) }
function flush_disks(    i, out) {
    out = "["
    for (i = 1; i <= ndisk; i++) {
        if (i > 1) out = out ","
        out = out disks[i]
    }
    out = out "]"
    return out
}
function store(    cap, busy, user, sys, wait, avail, used, mp, su, sp, obj, tshort) {
    if (epoch == "") return
    cap = interval * hertz * nrcpu
    if (cap <= 0) cap = 1
    user = pct(utime + ntime, cap)
    sys  = pct(stime + irq + sirq, cap)
    wait = pct(wtime, cap)
    busy = pct(utime + ntime + stime + irq + sirq + steal + guest, cap)
    avail = mem_free + mem_cache + mem_buf + mem_slabrec
    if (avail > mem_tot) avail = mem_tot
    if (avail < 0) avail = 0
    used = mem_tot - avail
    if (used < 0) used = 0
    mp = pct(used, mem_tot)
    su = swp_tot - swp_free
    if (su < 0) su = 0
    sp = pct(su, swp_tot)
    tshort = time
    if (length(tshort) >= 5) tshort = substr(tshort, 1, 5)
    obj = sprintf("{\"time\":\"%s\",\"epoch\":%s,\"interval_s\":%s,\"nrcpu\":%s,\"cpu_busy_pct\":%s,\"cpu_user_pct\":%s,\"cpu_sys_pct\":%s,\"cpu_wait_pct\":%s,\"load_1\":%s,\"load_5\":%s,\"load_15\":%s,\"loadavg\":\"%s\",\"mem_total_kb\":%d,\"mem_used_kb\":%d,\"mem_avail_kb\":%d,\"mem_used_pct\":%s,\"cache_kb\":%d,\"swap_total_kb\":%d,\"swap_used_kb\":%d,\"swap_used_pct\":%s,\"disk\":%s}",
        jesc(tshort), epoch+0, interval+0, nrcpu+0,
        r1(busy), r1(user), r1(sys), r1(wait),
        r2(l1), r2(l5), r2(l15), jesc(sprintf("%.2f %.2f %.2f", l1, l5, l15)),
        int(mem_tot), int(used), int(avail), r1(mp), int(mem_cache),
        int(swp_tot), int(su), r1(sp), flush_disks())
    ns++
    samples[ns] = obj
    stime_a[ns] = tshort
    s_epoch[ns] = epoch
}
function reset_sample() {
    epoch=""; time=""; interval=0; hertz=0; nrcpu=0
    stime=0; utime=0; ntime=0; itime=0; wtime=0; irq=0; sirq=0; steal=0; guest=0
    l1=0; l5=0; l15=0
    mem_tot=0; mem_free=0; mem_cache=0; mem_buf=0; mem_slabrec=0
    swp_tot=0; swp_free=0
    ndisk=0
    delete disks
}
BEGIN { reset_sample(); skip=0; ns=0 }
$1 == "RESET" { skip=1; next }
$1 == "SEP" {
    if (!skip) store()
    skip=0
    reset_sample()
    next
}
$1 == "CPU" {
    epoch=$3; time=$5; interval=$6+0
    hertz=$7+0; nrcpu=$8+0
    stime=$9+0; utime=$10+0; ntime=$11+0; itime=$12+0; wtime=$13+0
    irq=$14+0; sirq=$15+0; steal=$16+0; guest=$17+0
    next
}
$1 == "CPL" {
    if (nrcpu == 0) nrcpu=$7+0
    l1=$8+0; l5=$9+0; l15=$10+0
    next
}
$1 == "MEM" {
    ps=$7+0; if (ps <= 0) ps=4096
    mem_tot=$8*ps/1024; mem_free=$9*ps/1024; mem_cache=$10*ps/1024
    mem_buf=$11*ps/1024; mem_slabrec=$14*ps/1024
    next
}
$1 == "SWP" {
    ps=$7+0; if (ps <= 0) ps=4096
    swp_tot=$8*ps/1024; swp_free=$9*ps/1024
    next
}
$1 == "DSK" {
    dname=$7; io=$8+0; iv=$6+0; if (iv <= 0) iv=interval
    bp = pct(io, iv * 1000)
    ndisk++
    disks[ndisk] = sprintf("{\"name\":\"%s\",\"busy_pct\":%s,\"reads\":%d,\"writes\":%d}",
        jesc(dname), r1(bp), $9+0, $11+0)
    next
}
END {
    if (epoch != "" && !skip) store()
    tjson = "["
    for (i = 1; i <= ns; i++) {
        if (i > 1) tjson = tjson ","
        tjson = tjson "\"" jesc(stime_a[i]) "\""
    }
    tjson = tjson "]"
    print tjson > times_file

    pick = ns
    if (target != "" && target != "latest") {
        for (i = ns; i >= 1; i--) {
            if (stime_a[i] == target) { pick = i; break }
        }
    }
    if (pick < 1) {
        print "null" > sample_file
        print "[]" > recent_file
        exit
    }
    print samples[pick] > sample_file

    start = pick - latest_n + 1
    if (start < 1) start = 1
    rjson = "["
    c = 0
    for (i = start; i <= pick; i++) {
        if (c++) rjson = rjson ","
        rjson = rjson samples[i]
    }
    rjson = rjson "]"
    print rjson > recent_file
}
' "$tmp_sys"

times_json="$(cat "$tmp_times" 2>/dev/null || echo '[]')"
recent_json="$(cat "$tmp_recent" 2>/dev/null || echo '[]')"
sample_json="$(cat "$tmp_sample" 2>/dev/null || echo 'null')"
[ -n "$times_json" ] || times_json='[]'
[ -n "$recent_json" ] || recent_json='[]'
[ -n "$sample_json" ] || sample_json='null'

chosen_time="$time_arg"
chosen_epoch=""
if [ "$sample_json" != "null" ]; then
    chosen_time="$(printf '%s' "$sample_json" | sed -n 's/.*"time":"\([^"]*\)".*/\1/p' | head -n1)"
    chosen_epoch="$(printf '%s' "$sample_json" | sed -n 's/.*"epoch":\([0-9][0-9]*\).*/\1/p' | head -n1)"
fi
[ -n "$chosen_time" ] || chosen_time=""

if [ "$sample_json" = "null" ] || [ -z "$chosen_time" ]; then
    emit "${status_prefix},\"file\":\"$(jesc "$file_name")\",\"time\":\"\",\"times\":${times_json},\"sample\":null,\"recent\":[],\"top_cpu\":[],\"top_mem\":[],\"error\":\"日志尚无可用采样（或指定时间不存在）\""
fi

# ---- processes for the chosen sample --------------------------------------
# atop 2.7.1 remaps bare hh:mm onto the raw file's first-record date.
# A post-midnight sample (00:02 after 23:52 in atop_YYYYMMDD) then misses.
# Prefer absolute YYYYMMDDHH:MM from the sample epoch (host timezone).
hh="${chosen_time%%:*}"
mm="${chosen_time##*:}"
hh=$((10#$hh)); mm=$((10#$mm))
end_m=$((mm + 1)); end_h=$hh
if [ "$end_m" -ge 60 ]; then end_m=0; end_h=$((end_h + 1)); fi
if [ "$end_h" -ge 24 ]; then end_h=23; end_m=59; fi
begin_hm="$(printf '%02d:%02d' "$hh" "$mm")"
end_hm="$(printf '%02d:%02d' "$end_h" "$end_m")"
begin_s="$begin_hm"
end_s="$end_hm"
if [[ "$chosen_epoch" =~ ^[0-9]+$ ]] && [ "$chosen_epoch" -gt 0 ]; then
    tz="${TIMEZONE:-Asia/Shanghai}"
    begin_abs="$(TZ="$tz" date -d "@${chosen_epoch}" '+%Y%m%d%H:%M' 2>/dev/null || true)"
    end_abs="$(TZ="$tz" date -d "@$((chosen_epoch + 90))" '+%Y%m%d%H:%M' 2>/dev/null || true)"
    if [ -n "$begin_abs" ] && [ -n "$end_abs" ]; then
        begin_s="$begin_abs"
        end_s="$end_abs"
    fi
fi

parse_proc_labels() {
    local src="$1"
    tmp_cpu="$(mktemp)"
    tmp_mem="$(mktemp)"
    awk -v top_n="$top_n" -v target_epoch="${chosen_epoch:-0}" \
        -v cpu_file="$tmp_cpu" -v mem_file="$tmp_mem" '
    function jesc(s,    t) { t=s; gsub(/\\/, "\\\\", t); gsub(/"/, "\\\"", t); return t }
    function r1(x) { return sprintf("%.1f", x+0) }
    function isproc_y(    f) {
        for (f = NF; f >= 7; f--) {
            if ($f == "y") return 1
            if ($f == "n") return 0
        }
        return 1
    }
    function same_sample() {
        if (target_epoch+0 <= 0) return 1
        return ($3+0 == target_epoch+0)
    }
    function dump(order, limit,    i, pid, out, c) {
        out = "["
        c = 0
        for (i = 1; i <= limit && i <= n; i++) {
            pid = order[i]
            if (pname[pid] == "") continue
            if (c++) out = out ","
            out = out sprintf("{\"pid\":%d,\"name\":\"%s\",\"cpu_pct\":%s,\"rss_kb\":%d,\"disk_kb\":%d}",
                pid+0, jesc(pname[pid]), r1(cpupct[pid]), int(rss[pid]+0), int(disk[pid]+0))
        }
        return out "]"
    }
    $1 == "RESET" { skip=1; next }
    $1 == "SEP" { skip=0; next }
    skip { next }
    !same_sample() { next }
    $1 == "PRC" {
        if (!isproc_y()) next
        pid=$7
        if (interval == 0) interval=$6+0
        hz=$10+0; if (hz <= 0) hz=100
        cpu[pid] = $11+$12
        hertz[pid] = hz
        pname[pid] = $8
        iv[pid] = $6+0
        pids[pid] = 1
        next
    }
    $1 == "PRM" {
        if (!isproc_y()) next
        pid=$7
        # rmem is already KiB in atop 2.7.1 parseable output
        rss[pid] = $12+0
        if (!(pid in pname)) pname[pid] = $8
        pids[pid] = 1
        next
    }
    $1 == "PRD" {
        if (!isproc_y()) next
        pid=$7
        # rsz/wsz are 512-byte sectors in atop 2.7.1
        disk[pid] = ($13 + $15) / 2
        pids[pid] = 1
        next
    }
    END {
        n = 0
        for (pid in pids) {
            n++
            id[n] = pid
            cap = (iv[pid] > 0 ? iv[pid] : interval) * (hertz[pid] > 0 ? hertz[pid] : 100)
            if (cap <= 0) cap = 1
            cpupct[pid] = (cpu[pid] + 0) * 100 / cap
        }
        for (i = 1; i <= n; i++) { corder[i] = id[i]; morder[i] = id[i] }
        for (i = 1; i <= n; i++) {
            for (j = i+1; j <= n; j++) {
                if (cpupct[corder[j]] > cpupct[corder[i]]) {
                    t = corder[i]; corder[i] = corder[j]; corder[j] = t
                }
                if ((rss[morder[j]]+0) > (rss[morder[i]]+0)) {
                    t = morder[i]; morder[i] = morder[j]; morder[j] = t
                }
            }
        }
        print dump(corder, top_n) > cpu_file
        print dump(morder, top_n) > mem_file
    }
    ' "$src"
    top_cpu_json="$(cat "$tmp_cpu" 2>/dev/null || echo '[]')"
    top_mem_json="$(cat "$tmp_mem" 2>/dev/null || echo '[]')"
    [ -n "$top_cpu_json" ] || top_cpu_json='[]'
    [ -n "$top_mem_json" ] || top_mem_json='[]'
}

tmp_prc="$(mktemp)"
top_cpu_json='[]'
top_mem_json='[]'
proc_error=""
if ! run_atop -r "$log_file" -Z -b "$begin_s" -e "$end_s" -P PRC,PRM,PRD >"$tmp_prc" 2>"$tmp_err"; then
    # Older atop rejects YYYYMMDDHH:MM; retry the bare hh:mm window.
    if [ "$begin_s" != "$begin_hm" ]; then
        begin_s="$begin_hm"
        end_s="$end_hm"
        if ! run_atop -r "$log_file" -Z -b "$begin_s" -e "$end_s" -P PRC,PRM,PRD >"$tmp_prc" 2>"$tmp_err"; then
            proc_error="$(head -n1 "$tmp_err" | tr -d '\r')"
            [ -n "$proc_error" ] || proc_error="读取进程采样失败"
        fi
    else
        proc_error="$(head -n1 "$tmp_err" | tr -d '\r')"
        [ -n "$proc_error" ] || proc_error="读取进程采样失败"
    fi
fi
if [ -z "$proc_error" ]; then
    parse_proc_labels "$tmp_prc"
fi
[ -n "$top_cpu_json" ] || top_cpu_json='[]'
[ -n "$top_mem_json" ] || top_mem_json='[]'

emit "${status_prefix},\"file\":\"$(jesc "$file_name")\",\"time\":\"$(jesc "$chosen_time")\",\"times\":${times_json},\"sample\":${sample_json},\"recent\":${recent_json},\"top_cpu\":${top_cpu_json},\"top_mem\":${top_mem_json},\"proc_error\":\"$(jesc "$proc_error")\",\"error\":\"\""
