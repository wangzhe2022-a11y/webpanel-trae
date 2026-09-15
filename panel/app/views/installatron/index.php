<?php /** @var bool $installed @var string $version @var array $job */ ?>
<div class="panel-card">
    <h3>
        Installatron —— 一键 Web 应用安装器
        <?php if ($installed): ?>
            <span class="layui-badge layui-bg-green" style="margin-left:8px">已安装</span>
        <?php else: ?>
            <span class="layui-badge" style="margin-left:8px">未安装</span>
        <?php endif; ?>
    </h3>
    <p style="color:#666;font-size:13px;line-height:1.9;margin:0">
        Installatron 是主流的一键 Web 应用安装器（WordPress、Joomla、Drupal、PrestaShop、phpMyAdmin 等
        <b>320+</b> 应用），内置自动更新、克隆、暂存与快照备份。本页集成其
        <b>Installatron Server</b> 独立版：官方 GUI 通过一次性会话直达，无需二次登录。
    </p>
</div>

<?php if (!$installed): ?>
<div class="panel-card">
    <h3>安装 Installatron Server</h3>
    <div class="layui-form-item">
        <label class="layui-form-label" style="width:110px">License Key</label>
        <div class="layui-input-inline" style="width:420px">
            <input id="itKey" class="layui-input mono" placeholder="粘贴 Installatron Server License Key" autocomplete="off">
        </div>
        <button class="layui-btn" id="btnItInstall">
            <span class="layui-icon layui-icon-upload-drag"></span> 一键安装
        </button>
    </div>
    <ul style="font-size:12.5px;color:#777;line-height:2;padding-left:14px;margin:8px 0 0">
        <li>License Key 在 <span class="mono">installatron.com → My Account → License Key</span> 页面生成（付费版）</li>
        <li>安装约需 <b>2-5 分钟</b>：官方安装器 + 自动预建 <span class="mono">installatron</span> MySQL 库（复用本机 MySQL，不会另装数据库）</li>
        <li>Nginx 配置会先自动备份到 <span class="mono">/www/server/backup/</span>；若安装器改动导致校验失败将自动回滚</li>
        <li>程序目录 <span class="mono">/usr/local/installatron/</span>，应用数据 <span class="mono">/var/installatron/</span>；官方每日自动升级</li>
    </ul>
</div>
<?php else: ?>
<div class="panel-card">
    <h3>管理</h3>
    <table class="layui-table" style="margin:0">
        <tbody>
        <tr>
            <td width="140">当前版本</td>
            <td class="mono"><?= $version !== '' ? e($version) : '未知（服务可能正在启动）' ?></td>
        </tr>
        <tr>
            <td>控制台入口</td>
            <td>
                <button class="layui-btn layui-btn-sm" id="btnItLogin" data-url="<?= e($loginUrl ?? '') ?>">
                            <span class="layui-icon layui-icon-website"></span> 打开 Installatron 控制台
                        </button>
                <span style="color:#999;font-size:12px;margin-left:8px">每次点击生成一次性会话，新窗口打开</span>
            </td>
        </tr>
        <tr>
            <td>版本维护</td>
            <td>
                <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnItUpgrade">
                    <span class="layui-icon layui-icon-refresh"></span> 立即升级
                </button>
                <span style="color:#999;font-size:12px;margin-left:8px">官方已每日自动升级，此按钮用于手动触发</span>
            </td>
        </tr>
        <tr>
            <td>卸载</td>
            <td>
                <button class="layui-btn layui-btn-sm layui-btn-danger" id="btnItUninstall">
                    <span class="layui-icon layui-icon-delete"></span> 卸载 Installatron
                </button>
            </td>
        </tr>
        </tbody>
    </table>
    <p style="color:#999;font-size:12px;margin:10px 0 0">
        在控制台中可为站点一键安装/更新/克隆/备份应用；面板「网站管理」创建的站点目录可直接在控制台中导入。
    </p>
</div>
<?php endif; ?>

<div class="panel-card" id="jobCard" style="display:none">
    <h3 id="jobTitle">任务进行中</h3>
    <div style="display:flex;align-items:center;gap:12px">
        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="font-size:26px;color:#1e9fff"></i>
        <div style="flex:1">
            <div id="jobPhase" style="font-size:14px;margin-bottom:8px">准备</div>
            <div class="layui-progress layui-progress-big" lay-filter="jobBar">
                <div class="layui-progress-bar layui-bg-blue"></div>
            </div>
        </div>
    </div>
    <p style="color:#999;font-size:12px;margin:10px 0 0">
        任务在服务器后台执行，可离开本页面稍后回来查看。
    </p>
</div>

