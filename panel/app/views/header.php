<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = [
    '/'        => ['仪表盘', 'layui-icon-console'],
    '/sites'   => ['网站管理', 'layui-icon-website'],
    '/databases' => ['数据库', 'layui-icon-table'],
    '/ssl'     => ['SSL 证书', 'layui-icon-auz'],
    '/files'   => ['文件管理', 'layui-icon-file'],
    '/backup'  => ['备份恢复', 'layui-icon-download-circle'],
];
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e($csrf) ?>">
<title>WebPanel 管理面板</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.9.16/dist/css/layui.css">
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
    .tag-alias { margin: 2px 4px 2px 0; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; }
    .secret-box { background: #f7f7f7; border: 1px dashed #bbb; border-radius: 6px; padding: 12px; margin: 10px 0; word-break: break-all; }
    .secret-box b { color: #333; }
    .secret-box .v { color: #c21f39; }
</style>
</head>
<body class="layui-layout-body">
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
