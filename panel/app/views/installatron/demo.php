<?php /** dry-run console stand-in page - mimics the Installatron GUI layout */ ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installatron — Applications (DRY-RUN)</title>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<style>
    body { margin: 0; background: #f5f5f5; font-family: -apple-system, "Segoe UI", Arial, "PingFang SC", sans-serif; }
    .it-header { background: #fff; border-bottom: 1px solid #ddd; padding: 14px 20px; display: flex; align-items: center; gap: 24px; }
    .it-logo { font-size: 22px; font-weight: 700; color: #1e9fff; letter-spacing: -1px; }
    .it-logo span { color: #333; }
    .it-tabs { display: flex; gap: 4px; margin-left: 30px; }
    .it-tab { padding: 8px 16px; font-size: 13px; color: #555; border: 1px solid transparent; border-radius: 4px; cursor: pointer; white-space: nowrap; }
    .it-tab.active { background: #1e9fff; color: #fff; border-color: #1e9fff; }
    .it-tabs a { text-decoration: none; }
    .it-body { padding: 20px; }
    .it-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 6px; padding: 24px; }
    .it-card h3 { margin: 0 0 14px; font-size: 16px; }
    .it-app-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .it-app-row:last-child { border-bottom: none; }
    .it-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; }
    .ic-wp { background: #21759b; }
    .ic-jm { background: #f18c18; }
    .ic-dp { background: #0077c0; }
    .it-app-info { flex: 1; }
    .it-app-name { font-weight: 600; font-size: 14px; }
    .it-app-meta { font-size: 12px; color: #999; margin-top: 2px; }
    .it-actions button { margin-left: 4px; }
    .it-demo-banner { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 10px 16px; border-radius: 4px; font-size: 12.5px; margin-bottom: 16px; }
    .it-demo-banner b { color: #856404; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }
</style>
</head>
<body>
<div class="it-header">
    <div class="it-logo">In<span>stallatron</span></div>
    <div class="it-tabs">
        <div class="it-tab active">My Applications (0)</div>
        <div class="it-tab">My Backups (0)</div>
        <div class="it-tab">Applications Browser</div>
        <div class="it-tab">Settings</div>
    </div>
</div>
<div class="it-body">
    <div class="it-demo-banner">
        <b>DRY-RUN 演示模式</b>：此为 Installatron 控制台布局演示。真实环境中此处直接嵌入官方 GUI，
        可在 <span class="mono">Applications Browser</span> 里为站点一键安装 WordPress / Joomla / Drupal / PrestaShop / phpMyAdmin 等 320+ 应用。
    </div>
    <div class="it-card">
        <h3>已安装应用</h3>
        <div class="it-app-row">
            <div class="it-icon ic-wp">W</div>
            <div class="it-app-info">
                <div class="it-app-name">WordPress</div>
                <div class="it-app-meta">暂无安装 · 点击右侧"安装应用"开始</div>
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
</body>
</html>
