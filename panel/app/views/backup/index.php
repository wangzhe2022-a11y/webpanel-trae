<?php /** @var array $backups @var string $backupDir @var int $keep @var array $job */ ?>
<div class="panel-card">
    <h3>
        备份与恢复
        <span style="float:right">
            <button class="layui-btn layui-btn-sm" id="btnBkFull">
                <span class="layui-icon layui-icon-download"></span> 一键全量备份
            </button>
            <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnBkFiles">备份文件与配置</button>
            <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnBkDb">仅备份数据库</button>
        </span>
    </h3>
    <p style="color:#666;font-size:13px;margin:0 0 6px">
        全量备份包含：面板数据、全部站点文件、SSL 证书、Nginx vhost、PHP-FPM 池、Node.js 服务单元、MySQL / PostgreSQL 全部业务库。
    </p>
    <p style="color:#999;font-size:12px;margin:0">
        备份目录：<span class="mono"><?= e($backupDir) ?></span>（仅 root 可读，可下载到本地）；
        每日自动 <b>03:30</b> 全量备份一次，最多保留最近 <?= max(1, $keep) ?> 份，自动轮转删除旧备份。
    </p>
</div>

<div class="panel-card" id="jobCard" style="display:none">
    <h3 id="jobTitle">任务进行中</h3>
    <div style="display:flex;align-items:center;gap:12px">
        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop wp-accent" style="font-size:26px"></i>
        <div style="flex:1">
            <div id="jobPhase" style="font-size:14px;margin-bottom:8px">准备</div>
            <div class="layui-progress layui-progress-big" lay-filter="jobBar">
                <div class="layui-progress-bar layui-bg-blue"></div>
            </div>
        </div>
    </div>
    <p style="color:#999;font-size:12px;margin:10px 0 0">
        备份/恢复在服务器后台执行，可离开本页面稍后回来查看；同一时间只允许一个任务。
    </p>
</div>

<div class="panel-card">
    <h3>备份记录</h3>
    <table class="layui-table" style="margin:0">
        <thead>
        <tr>
            <th>备份文件</th>
            <th width="100">类型</th>
            <th width="110">大小</th>
            <th width="170">创建时间</th>
            <th width="240">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$backups): ?>
            <tr><td colspan="5" style="text-align:center;color:#999;padding:40px">暂无备份</td></tr>
        <?php endif; ?>
        <?php foreach ($backups as $b): ?>
        <tr data-name="<?= e($b['name']) ?>">
            <td class="mono" style="font-size:12px"><?= e($b['name']) ?></td>
            <td>
                <?php if (($b['scope'] ?? '') === 'full'): ?>
                    <span class="layui-badge layui-bg-green">全量</span>
                <?php elseif (($b['scope'] ?? '') === 'db'): ?>
                    <span class="layui-badge layui-bg-cyan">数据库</span>
                <?php else: ?>
                    <span class="layui-badge layui-bg-blue">文件</span>
                <?php endif; ?>
            </td>
            <td class="mono"><?= e(human_size((int) ($b['size'] ?? 0))) ?></td>
            <td class="mono" style="font-size:12px"><?= e($b['mtime'] ?? '') ?></td>
            <td>
                <button class="layui-btn layui-btn-xs layui-btn-warm btn-restore">恢复</button>
                <button class="layui-btn layui-btn-xs btn-download">下载</button>
                <button class="layui-btn layui-btn-xs layui-btn-danger btn-del">删除</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
