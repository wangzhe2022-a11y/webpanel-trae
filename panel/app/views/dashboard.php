<?php
/** @var array $info @var array $stats */
$memPct = 0;
if (!empty($info['mem_total_kb'])) {
    $memPct = round(100 * (1 - ($info['mem_available_kb'] / $info['mem_total_kb'])));
}
?>
<div class="layui-row layui-col-space15">
    <div class="layui-col-md3">
        <div class="stat-card c-blue">
            <span class="layui-icon layui-icon-website"></span>
            <div class="num"><?= (int) $stats['sites'] ?></div><div class="label">托管网站</div>
        </div>
    </div>
    <div class="layui-col-md3">
        <div class="stat-card c-green">
            <span class="layui-icon layui-icon-table"></span>
            <div class="num"><?= (int) $stats['databases'] ?></div><div class="label">MySQL 数据库</div>
        </div>
    </div>
    <div class="layui-col-md3">
        <div class="stat-card c-orange">
            <span class="layui-icon layui-icon-auz"></span>
            <div class="num"><?= (int) $stats['ssl'] ?></div><div class="label">已启用 HTTPS</div>
        </div>
    </div>
    <div class="layui-col-md3">
        <div class="stat-card c-purple">
            <span class="layui-icon layui-icon-cpu"></span>
            <div class="num"><?= e((string) ($info['cpu_cores'] ?? '-')) ?> <span style="font-size:14px">核</span></div>
            <div class="label">CPU · <?= e((string) ($info['hostname'] ?? '')) ?></div>
        </div>
    </div>
</div>

<div class="layui-row layui-col-space15" style="margin-top:2px">
    <div class="layui-col-md6">
        <div class="panel-card">
            <h3>系统信息</h3>
            <table class="layui-table" lay-skin="line" style="margin:0">
                <tr><td width="110">操作系统</td><td><?= e($info['os'] ?? '-') ?></td></tr>
                <tr><td>内核</td><td class="mono"><?= e($info['kernel'] ?? '-') ?></td></tr>
                <tr><td>运行时间</td><td><?= e($info['uptime'] ?? '-') ?></td></tr>
                <tr><td>负载 (1/5/15)</td><td class="mono"><?= e($info['loadavg'] ?? '-') ?></td></tr>
                <tr><td>内存使用</td><td>
                    <div class="layui-progress layui-progress-big" lay-filter="memBar" lay-showpercent="true">
                        <div class="layui-progress-bar" lay-percent="<?= $memPct ?>%"></div>
                    </div>
                    <span class="mono" style="color:#888;font-size:12px">
                        <?= isset($info['mem_available_kb']) ? format_bytes($info['mem_total_kb'] - $info['mem_available_kb']) . ' / ' . format_bytes($info['mem_total_kb']) : '-' ?>
                    </span>
                </td></tr>
            </table>
        </div>
        <div class="panel-card">
            <h3>磁盘</h3>
            <table class="layui-table" style="margin:0">
                <thead><tr><th>挂载点</th><th>总量</th><th>已用</th><th>可用</th><th>使用率</th></tr></thead>
                <tbody>
                <?php foreach (($info['disk'] ?? []) as $d): ?>
                    <tr>
                        <td class="mono"><?= e($d['fs']) ?></td>
                        <td><?= e($d['size']) ?></td><td><?= e($d['used']) ?></td><td><?= e($d['avail']) ?></td>
                        <td>
                            <div class="layui-progress" lay-showpercent="true" style="width:120px">
                                <div class="layui-progress-bar <?= $d['use_pct'] > 85 ? 'layui-bg-red' : '' ?>" lay-percent="<?= (int) $d['use_pct'] ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="panel-card">
            <h3>
                服务状态
                <button class="layui-btn layui-btn-sm layui-btn-primary" style="float:right" id="btnRefreshSvc">
                    <span class="layui-icon layui-icon-refresh"></span> 刷新
                </button>
            </h3>
            <table class="layui-table" style="margin:0">
                <thead><tr><th>服务</th><th>状态</th><th style="width:210px">操作</th></tr></thead>
                <tbody id="svcBody">
                <?php foreach (($info['services'] ?? []) as $s): ?>
                    <tr data-name="<?= e(str_replace(['php74-php-fpm','php80-php-fpm','php81-php-fpm','php82-php-fpm','php83-php-fpm'], ['php74fpm','php80fpm','php81fpm','php82fpm','php83fpm'], str_replace(['nginx','mysqld','php-fpm'], ['nginx','mysql','phpfpm'], $s['unit']))) ?>">
                        <td><?= e($s['name']) ?></td>
                        <td>
                            <?php if ($s['status'] === 'active'): ?>
                                <span class="layui-badge layui-bg-green">运行中</span>
                            <?php elseif ($s['status'] === 'inactive' || $s['status'] === 'dead'): ?>
                                <span class="layui-badge">已停止</span>
                            <?php else: ?>
                                <span class="layui-badge layui-bg-orange"><?= e($s['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="layui-btn layui-btn-xs layui-btn-primary svc-act" data-act="restart">重启</button>
                            <button class="layui-btn layui-btn-xs layui-btn-primary svc-act" data-act="reload">重载</button>
                            <button class="layui-btn layui-btn-xs layui-btn-primary svc-act" data-act="start">启动</button>
                            <button class="layui-btn layui-btn-xs layui-btn-danger svc-act" data-act="stop">停止</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
layui.use(['element', 'layer', 'table'], function () {
    var layer = layui.layer, $ = layui.$, element = layui.element;
    element.render('progress');

    function refreshSvc() {
        fetch('/sys/info').then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) { layer.msg(res.error || '获取失败', { icon: 2 }); return; }
            var map = { nginx: 'nginx', mysqld: 'mysql', 'php-fpm': 'phpfpm' };
            [74, 80, 81, 82, 83].forEach(function (v) { map['php' + v + '-php-fpm'] = 'php' + v + 'fpm'; });
            (res.services || []).forEach(function (s) {
                var key = map[s.unit];
                var $tr = $('#svcBody tr[data-name="' + key + '"]');
                var badge = s.status === 'active'
                    ? '<span class="layui-badge layui-bg-green">运行中</span>'
                    : (s.status === 'inactive' || s.status === 'dead')
                        ? '<span class="layui-badge">已停止</span>'
                        : '<span class="layui-badge layui-bg-orange">' + s.status + '</span>';
                $tr.find('td:eq(1)').html(badge);
            });
        });
    }
    $('#btnRefreshSvc').on('click', refreshSvc);

    $('#svcBody').on('click', '.svc-act', function () {
        var name = $(this).closest('tr').data('name');
        var act = $(this).data('act');
        if (act === 'stop' && name !== 'nginx') { /* allow */ }
        layer.confirm('对服务 <b>' + name + '</b> 执行「' + act + '」操作？', { title: '服务操作' }, function (idx) {
            layer.close(idx);
            var load = layer.load(2);
            WP.post('/sys/svc', { service: name, action: act }).then(function (res) {
                layer.close(load);
                layer.msg(res.ok ? '操作成功' : (res.error || '操作失败'), { icon: res.ok ? 1 : 2 });
                setTimeout(refreshSvc, 1200);
            });
        });
    });
});
</script>
