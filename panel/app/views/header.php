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
<link rel="stylesheet" href="/static/css/panel.css">
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
            <span class="layui-icon layui-icon-template-1 wp-logo-icon"></span>
            WebPanel 管理面板
        </div>
        <div class="header-right">
            <span class="wp-user layui-elip">
                <span class="layui-icon layui-icon-username"></span>
                <?= e($currentUser['username'] ?? 'admin') ?>
            </span>
            <a href="javascript:;" id="btnLogout" class="wp-logout"><span class="layui-icon layui-icon-logout"></span> 退出</a>
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
