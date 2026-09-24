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
$wallpaperSrc = panel_appearance_src();
?><!doctype html>
<html lang="zh-CN"<?php if ($wallpaperSrc !== ''): ?> data-wallpaper="<?= e($wallpaperSrc) ?>"<?php endif; ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e($csrf) ?>">
<title>WebPanel 管理面板</title>
<script>
(function () {
    var t = 'dark';
    try { t = localStorage.getItem('wp.theme') || t; } catch (e) {}
    if (t !== 'light') t = 'dark';
    document.documentElement.setAttribute('data-theme', t);
    var src = document.documentElement.getAttribute('data-wallpaper') || '';
    if (t === 'dark' && src) {
        document.documentElement.style.setProperty('--wp-wallpaper-image', 'url("' + String(src).replace(/"/g, '') + '")');
    }
    var collapsed = false;
    try { collapsed = localStorage.getItem('wp.sideCollapsed') === '1'; } catch (e) {}
    if (collapsed) document.documentElement.classList.add('wp-side-collapsed');
})();
</script>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<link rel="stylesheet" href="/static/css/panel.css">
</head>
<body class="layui-layout-body<?= $uri === '/' ? ' wp-page-dashboard' : '' ?>">
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
        <button type="button" class="wp-icon-btn wp-side-toggle" id="btnSideToggle" title="折叠 / 展开侧栏" aria-label="折叠侧栏" aria-expanded="true">
            <span class="layui-icon layui-icon-shrink-right" id="btnSideToggleIcon"></span>
        </button>
        <div class="layui-logo layui-elip">
            <span class="layui-icon layui-icon-template-1 wp-logo-icon"></span>
            <span class="wp-logo-text">WebPanel<span class="wp-logo-rest"> 管理面板</span></span>
        </div>
        <div class="header-right">
            <?php if ($uri !== '/'): ?>
            <div class="wp-clock" id="wpClock" title="本地时间">
                <div class="wp-clock-time" id="wpClockTime">--:--</div>
                <div class="wp-clock-date" id="wpClockDate"></div>
            </div>
            <?php endif; ?>
            <button type="button" class="wp-icon-btn" id="btnTheme" title="切换到浅色主题" aria-label="切换主题">
                <span class="layui-icon layui-icon-light" id="btnThemeIcon"></span>
            </button>
            <div class="wp-appear-wrap">
                <button type="button" class="wp-icon-btn" id="btnAppearance" title="外观" aria-label="外观">
                    <span class="layui-icon layui-icon-set"></span>
                </button>
                <div class="wp-appear" id="wpAppear" hidden>
                    <div class="wp-appear-title">外观</div>
                    <p class="wp-appear-hint">壁纸仅在深色玻璃主题下显示。未设置时使用深色渐变。</p>
                    <div class="wp-appear-row">
                        <button type="button" class="layui-btn layui-btn-sm" id="btnWpUpload">上传壁纸</button>
                        <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btnWpClear">清除</button>
                        <input type="file" id="wpFile" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" hidden>
                    </div>
                    <div class="wp-appear-row">
                        <input type="url" id="wpUrl" class="layui-input" placeholder="或填写图片 URL（http/https）">
                        <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btnWpUrl">应用</button>
                    </div>
                    <p class="wp-appear-hint">jpg / png / webp，最大 8MB</p>
                    <div class="wp-appear-preview" id="wpPreview" <?= $wallpaperSrc === '' ? 'hidden' : '' ?>>
                        <?php if ($wallpaperSrc !== ''): ?>
                        <img src="<?= e($wallpaperSrc) ?>" alt="当前壁纸">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <span class="wp-user layui-elip">
                <span class="layui-icon layui-icon-username"></span>
                <?= e($currentUser['username'] ?? 'admin') ?>
            </span>
            <a href="javascript:;" id="btnLogout" class="wp-logout"><span class="layui-icon layui-icon-logout"></span> 退出</a>
        </div>
    </div>
    <div class="layui-side wp-side" id="wpSide">
        <div class="layui-side-scroll">
            <ul class="wp-side-nav" id="wpSideNav">
                <?php foreach ($nav as $path => [$label, $icon]): ?>
                <li class="<?= $uri === $path ? 'is-active' : '' ?>">
                    <a href="<?= e($path) ?>" title="<?= e($label) ?>">
                        <span class="layui-icon <?= e($icon) ?>"></span>
                        <span class="wp-side-label"><?= e($label) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="layui-body">
