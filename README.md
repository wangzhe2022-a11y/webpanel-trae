# webpanel-trae

轻量自研的 Linux 主机管理面板，用来替代 cPanel / 宝塔。面向 **WordPress + WooCommerce**
场景设计，运行于腾讯云 **AlmaLinux 8.10（4 核 8G SA9 已验证）**。

## 功能

| 模块 | 能力 |
| --- | --- |
| 网站/域名 | 创建站点（**PHP 站点 / Node.js 站点**二选一）、主域名 + 多别名、独立 Linux 用户隔离、Nginx vhost（WordPress 伪静态/上传加固） |
| 多版本 PHP | PHP **7.4 / 8.0 / 8.1 / 8.2 / 8.3**（Remi SCL 并行安装），每个站点独立 FPM 池（unix socket、ondemand 省电），页面下拉一键切换 |
| Node.js | **Node.js 22 LTS**（NodeSource 官方仓库），站点应用以独立系统用户跑在 systemd 单元里（崩溃自动拉起），Nginx 反向代理 + WebSocket 支持；页面一键「npm i / 重启」 |
| 数据库 | **MySQL 8.0** 与 **PostgreSQL 16**（PGDG 官方仓库）双引擎并存，建库/建用户/改密/删除任选；MySQL utf8mb4 账号仅授权本库；PG 仅监听 127.0.0.1（scram-sha-256）；密码自动生成、只显示一次 |
| SSL | 方式一：acme.sh 自动签发 Let's Encrypt（http-01）+ 自动续签（cron）+ 一键 HTTPS 跳转 + HSTS；方式二：**上传第三方证书**（腾讯云 TrustAsia 等，粘贴 PEM 或选文件，自动校验证书/私钥/域名匹配与有效期）。PHP 与 Node 站点均支持 |
| 文件管理 | 目录浏览、在线编辑文本、上传/下载、新建、重命名、改权限、删除；**严格 jailed 在站点目录内**，拒绝路径穿越与符号链接逃逸 |
| WordPress | WP-CLI 一键部署中文版 WordPress（wp-config、固定链接、WooCommerce 内存参数、FS_METHOD 全部配好），自动生成管理员密码 |
| 系统 | 仪表盘（CPU/内存/磁盘/负载）、Nginx/MySQL/PostgreSQL/各版本 PHP-FPM/Node 服务启停重载、操作审计日志 |

## 架构与安全模型

```
浏览器 ──HTTPS:8888──▶ nginx ──unix socket──▶ php-fpm 池 [webpanel 用户]
                                                  │ 仅能调用
                                                  ▼
                          /etc/sudoers.d/webpanel 白名单（8 个固定脚本，NOPASSWD）
                                                  │ 校验全部参数后
                                                  ▼
                          bin/wp-*.sh（root）→ useradd / nginx vhost / fpm 池 / mysql / postgres / systemd / acme.sh
```

- 站点流量：PHP 站点走 `nginx → php-fpm（站点独立池）`；Node 站点走 `nginx → 127.0.0.1:<端口>（systemd 托管的站点应用）`

- 面板本体：PHP 8.2 + **SQLite**（零额外服务），数据 `/usr/local/webpanel/panel/data`
- 每个站点：独立系统用户 + 独立 FPM 池 + `open_basedir` 站点目录；站点 A 无法读写站点 B
- 密码等密钥只走 stdin 传给特权脚本，不出现在进程参数里
- 全站 CSRF token、登录失败限速（10 分钟 8 次）、会话 httpOnly/SameSite、面板自签 HTTPS
- SELinux 安装时切为 permissive（与宝塔等第三方面板一致，可日后自行加固）

## 一键安装（腾讯云 AlmaLinux 8.10）

```bash
# 1. 上传本仓库到服务器（或 git clone）
dnf install -y git
git clone <你的仓库地址> webpanel && cd webpanel

# 2. 执行安装（全程约 10-20 分钟，取决于网速）
sudo bash install.sh
# 可选：指定面板端口/管理员/证书邮箱
# sudo PANEL_PORT=8888 PANEL_ADMIN=admin ACME_EMAIL=you@example.com bash install.sh
```

安装器会完成：Nginx、MySQL 8（root 随机密码写入 `/root/.my.cnf`）、PostgreSQL 16（仅监听
127.0.0.1，peer + scram-sha-256）、Node.js 22 LTS、5 个版本 PHP-FPM、
面板账号（密码展示一次并保存在 `/root/.webpanel-admin.txt`）、自签证书、
acme.sh、WP-CLI、防火墙放行、定时续期任务。任一外部仓库不可达时对应功能自动降级（面板仍可用）。

### 腾讯云控制台必做

1. **安全组**放行入站 TCP：`80`、`443`、`8888`（面板端口建议只对自己的办公/家庭 IP 放行）
2. 域名添加 **A 记录**指向服务器公网 IP（`shop.example.com` 和 `www.shop.example.com` 都要）
3. 浏览器访问 `https://<公网IP>:8888/`，自签证书提示“不安全”属正常，继续访问即可

## 上线一个 WordPress + WooCommerce 站点（标准流程）

1. **网站管理 → 创建网站**：填主域名、别名 `www.…`、选 PHP（WooCommerce 建议 8.1/8.2），
   勾选“同时创建数据库”
2. 保存弹出的 **数据库密码**（只显示一次）
3. 该行点 **WP**：填站点标题/管理员/邮箱 → 开始部署（WP-CLI 自动下载安装，约 1 分钟）
4. 到 **SSL 证书** 页点“申请证书”→ 自动签发并切到 HTTPS；需要时打开 HSTS
5. 浏览器打开 `https://域名/wp-admin/`，用弹出的管理员密码登录，后台安装 WooCommerce 插件即可
6. 主题/插件文件可在 **文件管理** 页直接上传到 `wp-content/`

