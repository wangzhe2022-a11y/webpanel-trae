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
| phpMyAdmin | **内置 SQL 浏览器**（官方 5.2.x）：导航栏 / 数据库页入口，挂在面板同一 HTTPS:8888 的 `/phpmyadmin/`，Nginx `auth_request` 校验面板登录会话；专用 MySQL 账号与 blowfish_secret 仅安装时生成，仓库不含密钥 |
| SSL | 方式一：acme.sh 自动签发 Let's Encrypt（http-01）+ 自动续签（cron）+ 一键 HTTPS 跳转 + HSTS；方式二：**上传第三方证书**（腾讯云 TrustAsia 等，粘贴 PEM 或选文件，自动校验证书/私钥/域名匹配与有效期）。PHP 与 Node 站点均支持 |
| 文件管理 | 目录浏览、**文件名搜索**（当前目录及子目录）、在线编辑文本、上传/下载（同名文件确认后覆盖）、**解压 / 压缩 zip**（tar.gz 可解压）、新建、重命名、改权限、删除；**严格 jailed 在站点目录内**，拒绝路径穿越、zip-slip 与符号链接逃逸。另可只读浏览 CVM 备份盘 **vdb（/mnt/backup）**（列表/下载/解压/搜索，禁止写入） |
| WordPress | WP-CLI 一键部署中文版 WordPress（wp-config、固定链接、WooCommerce 内存参数、FS_METHOD 全部配好），自动生成管理员密码 |
| 备份恢复 | **一键备份/恢复**：全量（站点文件+证书+vhost+FPM/Node 配置+MySQL+PostgreSQL+面板库）/仅文件/仅数据库三种范围，后台异步执行、页面实时进度；恢复需输入 RESTORE 二次确认；每日 3:30 自动全量备份，保留最近 10 份自动轮转，支持下载到本地 |
| Installatron Remote | 对接官方云端 **Installatron Remote**（[installatron.com/apps](https://installatron.com/apps)）：面板提供本机 SFTP/SSH 连接参数（主机、端口 22、站点 sysuser、文档根 `/www/wwwroot/<站点用户>/public`）；数据库在「数据库」页建好后填入安装向导。**不**在本机安装 Installatron Server，也**不**保存 installatron.com 密码 |
| 系统 | 仪表盘（CPU/内存/磁盘/负载 + **atop 历史采样**）、Nginx/MySQL/PostgreSQL/各版本 PHP-FPM/Node 服务启停重载、操作审计日志 |

## 架构与安全模型

```
浏览器 ──HTTPS:8888──▶ nginx ──unix socket──▶ php-fpm 池 [webpanel 用户]
                                                  │ 仅能调用
                                                  ▼
                          /etc/sudoers.d/webpanel 白名单（11 个固定脚本，NOPASSWD）
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

## 一键安装

### AlmaLinux 8.10

```bash
dnf install -y git
git clone <你的仓库地址> webpanel && cd webpanel
sudo bash install.sh
# 可选：sudo PANEL_PORT=8888 PANEL_ADMIN=admin ACME_EMAIL=you@example.com bash install.sh
```

### AlmaLinux 10.x

```bash
dnf install -y git
git clone <你的仓库地址> webpanel && cd webpanel
sudo bash install-al10.sh
# 可选：sudo PANEL_PORT=8888 PANEL_ADMIN=admin ACME_EMAIL=you@example.com bash install-al10.sh
```

> **AlmaLinux 10 与 8 的差异**：
> - MySQL 8.4 LTS（8.0 在 EL10 已停更）
> - PostgreSQL 16 用系统自带（无需 PGDG 仓库）
> - 移除所有 `dnf module` 命令（EL10 已废弃 DNF Modules）
> - PHP 8.1 已 EOL，多版本为 7.4 / 8.0 / 8.2 / 8.3
> - 面板自身用系统 PHP 8.4
> - CRB 仓库自动启用
>
> 面板 CLI（`/usr/bin/php`，跑 `bin/fs-worker.php`）需要 `posix` 扩展。AlmaLinux 对应包是 **`php-process`**（AppStream `php-cli` 默认不一定带上）。安装器会装上；若文件管理上传报 `Call to undefined function posix_getpwnam()`，执行 `dnf install -y php-process`。`fs-worker` 在缺扩展时会回退到 `getent passwd`。

安装器会完成：Nginx、MySQL 8（root 随机密码写入 `/root/.my.cnf`）、PostgreSQL 16（仅监听
127.0.0.1，peer + scram-sha-256）、Node.js 22 LTS、5 个版本 PHP-FPM、
面板账号（密码展示一次并保存在 `/root/.webpanel-admin.txt`）、自签证书、
acme.sh、WP-CLI、**phpMyAdmin**（官方包，失败不阻断面板）、防火墙放行、定时续期任务。任一外部仓库不可达时对应功能自动降级（面板仍可用）。

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

## phpMyAdmin（SQL 浏览器）

面板内置官方 [phpMyAdmin 5.2](https://www.phpmyadmin.net/downloads/)，用来浏览 / 执行 SQL、导入导出 MySQL，
**不依赖 Installatron**。数据库页的建库 / 改密 / 删除仍然走原来的「数据库」模块。

- **入口**：导航栏 **phpMyAdmin**，或「数据库」页右上角按钮
- **地址**：与面板相同的 HTTPS 端口（默认 8888）下的 `/phpmyadmin/`，不是独立公网站点
- **鉴权**：Nginx `auth_request` 检查面板会话 cookie（`WEBPANELSESS`）；未登录跳转 `/login`
- **MySQL**：专用账号 `webpanel_pma`@`127.0.0.1`（安装时随机密码，写入
  `/www/server/phpmyadmin/config.secret.php`，权限 640）；**不会**把 `/root/.my.cnf` 交给 php-fpm
- **安装位置**：`/www/server/phpmyadmin`，官方 tarball + SHA256 校验；`blowfish_secret` 仅安装时生成

已有面板升级（CVM 已在跑、不必重装）见下方「已有服务器启用 phpMyAdmin」。

手动重装 / 查看状态：

```bash
sudo /usr/local/webpanel/bin/wp-pma.sh status
sudo /usr/local/webpanel/bin/wp-pma.sh install    # 幂等：已是同一版本则只刷新配置
sudo /usr/local/webpanel/bin/wp-pma.sh uninstall
```

### 已有服务器启用 phpMyAdmin

CVM 上已经跑着旧版 WebPanel 时，**不必重跑** `install.sh`（避免动 MySQL root）。在源码目录更新后执行：

```bash
# 1. 更新程序文件（按你的部署方式 git pull / 拷贝）
sudo cp -a bin panel config /usr/local/webpanel/
sudo chmod 755 /usr/local/webpanel/bin/*.sh
sudo chgrp -R webpanel /usr/local/webpanel/panel
sudo find /usr/local/webpanel/panel -type d -exec chmod 750 {} \;
sudo find /usr/local/webpanel/panel -type f -exec chmod 640 {} \;
# 面板 data 仍须 webpanel 可写
sudo chown -R webpanel:webpanel /usr/local/webpanel/panel/data

# 2. sudoers 增加 wp-pma.sh
sudo install -m 440 /usr/local/webpanel/config/sudoers.d/webpanel /etc/sudoers.d/webpanel
sudo visudo -cf /etc/sudoers.d/webpanel

# 3. 下载官方 phpMyAdmin、写 Nginx include、FPM 池、本机专用 MySQL 账号
sudo /usr/local/webpanel/bin/wp-pma.sh install
```

若第 3 步提示面板 Nginx 配置不存在，确认 `/etc/nginx/conf.d/00-webpanel.conf` 在；脚本会自动插入
`include .../phpmyadmin.inc`。完成后登录面板 → **phpMyAdmin**。

### 已有服务器启用 atop 历史

CVM 已安装 `atop`（EPEL 2.7.x，`LOGINTERVAL=600`，日志 `/var/log/atop/atop_YYYYMMDD`）时，更新面板后还需要把新的只读脚本写进 sudoers：

```bash
sudo cp -a bin/wp-atop.sh /usr/local/webpanel/bin/
sudo chmod 755 /usr/local/webpanel/bin/wp-atop.sh
sudo cp -a panel /usr/local/webpanel/
sudo chgrp -R webpanel /usr/local/webpanel/panel
sudo find /usr/local/webpanel/panel -type d -exec chmod 750 {} \;
sudo find /usr/local/webpanel/panel -type f -exec chmod 640 {} \;
sudo chown -R webpanel:webpanel /usr/local/webpanel/panel/data
sudo install -m 440 /usr/local/webpanel/config/sudoers.d/webpanel /etc/sudoers.d/webpanel
sudo visudo -cf /etc/sudoers.d/webpanel
```

日志由 root 的 `atop` 服务写入，面板用户不能直接读；`wp-atop.sh` 只允许：

```bash
atop -r /var/log/atop/FILE -Z -P CPU,CPL,MEM,SWP,DSK
atop -r /var/log/atop/FILE -Z -b YYYYMMDDHH:MM -e YYYYMMDDHH:MM -P PRC,PRM,PRD
```

登录仪表盘后应看到 **atop 历史** 卡片（实时「主机监控」不变）。未安装 atop 或日志为空时显示说明，不会 500。

## 备份与恢复

**备份恢复** 页（导航栏）提供一键操作，全部后台异步执行，页面实时显示进度：

| 操作 | 说明 |
| --- | --- |
| 一键全量备份 | 站点文件 + 证书 + Nginx vhost + PHP-FPM 池配置 + Node systemd 单元 + MySQL 全库 + PostgreSQL 全库 + 面板 SQLite（原子快照，运行中安全） |
| 备份文件与配置 | 同上但不含数据库 |
| 仅备份数据库 | 只含 MySQL / PostgreSQL / 面板库 |
| 恢复 | 从任一备份还原：文件/证书/配置原位覆盖，数据库按名重建导入，恢复前面板库自动留 `panel.db.pre-restore.*` 兜底；需输入 `RESTORE` 二次确认 |
| 下载 / 删除 | 备份归档 `/www/server/backup/webpanel-<类型>-<时间戳>.tar.gz`，可下载到本地冷存 |

要点：

- **自动备份**：每日 3:30 全量（`/etc/cron.d/webpanel-backup`），自动轮转保留最近 10 份
- 同一时刻只允许一个备份/恢复任务（文件锁互斥），完成后页面自动刷新
- 恢复为**覆盖式**：同名站点/数据库将被替换，请先确认备份点正确；数据库用户与密码不在备份范围（本机恢复不受影响）
- 跨机迁移：新机器装好面板后，把归档放到 `/www/server/backup/` 再在页面恢复即可
- 快照级容灾建议搭配腾讯云**云硬盘快照**（系统盘+数据盘），可实现整机级回滚

## Installatron Remote（云端一键 Web 应用安装器）

面板对接 **Installatron Remote**（官方云端控制台，不是本机 Installatron Server）——320+ 应用的安装/更新/克隆/备份在 [installatron.com/apps](https://installatron.com/apps) 完成。Remote 免费档可无限安装/导入与每月少量更新；**Remote Premium**（按年订阅）提供无限更新、自动更新、定时备份与克隆。Remote Premium **不能**用来在本机安装 Installatron Server。

1. 导航栏 **Installatron** →「打开 Installatron Remote」进入官方云端控制台（在 installatron.com 登录/注册；面板不保存该账号密码）
2. 添加网站时协议选 **SFTP 或 SSH**（本机未提供 FTP 服务；MySQL/PostgreSQL 只监听 127.0.0.1，明文 FTP 无法在远端连库）
3. 主机填本机**公网 IP** 或已解析域名，端口默认 **22**；用户建议填该站点系统用户（`sysuser`），路径填 `/www/wwwroot/<站点用户>/public`
4. 需要数据库的应用：先在面板 **数据库** 页建库（密码只显示一次），安装向导里主机填 `127.0.0.1`（PostgreSQL 为 `127.0.0.1:5432`）

连接注意：

- 站点系统用户默认 shell 为 `/sbin/nologin`，不能交互式 SSH。要用 SFTP 连接，需为该用户配置 OpenSSH 内部 SFTP（chroot 到 `/www/wwwroot/<sysuser>`），或改用已有运维 SSH 账号并填写对应文档根
- 腾讯云**安全组**需放行入站 TCP `22`，否则 installatron.com 云端连不上
- WordPress 若只想本机一键部署，仍可用「网站管理」里的 WP-CLI，不必走 Remote

### 若旧环境曾安装 Installatron Server

本面板已移除 Server 的安装/升级/卸载与一次性 GUI 登录。从未装过 Server 的机器（当前 CVM 即是这种情况）无需处理。若 `/usr/local/installatron` 仍在：

```bash
rpm -e installatron-server
rm -rf /usr/local/installatron /var/installatron
rm -f /etc/cron.d/installatron
nginx -t && systemctl reload nginx   # 检查是否残留其 Nginx 片段
# 可选：删除当时预建的 MySQL 库/账号 installatron
```

## 从 cPanel 迁移到 WebPanel

> cPanel 与任何第三方面板都**不能共存**（两者都管理 Web/MySQL/PHP/DNS/邮件等底层服务）。
> 迁移路线：**备份 → 记录映射表 → 卸载 cPanel → 清理残留 → 装 WebPanel → 还原站点 → 取消 cPanel 授权**。

### 1. 全量备份（详细步骤）

> 备份是整个迁移最关键的一步。**先在服务器本地打包，再下载到外部存储**，不要只留在本机。

#### 1.1 备份前准备

```bash
# 检查磁盘剩余空间（备份通常需要站点总大小的 1.5-2 倍空间）
df -h /root /home

# 创建备份目录结构
mkdir -p /root/migration/{accounts,sites,dbs,ssl,dns,mail,config,logs}

# 记录备份开始时间
echo "备份开始: $(date)" > /root/migration/BACKUP-LOG.txt
```

#### 1.2 cPanel 账户级完整备份（推荐主方案）

`pkgacct` 会把每个 cPanel 用户的**站点文件 + 数据库 + 邮件 + 转发器 + 自动回复 + DNS zone + 配额 + 子域名**打成一个 tar.gz，
这是最完整、还原最方便的方式：

```bash
# 遍历所有 cPanel 用户（排除系统目录）
for user in $(ls -1 /home | grep -vE '^(lost\+found|cPanelInstall|cpeasyapache|\.cpan)$'); do
    echo "[$(date)] 开始备份账户: $user" >> /root/migration/BACKUP-LOG.txt
    /scripts/pkgacct "$user" /root/migration/accounts/ \
        && echo "[$(date)] 完成: $user" >> /root/migration/BACKUP-LOG.txt \
        || echo "[$(date)] 失败: $user" >> /root/migration/BACKUP-LOG.txt
done

# 检查产物（每个用户一个 cpmove-<user>.tar.gz）
ls -lh /root/migration/accounts/
```

> `pkgacct` 的 tar.gz 里包含：`homedir.tar`（站点文件）、`mysql/`（各库 SQL）、`psql/`（PG 库）、
> `mail/`（邮件数据）、`dnszones/`（DNS 记录）、`sslcerts/`（SSL）、`counters/`、`bandwidth/` 等。

#### 1.3 单独备份站点文件（兜底方案，防止 pkgacct 遗漏）

```bash
for user in $(ls -1 /home | grep -vE '^(lost\+found|cPanelInstall|cpeasyapache|\.cpan)$'); do
    # 备份 public_html（站点根目录）
    if [ -d "/home/$user/public_html" ]; then
        tar -czf "/root/migration/sites/${user}-public_html.tar.gz" \
            -C /home "$user/public_html" 2>/dev/null
    fi
    # 备份用户整个 home（含 .htaccess、.ssh、cron 等隐藏文件）
    tar -czf "/root/migration/sites/${user}-home.tar.gz" \
        --exclude="/home/$user/.cpanel/datastore" \
        --exclude="/home/$user/.cpanel/caches" \
        -C /home "$user" 2>/dev/null
done
```

#### 1.4 数据库备份（逐库 SQL，可独立还原）

```bash
# 获取 MySQL root 密码（cPanel 存放在 /root/.my.cnf）
cat /root/.my.cnf 2>/dev/null

# 排除系统库，逐个导出业务库
for db in $(mysql -e "SHOW DATABASES" -N | grep -vE '^(information_schema|mysql|performance_schema|sys)$'); do
    echo "[$(date)] 导出数据库: $db" >> /root/migration/BACKUP-LOG.txt
    mysqldump --single-transaction --routines --triggers --events \
        --quick --default-character-set=utf8mb4 \
        "$db" > "/root/migration/dbs/${db}.sql" 2>> /root/migration/BACKUP-LOG.txt
done

# 额外：导出 MySQL 用户和权限（还原后重建账号用）
mysql -e "SELECT user,host FROM mysql.user;" > /root/migration/dbs/mysql-users.txt
mysqldump --no-data --skip-triggers mysql > /root/migration/dbs/mysql-schema.sql 2>/dev/null

# 验证每个 SQL 文件非空且可解析
for f in /root/migration/dbs/*.sql; do
    [ -s "$f" ] || echo "警告: $f 为空" >> /root/migration/BACKUP-LOG.txt
done
```

#### 1.5 SSL 证书备份

```bash
# cPanel 管理的证书（每个站点的证书、私钥、CA 链）
cp -r /var/cpanel/ssl/ /root/migration/ssl/cpanel-ssl/ 2>/dev/null

# 系统级 CA 证书和私钥
cp -r /etc/pki/tls/ /root/migration/ssl/system-tls/ 2>/dev/null

# cPanel 的 SSL 存储库（含 Let's Encrypt / AutoSSL 签发的证书）
cp -r /var/cpanel/ssl/apache_tls/ /root/migration/ssl/apache_tls/ 2>/dev/null

# 导出所有已安装证书的摘要（方便还原时对照）
for crt in /var/cpanel/ssl/apache_tls/*/combined; do
    domain=$(basename $(dirname "$crt"))
    openssl x509 -in "$crt" -noout -subject -enddate 2>/dev/null \
        >> /root/migration/ssl/cert-summary.txt
done
```

#### 1.6 DNS 配置备份

```bash
# 导出所有 DNS zone 文件（cPanel 的 BIND 配置）
cp -r /var/named/*.db /root/migration/dns/ 2>/dev/null
cp /etc/named.conf /root/migration/dns/named.conf 2>/dev/null

# 导出每个域名的 NS 记录摘要
for zone in /var/named/*.db; do
    domain=$(basename "$zone" .db)
    grep -E '^(NS|A|CNAME|MX)' "$zone" >> "/root/migration/dns/${domain}-records.txt" 2>/dev/null
done

# 重要：如果域名 DNS 由 cPanel 服务器托管，卸载前必须把 DNS 迁到
# 第三方（Cloudflare / DNSPod / 阿里云 DNS），否则站点会断！
```

#### 1.7 邮件数据备份

```bash
# cPanel 的邮件存储（每个用户的所有邮箱）
for user in $(ls -1 /home | grep -vE '^(lost\+found|cPanelInstall|cpeasyapache|\.cpan)$'); do
    if [ -d "/home/$user/mail" ]; then
        tar -czf "/root/migration/mail/${user}-mail.tar.gz" \
            -C /home "$user/mail" 2>/dev/null
    fi
done

# 邮件别名和转发器
cp -r /etc/valiases /root/migration/mail/valiases/ 2>/dev/null
cp -r /etc/vfilters /root/migration/mail/vfilters/ 2>/dev/null
```

#### 1.8 cPanel/WHM 全局配置备份

```bash
# cPanel 核心配置
cp -r /var/cpanel /root/migration/config/var-cpanel/ 2>/dev/null
cp -r /etc/cpanel /root/migration/config/etc-cpanel/ 2>/dev/null

# Apache 配置（vhost、PHP 处理、模块）
cp -r /etc/apache2/conf/ /root/migration/config/apache-conf/ 2>/dev/null
cp -r /usr/local/apache/conf/ /root/migration/config/apache-conf-local/ 2>/dev/null

# PHP 配置（php.ini、每个版本的配置）
cp /usr/local/lib/php.ini /root/migration/config/php.ini 2>/dev/null
find /opt/cpanel/ -name "php.ini" -exec cp --parents {} /root/migration/config/ \; 2>/dev/null

# crontab（所有用户的定时任务）
for user in $(ls -1 /var/spool/cron); do
    crontab -u "$user" -l > "/root/migration/config/cron-${user}.txt" 2>/dev/null
done
```

#### 1.9 生成站点映射表（还原时对照用）

```bash
cat > /root/migration/site-map.txt <<'EOF'
# 域名 | docroot | 数据库名 | 数据库用户 | PHP 版本 | 证书状态
# 请根据 WHM 后台信息填写，还原时逐一对照
EOF

# 自动抓取域名 → docroot 映射
cat /etc/userdatadomains | grep -vE '^(\#|$)' | while IFS=: read domain rest; do
    user=$(echo "$rest" | cut -d'=' -f1)
    echo "$domain => /home/$user/public_html" >> /root/migration/site-map-auto.txt
done

# 自动抓取数据库归属（哪个用户有哪些库）
cat /var/cpanel/databases/*.json 2>/dev/null | grep -oP '"dbname":"[^"]+"' | sort -u \
    >> /root/migration/databases-list.txt
```

#### 1.10 备份完整性校验

```bash
# 统计备份总大小
du -sh /root/migration/

# 检查所有 tar.gz 是否损坏
echo "=== tar.gz 完整性检查 ===" >> /root/migration/BACKUP-LOG.txt
for f in /root/migration/accounts/*.tar.gz /root/migration/sites/*.tar.gz /root/migration/mail/*.tar.gz; do
    [ -f "$f" ] || continue
    if gzip -t "$f" 2>/dev/null; then
        echo "OK: $f" >> /root/migration/BACKUP-LOG.txt
    else
        echo "损坏: $f" >> /root/migration/BACKUP-LOG.txt
    fi
done

# 检查 SQL 文件数量是否和数据库数量一致
echo "SQL 文件数: $(ls /root/migration/dbs/*.sql 2>/dev/null | wc -l)" >> /root/migration/BACKUP-LOG.txt
echo "数据库数: $(mysql -e 'SHOW DATABASES' -N | grep -vE '^(information_schema|mysql|performance_schema|sys)$' | wc -l)" >> /root/migration/BACKUP-LOG.txt

# 最终确认
echo "备份完成: $(date)" >> /root/migration/BACKUP-LOG.txt
cat /root/migration/BACKUP-LOG.txt
```

#### 1.11 异地传输（务必执行！）

```bash
# 方式一：下载到本地（在你本地电脑执行）
# scp -r root@<服务器IP>:/root/migration/ /本地/备份目录/

# 方式二：传到另一台服务器
# rsync -avz /root/migration/ root@<备份服务器>:/backup/cpanel-migration/

# 方式三：上传到对象存储（腾讯云 COS / 阿里云 OSS 等）
# 例如用 coscmd：
# coscmd upload -r /root/migration/ cos://你的bucket/cpanel-migration/

# 确认异地存储成功后，再继续卸载 cPanel
```

> ⚠️ **在确认备份已安全存放到外部存储之前，绝对不要卸载 cPanel。**

### 2. 整理站点映射表（还原时对照）

步骤 1.9 已自动生成域名→docroot 映射和数据库列表。现在补充 PHP 版本等信息，
整理成一张完整表（手动填写或从 WHM 导出）：

```bash
cat > /root/migration/site-map-final.txt <<'EOF'
# domain        | docroot                    | db_name      | db_user     | php_ver
example.com     | /home/user1/public_html    | user1_wp     | user1_wp    | 8.2
blog.example.com| /home/user1/public_html/blog| user1_blog  | user1_blog  | 8.1
EOF
```

- **PHP 版本**：WHM → MultiPHP Manager，或 `cat /var/cpanel/userdata/*/*.phpversion` 2>/dev/null
- **docroot**：已自动生成在 `site-map-auto.txt`
- **数据库**：已自动生成在 `databases-list.txt`

### 3. 卸载 cPanel

```bash
/usr/local/cpanel/scripts/uninstall_cpanel
reboot
```

cPanel 卸载后会残留 Apache / MySQL / PHP / DNS / 邮件服务，需手动清理（确认备份已完成）：

```bash
systemctl stop httpd mysql named dovecot exim 2>/dev/null
yum remove -y ea-apache24 ea-php* httpd* mysql* mariadb* bind* dovecot* exim* cpanel* 2>/dev/null
rm -rf /etc/httpd /etc/my.cnf /etc/named.conf /etc/dovecot /etc/exim.conf
rm -rf /usr/local/apache /usr/local/cpanel /var/cpanel /var/named
# /var/lib/mysql 如需保留数据库文件则不删；否则删除
reboot
```

验证环境干净（应无输出）：
```bash
ss -tlnp | grep -E ':(80|443|3306|25|53)\b'
which httpd nginx mysqld php-fpm named
```

### 4. 安装 LEMP 栈 + WebPanel

```bash
# EPEL + Remi 源
yum install -y epel-release
yum install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Nginx
yum install -y nginx && systemctl enable --now nginx

# MySQL 8
yum install -y mysql-server && systemctl enable --now mysqld && mysql_secure_installation

# PHP-FPM（按需多版本）
yum install -y php82-php-fpm php82-php-mysqlnd php82-php-gd php82-php-mbstring \
               php82-php-xml php82-php-curl php82-php-zip php82-php-bcmath \
               php74-php-fpm php74-php-mysqlnd php81-php-fpm php81-php-mysqlnd
systemctl enable --now php82-php-fpm

# 可选：PostgreSQL 16
yum install -y postgresql-server postgresql-contrib
postgresql-setup --initdb && systemctl enable --now postgresql

# 部署 WebPanel（见上方「一键安装」章节）
sudo bash install.sh
```

### 5. 还原备份资料到 WebPanel（详细步骤）

> 还原前确认：WebPanel 已安装完毕、Nginx/MySQL/PHP-FPM 服务正常、备份文件已上传到服务器。

#### 5.1 上传备份到新服务器

```bash
# 把之前存到外部的备份传回来
# 方式一：从本地 scp 上传
# scp -r /本地/备份目录/migration/ root@<新服务器IP>:/root/migration/

# 方式二：从备份服务器 rsync
# rsync -avz root@<备份服务器>:/backup/cpanel-migration/ /root/migration/

# 验证上传完整
ls -lh /root/migration/accounts/
ls -lh /root/migration/dbs/
du -sh /root/migration/
```

#### 5.2 逐个还原站点（对照 site-map-final.txt）

对映射表中的每一个域名，执行以下流程：

**步骤 A：在面板创建站点骨架**

1. 登录 WebPanel → **网站管理** → **创建网站**
2. 填写：主域名、别名（www 等）、PHP 版本（与映射表一致）
3. 勾选「同时创建数据库」（如果该站点有数据库）
4. 记下弹出的：**站点用户名**、**数据库名**、**数据库密码**

> 面板会自动创建：系统用户、`/www/wwwroot/<站点用户>/public/` 目录、Nginx vhost、PHP-FPM 池。

**步骤 B：还原站点文件**

```bash
# 解压站点文件到面板创建的站点目录
# 注意：cPanel 的站点在 public_html，本面板在 public/
tar -xzf /root/migration/sites/<cpanel用户>-public_html.tar.gz \
    -C /www/wwwroot/<站点用户>/public/ --strip-components=1

# 如果用的是 home 完整备份（含 public_html 目录）：
tar -xzf /root/migration/sites/<cpanel用户>-home.tar.gz \
    -C /tmp/cpanel-home/
cp -a /tmp/cpanel-home/<cpanel用户>/public_html/. /www/wwwroot/<站点用户>/public/

# 修正属主（面板站点用户 + www 组）
chown -R <站点用户>:www /www/wwwroot/<站点用户>/

# 修正权限（标准 WordPress/PHP 权限）
find /www/wwwroot/<站点用户>/public/ -type d -exec chmod 755 {} \;
find /www/wwwroot/<站点用户>/public/ -type f -exec chmod 644 {} \;
# wp-content/uploads 等可写目录按需给 775
chmod -R 775 /www/wwwroot/<站点用户>/public/wp-content/uploads 2>/dev/null
```

**步骤 C：还原数据库**

```bash
# 方式一：用面板创建的数据库名和用户（推荐，密码已由面板管理）
# 面板「数据库」页已建好 <db_name> 和 <db_user>，直接导入：
mysql <db_name> < /root/migration/dbs/<原cPanel库名>.sql

# 方式二：如果想保留原数据库名（需先建库建用户）
mysql -e "CREATE DATABASE <原库名> CHARACTER SET utf8mb4;
          CREATE USER '<原用户>'@'localhost' IDENTIFIED BY '<新密码>';
          GRANT ALL PRIVILEGES ON <原库名>.* TO '<原用户>'@'localhost';
          FLUSH PRIVILEGES;"
mysql <原库名> < /root/migration/dbs/<原库名>.sql

# 验证导入
mysql <db_name> -e "SHOW TABLES;"
mysql <db_name> -e "SELECT COUNT(*) FROM <某个核心表>;"
```

**步骤 D：更新站点配置文件中的数据库连接**

```bash
# WordPress
vi /www/wwwroot/<站点用户>/public/wp-config.php
# 修改：DB_NAME, DB_USER, DB_PASSWORD, DB_HOST（通常是 localhost）

# Joomla
vi /www/wwwroot/<站点用户>/public/configuration.php

# Drupal
vi /www/wwwroot/<站点用户>/public/sites/default/settings.php

# PrestaShop
vi /www/wwwroot/<站点用户>/public/app/config/parameters.php
```

**步骤 E：处理特殊配置**

```bash
# 还原 .htaccess（cPanel 的伪静态规则，Nginx 需要转换）
# WordPress 的伪静态在面板 vhost 模板里已内置，无需 .htaccess
# 如果有自定义规则，需要转换为 Nginx 语法放到站点 vhost 里

# 还原 wp-config.php 里的密钥和盐（如果迁移的是 WordPress）
# cPanel 的 wp-config.php 里的 AUTH_KEY / SECURE_AUTH_KEY 等保持不变即可

# 还原自定义 php.ini 配置（如果有）
# 面板的 PHP-FPM 池配置在 /etc/opt/remi/phpXX/php-fpm.d/<站点用户>.conf
# 可通过面板「网站管理 → 站点设置 → PHP 配置」修改
```

#### 5.3 还原 SSL 证书

**方式一：上传旧证书（推荐，保持原证书有效）**

1. 从备份中找到该域名的证书文件：
   ```bash
   # cPanel 的证书通常在 /var/cpanel/ssl/apache_tls/<域名>/
   ls /root/migration/ssl/apache_tls/<域名>/
   # combined = 证书 + CA 链，crt 里可能是纯证书，需要分离
   ```
2. 提取证书和私钥：
   ```bash
   # combined 文件里通常是：证书 + 中间证书 + 私钥
   # 分离证书（BEGIN CERTIFICATE 到 END CERTIFICATE 的第一段）
   # 分离私钥（BEGIN PRIVATE KEY 到 END PRIVATE KEY）
   ```
3. 面板 **SSL 证书** 页 → 站点行点「上传证书」→ 粘贴证书链和私钥 → 部署

**方式二：用 acme.sh 重新签发（证书已过期或找不到时）**

1. 确保域名 A 记录已指向新服务器
2. 面板 **SSL 证书** 页 → 站点行点「申请证书」→ 自动签发 Let's Encrypt
3. 签发成功后面板自动配置 Nginx 并开启 HTTPS

#### 5.4 还原邮件（可选）

> WebPanel 当前不内置邮件服务。如需保留邮件，建议迁移到第三方邮件服务（腾讯企业邮、阿里云邮、Google Workspace 等）。

```bash
# 如果要手动还原邮件到本地（需自行安装 Dovecot + Postfix，不在面板范围内）
for user in $(ls /root/migration/mail/*-mail.tar.gz); do
    tar -xzf "$user" -C /tmp/mail-restore/
done
# 然后按第三方邮件服务商的导入指南操作
```

#### 5.5 还原定时任务（crontab）

```bash
# 查看备份的 crontab
cat /root/migration/config/cron-*.txt

# 对每个站点用户，在面板里手动重建定时任务
# 或直接用 crontab 命令（面板站点用户是独立系统用户）
crontab -u <站点用户> /root/migration/config/cron-<cpanel用户>.txt

# 注意：cPanel 的 cron 路径可能用的是 /home/<cpanel用户>/...
# 需要改成 /www/wwwroot/<站点用户>/...
```

#### 5.6 还原后的验证清单

```bash
# 1. 站点文件权限正确
ls -la /www/wwwroot/<站点用户>/public/index.php

# 2. 数据库可连接（用站点配置里的账号）
mysql -u<db_user> -p<db_pass> <db_name> -e "SELECT 1;"

# 3. Nginx vhost 生效
nginx -t
curl -I http://<域名>
curl -I https://<域名>

# 4. PHP 版本正确（在站点目录创建 phpinfo 临时文件测试）
echo '<?php phpinfo(); ?>' > /www/wwwroot/<站点用户>/public/info.php
curl -s http://<域名>/info.php | grep "PHP Version"
rm /www/wwwroot/<站点用户>/public/info.php

# 5. 浏览器访问站点首页和后台，确认功能正常

# 6. 检查错误日志
tail -f /www/wwwlogs/<站点用户>.error.log
```

#### 5.7 常见问题处理

| 问题 | 原因 | 解决 |
| --- | --- | --- |
| 500 错误 | 属主/权限不对 | `chown -R <站点用户>:www` + 目录 755 / 文件 644 |
| 数据库连接失败 | 密码/库名不一致 | 检查 wp-config.php 与面板数据库页的账号密码 |
| 404（除首页外） | 伪静态规则缺失 | WordPress：面板已内置；其他程序需转换 .htaccess 为 Nginx 规则 |
| 上传大小限制 | PHP 默认 2M | 面板「网站管理 → PHP 配置」调大 upload_max_filesize / post_max_size |
| 证书不匹配 | 域名与证书不一致 | 重新签发或上传正确的证书 |
| 邮件发送失败 | 面板无邮件服务 | 用 SMTP 插件（如 WP Mail SMTP）走第三方邮箱 |

### 6. 取消 cPanel 授权

在购买渠道取消续订：
- cPanel 官方：[store.cpanel.net](https://store.cpanel.net) → My Account → Cancel License
- 经销商（腾讯云/阿里云/Namecheap 等）：对应平台订单管理取消

### 风险提示

| 环节 | 风险 | 缓解 |
| --- | --- | --- |
| 卸载 cPanel | `/home` 数据可能被误删 | 卸载前必须有外部备份，别只留在本机 |
| MySQL 版本差异 | cPanel 可能用 5.7，新装可能是 8.0 | 确认版本一致，否则导入可能报字符集错误 |
| PHP 扩展差异 | ionCube / SourceGuardian 等加密扩展 | 提前确认站点是否依赖，按需装到对应 PHP 版本 |
| DNS 切换 | cPanel 卸载后 DNS 解析中断 | 提前迁到 Cloudflare / DNSPod，TTL 改小 |
| 邮件 | cPanel 邮件服务卸载后邮件丢失 | 用 `/scripts/pkgacct` 完整备份，或迁第三方邮件 |

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

# phpMyAdmin 状态 / 重装
sudo /usr/local/webpanel/bin/wp-pma.sh status
sudo /usr/local/webpanel/bin/wp-pma.sh install

# 手动触发一次全量备份 / 查看备份列表 / 任务状态
sudo /usr/local/webpanel/bin/wp-backup.sh create full
sudo /usr/local/webpanel/bin/wp-backup.sh list
sudo /usr/local/webpanel/bin/wp-backup.sh status

# 卸载（保留站点）/ 彻底卸载（含数据，需交互确认）
sudo bash /usr/local/webpanel/uninstall.sh
sudo bash /usr/local/webpanel/uninstall.sh --purge
```

### 运维跟进：大文件下载（已有 CVM）

新安装会从 `config/nginx/panel.conf.tmpl` 自动带上 `panel-download.inc`。
**已经在跑的机器**需要把同一段加进面板 Nginx，否则多 GB 的 vdb / 备份下载
在修了 PHP 死锁之后，仍可能被默认 `fastcgi_read_timeout 60s` 在读 body 时掐断。

```nginx
# /etc/nginx/conf.d/00-webpanel.conf  （server { } 内，location / 之前）
include /usr/local/webpanel/config/nginx/panel-download.inc;
```

然后：

```bash
sudo nginx -t && sudo systemctl reload nginx
```

不要用 X-Accel-Redirect 去读 `/mnt/backup`：vdb 文件通常是 root 属主，面板
php-fpm 还有 `open_basedir`。给 nginx worker 放开备份盘读取会削弱隔离。
下载继续走 `sudo wp-fs.sh cat`（只读 jail），只是不再占用 PHP 会话锁、也不再
先堵死 stderr。

可选（一般不必）：若仍有超慢链路被 FPM `request_terminate_timeout` 杀掉，再在
`/etc/php-fpm.d/webpanel.conf` 里把该值调到 `7200`，`systemctl reload php-fpm`。

## 目录结构

```
install.sh                  AlmaLinux 8 一键安装器
install-al10.sh             AlmaLinux 10 一键安装器
uninstall.sh
config/
  nginx/                    面板 vhost、站点 HTTP/HTTPS 模板、Node 反向代理模板、phpMyAdmin include、大文件下载 timeout
  php-fpm/                  面板池、站点池、phpMyAdmin 池模板
  phpmyadmin/config.inc.php phpMyAdmin 配置模板（不含密钥）
  systemd/node-site.service.tmpl   Node 站点 systemd 单元模板
  sudoers.d/webpanel        特权脚本白名单
  cron.d/webpanel-acme      证书自动续签
  cron.d/webpanel-backup    每日 3:30 自动全量备份（保留最近 10 份）
bin/
  wp-lib.sh                 参数校验/模板渲染/公共函数
  wp-site.sh                站点、vhost、FPM 池生命周期
  wp-node.sh                Node 站点生命周期（systemd 单元、npm i、启停）
  wp-db.sh                  MySQL 建库建用户改密删除（密码走 stdin）
  wp-pg.sh                  PostgreSQL 建库建用户改密删除（密码走 stdin）
  wp-pma.sh                 内置 phpMyAdmin 安装/状态/卸载（官方 tarball + SHA256）
  wp-ssl.sh                 acme.sh 签发/第三方证书部署/删除/列表
  wp-backup.sh              一键备份/恢复（异步任务、文件锁互斥、轮转清理）
  wp-fs.sh + fs-worker.php  文件管理（站点 jail + vdb /mnt/backup 只读 jail、chown、禁 setuid）
  wp-sys.sh                 主机信息与服务控制
  wp-atop.sh                只读解析 /var/log/atop（仪表盘历史采样）
  wp-wp.sh                  WP-CLI 一键部署 WordPress
panel/
  public/index.php          前端控制器
  app/                      路由 / 认证 / SQLite / 控制器 / 视图（Layui 2.9）
  tools/admin.php           命令行管理员维护
```

## 已知边界与后续可扩展

- 单机单租户面板；多服务器/负载均衡、DNS/CDN 管理不在范围内
- 面板数据库为 SQLite，已包含在每次备份归档中（原子快照）
- phpMyAdmin 仅覆盖 **MySQL**（PostgreSQL 请用客户端连 `127.0.0.1:5432`）；不提供 phpMyAdmin 配置存储库（书签/关系视图等高级项需自行开 pmadb）
- 备份为**本机归档**，建议定期下载到本地/对象存储（COS）实现异地容灾；整机级回滚可搭配腾讯云云硬盘快照
