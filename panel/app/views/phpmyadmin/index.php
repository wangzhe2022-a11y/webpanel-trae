<?php
/** @var bool $installed @var string $version @var string $pmaUrl @var string $error */
?>
<div class="panel-card">
    <h3>
        phpMyAdmin
        <?php if ($installed): ?>
            <span class="layui-badge layui-bg-green" style="margin-left:8px">已安装</span>
        <?php else: ?>
            <span class="layui-badge" style="margin-left:8px">未安装</span>
        <?php endif; ?>
        <?php if ($installed && $version !== ''): ?>
            <span class="mono" style="float:right;color:#999;font-weight:400;font-size:12px">
                <?= e($version) ?>
            </span>
        <?php endif; ?>
    </h3>
    <p style="color:#666;font-size:13px;line-height:1.9;margin:0">
        内置 <b>phpMyAdmin</b>，在面板同一 HTTPS 端口下浏览 / 执行 SQL、导入导出。
        仅登录面板后可打开（Nginx 校验会话），不会作为独立公网站点暴露。
        连接本机 MySQL；站点库仍请在「数据库」页创建 / 改密 / 删除。
        PostgreSQL 请使用客户端连接 <span class="mono">127.0.0.1:5432</span>。
    </p>
</div>

<?php if ($error !== ''): ?>
<div class="panel-card">
    <p style="color:#ff5722;margin:0">状态查询失败：<?= e($error) ?>。已有服务器请确认已安装 sudoers 并执行
        <span class="mono">sudo /usr/local/webpanel/bin/wp-pma.sh install</span>。</p>
</div>
<?php endif; ?>

<?php if (!$installed): ?>
<div class="panel-card">
    <h3>安装 phpMyAdmin</h3>
    <p style="color:#666;font-size:13px;line-height:1.8">
        将从 <span class="mono">files.phpmyadmin.net</span> 下载官方发行包（约 14 MB），
        安装到 <span class="mono">/www/server/phpmyadmin</span>，并生成本机专用 MySQL 账号与
        <span class="mono">blowfish_secret</span>（不写入仓库）。
    </p>
    <button class="layui-btn" id="btnPmaInstall">
        <span class="layui-icon layui-icon-download-circle"></span> 一键安装
    </button>
    <p style="color:#999;font-size:12px;margin-top:10px">
        也可在服务器上执行：
        <span class="mono">sudo /usr/local/webpanel/bin/wp-pma.sh install</span>
    </p>
</div>
<?php elseif (PANEL_DRY): ?>
<div class="panel-card">
    <h3>SQL 浏览器（演示）</h3>
    <p style="color:#666;font-size:13px;line-height:1.8;margin:0 0 12px">
        DRY-RUN 模式不会下载 phpMyAdmin。生产环境登录后将在此嵌入官方界面，也可新窗口打开
        <span class="mono">/phpmyadmin/</span>。
    </p>
    <div style="border:1px solid #e6e6e6;border-radius:6px;background:#fafafa;padding:28px 20px">
        <table class="layui-table" style="margin:0">
            <thead>
            <tr>
                <th>数据库</th>
                <th>字符集</th>
                <th>表</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td class="mono">wp_shop</td>
                <td>utf8mb4_unicode_ci</td>
                <td>12</td>
                <td><span class="layui-badge-rim">浏览</span> <span class="layui-badge-rim">SQL</span></td>
            </tr>
            <tr>
                <td class="mono">wp_blog</td>
                <td>utf8mb4_unicode_ci</td>
                <td>8</td>
                <td><span class="layui-badge-rim">浏览</span> <span class="layui-badge-rim">SQL</span></td>
            </tr>
            </tbody>
        </table>
        <p style="color:#999;font-size:12px;margin:12px 0 0">演示数据，生产环境显示本机真实 MySQL 库表。</p>
    </div>
</div>
<?php else: ?>
<div class="panel-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <h3 style="margin:0">SQL 浏览器</h3>
        <a class="wp-link" href="<?= e($pmaUrl) ?>" target="_blank" rel="noopener" style="font-size:13px">
            <span class="layui-icon layui-icon-link"></span> 新窗口打开
        </a>
    </div>
    <iframe id="pmaFrame" title="phpMyAdmin" src="<?= e($pmaUrl) ?>"
        style="width:100%;height:720px;border:1px solid #e6e6e6;border-radius:6px;background:#fff"></iframe>
    <p style="color:#999;font-size:12px;margin:10px 0 0">
        已通过面板会话进入，使用专用账号连接本机 MySQL（无需再输入数据库密码）。
        导入大 SQL 文件建议新窗口打开。
    </p>
</div>
<?php endif; ?>

<?php if (!$installed): ?>
<script>
layui.use(['layer'], function () {
    var layer = layui.layer;
    var btn = document.getElementById('btnPmaInstall');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var idx = layer.msg('正在下载并安装 phpMyAdmin…', { icon: 16, time: 0, shade: 0.2 });
        WP.post('/phpmyadmin/install', {}).then(function (res) {
            layer.close(idx);
            if (!res.ok) { layer.alert(res.error || '安装失败', { icon: 2 }); return; }
            layer.msg('安装完成', { icon: 1, time: 800 });
            setTimeout(function () { location.reload(); }, 600);
        }).catch(function () {
            layer.close(idx);
            layer.alert('请求失败，请检查网络后重试，或在服务器执行 wp-pma.sh install', { icon: 2 });
        });
    });
});
</script>
<?php endif; ?>
