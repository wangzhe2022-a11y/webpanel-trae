#!/bin/bash
# ============================================================================
# WebPanel 一键安装脚本  -  AlmaLinux 10.x / RHEL 10.x
#
# 与 AlmaLinux 8.x 版本的关键差异：
#   - Remi / MySQL / PGDG 仓库使用 EL-10 地址
#   - DNF Modules 已废弃，移除所有 dnf module 命令
#   - MySQL 8.0 在 EL10 已停更，改用 MySQL 8.4 LTS
#   - PHP 8.1 已 EOL，不再提供（保留 7.4/8.0/8.2/8.3）
#   - 面板自身 PHP 走系统仓库（AlmaLinux 10 自带 PHP 8.4）
#   - CRB 仓库通过 crb enable 开启
#
# 安装内容：
#   - Nginx
#   - MySQL 8.4 LTS（Oracle 官方 community 仓库）
#   - PostgreSQL 16（系统自带，仅监听 127.0.0.1）
#   - PHP 7.4 / 8.0 / 8.2 / 8.3（Remi SCL，各版本独立 FPM）
#   - Node.js 22 LTS（NodeSource，systemd 托管 + Nginx 反代）
#   - 面板本体（系统 PHP + SQLite + Layui），HTTPS 端口 8888（自签证书）
#   - phpMyAdmin（挂在面板 /phpmyadmin/，登录会话保护）
#   - acme.sh（Let's Encrypt 自动签发/续期）
#   - WP-CLI（一键部署 WordPress / WooCommerce）
#
# 可覆盖变量：
#   PANEL_PORT=8888 PANEL_ADMIN=admin ACME_EMAIL=admin@example.com bash install-al10.sh
# ============================================================================

set -euo pipefail

PANEL_PORT="${PANEL_PORT:-8888}"
PANEL_ADMIN="${PANEL_ADMIN:-admin}"
ACME_EMAIL="${ACME_EMAIL:-}"
# Comma-separated IPv4 addresses allowed to access the panel.
# If empty, no IP whitelist is enforced (panel login lockout still applies).
# Example: PANEL_ALLOW_IPS="1.2.3.4,5.6.7.8"
PANEL_ALLOW_IPS="${PANEL_ALLOW_IPS:-}"
INSTALL_DIR="/usr/local/webpanel"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

WWW_ROOT=/www/wwwroot
LOG_ROOT=/www/wwwlogs
CERT_ROOT=/www/server/certs
ACME_HOME=/www/server/acme.sh
VHOST_DIR=/www/server/panel/vhost
# PHP 8.1 已 EOL，Remi 不再提供 EL10 包
PHP_VERSIONS=(74 80 82 83)