layui.use(['layer', 'element'], function () {
    var layer = layui.layer, element = layui.element, $ = layui.$;
    var pollTimer = null;
    var busy = false;

    function setBusy(b) {
        busy = b;
        $('#btnBkFull,#btnBkFiles,#btnBkDb').prop('disabled', b).toggleClass('layui-btn-disabled', b);
    }

    function jobRunning() {
        return busy;
    }

    function showJob(j) {
        $('#jobCard').show();
        var kind = j.kind === 'restore' ? '恢复' : '备份';
        var pct = j.total > 0 ? Math.round(j.progress * 100 / j.total) : 0;
        $('#jobTitle').text(kind + '任务进行中 —— ' + (j.name || ''));
        $('#jobPhase').text((j.phase || '') + '（' + (j.progress || 0) + '/' + (j.total || 0) + '）');
        element.progress('jobBar', pct + '%');
    }

    function poll() {
        if (pollTimer) clearInterval(pollTimer);
        setBusy(true);
        $('#jobCard').show();
        pollTimer = setInterval(function () {
            fetch('/backup/status', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
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

    function startCreate(scope, tip) {
        if (jobRunning()) { layer.msg('已有任务正在运行', { icon: 0 }); return; }
        var load = layer.load(2);
        WP.post('/backup/create', { scope: scope }).then(function (res) {
            layer.close(load);
            if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
            layer.msg(tip + '已开始，任务在后台执行', { icon: 1, time: 1500 });
            poll();
        });
    }

    $('#btnBkFull').on('click', function () { startCreate('full', '全量备份'); });
    $('#btnBkFiles').on('click', function () { startCreate('files', '文件备份'); });
    $('#btnBkDb').on('click', function () { startCreate('db', '数据库备份'); });

    $('.btn-download').on('click', function () {
        var name = $(this).closest('tr').data('name');
        location.href = '/backup/download?name=' + encodeURIComponent(name) + '&_csrf=' + encodeURIComponent(WP.csrf);
    });

    $('.btn-del').on('click', function () {
        var $tr = $(this).closest('tr');
        var name = $tr.data('name');
        layer.confirm('删除备份 <b class="mono">' + layui.util.escape(name) + '</b>？此操作不可恢复。', {
            title: '删除备份'
        }, function (idx) {
            layer.close(idx);
            WP.post('/backup/delete', { name: name }).then(function (res) {
                if (res.ok) { layer.msg('已删除', { icon: 1 }); $tr.remove(); }
                else { layer.alert(res.error, { icon: 2 }); }
            });
        });
    });

    $('.btn-restore').on('click', function () {
        if (jobRunning()) { layer.msg('已有任务正在运行', { icon: 0 }); return; }
        var name = $(this).closest('tr').data('name');
        layer.open({
            type: 1, title: '恢复备份', area: ['520px', '360px'],
            content: '<div style="padding:18px 20px">'
                + '<p style="line-height:1.8;font-size:13px">即将从备份 <b class="mono">' + layui.util.escape(name) + '</b> 恢复：</p>'
                + '<ul style="font-size:13px;color:#555;line-height:1.9;padding-left:18px">'
                + '<li>覆盖站点文件、证书与全部服务配置</li>'
                + '<li>重建 MySQL / PostgreSQL 中的同名数据库</li>'
                + '<li>覆盖面板自身的站点/数据库记录</li></ul>'
                + '<p style="color:#ff5722;font-size:12px">当前同名数据将被替换且无法撤销。数据库用户与密码不在备份范围内（本机恢复不受影响）。</p>'
                + '<div class="layui-form-item" style="margin-top:6px">'
                + '<label class="layui-form-label">确认输入</label>'
                + '<div class="layui-input-inline"><input id="restoreConfirm" class="layui-input mono" placeholder="输入 RESTORE 确认"></div></div>'
                + '</div>',
            btn: ['开始恢复', '取消'],
            yes: function (idx, layero) {
                var v = layero.find('#restoreConfirm').val().trim();
                if (v !== 'RESTORE') { layer.msg('请输入大写 RESTORE 以确认', { icon: 2 }); return; }
                layer.close(idx);
                WP.post('/backup/restore', { name: name, confirm: v }).then(function (res) {
                    if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
                    layer.msg('恢复任务已开始，请勿关闭服务器', { icon: 1, time: 1500 });
                    poll();
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