<script>
layui.use(['layer', 'element'], function () {
    var layer = layui.layer, element = layui.element, $ = layui.$;
    var pollTimer = null;
    var busy = false;

    function setBusy(b) {
        busy = b;
        $('#btnItInstall,#btnItUpgrade,#btnItLogin,#btnItUninstall').prop('disabled', b).toggleClass('layui-btn-disabled', b);
    }

    function showJob(j) {
        $('#jobCard').show();
        var kind = j.kind === 'upgrade' ? '升级' : '安装';
        var pct = j.total > 0 ? Math.round(j.progress * 100 / j.total) : 0;
        $('#jobTitle').text(kind + '任务进行中 —— ' + (j.name || 'Installatron Server'));
        $('#jobPhase').text((j.phase || '') + '（' + (j.progress || 0) + '/' + (j.total || 0) + '）');
        element.progress('jobBar', pct + '%');
    }

    function poll() {
        if (pollTimer) clearInterval(pollTimer);
        setBusy(true);
        $('#jobCard').show();
        pollTimer = setInterval(function () {
            fetch('/installatron/status', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    var j = res.job || { state: 'idle' };
                    if (j.state === 'running') { showJob(j); return; }
                    clearInterval(pollTimer); pollTimer = null; setBusy(false);
                    $('#jobCard').hide();
                    if (j.state === 'failed') {
                        layer.alert((j.error || '任务失败'), { icon: 2, title: '任务失败' }, function () { location.reload(); });
                    } else {
                        layer.msg('任务完成', { icon: 1, time: 1500 }, function () { location.reload(); });
                    }
                })
                .catch(function () { /* transient network error: keep polling */ });
        }, 2000);
    }

    function jobRunning() {
        return busy;
    }

    $('#btnItInstall').on('click', function () {
        if (jobRunning()) { layer.msg('已有任务正在运行', { icon: 0 }); return; }
        var key = $('#itKey').val().trim();
        if (!key) { layer.msg('请先粘贴 License Key', { icon: 2 }); return; }
        layer.confirm('开始安装 Installatron Server？<br><span style="font-size:12px;color:#999">约 2-5 分钟，安装期间会预建数据库并备份 Nginx 配置。</span>', {
            title: '一键安装'
        }, function (idx) {
            layer.close(idx);
            WP.post('/installatron/install', { key: key }).then(function (res) {
                if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
                layer.msg('安装任务已开始', { icon: 1, time: 1500 });
                poll();
            });
        });
    });

    $('#btnItLogin').on('click', function () {
        if (jobRunning()) { layer.msg('任务运行中，请稍后再试', { icon: 0 }); return; }
        // URL is pre-fetched at page render (data-url) so we can open it
        // synchronously inside the click gesture - the only way to avoid
        // popup blockers. If the browser still blocks the new tab, fall back
        // to navigating the current tab (works everywhere).
        var url = $(this).data('url');
        if (!url) {
            // URL expired/missing - fetch a fresh one and navigate
            var load = layer.load(2);
            WP.post('/installatron/login', {}).then(function (res) {
                layer.close(load);
                if (!res.ok || !res.url) { layer.alert(res.error || '控制台未返回会话地址', { icon: 2 }); return; }
                location.href = res.url;
            }).catch(function () { layer.close(load); });
            return;
        }
        var win = window.open(url, '_blank');
        if (!win) {
            // popup blocked -> open in current tab instead
            location.href = url;
        }
    });

    $('#btnItUpgrade').on('click', function () {
        if (jobRunning()) { layer.msg('已有任务正在运行', { icon: 0 }); return; }
        layer.confirm('立即升级 Installatron Server？', { title: '手动升级' }, function (idx) {
            layer.close(idx);
            WP.post('/installatron/upgrade', {}).then(function (res) {
                if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
                layer.msg('升级任务已开始', { icon: 1, time: 1500 });
                poll();
            });
        });
    });

    $('#btnItUninstall').on('click', function () {
        if (jobRunning()) { layer.msg('任务运行中，请稍后再试', { icon: 0 }); return; }
        layer.open({
            type: 1, title: '卸载 Installatron', area: ['520px', '380px'],
            content: '<div style="padding:18px 20px">'
                + '<p style="line-height:1.8;font-size:13px">即将卸载 <b>Installatron Server</b>：</p>'
                + '<ul style="font-size:13px;color:#555;line-height:1.9;padding-left:18px">'
                + '<li>删除程序 <span class="mono">/usr/local/installatron/</span> 与升级 cron</li>'
                + '<li>默认<b>保留</b>应用数据 <span class="mono">/var/installatron/</span>（重装可恢复）</li></ul>'
                + '<div class="layui-form-item" style="margin:4px 0">'
                + '<input type="checkbox" id="purgeData" lay-skin="primary" title="同时删除应用数据（不可恢复）"></div>'
                + '<p style="color:#ff5722;font-size:12px">请确认；卸载不影响面板与站点运行。</p>'
                + '<div class="layui-form-item" style="margin-top:6px">'
                + '<label class="layui-form-label">确认输入</label>'
                + '<div class="layui-input-inline"><input id="unConfirm" class="layui-input mono" placeholder="输入 UNINSTALL 确认"></div></div>'
                + '</div>',
            btn: ['卸载', '取消'],
            yes: function (idx, layero) {
                var v = layero.find('#unConfirm').val().trim();
                if (v !== 'UNINSTALL') { layer.msg('请输入大写 UNINSTALL 以确认', { icon: 2 }); return; }
                var purge = layero.find('#purgeData').is(':checked') ? '1' : '';
                layer.close(idx);
                WP.post('/installatron/uninstall', { confirm: v, purge: purge }).then(function (res) {
                    if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
                    layer.msg('已卸载', { icon: 1, time: 1500 }, function () { location.reload(); });
                });
            }
        });
    });

    // resume polling if a job is already running when the page loads
    <?php if (($job['state'] ?? '') === 'running'): ?>
    poll();
    <?php endif; ?>
});
</script>
