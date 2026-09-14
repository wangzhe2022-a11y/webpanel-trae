#!/bin/bash
# ============================================================================
# WebPanel 一键安装脚本  -  AlmaLinux 8.x / RHEL 8.x（兼容 8.10）
#
# 安装内容：
#   - Nginx（官方仓库 module）
#   - MySQL 8.0（Oracle 官方 community 仓库）
#   - PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3（Remi SCL，各版本独立 FPM 可随时切换）
#   - 面板本体（PHP 8.2 + SQLite + Layui），HTTPS 端口 8888（自签证书）
#   - acme.sh（Let's Encrypt 自动签发/续期）
#   - WP-CLI（一键部署 WordPress / WooCommerce）
#
# 可覆盖变量：
#   PANEL_PORT=8888 PANEL_ADMIN=admin ACME_EMAIL=admin@example.com bash install.sh
#
# 用法：
#   dnf install -y curl git ; git clone <repo> webpanel ; cd webpanel ; bash install.sh
# ============================================================================

set -euo pipefail

PANEL_PORT="${PANEL_PORT:-8888}"
PANEL_ADMIN="${PANEL_ADMIN:-admin}"
ACME_EMAIL="${ACME_EMAIL:-}"
INSTALL_DIR="/usr/local/webpanel"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

WWW_ROOT=/www/wwwroot
LOG_ROOT=/www/wwwlogs
CERT_ROOT=/www/server/certs
ACME_HOME=/www/server/acme.sh
VHOST_DIR=/www/server/panel/vhost
PHP_VERSIONS=(74 80 81 82 83)

