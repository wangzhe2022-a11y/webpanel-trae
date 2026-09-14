#!/bin/bash
# ============================================================================
# wp-db.sh - MySQL 8 database / user management (root only via sudo)
#
#   create <db_name> <db_user>      password read from stdin (single line)
#   passwd <db_name> <db_user>      new password read from stdin
#   delete <db_name> <db_user>
#
# Each site database is utf8mb4; the user is granted ONLY that database.
# Credentials never appear in the process arguments (stdin instead).
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

usage() { fail "usage: wp-db.sh {create|passwd|delete} <db> <user>" 64; }
[ $# -ge 1 ] || usage
action="$1"; shift
require_root

read_password() {
    local pw
    IFS= read -r pw
    [ ${#pw} -ge 8 ] && [ ${#pw} -le 128 ] || fail "password length must be 8-128"
    case "$pw" in
        *[\"]*|*\\*) fail "password contains unsupported characters" ;;
    esac
    printf '%s' "$pw"
}

# SQL-safe backtick quoting (validator already limits charset)
qt() { printf '`%s`' "$1"; }

cmd_create() {
    [ $# -eq 2 ] || usage
    local db="$1" user="$2" pw
    valid_dbname "$db" || fail "invalid database name"
    valid_dbuser "$user" || fail "invalid database user"
    pw="$(read_password)"

    if ! is_dry_run; then
        [ -z "$(mysql_cmd -e "SHOW DATABASES LIKE '$db';")" ] || fail "database already exists"
        [ -z "$(mysql_cmd -e "SELECT 1 FROM mysql.user WHERE user='$user' AND host='localhost';")" ] || fail "db user already exists"
        mysql_cmd <<SQL
CREATE DATABASE $(qt "$db") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$user'@'localhost' IDENTIFIED WITH caching_sha2_password BY '$pw';
GRANT ALL PRIVILEGES ON $(qt "$db").* TO '$user'@'localhost';
FLUSH PRIVILEGES;
SQL
    fi
    ok "\"database\":\"$db\",\"user\":\"$user\""
}

cmd_passwd() {
    [ $# -eq 2 ] || usage
    local db="$1" user="$2" pw
    valid_dbname "$db" || fail "invalid database name"
    valid_dbuser "$user" || fail "invalid database user"
    pw="$(read_password)"

    if ! is_dry_run; then
        [ -n "$(mysql_cmd -e "SELECT 1 FROM mysql.user WHERE user='$user' AND host='localhost';")" ] || fail "db user does not exist"
        mysql_cmd -e "ALTER USER '$user'@'localhost' IDENTIFIED WITH caching_sha2_password BY '$pw'; FLUSH PRIVILEGES;"
    fi
    ok
}

cmd_delete() {
    [ $# -eq 2 ] || usage
    local db="$1" user="$2"
    valid_dbname "$db" || fail "invalid database name"
    valid_dbuser "$user" || fail "invalid database user"

    if ! is_dry_run; then
        mysql_cmd -e "DROP DATABASE IF EXISTS $(qt "$db"); DROP USER IF EXISTS '$user'@'localhost'; FLUSH PRIVILEGES;"
    fi
    ok
}

case "$action" in
    create) cmd_create "$@" ;;
    passwd) cmd_passwd "$@" ;;
    delete) cmd_delete "$@" ;;
    *) usage ;;
esac
