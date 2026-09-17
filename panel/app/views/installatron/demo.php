<?php /** dry-run console stand-in page - mimics the Installatron GUI layout with working tabs */ ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installatron — Applications (DRY-RUN)</title>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<style>
    body { margin: 0; background: #f5f5f5; font-family: -apple-system, "Segoe UI", Arial, "PingFang SC", sans-serif; }
    .it-header { background: #fff; border-bottom: 1px solid #ddd; padding: 14px 20px; display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
    .it-logo { font-size: 22px; font-weight: 700; color: #1e9fff; letter-spacing: -1px; }
    .it-logo span { color: #333; }
    .it-tabs { display: flex; gap: 4px; margin-left: 30px; }
    .it-tab { padding: 8px 16px; font-size: 13px; color: #555; border: 1px solid transparent; border-radius: 4px; cursor: pointer; white-space: nowrap; }
    .it-tab.active { background: #1e9fff; color: #fff; border-color: #1e9fff; }
    .it-body { padding: 20px; }
    .it-pane { display: none; }
    .it-pane.active { display: block; }
    .it-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 6px; padding: 24px; }
    .it-card h3 { margin: 0 0 14px; font-size: 16px; }
    .it-app-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .it-app-row:last-child { border-bottom: none; }
    .it-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; }
    .ic-wp { background: #21759b; }
    .ic-jm { background: #f18c18; }
    .ic-dp { background: #0077c0; }
    .ic-ps { background: #df0067; }
    .ic-pma { background: #6c78af; }
    .it-app-info { flex: 1; }
    .it-app-name { font-weight: 600; font-size: 14px; }
    .it-app-meta { font-size: 12px; color: #999; margin-top: 2px; }
    .it-actions button { margin-left: 4px; }
    .it-demo-banner { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 10px 16px; border-radius: 4px; font-size: 12.5px; margin-bottom: 16px; }
    .it-demo-banner b { color: #856404; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }

    /* Applications Browser */
    .it-cat { margin-bottom: 24px; }
    .it-cat-title { font-size: 15px; font-weight: 700; color: #1e9fff; border-bottom: 2px solid #1e9fff; padding-bottom: 6px; margin-bottom: 8px; }
    .it-cat-desc { font-size: 12.5px; color: #777; margin-bottom: 14px; line-height: 1.7; }
    .it-app-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 16px; }
    .it-app-tile { text-align: center; cursor: pointer; }
    .it-app-tile-icon { width: 72px; height: 72px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 20px; margin: 0 auto 6px; }
    .it-app-tile-name { font-size: 12.5px; color: #1e9fff; }
    .it-app-tile-type { font-size: 11px; color: #999; }
</style>
</head>
<body>
<div class="it-header">
    <div class="it-logo">In<span>stallatron</span></div>
    <div class="it-tabs">
        <div class="it-tab active" data-tab="apps">My Applications (0)</div>
        <div class="it-tab" data-tab="backups">My Backups (0)</div>
        <div class="it-tab" data-tab="browser">Applications Browser</div>
        <div class="it-tab" data-tab="settings">Settings</div>
    </div>
</div>
<div class="it-body">
    <div class="it-demo-banner">
        <b>DRY-RUN 演示模式</b>：此为 Installatron 控制台布局演示。真实环境中此处直接嵌入官方 GUI，
        可在 <span class="mono">Applications Browser</span> 里为站点一键安装 WordPress / Joomla / Drupal / PrestaShop / phpMyAdmin 等 320+ 应用。
    </div>

    <!-- My Applications -->
    <div class="it-pane active" id="pane-apps">
        <div class="it-card">
            <h3>已安装应用</h3>
            <div class="it-app-row">
                <div class="it-icon ic-wp">W</div>
                <div class="it-app-info">
                    <div class="it-app-name">WordPress</div>
                    <div class="it-app-meta">暂无安装 · 在 Applications Browser 中选择安装</div>
                </div>
                <div class="it-actions">
                    <button class="layui-btn layui-btn-sm layui-btn-primary" disabled>安装应用</button>
                </div>
            </div>
            <div class="it-app-row">
                <div class="it-icon ic-jm">J</div>
                <div class="it-app-info">
                    <div class="it-app-name">Joomla</div>
                    <div class="it-app-meta">暂无安装</div>
                </div>
                <div class="it-actions">
                    <button class="layui-btn layui-btn-sm layui-btn-primary" disabled>安装应用</button>
                </div>
            </div>
            <div class="it-app-row">
                <div class="it-icon ic-dp">D</div>
                <div class="it-app-info">
                    <div class="it-app-name">Drupal</div>
                    <div class="it-app-meta">暂无安装</div>
                </div>
                <div class="it-actions">
                    <button class="layui-btn layui-btn-sm layui-btn-primary" disabled>安装应用</button>
                </div>
            </div>
            <p style="color:#999;font-size:12.5px;margin-top:14px">
                面板「网站管理」中创建的站点目录会出现在安装向导的目标路径下拉中，可直接选用。
            </p>
        </div>
    </div>

    <!-- My Backups -->
    <div class="it-pane" id="pane-backups">
        <div class="it-card">
            <h3>应用备份</h3>
            <p style="color:#999;font-size:13px">暂无备份。安装应用后可在此创建快照、自动定时备份与一键还原。</p>
        </div>
    </div>

    <!-- Applications Browser -->
    <div class="it-pane" id="pane-browser">
        <div class="it-card">
            <p style="color:#555;font-size:13px;line-height:1.8;margin-top:0">
                以下 Web 应用均可添加到你的网站，每个应用可安装多个实例。选择应用并点击 <b>install this application</b> 按钮即可。
            </p>

            <div class="it-cat">
                <div class="it-cat-title">Content Management</div>
                <div class="it-cat-desc">内容管理系统（CMS）用于管理网站动态内容，包括博客、新闻、分类、评论、用户登录、统计等。</div>
                <div class="it-app-grid">
                    <div class="it-app-tile"><div class="it-app-tile-icon ic-wp">W</div><div class="it-app-tile-name">WordPress</div><div class="it-app-tile-type">blog</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon ic-jm">J</div><div class="it-app-tile-name">Joomla</div><div class="it-app-tile-type">cms</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon ic-dp">D</div><div class="it-app-tile-name">Drupal</div><div class="it-app-tile-type">cms</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#e8a33d">T</div><div class="it-app-tile-name">TYPO3</div><div class="it-app-tile-type">cms</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#00a651">M</div><div class="it-app-tile-name">MODX</div><div class="it-app-tile-type">cms</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#764abc">G</div><div class="it-app-tile-name">Grav</div><div class="it-app-tile-type">cms</div></div>
                </div>
            </div>

            <div class="it-cat">
                <div class="it-cat-title">Community Building</div>
                <div class="it-cat-desc">社区类应用，包括论坛、留言板、邮件列表等。</div>
                <div class="it-app-grid">
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#ff9933">p</div><div class="it-app-tile-name">phpBB</div><div class="it-app-tile-type">forum</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#9c27b0">M</div><div class="it-app-tile-name">MediaWiki</div><div class="it-app-tile-type">wiki</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#00bcd4">D</div><div class="it-app-tile-name">DokuWiki</div><div class="it-app-tile-type">wiki</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#795548">F</div><div class="it-app-tile-name">FluxBB</div><div class="it-app-tile-type">forum</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#3f51b5">M</div><div class="it-app-tile-name">MyBB</div><div class="it-app-tile-type">forum</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#ff5722">E</div><div class="it-app-tile-name">Elgg</div><div class="it-app-tile-type">social</div></div>
                </div>
            </div>

            <div class="it-cat">
                <div class="it-cat-title">E-Commerce</div>
                <div class="it-cat-desc">电商系统，支持商品、购物车、支付、订单管理。</div>
                <div class="it-app-grid">
                    <div class="it-app-tile"><div class="it-app-tile-icon ic-ps">P</div><div class="it-app-tile-name">PrestaShop</div><div class="it-app-tile-type">ecommerce</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#96588a">W</div><div class="it-app-tile-name">WooCommerce</div><div class="it-app-tile-type">ecommerce</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#ee2c48">O</div><div class="it-app-tile-name">OpenCart</div><div class="it-app-tile-type">ecommerce</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#39b54a">M</div><div class="it-app-tile-name">Magento</div><div class="it-app-tile-type">ecommerce</div></div>
                </div>
            </div>

            <div class="it-cat">
                <div class="it-cat-title">Utilities</div>
                <div class="it-cat-desc">数据库管理、文件管理等实用工具。</div>
                <div class="it-app-grid">
                    <div class="it-app-tile"><div class="it-app-tile-icon ic-pma">p</div><div class="it-app-tile-name">phpMyAdmin</div><div class="it-app-tile-type">db admin</div></div>
                    <div class="it-app-tile"><div class="it-app-tile-icon" style="background:#e87c0c">A</div><div class="it-app-tile-name">Adminer</div><div class="it-app-tile-type">db admin</div></div>
                </div>
            </div>

            <p style="color:#999;font-size:12px;margin-top:20px">
                以上为演示分类。真实 Installatron 提供 320+ 应用，涵盖博客、CMS、电商、论坛、Wiki、CRM、项目管理、学习管理等全部类别。
            </p>
        </div>
    </div>

    <!-- Settings -->
    <div class="it-pane" id="pane-settings">
        <div class="it-card">
            <h3>Settings</h3>
            <p style="color:#999;font-size:13px">在此配置 Installatron 的全局偏好：自动更新策略、备份频率、通知、语言等。</p>
            <table class="layui-table" style="margin:0">
                <tbody>
                <tr><td width="180">自动更新</td><td>开启（推荐）</td></tr>
                <tr><td>自动备份</td><td>每日</td></tr>
                <tr><td>备份保留</td><td>7 份</td></tr>
                <tr><td>语言</td><td>English</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.it-tab').forEach(function (t) {
    t.addEventListener('click', function () {
        document.querySelectorAll('.it-tab').forEach(function (x) { x.classList.remove('active'); });
        document.querySelectorAll('.it-pane').forEach(function (x) { x.classList.remove('active'); });
        t.classList.add('active');
        document.getElementById('pane-' + t.dataset.tab).classList.add('active');
    });
});
</script>
</body>
</html>
