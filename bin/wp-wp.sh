#!/bin/bash
# ============================================================================
# wp-wp.sh - one-click WordPress (+WooCommerce-ready) deploy via WP-CLI
#
#   install <user> <domain> <db> <dbuser> <title> <admin_user> <admin_email>
#       stdin: line1 = db password, line2 = wp admin password
#
# Files are downloaded as the site user, so ownership is correct from the
# first byte (core/plugin updates work without chmod 777).
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=wp-lib.sh
. "$SCRIPT_DIR/wp-lib.sh"

[ $# -ge 1 ] || fail "usage: wp-wp.sh install ..." 64
action="$1"; shift
require_root
[ "$action" = "install" ] || fail "unknown action"

[ $# -eq 7 ] || fail "usage: install <user> <domain> <db> <dbuser> <title> <admin> <email>"
user="$1"; domain="$2"; db="$3"; dbuser="$4"; title="$5"; admin="$6"; email="$7"

valid_user "$user" || fail "invalid site user"
valid_domain "${domain,,}" || fail "invalid domain"
valid_dbname "$db" || fail "invalid database name"
valid_dbuser "$dbuser" || fail "invalid database user"
re_admin='^[A-Za-z0-9_.-]{3,60}$'
[[ "$admin" =~ $re_admin ]] || fail "invalid admin username"
[[ "$email" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || fail "invalid admin email"

IFS= read -r dbpass
IFS= read -r wppass
[ ${#wppass} -ge 8 ] || fail "wp admin password too short"
[ ${#dbpass} -ge 8 ] || fail "db password too short"

wp="/usr/local/bin/wp"
webroot="$(docroot_of "$user")"

if is_dry_run; then
    echo "[dry-run] wp core download/config/install for $user @ $domain ($db)" >&2
    ok "\"domain\":\"$domain\",\"admin\":\"$admin\",\"url\":\"http://$domain/wp-admin/\""
fi

[ -x "$wp" ] || fail "wp-cli not installed"
id "$user" &>/dev/null || fail "site user missing"
[ -z "$(ls -A "$webroot" 2>/dev/null)" ] || fail "document root is not empty"

runwp() {
    runuser -u "$user" -- env HOME="$WEB_ROOT/$user" "$wp" --path="$webroot" "$@"
}

# 1. download core
runwp core download --locale=zh_CN --force >/dev/null || fail "wp core download failed"

# 2. wp-config.php (password supplied via stdin prompt, never via argv)
printf '%s\n' "$dbpass" | runwp config create \
    --dbname="$db" --dbuser="$dbuser" --dbhost="localhost" --dbprefix="wp_" \
    --prompt=dbpass --force >/dev/null || fail "wp config create failed"

runwp config set FS_METHOD direct >/dev/null
runwp config set WP_MEMORY_LIMIT 512M >/dev/null
runwp config set WP_MAX_MEMORY_LIMIT 512M >/dev/null
runwp config set WP_POST_REVISIONS 20 --raw >/dev/null
runwp config set DISALLOW_FILE_EDIT true --raw >/dev/null

# 3. install (admin password via stdin prompt)
printf '%s\n' "$wppass" | runwp core install \
    --url="http://${domain,,}" --title="$title" \
    --admin_user="$admin" --admin_email="$email" \
    --prompt=admin_password --skip-email >/dev/null || fail "wp core install failed"

# 4. WooCommerce-friendly defaults + pretty permalinks
runwp rewrite structure '/%post%/' >/dev/null
runwp rewrite flush --hard >/dev/null 2>&1 || true
runwp option update timezone_string 'Asia/Shanghai' >/dev/null
runwp option update blog_charset 'UTF-8' >/dev/null

# 5. harden permissions
chmod 640 "$webroot/wp-config.php"
chown "$user:$user" "$webroot/wp-config.php"
find "$webroot" -type d -exec chmod 755 {} \;
find "$webroot" -type f -exec chmod 644 {} \;
chmod 640 "$webroot/wp-config.php"

ok "\"domain\":\"${domain,,}\",\"admin\":\"$admin\",\"url\":\"http://${domain,,}/wp-admin/\""
