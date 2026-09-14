#!/bin/bash
# ============================================================================
# wp-ssl.sh - certificates for sites
#   issue  <user> <domains_csv> [type] [node_port]
#                                  acme.sh Let's Encrypt issue + auto-install
#   deploy <user> <domains_csv> <hsts> <upload_dir> [type] [node_port]
#                                  install a third-party certificate (e.g.
#                                  Tencent Cloud TrustAsia) that the panel
#                                  uploaded to <upload_dir>/{fullchain,privkey}.pem
#   remove <user> <domains_csv> [type] [node_port]
#                                  revoke/remove cert + flip vhost back
#   list                            json of all installed certificates
#
# type is "php" (default) or "node" (reverse-proxy vhost needs the port).
#
# Requirements: every domain must already have an A/AAAA record pointing at
# this server, otherwise http-01 validation fails.
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-ssl.sh {issue|remove|list} ..." 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

cmd_issue() {
    [ $# -ge 2 ] && [ $# -le 4 ] || fail "usage: issue <user> <domains_csv> [type] [node_port]"
    local user="$1" domains="$2" type="${3:-php}" port="${4:-}"
    valid_user "$user" || fail "invalid system user: $user"

    local IFS=',' d primary
    local -a doms=()
    for d in $domains; do
        d="${d,,}"
        valid_domain "$d" || fail "invalid domain: $d"
        doms+=("$d")
    done
    primary="${doms[0]}"

    local webroot certdir
    webroot="$(docroot_of "$user")"
    certdir="$CERT_ROOT/$primary"

    if is_dry_run; then
        echo "[dry-run] acme issue ${doms[*]} webroot=$webroot" >&2
        render_vhost "$user" "$domains" 1 0 "$type" "$port"
        ok "\"domain\":\"$primary\",\"not_before\":\"dryrun\",\"not_after\":\"dryrun\""
    fi

    [ -x "$ACME_BIN" ] || fail "acme.sh not installed: $ACME_BIN"
    mkdir -p "$webroot" "$certdir"
    chmod 755 "$WEB_ROOT/$user" "$webroot"

    # make sure port-80 vhost currently serves ACME challenges
    render_vhost "$user" "$domains" 0 0 "$type" "$port"
    nginx -t && systemctl reload nginx

    local -a args=(--issue --server letsencrypt --keylength ec-2048 -w "$webroot")
    for d in "${doms[@]}"; do args+=(-d "$d"); done

    if ! "$ACME_BIN" --home "$ACME_HOME" "${args[@]}" >"/tmp/acme-issue-$user.log" 2>&1; then
        # cert already valid for 60+ days makes acme.sh exit 2 ("skip") - acceptable
        if grep -q "Skip, Next renewal time is" "/tmp/acme-issue-$user.log"; then
            :
        else
            sed 's/"/\x27/g' "/tmp/acme-issue-$user.log" | tail -20 >&2
            fail "certificate issuance failed - check DNS A record and port 80 reachability"
        fi
    fi

    "$ACME_BIN" --home "$ACME_HOME" --install-cert -d "$primary" --ecc \
        --fullchain-file "$certdir/fullchain.pem" \
        --key-file "$certdir/privkey.pem" \
        --reloadcmd "systemctl reload nginx" >>"/tmp/acme-issue-$user.log" 2>&1 \
        || fail "certificate install failed"
    chmod 640 "$certdir/fullchain.pem" "$certdir/privkey.pem"
    chown root: "$certdir/fullchain.pem" "$certdir/privkey.pem"

    # flip the site to HTTPS (HSTS off by default; UI can enable)
    render_vhost "$user" "$domains" 1 0 "$type" "$port"
    nginx -t && systemctl reload nginx

    local dates
    dates="$(openssl x509 -in "$certdir/fullchain.pem" -noout -dates 2>/dev/null | \
        awk -F= '{printf "\"%s\":\"%s\",", tolower($1), $2}')"
    dates="${dates%,}"
    ok "\"domain\":\"$primary\",$dates"
}

