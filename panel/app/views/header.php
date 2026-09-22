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
<title>WebPanel 管理面板</title>
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
    .stat-card { border-radius: 8px; padding: 20px; color: #fff; position: relative; }
    .stat-card .num { font-size: 32px; font-weight: 600; }
    .stat-card .label { opacity: .85; font-size: 13px; }
    .stat-card .layui-icon { position: absolute; right: 18px; top: 18px; font-size: 42px; opacity: .35; }
    .c-blue { background: linear-gradient(135deg,#1e9fff,#0c7cd5); }
    .c-green { background: linear-gradient(135deg,#16baaa,#0e9a8c); }
    .c-orange { background: linear-gradient(135deg:#ffb800,#e69500); }
    .c-purple { background: linear-gradient(135deg:#a233c6,#7d2399); }
    .panel-card { background: #fff; border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
    .panel-card h3 { margin: 0 0 14px; font-size: 15px; }
    .mon-row { margin-bottom: 16px; }
    .mon-row:last-child { margin-bottom: 0; }
    .mon-k { font-size: 13px; margin-bottom: 6px; overflow: hidden; }
    .mon-k .right { float: right; color: #666; font-size: 12px; }
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
            <span class="layui-icon layui-icon-template-1" style="color:#1e9fff"></span>
            WebPanel 管理面板
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