### 使用腾讯云 TrustAsia 免费证书（可选）

面板默认用 Let's Encrypt **全自动签发+续期**（推荐，零维护）。如果你更希望使用
[腾讯云免费证书](https://console.cloud.tencent.com/ssl)（TrustAsia，DV 单域名，有效期 90 天，
单账号每年最多 50 张，不支持泛域名），流程：

1. 腾讯云 SSL 控制台 → 申请免费证书（域名验证选自动 DNS 最快，十几分钟签发）
2. 下载证书 zip → 解压 → 打开 **Nginx** 目录（`域名_bundle.crt` + `域名.key`）
3. 面板 **SSL 证书** 页 → 站点行点「上传证书」→ 粘贴/选择这两个文件 → 部署
4. 面板会自动校验：证书可解析、与私钥匹配、未过期、覆盖站点域名；之后自动切 HTTPS

> 注意：TrustAsia 免费证书**不会自动续期**（面板会以「手动部署」橙色标识提醒），
> 到期前 7 天左右需重新申请并再次上传。若想彻底免维护，直接用方式一即可。


> 文件以站点用户身份落盘，WordPress 后台在线升级、装插件**不需要 777 权限**。
> 站点目录：`/www/wwwroot/<站点用户>/public`，日志：`/www/wwwlogs/`。

## 上线一个 Node.js 应用（Express / Next / Nest 均可）

1. **网站管理 → 创建网站**：站点类型选 **Node.js**，填域名、**应用端口**（1024-65535，
   如 3000）与**启动命令**（如 `npm start`、`node dist/server.js`）
2. 保存后会得到：独立系统用户、systemd 服务 `wp-node-<站点用户>.service`（崩溃自动拉起）、
   Nginx 反向代理（含 WebSocket 升级头）
3. **文件管理** 上传项目代码到 `/www/wwwroot/<站点用户>/app/`（确保有 `package.json`）
4. 站点行点 **npm i**（以站点用户身份执行 `npm install`，完成后自动重启服务）
5. 到 **SSL 证书** 页签发/上传证书，与 PHP 站点完全一致（ACME 验证路径已自动绕开代理）

安全边界：应用只允许监听 `127.0.0.1:<端口>`，公网流量必须经 Nginx；启动命令仅允许
`node/npm/npx/yarn/pnpm/bun/deno` 及安全字符（无 shell 元字符，systemd 直接执行）；
服务以站点独立系统用户运行，文件系统加固（`NoNewPrivileges` / `ProtectSystem` / `PrivateTmp`）。

## PostgreSQL 数据库

- 创建站点或独立建库时，**数据库引擎**选 PostgreSQL 即可；连接信息弹窗一次性展示
- 连接参数：主机 `127.0.0.1`，端口 `5432`，用户/密码见弹窗；认证方式 scram-sha-256
- 数据库用户名不能以 `pg_` 开头（PostgreSQL 保留前缀）
- 服务管理在「系统」页（`postgres` 行启停/重载）；数据目录 `/var/lib/pgsql/16/`

## 运维速查

```bash
# 重置面板管理员密码
/usr/local/webpanel/bin/wp-panel.sh password admin

# 面板日志
tail -f /www/wwwlogs/panel.php-error.log
# Nginx / PHP / MySQL / PostgreSQL / Node 站点
tail -f /www/wwwlogs/<站点用户>.error.log
journalctl -u nginx -f
journalctl -u php82-php-fpm -f
journalctl -u mysqld -f
journalctl -u postgresql-16 -f
journalctl -u wp-node-<站点用户> -f

# 手动测试全部证书续签（不影响未到期证书）
/www/server/acme.sh/acme.sh --cron --home /www/server/acme.sh

# 卸载（保留站点）/ 彻底卸载（含数据，需交互确认）
sudo bash /usr/local/webpanel/uninstall.sh
sudo bash /usr/local/webpanel/uninstall.sh --purge
```

## 目录结构

```
install.sh                  AlmaLinux 8 一键安装器
uninstall.sh
config/
  nginx/                    面板 vhost、站点 HTTP/HTTPS 模板、Node 反向代理模板
  php-fpm/                  面板池与站点池模板
  systemd/node-site.service.tmpl   Node 站点 systemd 单元模板
  sudoers.d/webpanel        特权脚本白名单
  cron.d/webpanel-acme      证书自动续签
bin/
  wp-lib.sh                 参数校验/模板渲染/公共函数
  wp-site.sh                站点、vhost、FPM 池生命周期
  wp-node.sh                Node 站点生命周期（systemd 单元、npm i、启停）
  wp-db.sh                  MySQL 建库建用户改密删除（密码走 stdin）
  wp-pg.sh                  PostgreSQL 建库建用户改密删除（密码走 stdin）
  wp-ssl.sh                 acme.sh 签发/第三方证书部署/删除/列表
  wp-fs.sh + fs-worker.php  文件管理（jail、chown、禁 setuid）
  wp-sys.sh                 主机信息与服务控制
  wp-wp.sh                  WP-CLI 一键部署 WordPress
panel/
  public/index.php          前端控制器
  app/                      路由 / 认证 / SQLite / 控制器 / 视图（Layui 2.9）
  tools/admin.php           命令行管理员维护
```

## 已知边界与后续可扩展

- 单机单租户面板；多服务器/负载均衡、DNS/CDN 管理不在范围内
- 面板数据库为 SQLite，已自动备份建议：定期 `cp panel/data/panel.db` 即可
- phpMyAdmin 未内置（数据库页面可满足建站需求）；需要可作为独立站点手工部署
- 备份/定时任务（wp-cron 之外的计划备份）是下一阶段建议优先补的功能