c_blue() { printf '\033[1;36m%s\033[0m\n' "$*"; }
c_ok()   { printf '\033[1;32m[ OK ]\033[0m %s\n' "$*"; }
c_warn() { printf '\033[1;33m[WARN]\033[0m %s\n' "$*"; }
die()    { printf '\033[1;31m[ERR ]\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------- preflight
[ "$(id -u)" -eq 0 ] || die "请使用 root 执行：sudo bash install-al10.sh"
[ -f /etc/redhat-release ] || die "本脚本仅支持 AlmaLinux/Rocky/RHEL 10.x"
grep -Eq 'release 10' /etc/redhat-release || c_warn "未检测到 10.x 版本，继续但不保证兼容"
[ -d "$SRC_DIR/panel" ] && [ -d "$SRC_DIR/bin" ] || die "源码目录结构不完整，请在仓库根目录执行"

c_blue "==> [1/12] 基础工具与仓库（EPEL / CRB / Remi / MySQL / PGDG / NodeSource）"
dnf install -y epel-release dnf-utils curl wget tar zip unzip bash-completion \
    policycoreutils-python-utils cronie firewalld openssl which

# CRB（CodeReady Builder）—— Remi 部分扩展依赖
dnf config-manager --set-enabled crb 2>/dev/null || crb enable 2>/dev/null || \
    c_warn "CRB 仓库启用失败，部分 PHP 扩展可能不可用"

# Remi 仓库（EL10）
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-10.rpm || \
    c_warn "Remi 仓库安装失败，多版本 PHP 可能不可用"

# MySQL 8.4 LTS 仓库（EL10；MySQL 8.0 在 EL10 已停更）
if ! rpm -q mysql84-community-release >/dev/null 2>&1; then
    dnf install -y https://dev.mysql.com/get/mysql84-community-release-el10-3.noarch.rpm || \
        die "MySQL 8.4 仓库安装失败，请检查外网连接"
fi
# 仓库默认启用 9.7，手动切到 8.4 LTS
dnf config-manager --disable mysql-9.7-lts-community mysql-tools-9.7-lts-community 2>/dev/null || true
dnf config-manager --enable mysql-8.4-lts-community mysql-tools-8.4-lts-community 2>/dev/null || true

# PostgreSQL：AlmaLinux 10 系统自带 16，无需 PGDG 仓库
# Node.js 22 LTS（NodeSource）
if ! rpm -q nodesource-release-el10 >/dev/null 2>&1; then
    curl -fsSL https://rpm.nodesource.com/setup_22.x | bash - \
        || c_warn "NodeSource 仓库安装失败，Node.js 功能将不可用"
fi
c_ok "软件仓库就绪"

c_blue "==> [2/12] 安装 Nginx 与 MySQL 8.4"
dnf install -y nginx mysql-community-server
c_ok "Nginx / MySQL 软件包安装完成"

c_blue "==> [3/12] 安装 PHP（面板用系统 PHP，站点支持多版本切换）"
# 面板自身使用 AlmaLinux 10 系统 PHP（8.4）
dnf install -y php-cli php-fpm php-pdo php-sqlite3 php-mbstring php-gd php-xml \
    php-curl php-opcache php-mysqlnd php-zip php-intl php-bcmath php-soap php-process

# 站点多版本：Remi SCL 并行安装，服务名 phpXX-php-fpm
for v in "${PHP_VERSIONS[@]}"; do
    c_blue "    - php$v（php${v}-php-fpm + WordPress/WooCommerce 常用扩展）"
    dnf install -y \
        "php${v}-php-cli" "php${v}-php-fpm" "php${v}-php-mysqlnd" \
        "php${v}-php-gd" "php${v}-php-mbstring" "php${v}-php-xml" \
        "php${v}-php-intl" "php${v}-php-zip" "php${v}-php-curl" \
        "php${v}-php-bcmath" "php${v}-php-opcache" "php${v}-php-soap" \
        || c_warn "PHP $v 安装失败，该版本站点功能不可用"
done
c_ok "PHP 安装完成（系统 PHP $(php -v 2>/dev/null | head -1 | awk '{print $2}') + Remi 多版本）"

c_blue "==> [4/12] 安装 Node.js 22 LTS 与 PostgreSQL 16"
# Node.js（NodeSource；仓库不可用时回退 AppStream 默认版本）
dnf install -y nodejs || c_warn "Node.js 安装失败，Node 站点功能不可用"
command -v node >/dev/null 2>&1 \
    && c_ok "Node.js $(node -v) / npm $(npm -v)" \
    || c_warn "Node.js 未安装成功"

# PostgreSQL 16（AlmaLinux 10 系统自带，无需额外仓库）
dnf install -y postgresql-server postgresql-contrib \
    || c_warn "PostgreSQL 安装失败，面板仅提供 MySQL 数据库"

c_blue "==> [5/12] 初始化 MySQL 8.4"
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

c_blue "==> [6/12] 初始化 PostgreSQL 16（仅本机 127.0.0.1:5432，scram-sha-256）"
if rpm -q postgresql-server >/dev/null 2>&1; then
    PGDATA=/var/lib/pgsql/data
    [ -s "$PGDATA/PG_VERSION" ] || postgresql-setup --initdb \
        || die "PostgreSQL initdb 失败"

    # 认证策略：本地套接字 peer，回环 TCP scram-sha-256（wp-pg.sh 依赖此策略）
    cat > "$PGDATA/pg_hba.conf" <<'EOF'
# managed by WebPanel - do not edit
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     peer
host    all             all             127.0.0.1/32            scram-sha-256
host    all             all             ::1/128                 scram-sha-256
EOF
    grep -q '^password_encryption' "$PGDATA/postgresql.conf" \
        || echo "password_encryption = scram-sha-256" >> "$PGDATA/postgresql.conf"

    systemctl enable --now postgresql
    c_ok "PostgreSQL 16 已启动（数据库引擎选择：MySQL / PostgreSQL 均可用）"
else
    c_warn "PostgreSQL 未安装，面板数据库功能仅 MySQL 可用"
fi

c_blue "==> [7/12] 创建目录、系统用户与面板文件"
id webpanel &>/dev/null || useradd --system --no-create-home --shell /sbin/nologin webpanel
install -d -m 755 "$WWW_ROOT" "$LOG_ROOT" "$CERT_ROOT" "$VHOST_DIR" \
    "$ACME_HOME" "$INSTALL_DIR"

# 部署程序文件
cp -a "$SRC_DIR/bin" "$SRC_DIR/panel" "$SRC_DIR/config" "$INSTALL_DIR/"
cp -a "$SRC_DIR/install.sh" "$SRC_DIR/install-al10.sh" "$SRC_DIR/uninstall.sh" "$INSTALL_DIR/" 2>/dev/null || true
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
install -d -o webpanel -g webpanel -m 775 \
    "$INSTALL_DIR/panel/public/static/uploads"

# sudoers 白名单（先 visudo 语法校验再落地）
install -m 440 "$INSTALL_DIR/config/sudoers.d/webpanel" /etc/sudoers.d/webpanel
visudo -cf /etc/sudoers.d/webpanel >/dev/null
c_ok "特权脚本与 sudoers 白名单就位"

c_blue "==> [8/12] 配置 Nginx 与 PHP-FPM"
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

# Panel IP access control files (deny list + allow list)
install -d -m 755 /www/server/panel
install -m 644 "$INSTALL_DIR/config/nginx/deny-ips.conf.tmpl" /www/server/panel/deny-ips.conf
if [ -n "$PANEL_ALLOW_IPS" ]; then
    {
        cat "$INSTALL_DIR/config/nginx/allow-ips.conf.tmpl"
        printf '\n'
        IFS=',' read -ra _allow_ips <<< "$PANEL_ALLOW_IPS"
        for _ip in "${_allow_ips[@]}"; do
            _ip="${_ip// /}"
            [ -n "$_ip" ] && printf 'allow %s;\n' "$_ip"
        done
        printf 'deny all;\n'
    } > /www/server/panel/allow-ips.conf
    c_ok "面板 IP 白名单已启用：$PANEL_ALLOW_IPS"
else
    install -m 644 "$INSTALL_DIR/config/nginx/allow-ips.conf.tmpl" /www/server/panel/allow-ips.conf
    c_warn "未设置面板 IP 白名单（PANEL_ALLOW_IPS 为空），所有 IP 均可访问面板登录页"
fi

# 内置 phpMyAdmin（下载失败不阻断面板安装，可稍后 wp-pma.sh install）
if "$INSTALL_DIR/bin/wp-pma.sh" install; then
    c_ok "phpMyAdmin 已安装（/phpmyadmin/，需登录面板）"
else
    c_warn "phpMyAdmin 安装失败（外网或校验问题），可稍后执行：$INSTALL_DIR/bin/wp-pma.sh install"
fi

# 托管站点 vhost 总入口（含 Node.js 反代所需的 websocket upgrade 映射）
cat >/etc/nginx/conf.d/zz-webpanel-sites.conf <<EOF
# managed by WebPanel - do not edit
map \$http_upgrade \$connection_upgrade {
    default upgrade;
    ''      close;
}
include $VHOST_DIR/*.conf;
EOF

nginx -t
c_ok "Nginx 配置完成"

c_blue "==> [9/12] 安装 acme.sh 与 WP-CLI"
if [ ! -x "$ACME_HOME/acme.sh" ]; then
    if [ -n "$ACME_EMAIL" ]; then
        curl -fsSL https://get.acme.sh | sh -s -- --home "$ACME_HOME" --nocron --accountemail "$ACME_EMAIL"
    else
        curl -fsSL https://get.acme.sh | sh -s -- --home "$ACME_HOME" --nocron
    fi
fi
install -m 644 "$INSTALL_DIR/config/cron.d/webpanel-acme" /etc/cron.d/webpanel-acme
install -m 644 "$INSTALL_DIR/config/cron.d/webpanel-backup" /etc/cron.d/webpanel-backup

if [ ! -x /usr/local/bin/wp ]; then
    curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod 755 /usr/local/bin/wp
fi
c_ok "acme.sh / WP-CLI 就绪"

c_blue "==> [10/12] 启动全部服务"
systemctl enable nginx php-fpm mysqld crond
rpm -q postgresql-server >/dev/null 2>&1 && systemctl enable postgresql
for v in "${PHP_VERSIONS[@]}"; do
    if systemctl cat "php${v}-php-fpm.service" >/dev/null 2>&1; then
        systemctl enable "php${v}-php-fpm"
    else
        c_warn "跳过 php${v}-php-fpm（未安装）"
    fi
done
systemctl restart php-fpm
for v in "${PHP_VERSIONS[@]}"; do
    if systemctl cat "php${v}-php-fpm.service" >/dev/null 2>&1; then
        systemctl restart "php${v}-php-fpm"
    fi
done
systemctl restart nginx

# 创建面板管理员（密码生成并在末尾展示）
c_blue "==> [11/12] 初始化面板账号"
/usr/bin/php "$INSTALL_DIR/panel/tools/admin.php" password "$PANEL_ADMIN" \
    | tee /root/.webpanel-admin.txt
chmod 600 /root/.webpanel-admin.txt

c_blue "==> [12/12] 防火墙 / SELinux / 时区"
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
  WebPanel 安装完成（AlmaLinux 10）
============================================================
  面板地址 : https://${PUB_IP:-<服务器公网IP>}:${PANEL_PORT}/
             （自签证书，浏览器提示不安全属正常，选择继续即可）
  phpMyAdmin: https://${PUB_IP:-<服务器公网IP>}:${PANEL_PORT}/phpmyadmin/
             （须先登录面板，未登录会跳到登录页）
  账号信息 : 已同时保存到 /root/.webpanel-admin.txt
  运行栈   : Nginx / PHP 7.4/8.0/8.2/8.3（Remi SCL）/ MySQL 8.4$(rpm -q postgresql-server >/dev/null 2>&1 && echo ' / PostgreSQL 16')$(command -v node >/dev/null 2>&1 && echo " / Node.js $(node -v)")

  必须在腾讯云控制台完成：
    1. 安全组放行入站 TCP 80 / 443 / ${PANEL_PORT}（面板端口建议仅对自己的 IP 开放）
    2. 域名添加 A 记录指向 ${PUB_IP:-<服务器公网IP>} 后，再到面板「SSL 证书」页签发证书

  常用目录：
    站点文件 : $WWW_ROOT/<站点用户>/public（PHP）或 $WWW_ROOT/<站点用户>/app（Node.js）
    站点日志 : $LOG_ROOT
    证书目录 : $CERT_ROOT
  找回/重置面板密码：
    ${INSTALL_DIR}/bin/wp-panel.sh password ${PANEL_ADMIN}
============================================================
EOF
