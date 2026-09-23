<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = [
    '/'        => ['仪表盘', 'layui-icon-console'],
    '/sites'   => ['网站管理', 'layui-icon-website'],
    '/databases' => ['数据库', 'layui-icon-table'],
    '/phpmyadmin' => ['phpMyAdmin', 'layui-icon-fonts-code'],
    '/ssl'     => ['SSL 证书', 'layui-icon-auz'],
    '/files'   => ['文件管理', 'layui-icon-file'],
    '/backup'  => ['备份恢复', 'layui-icon-download-circle'],
    '/installatron' => ['Installatron', 'layui-icon-app'],
];
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e($csrf) ?>">
<title>Console</title>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<style>
    .layui-layout-admin .layui-header { background: #23262E; color: #fff; }
    .layui-layout-admin .layui-logo { color: #fff; font-weight: 600; width: 220px; }
    .layui-side { background: #2F4056; top: 60px; }
    .layui-body { top: 60px; background: #f2f2f2; padding: 16px; }
    .layui-nav-tree .layui-nav-item a { height: 48px; line-height: 48px; }
    .layui-nav-tree .layui-nav-item a .layui-icon { font-size: 18px; margin-right: 10px; }
    .header-right { float: right; line-height: 60px; padding-right: 20px; }
    .header-right .layui-icon { font-size: 18px; }
    .stat-card { border-radius: 8px; padding: 20px; color: #fff !important; position: relative; }
    .stat-card .num { font-size: 32px; font-weight: 600; color: #fff !important; }
    .stat-card .label { opacity: .95; font-size: 13px; color: #fff !important; }
    .stat-card .layui-icon { position: absolute; right: 18px; top: 18px; font-size: 42px; opacity: .45; color: #fff !important; }
    .stat-card.c-blue { background: linear-gradient(135deg,#1e9fff,#0c7cd5) !important; }
    .stat-card.c-green { background: linear-gradient(135deg,#16baaa,#0e9a8c) !important; }
    .stat-card.c-orange { background: linear-gradient(135deg,#ff9800,#e65100) !important; }
    .stat-card.c-purple { background: linear-gradient(135deg,#9c27b0,#6a1b9a) !important; }
    .site-ops { display: flex; flex-wrap: nowrap; align-items: center; gap: 4px; white-space: nowrap; max-width: none; }
    .site-ops .layui-btn { flex: 0 0 auto; margin-left: 0 !important; margin-right: 0 !important; }
    .site-ops .layui-icon { overflow: visible; line-height: 1; }
    .panel-card { background: #fff; border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
    .panel-card h3 { margin: 0 0 14px; font-size: 15px; }
    .mon-row { margin-bottom: 16px; }
    .mon-row:last-child { margin-bottom: 0; }
    .mon-k { font-size: 13px; margin-bottom: 6px; overflow: hidden; }
    .mon-k .right { float: right; max-width: 70%; color: #666; font-size: 12px; text-align: right; white-space: normal; word-break: break-word; }
    .mon-meta { color: #888; font-size: 12px; margin-top: 6px; }
    .mon-updated { color: #999; font-size: 12px; font-weight: 400; float: right; line-height: 22px; }
    .warn-text { color: #ff5722; }
    .mon-top { width: 100%; margin-top: 8px; }
    .mon-top td { padding: 2px 0; font-size: 12px; color: #555; }
    .mon-top td:last-child { text-align: right; color: #888; }
    .tag-alias { margin: 2px 4px 2px 0; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; }
    .secret-box { background: #f7f7f7; border: 1px dashed #bbb; border-radius: 6px; padding: 12px; margin: 10px 0; word-break: break-all; }
    .secret-box b { color: #333; }
    .secret-box .v { color: #c21f39; }
    .atop-badge { display: inline-block; padding: 1px 8px; border-radius: 4px; font-size: 12px; margin-right: 6px; line-height: 20px; }
    .atop-badge.ok { background: #e8f5e9; color: #2e7d32; }
    .atop-badge.warn { background: #fff3e0; color: #e65100; }
    .atop-badge.err { background: #ffebee; color: #c62828; }
    .atop-badge.mute { background: #f2f2f2; color: #666; }
    .atop-toolbar { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; margin: 12px 0 14px; }
    .atop-toolbar label { font-size: 13px; color: #555; }
    .atop-toolbar .layui-input, .atop-toolbar .layui-select { height: 32px; }
    .atop-empty { color: #888; font-size: 13px; padding: 8px 0 4px; }
    .atop-logs { width: 100%; margin: 0 0 12px; }
    .atop-logs td, .atop-logs th { font-size: 12px; }
    .atop-proc { width: 100%; margin-top: 4px; }
    .atop-proc th { text-align: left; font-size: 12px; color: #888; font-weight: 400; padding: 2px 6px 4px 0; }
    .atop-proc th:last-child, .atop-proc td:last-child { text-align: right; }
    .atop-proc td { padding: 2px 6px 2px 0; font-size: 12px; color: #555; }
    .atop-proc td:last-child { color: #888; }
    .atop-split { display: flex; flex-wrap: wrap; gap: 16px; }
    .atop-split > div { flex: 1 1 280px; min-width: 0; }
</style>
</head>
<body class="layui-layout-body">
<script src="/static/layui/layui.js"></script>
<script>
/* Shared helpers for every panel page - loaded BEFORE view scripts so inline
   layui.use(...) calls in views always find the layui object available. */
window.WP = (function () {
    var csrf = document.querySelector('meta[name="csrf"]').getAttribute('content');

    function post(url, data) {
        var body;
        if (data instanceof FormData) {
            data.append('_csrf', csrf);
            body = data;
        } else {
            body = new URLSearchParams(data || {});
            body.append('_csrf', csrf);
        }
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { j.__status = r.status; return j; }); });
    }

    function copy(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
        return Promise.resolve();
    }

    return { post: post, copy: copy, csrf: csrf };
})();
</script>
<div class="layui-layout layui-layout-admin">
    <div class="layui-header">
        <div class="layui-logo layui-elip">
            Console
        </div>
        <div class="header-right">
            <span class="layui-elip" style="display:inline-block;max-width:160px;vertical-align:middle">
                <span class="layui-icon layui-icon-username"></span>
                <?= e($currentUser['username'] ?? 'admin') ?>
            </span>
            &nbsp;&nbsp;
            <a href="javascript:;" id="btnLogout" style="color:#eee"><span class="layui-icon layui-icon-logout"></span> 退出</a>
        </div>
    </div>
    <div class="layui-side">
        <ul class="layui-nav layui-nav-tree" lay-filter="sideNav">
            <?php foreach ($nav as $path => [$label, $icon]): ?>
            <li class="layui-nav-item <?= $uri === $path ? 'layui-nav-itemed layui-this' : '' ?>">
                <a href="<?= e($path) ?>"><span class="layui-icon <?= e($icon) ?>"></span><?= e($label) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="layui-body">
