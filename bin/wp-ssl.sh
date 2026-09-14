#!/bin/bash
# ============================================================================
# wp-ssl.sh - Let's Encrypt certificates via acme.sh, http-01 webroot mode
#
#   issue  <user> <domains_csv>     issue + install cert + flip vhost to HTTPS
#   remove <user> <domains_csv>     revoke/remove cert + flip vhost back
#   list                            json of all installed certificates
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
    [ $# -eq 2 ] || fail "usage: issue <user> <domains_csv>"
    local user="$1" domains="$2"
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
        render_vhost "$user" "$domains" 1 0
        ok "\"domain\":\"$primary\",\"not_before\":\"dryrun\",\"not_after\":\"dryrun\""
    fi

    [ -x "$ACME_BIN" ] || fail "acme.sh not installed: $ACME_BIN"
    mkdir -p "$webroot" "$certdir"
    chmod 755 "$WEB_ROOT/$user" "$webroot"

    # make sure port-80 vhost currently serves ACME challenges
    render_vhost "$user" "$domains" 0 0
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
    render_vhost "$user" "$domains" 1 0
    nginx -t && systemctl reload nginx

    local dates
    dates="$(openssl x509 -in "$certdir/fullchain.pem" -noout -dates 2>/dev/null | \
        awk -F= '{printf "\"%s\":\"%s\",", tolower($1), $2}')"
    dates="${dates%,}"
    ok "\"domain\":\"$primary\",$dates"
}

cmd_remove() {
    [ $# -eq 2 ] || fail "usage: remove <user> <domains_csv>"
    local user="$1" domains="$2"
    valid_user "$user" || fail "invalid system user: $user"
    local primary="${domains%%,*}"

    if ! is_dry_run; then
        if [ -x "$ACME_BIN" ]; then
            "$ACME_BIN" --home "$ACME_HOME" --remove -d "$primary" --ecc >/dev/null 2>&1 || true
        fi
        rm -rf "$CERT_ROOT/$primary"
    fi
    render_vhost "$user" "$domains" 0 0
    reload_nginx
    ok
}

cmd_list() {
    if is_dry_run; then
        printf '{"ok":true,"certs":[{"domain":"example.com","not_before":"dryrun","not_after":"dryrun"}]}\n'
        return 0
    fi
    local out="[" first=1 d
    for d in "$CERT_ROOT"/*; do
        [ -f "$d/fullchain.pem" ] || continue
        local name nb na
        name="$(basename "$d")"
        nb="$(openssl x509 -in "$d/fullchain.pem" -noout -startdate 2>/dev/null | cut -d= -f2-)"
        na="$(openssl x509 -in "$d/fullchain.pem" -noout -enddate   2>/dev/null | cut -d= -f2-)"
        [ $first -eq 0 ] && out+=","
        out+="$(printf '{"domain":"%s","not_before":"%s","not_after":"%s"}' "$name" "$nb" "$na")"
        first=0
    done
    out+="]"
    printf '{"ok":true,"certs":%s}\n' "$out"
}

case "$action" in
    issue)  cmd_issue "$@" ;;
    remove) cmd_remove "$@" ;;
    list)   cmd_list ;;
    *) usage ;;
esac
