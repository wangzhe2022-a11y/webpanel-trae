#!/bin/bash
# ============================================================================
# wp-pg.sh - PostgreSQL database / role management (root only via sudo)
#
#   create <db_name> <db_user>      password read from stdin (single line)
#   passwd <db_name> <db_user>      new password read from stdin
#   delete <db_name> <db_user>      drop database + role
#   list                            json of databases + sizes
#
# All SQL is executed as the local postgres superuser via runuser; app
# processes connect over TCP 127.0.0.1:5432 with scram-sha-256 passwords.
# Credentials never appear in the process arguments (stdin instead).
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-pg.sh {create|passwd|delete|list} ..." 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

# "pg_" prefix is reserved by PostgreSQL itself
valid_pgrole() {
    valid_dbuser "$1" || return 1
    case "$1" in pg_*) return 1 ;; esac
}

# psql binary: PGDG installs into /usr/pgsql-<major>/bin
PG_PSQL="${PG_PSQL:-$([ -x /usr/pgsql-16/bin/psql ] && echo /usr/pgsql-16/bin/psql || command -v psql)}"
PG_PSQL="${PG_PSQL:-/usr/pgsql-16/bin/psql}"

pg_sql() {
    # pg_sql [-v name=value]... "SQL..."
    # SQL is fed via stdin: psql performs :'var' interpolation for stdin
    # scripts but NOT for -c strings (passwords must never hit argv).
    local -a vopt=()
    while [ "$1" = "-v" ]; do vopt+=(-v "$2"); shift 2; done
    if is_dry_run; then echo "[dry-run] psql: $*" >&2; return 0; fi
    printf '%s\n' "$1" | runuser -u postgres -- "$PG_PSQL" -v ON_ERROR_STOP=1 "${vopt[@]}" -qAt
}

read_password() {
    local pw
    IFS= read -r pw
    [ ${#pw} -ge 8 ] && [ ${#pw} -le 128 ] || fail "password length must be 8-128"
    case "$pw" in
        *[\']*) fail "password contains unsupported characters (single quote)" ;;
    esac
    printf '%s' "$pw"
}

cmd_create() {
    [ $# -eq 2 ] || usage
    local db="$1" user="$2" pw
    valid_dbname "$db" || fail "invalid database name"
    valid_pgrole "$user" || fail "invalid database user（pg_ 前缀为 PostgreSQL 保留字）"
    pw="$(read_password)"

    if ! is_dry_run; then
        [ -n "$(pg_sql "SELECT 1 FROM pg_database WHERE datname='$db';")" ] && fail "database already exists"
        [ -n "$(pg_sql "SELECT 1 FROM pg_roles WHERE rolname='$user';")" ] && fail "db user already exists"
        pg_sql -v pw="$pw" "CREATE ROLE \"$user\" LOGIN PASSWORD :'pw';" || fail "create role failed"
        pg_sql "CREATE DATABASE \"$db\" OWNER \"$user\" ENCODING 'UTF8';" || fail "create database failed"
        pg_sql -v pw="$pw" "ALTER ROLE \"$user\" PASSWORD :'pw';" >/dev/null
    fi
    ok "\"database\":\"$db\",\"user\":\"$user\",\"engine\":\"postgres\""
}

cmd_passwd() {
    [ $# -eq 2 ] || usage
    local db="$1" user="$2" pw
    valid_dbname "$db" || fail "invalid database name"
    valid_pgrole "$user" || fail "invalid database user（pg_ 前缀为 PostgreSQL 保留字）"
    pw="$(read_password)"

    if ! is_dry_run; then
        [ -n "$(pg_sql "SELECT 1 FROM pg_roles WHERE rolname='$user';")" ] || fail "db user does not exist"
        pg_sql -v pw="$pw" "ALTER ROLE \"$user\" PASSWORD :'pw';" || fail "alter password failed"
    fi
    ok
}

cmd_delete() {
    [ $# -eq 2 ] || usage
    local db="$1" user="$2"
    valid_dbname "$db" || fail "invalid database name"
    valid_pgrole "$user" || fail "invalid database user（pg_ 前缀为 PostgreSQL 保留字）"

    if ! is_dry_run; then
        pg_sql "DROP DATABASE IF EXISTS \"$db\";" || fail "drop database failed"
        pg_sql "DROP ROLE IF EXISTS \"$user\";" || fail "drop role failed"
    fi
    ok
}

cmd_list() {
    if is_dry_run; then
        printf '{"ok":true,"dbs":[{"name":"pg_demo","size":"12 MB","owner":"pg_demo_user"}]}\n'
        return 0
    fi
    local out="[" first=1 row
    while IFS='|' read -r row; do
        local name size owner
        name="$(cut -d'|' -f1 <<<"$row")"
        size="$(cut -d'|' -f2 <<<"$row")"
        owner="$(cut -d'|' -f3 <<<"$row")"
        [ $first -eq 0 ] && out+=","
        out+="$(printf '{"name":"%s","size":"%s","owner":"%s"}' "$name" "$size" "$owner")"
        first=0
    done < <(pg_sql "SELECT datname || '|' || pg_size_pretty(pg_database_size(datname)) || '|' || pg_get_userbyid(datdba) FROM pg_database WHERE NOT datistemplate AND datname NOT IN ('postgres');")
    out+="]"
    printf '{"ok":true,"dbs":%s}\n' "$out"
}

case "$action" in
    create) cmd_create "$@" ;;
    passwd) cmd_passwd "$@" ;;
    delete) cmd_delete "$@" ;;
    list)   cmd_list ;;
    *) usage ;;
esac
