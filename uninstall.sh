#!/bin/bash
# ============================================================================
# WebPanel 卸载脚本
#   bash uninstall.sh             # 仅卸载面板，保留站点/数据库/Nginx/PHP/MySQL
#   bash uninstall.sh --purge     # 危险：同时删除全部站点文件、数据库、证书
# ============================================================================
set -uo pipefail

[ "$(id -u)" -eq 0 ] || { echo "需要 root 权限"; exit 1; }

INSTALL_DIR=/usr/local/webpanel
VHOST_DIR=/www/server/panel/vhost
PURGE=0
[ "${1:-}" = "--purge" ] && PURGE=1
PHP_VERSIONS=(74 80 81 82 83)

echo "==> 停止面板相关服务"
systemctl stop php-fpm 2>/dev/null || true

echo "==> 移除面板 Nginx 配置"
rm -f /etc/nginx/conf.d/00-webpanel.conf
[ "$PURGE" -eq 1 ] && rm -rf "$VHOST_DIR" || rm -f "$VHOST_DIR"/*.conf 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo "==> 移除面板 FPM 池、phpMyAdmin 与 sudoers"
rm -f /etc/php-fpm.d/webpanel.conf /etc/php-fpm.d/phpmyadmin.conf
rm -f /etc/sudoers.d/webpanel
rm -rf /www/server/phpmyadmin
if [ -f /root/.my.cnf ]; then
    mysql --defaults-file=/root/.my.cnf -e \
        "DROP USER IF EXISTS 'webpanel_pma'@'127.0.0.1'; DROP USER IF EXISTS 'webpanel_pma'@'localhost'; FLUSH PRIVILEGES;" \
        2>/dev/null || true
fi

if [ "$PURGE" -eq 1 ]; then
    read -r -p "确认删除所有站点文件、数据库与证书？输入 YES 继续: " ans
    [ "$ans" = "YES" ] || { echo "已取消"; exit 1; }

    echo "==> 删除站点 FPM 池、Node 服务单元、系统用户与文件"
    for v in "${PHP_VERSIONS[@]}"; do
        rm -f /etc/opt/remi/php$v/php-fpm.d/*.conf
        systemctl restart "php${v}-php-fpm" 2>/dev/null || true
    done
    for u in /etc/systemd/system/wp-node-*.service; do
        [ -f "$u" ] || continue
        systemctl disable --now "$(basename "$u")" 2>/dev/null || true
        rm -f "$u"
    done
    systemctl daemon-reload 2>/dev/null || true
    for u in $(awk -F: '$6 ~ "^/www/wwwroot/" {print $1}' /etc/passwd); do
        userdel -r "$u" 2>/dev/null || true
    done
    rm -rf /www/wwwroot /www/wwwlogs /www/server/certs

    echo "==> 删除所有非系统数据库"
    if [ -f /root/.my.cnf ]; then
        for db in $(mysql -N -B -e "SELECT schema_name FROM information_schema.schemata
            WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys');" 2>/dev/null); do
            mysql -e "DROP DATABASE \`$db\`;"
        done
    fi
    # PostgreSQL：删除面板创建的用户库（跳过 postgres 模板库）
    PG_PSQL="$([ -x /usr/pgsql-16/bin/psql ] && echo /usr/pgsql-16/bin/psql || command -v psql || true)"
    if [ -n "$PG_PSQL" ] && runuser -u postgres -- "$PG_PSQL" -qAt -c "SELECT 1" >/dev/null 2>&1; then
        for db in $(runuser -u postgres -- "$PG_PSQL" -qAt -c "SELECT datname FROM pg_database WHERE NOT datistemplate AND datname <> 'postgres';" 2>/dev/null); do
            runuser -u postgres -- "$PG_PSQL" -qAt -c "DROP DATABASE IF EXISTS \"$db\";" 2>/dev/null || true
        done
    fi

    echo "==> 卸载软件包（Nginx/MySQL/PHP/Node.js/PostgreSQL 全部移除）"
    dnf remove -y nginx mysql-community-server postgresql16-server nodejs \
        "php*-php-*" php php-fpm php-cli 2>/dev/null || true
    rm -rf /www /var/lib/mysql /var/lib/pgsql
fi

echo "==> 删除面板程序"
systemctl restart php-fpm 2>/dev/null || true
rm -rf "$INSTALL_DIR"
rm -f /etc/cron.d/webpanel-acme

echo "卸载完成。$([ "$PURGE" -eq 1 ] && echo '（已清除全部数据）')"