cmd_deploy() {
    [ $# -ge 4 ] && [ $# -le 6 ] || fail "usage: deploy <user> <domains_csv> <hsts> <upload_dir> [type] [node_port]"
    local user="$1" domains="$2" hsts="$3" updir="$4" type="${5:-php}" port="${6:-}"
    valid_user "$user" || fail "invalid system user: $user"
    [[ "$hsts" =~ ^[01]$ ]] || fail "invalid hsts flag"

    local IFS=',' d
    local -a doms=()
    for d in $domains; do
        d="${d,,}"
        valid_domain "$d" || fail "invalid domain: $d"
        doms+=("$d")
    done
    local primary="${doms[0]}"

    # the upload dir must live inside the panel temp area and carry the fixed file names
    local prefix="${PANEL_UPLOAD_DIR:-/usr/local/webpanel/panel/data/tmp}"
    [[ "$updir" =~ ^"$prefix"/cert-[A-Za-z0-9._-]+$ ]] || fail "invalid upload directory"
    [ -f "$updir/fullchain.pem" ] && [ -f "$updir/privkey.pem" ] || fail "upload files missing"

    if is_dry_run; then
        echo "[dry-run] deploy third-party cert for $user @ $primary" >&2
        render_vhost "$user" "$domains" 1 "$hsts" "$type" "$port"
        ok "\"domain\":\"$primary\",\"not_after\":\"dryrun\",\"source\":\"manual\""
    fi

    # ---------- certificate sanity checks (openssl) ----------
    openssl x509 -in "$updir/fullchain.pem" -noout >/dev/null 2>&1 \
        || fail "证书无法解析：fullchain.pem 不是有效的 PEM 证书"
    openssl pkey -in "$updir/privkey.pem" -noout >/dev/null 2>&1 \
        || fail "私钥无法解析：privkey.pem 不是有效的 PEM 私钥"

    # cert and key must belong together (works for RSA and EC)
    local cp kp
    cp="$(openssl x509 -in "$updir/fullchain.pem" -noout -pubkey 2>/dev/null | openssl sha256 2>/dev/null)"
    kp="$(openssl pkey -in "$updir/privkey.pem" -pubout 2>/dev/null | openssl sha256 2>/dev/null)"
    [ -n "$cp" ] && [ "$cp" = "$kp" ] || fail "证书与私钥不匹配"

    # not expired
    local end_line end_ts
    end_line="$(openssl x509 -in "$updir/fullchain.pem" -noout -enddate 2>/dev/null | cut -d= -f2-)"
    end_ts="$(date -d "$end_line" +%s 2>/dev/null || true)"
    [ -n "$end_ts" ] && [ "$end_ts" -gt "$(date +%s)" ] \
        || fail "证书已过期或有效期无法读取"

    # certificate must cover at least one domain of this site (SAN, fallback CN)
    local sans cn covered=0
    sans="$(openssl x509 -in "$updir/fullchain.pem" -noout -ext subjectAltName 2>/dev/null \
        | grep -o 'DNS:[^, ]*' | cut -d: -f2- | tr 'A-Z' 'a-z')"
    cn="$(openssl x509 -in "$updir/fullchain.pem" -noout -subject 2>/dev/null \
        | sed 's/.*CN *= *//' | tr 'A-Z' 'a-z')"
    for d in "${doms[@]}"; do
        if grep -qx "$d" <<<"$sans" || [ "$d" = "$cn" ]; then covered=1; break; fi
    done
    [ "$covered" = 1 ] || fail "证书不包含该站点的任何域名（SAN/CN 均不匹配）"

    # ---------- install ----------
    local certdir="$CERT_ROOT/$primary"
    mkdir -p "$certdir"
    install -m 640 "$updir/fullchain.pem" "$certdir/fullchain.pem"
    install -m 640 "$updir/privkey.pem" "$certdir/privkey.pem"
    chown root: "$certdir/fullchain.pem" "$certdir/privkey.pem"
    # marker: this cert is NOT managed by acme.sh (no auto renewal)
    touch "$certdir/.manual"
    chmod 644 "$certdir/.manual"
    rm -rf "$updir"

    # remember whether ALL site domains are covered, to warn about www later
    local missing=""
    for d in "${doms[@]}"; do
        grep -qx "$d" <<<"$sans" || [ "$d" = "$cn" ] || missing+="$d "
    done

    render_vhost "$user" "$domains" 1 "$hsts" "$type" "$port"
    reload_nginx

    local na
    na="$(openssl x509 -in "$certdir/fullchain.pem" -noout -enddate | cut -d= -f2-)"
    ok "\"domain\":\"$primary\",\"not_after\":\"$na\",\"source\":\"manual\",\"missing\":\"${missing% }\""
}

cmd_remove() {
    [ $# -ge 2 ] && [ $# -le 4 ] || fail "usage: remove <user> <domains_csv> [type] [node_port]"
    local user="$1" domains="$2" type="${3:-php}" port="${4:-}"
    valid_user "$user" || fail "invalid system user: $user"
    local primary="${domains%%,*}"

    if ! is_dry_run; then
        if [ -x "$ACME_BIN" ]; then
            "$ACME_BIN" --home "$ACME_HOME" --remove -d "$primary" --ecc >/dev/null 2>&1 || true
        fi
        rm -rf "$CERT_ROOT/$primary"
    fi
    render_vhost "$user" "$domains" 0 0 "$type" "$port"
    reload_nginx
    ok
}

cmd_list() {
    if is_dry_run; then
        printf '{"ok":true,"certs":[{"domain":"example.com","not_before":"dryrun","not_after":"dryrun","source":"acme"}]}\n'
        return 0
    fi
    local out="[" first=1 d
    for d in "$CERT_ROOT"/*; do
        [ -f "$d/fullchain.pem" ] || continue
        local name nb na src="acme"
        name="$(basename "$d")"
        [ -f "$d/.manual" ] && src="manual"
        nb="$(openssl x509 -in "$d/fullchain.pem" -noout -startdate 2>/dev/null | cut -d= -f2-)"
        na="$(openssl x509 -in "$d/fullchain.pem" -noout -enddate   2>/dev/null | cut -d= -f2-)"
        [ $first -eq 0 ] && out+=","
        out+="$(printf '{"domain":"%s","not_before":"%s","not_after":"%s","source":"%s"}' "$name" "$nb" "$na" "$src")"
        first=0
    done
    out+="]"
    printf '{"ok":true,"certs":%s}\n' "$out"
}

case "$action" in
    issue)  cmd_issue "$@" ;;
    deploy) cmd_deploy "$@" ;;
    remove) cmd_remove "$@" ;;
    list)   cmd_list ;;
    *) usage ;;
esac