c_blue() { printf '\033[1;36m%s\033[0m\n' "$*"; }
c_ok()   { printf '\033[1;32m[ OK ]\033[0m %s\n' "$*"; }
c_warn() { printf '\033[1;33m[WARN]\033[0m %s\n' "$*"; }
die()    { printf '\033[1;31m[ERR ]\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------- preflight
[ "$(id -u)" -eq 0 ] || die "请使用 root 执行：sudo bash install.sh"
[ -f /etc/redhat-release ] || die "本脚本仅支持 AlmaLinux/Rocky/RHEL 8.x"
grep -Eq 'release 8' /etc/redhat-release || c_warn "未检测到 8.x 版本，继续但不保证兼容"
[ -d "$SRC_DIR/panel" ] && [ -d "$SRC_DIR/bin" ] || die "源码目录结构不完整，请在仓库根目录执行"

c_blue "==> [1/10] 基础工具与仓库（EPEL / Remi / MySQL 8.0）"
dnf install -y epel-release dnf-utils curl wget tar unzip bash-completion \
    policycoreutils-python-utils cronie firewalld openssl which
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm || \
    c_warn "Remi 仓库安装失败，稍后多版本 PHP 可能不可用（可检查外网/代理）"
if ! rpm -q mysql80-community-release >/dev/null 2>&1; then
    dnf install -y https://dev.mysql.com/get/mysql80-community-release-el8-9.noarch.rpm
fi
c_ok "软件仓库就绪"

c_blue "==> [2/10] 安装 Nginx 与 MySQL 8.0"
dnf install -y nginx mysql-community-server
c_ok "Nginx / MySQL 软件包安装完成"

c_blue "==> [3/10] 安装 PHP（面板运行于 8.2，站点支持多版本切换）"
# 面板自身使用系统模块 PHP 8.2
dnf module reset -y php
dnf module enable -y php:remi-8.2
dnf install -y php-cli php-fpm php-pdo php-sqlite3 php-mbstring php-gd php-xml \
    php-json php-curl php-opcache

# 站点多版本：Remi SCL 并行安装，服务名 phpXX-php-fpm
for v in "${PHP_VERSIONS[@]}"; do
    c_blue "    - php$v（php${v}-php-fpm + WordPress/WooCommerce 常用扩展）"
    dnf install -y \
        "php${v}-php-cli" "php${v}-php-fpm" "php${v}-php-mysqlnd" \
        "php${v}-php-gd" "php${v}-php-mbstring" "php${v}-php-xml" \
        "php${v}-php-intl" "php${v}-php-zip" "php${v}-php-curl" \
        "php${v}-php-bcmath" "php${v}-php-opcache" "php${v}-php-soap"
done
c_ok "PHP 安装完成"

c_blue "==> [4/10] 初始化 MySQL 8.0"
systemctl enable --now mysqld
for i in $(seq 1 30); do
    mysqladmin ping --silent 2>/dev/null && break
    sleep 1
done

MYSQL_ROOT_PW="Rp_$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)9"
TMP_PW="$(grep 'temporary password' /var/log/mysqld.log 2>/dev/null | tail -1 | awk '{print $NF}' || true)"
if [ -n "$TMP_PW" ]; then
    mysql --connect-expired-password -uroot -p"$TMP_PW" \
        -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${MYSQL_ROOT_PW}'; FLUSH PRIVILEGES;" \
        || die "MySQL root 密码初始化失败"
else
    c_warn "未找到临时密码，可能 MySQL 已初始化（复用现有实例）"
fi

umask 077
cat >/root/.my.cnf <<EOF
[client]
user=root
password="${MYSQL_ROOT_PW}"
socket=/var/lib/mysql/mysql.sock
EOF
chmod 600 /root/.my.cnf
mysql -e "SELECT VERSION()" >/dev/null || die "MySQL root 凭据写入失败"
c_ok "MySQL 已启动，root 密码已保存到 /root/.my.cnf（0600）"

c_blue "==> [5/10] 创建目录、系统用户与面板文件"
id webpanel &>/dev/null || useradd --system --no-create-home --shell /sbin/nologin webpanel
install -d -m 755 "$WWW_ROOT" "$LOG_ROOT" "$CERT_ROOT" "$VHOST_DIR" \
    "$ACME_HOME" "$INSTALL_DIR"

# 部署程序文件
cp -a "$SRC_DIR/bin" "$SRC_DIR/panel" "$SRC_DIR/config" "$INSTALL_DIR/"
cp -a "$SRC_DIR/install.sh" "$SRC_DIR/uninstall.sh" "$INSTALL_DIR/" 2>/dev/null || true
chown -R root:root "$INSTALL_DIR"
chmod 755 "$INSTALL_DIR"/bin/*.sh
chmod 644 "$INSTALL_DIR"/bin/fs-worker.php

# 面板 PHP 代码：root 所有、webpanel 组只读
chgrp -R webpanel "$INSTALL_DIR/panel"
find "$INSTALL_DIR/panel" -type d -exec chmod 750 {} \;
find "$INSTALL_DIR/panel" -type f -exec chmod 640 {} \;
install -d -o webpanel -g webpanel -m 700 \
    "$INSTALL_DIR/panel/data" "$INSTALL_DIR/panel/data/sessions" \
    "$INSTALL_DIR/panel/data/tmp" "$INSTALL_DIR/panel/data/cache"

# sudoers 白令（先 visudo 语法校验再落地）
install -m 440 "$INSTALL_DIR/config/sudoers.d/webpanel" /etc/sudoers.d/webpanel
visudo -cf /etc/sudoers.d/webpanel >/dev/null
c_ok "特权脚本与 sudoers 白名单就位"

c_blue "==> [6/10] 配置 Nginx 与 PHP-FPM"
# 关闭各 FPM 默认池（默认都抢 9000 端口）
[ -f /etc/php-fpm.d/www.conf ] && mv -f /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.disabled
for v in "${PHP_VERSIONS[@]}"; do
    poolf="/etc/opt/remi/php$v/php-fpm.d/www.conf"
    [ -f "$poolf" ] && mv -f "$poolf" "${poolf}.disabled"
done

# 面板自己的 FPM 池
install -m 644 "$INSTALL_DIR/config/php-fpm/panel.conf" /etc/php-fpm.d/webpanel.conf

# 面板 vhost（HTTPS 自签证书）
install -d "$CERT_ROOT/panel"
if [ ! -f "$CERT_ROOT/panel/fullchain.pem" ]; then
    openssl req -x509 -newkey rsa:2048 -nodes -days 3650 \
        -subj "/CN=webpanel-selfsigned" \
        -keyout "$CERT_ROOT/panel/privkey.pem" \
        -out    "$CERT_ROOT/panel/fullchain.pem" >/dev/null 2>&1
fi
sed "s|{{PORT}}|$PANEL_PORT|g" "$INSTALL_DIR/config/nginx/panel.conf.tmpl" \
    > /etc/nginx/conf.d/00-webpanel.conf

# 托管站点 vhost 总入口
cat >/etc/nginx/conf.d/zz-webpanel-sites.conf <<EOF
# managed by WebPanel - do not edit
include $VHOST_DIR/*.conf;
EOF

nginx -t
c_ok "Nginx 配置完成"

c_blue "==> [7/10] 安装 acme.sh 与 WP-CLI"
if [ ! -x "$ACME_HOME/acme.sh" ]; then
    if [ -n "$ACME_EMAIL" ]; then
        curl -fsSL https://get.acme.sh | sh -s -- --home "$ACME_HOME" --nocron --accountemail "$ACME_EMAIL"
    else
        curl -fsSL https://get.acme.sh | sh -s -- --home "$ACME_HOME" --nocron
    fi
fi
install -m 644 "$INSTALL_DIR/config/cron.d/webpanel-acme" /etc/cron.d/webpanel-acme

if [ ! -x /usr/local/bin/wp ]; then
    curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod 755 /usr/local/bin/wp
fi
c_ok "acme.sh / WP-CLI 就绪"

c_blue "==> [8/10] 启动全部服务"
systemctl enable nginx php-fpm mysqld crond
for v in "${PHP_VERSIONS[@]}"; do
    systemctl enable "php${v}-php-fpm"
done
systemctl restart php-fpm
for v in "${PHP_VERSIONS[@]}"; do
    systemctl restart "php${v}-php-fpm"
done
systemctl restart nginx

# 创建面板管理员（密码生成并在末尾展示）
c_blue "==> [9/10] 初始化面板账号"
/usr/bin/php "$INSTALL_DIR/panel/tools/admin.php" password "$PANEL_ADMIN" \
    | tee /root/.webpanel-admin.txt
chmod 600 /root/.webpanel-admin.txt

c_blue "==> [10/10] 防火墙 / SELinux / 时区"
systemctl enable --now firewalld >/dev/null 2>&1 || true
firewall-cmd --permanent --add-service=http  >/dev/null 2>&1 || true
firewall-cmd --permanent --add-service=https >/dev/null 2>&1 || true
firewall-cmd --permanent --add-port="${PANEL_PORT}/tcp" >/dev/null 2>&1 || true
firewall-cmd --reload >/dev/null 2>&1 || true

# Nginx/PHP-FPM 在自定义路径运行需要 SELinux 策略；第三方面板通行做法是 permissive
if command -v getenforce >/dev/null && [ "$(getenforce)" = "Enforcing" ]; then
    setenforce 0 || true
    sed -i 's/^SELINUX=enforcing/SELINUX=permissive/' /etc/selinux/config
    c_warn "SELinux 已切换为 permissive（第三方面板通行做法，可日后按需加固）"
fi
timedatectl set-timezone Asia/Shanghai 2>/dev/null || true

# ------------------------------------------------------------------ summary
PUB_IP="$(curl -fsSL --max-time 3 http://169.254.0.23/latest/meta-data/public-ipv4 2>/dev/null || true)"
[ -z "$PUB_IP" ] && PUB_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"

cat <<EOF

============================================================
  WebPanel 安装完成
============================================================
  面板地址 : https://${PUB_IP:-<服务器公网IP>}:${PANEL_PORT}/
             （自签证书，浏览器提示不安全属正常，选择继续即可）
  账号信息 : 已同时保存到 /root/.webpanel-admin.txt

  必须在腾讯云控制台完成：
    1. 安全组放行入站 TCP 80 / 443 / ${PANEL_PORT}（面板端口建议仅对自己的 IP 开放）
    2. 域名添加 A 记录指向 ${PUB_IP:-<服务器公网IP>} 后，再到面板「SSL 证书」页签发证书

  常用目录：
    站点文件 : $WWW_ROOT/<站点用户>/public
    站点日志 : $LOG_ROOT
    证书目录 : $CERT_ROOT
  找回/重置面板密码：
    ${INSTALL_DIR}/bin/wp-panel.sh password ${PANEL_ADMIN}
============================================================
EOF
