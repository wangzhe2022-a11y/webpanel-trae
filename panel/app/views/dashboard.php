<?php
/** @var array $info @var array $stats */
$memTotal = (int) ($info['mem_total_kb'] ?? 0);
$memAvail = (int) ($info['mem_available_kb'] ?? 0);
$memUsed = (int) ($info['mem_used_kb'] ?? max(0, $memTotal - $memAvail));
$memPct = (float) ($info['mem_used_pct'] ?? ($memTotal > 0 ? round(100 * $memUsed / $memTotal, 1) : 0));
$swapTotal = (int) ($info['swap_total_kb'] ?? 0);
$swapUsed = (int) ($info['swap_used_kb'] ?? 0);
$swapPct = (float) ($info['swap_used_pct'] ?? ($swapTotal > 0 ? round(100 * $swapUsed / $swapTotal, 1) : 0));
$cpuPct = (float) ($info['cpu_usage_pct'] ?? 0);
$cpuCores = (int) ($info['cpu_cores'] ?? 0);
$loadavg = (string) ($info['loadavg'] ?? '-');
$load1 = (float) ($info['load_1'] ?? 0);
if ($load1 == 0.0 && $loadavg !== '-' && $loadavg !== '') {
    $load1 = (float) explode(' ', $loadavg)[0];
}
$loadRatio = $cpuCores > 0 ? $load1 / $cpuCores : $load1;
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
            <div class="num" id="statCpuPct"><?= isset($info['cpu_usage_pct']) ? e(rtrim(rtrim(number_format($cpuPct, 1, '.', ''), '0'), '.')) . '%' : e((string) ($cpuCores ?: '-')) ?></div>
            <div class="label" id="statCpuLabel">CPU<?= $cpuCores ? ' · ' . $cpuCores . ' 核' : '' ?> · <?= e((string) ($info['hostname'] ?? '')) ?></div>
        </div>
    </div>
</div>

<div class="layui-row layui-col-space15" style="margin-top:2px">
    <div class="layui-col-md6">
        <div class="panel-card">
            <h3>
                主机监控
                <span class="mon-updated" id="monUpdated">每 15 秒自动刷新</span>
            </h3>
            <div class="mon-row">
                <div class="mon-k">内存 <span class="right mono" id="memText">
                    <?= $memTotal ? e(format_bytes($memUsed) . ' / ' . format_bytes($memTotal) . ' · 可用 ' . format_bytes($memAvail) . ' · ' . rtrim(rtrim(number_format($memPct, 1, '.', ''), '0'), '.') . '%') : '-' ?>
                </span></div>
                <div class="layui-progress layui-progress-big" lay-filter="memBar" lay-showpercent="true">
                    <div class="layui-progress-bar <?= e(panel_gauge_class($memPct, 85, 95)) ?>" lay-percent="<?= e((string) $memPct) ?>%"></div>
                </div>
            </div>
            <div class="mon-row" id="swapRow" <?= $swapTotal > 0 ? '' : 'style="display:none"' ?>>
                <div class="mon-k">交换分区 <span class="right mono" id="swapText">
                    <?= $swapTotal ? e(format_bytes($swapUsed) . ' / ' . format_bytes($swapTotal) . ' · ' . rtrim(rtrim(number_format($swapPct, 1, '.', ''), '0'), '.') . '%') : '' ?>
                </span></div>
                <div class="layui-progress" lay-filter="swapBar" lay-showpercent="true">
                    <div class="layui-progress-bar <?= e(panel_gauge_class($swapPct, 50, 80)) ?>" lay-percent="<?= e((string) $swapPct) ?>%"></div>
                </div>
            </div>
            <div class="mon-row">
                <div class="mon-k">CPU / 负载 <span class="right mono" id="cpuText">
                    <?= e(($cpuPct > 0 ? rtrim(rtrim(number_format($cpuPct, 1, '.', ''), '0'), '.') . '% · ' : '') . '负载 ' . $loadavg . ($cpuCores ? ' · ' . $cpuCores . ' 核' : '')) ?>
                </span></div>
                <div class="layui-progress" lay-filter="cpuBar" lay-showpercent="true">
                    <div class="layui-progress-bar <?= e(panel_gauge_class($cpuPct, 85, 95)) ?>" lay-percent="<?= e((string) $cpuPct) ?>%"></div>
                </div>
                <div class="mon-meta" id="loadText">
                    负载 1/5/15：<span class="mono <?= $loadRatio >= 1.5 ? 'warn-text' : '' ?>"><?= e($loadavg) ?></span>
                    <?php if ($cpuCores): ?> · 每核 1 分钟 <?= e(number_format($loadRatio, 2)) ?><?php endif; ?>
                </div>
            </div>
            <table class="layui-table" lay-skin="line" style="margin:12px 0 0">
                <tr><td width="110">主机名</td><td id="hostName"><?= e((string) ($info['hostname'] ?? '-')) ?></td></tr>
                <tr><td>操作系统</td><td id="hostOs"><?= e((string) ($info['os'] ?? '-')) ?></td></tr>
                <tr><td>内核</td><td class="mono" id="hostKernel"><?= e((string) ($info['kernel'] ?? '-')) ?></td></tr>
                <tr><td>运行时间</td><td id="hostUptime"><?= e((string) ($info['uptime'] ?? '-')) ?></td></tr>
            </table>
            <div class="mon-meta" id="topLabel" <?= empty($info['top']) ? 'style="display:none"' : '' ?>>占用内存最多</div>
            <table class="mon-top" id="topTable" <?= empty($info['top']) ? 'style="display:none"' : '' ?>>
                <tbody>
                <?php foreach (($info['top'] ?? []) as $p): ?>
                    <tr>
                        <td class="mono"><?= e((string) ($p['name'] ?? '')) ?></td>
                        <td class="mono"><?= isset($p['rss_kb']) ? e(format_bytes((int) $p['rss_kb'])) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-card">
            <h3>磁盘</h3>
            <table class="layui-table" style="margin:0">
                <thead><tr><th>挂载点</th><th>总量</th><th>已用</th><th>可用</th><th>使用率</th></tr></thead>
                <tbody id="diskBody">
                <?php foreach (($info['disk'] ?? []) as $i => $d): ?>
                    <?php $dp = (float) ($d['use_pct'] ?? 0); ?>
                    <tr>
                        <td class="mono"><?= e((string) ($d['fs'] ?? '')) ?></td>
                        <td><?= e((string) ($d['size'] ?? '')) ?></td>
                        <td><?= e((string) ($d['used'] ?? '')) ?></td>
                        <td><?= e((string) ($d['avail'] ?? '')) ?></td>
                        <td>
                            <div class="layui-progress" lay-filter="diskBar<?= (int) $i ?>" lay-showpercent="true" style="width:120px">
                                <div class="layui-progress-bar <?= e(panel_gauge_class($dp, 80, 90)) ?>" lay-percent="<?= (int) $dp ?>%"></div>
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

    function fmtKb(kb) {
        kb = Number(kb) || 0;
        if (kb >= 1048576) return (kb / 1048576).toFixed(1) + ' GB';
        if (kb >= 1024) return (kb / 1024).toFixed(1) + ' MB';
        return kb + ' KB';
    }
    function trimPct(n) {
        var s = (Number(n) || 0).toFixed(1);
        return s.replace(/\.0$/, '');
    }
    function gaugeCls(pct, warn, crit) {
        if (pct >= crit) return 'layui-bg-red';
        if (pct >= warn) return 'layui-bg-orange';
        return '';
    }
    function setBar(filter, pct, warn, crit) {
        var $bar = $('[lay-filter="' + filter + '"] .layui-progress-bar');
        $bar.removeClass('layui-bg-red layui-bg-orange').addClass(gaugeCls(pct, warn, crit));
        element.progress(filter, trimPct(pct) + '%');
    }

    function applyInfo(res) {
        var memTotal = Number(res.mem_total_kb) || 0;
        var memAvail = Number(res.mem_available_kb) || 0;
        var memUsed = Number(res.mem_used_kb);
        if (!memUsed && memTotal) memUsed = Math.max(0, memTotal - memAvail);
        var memPct = Number(res.mem_used_pct);
        if (!memPct && memTotal) memPct = 100 * memUsed / memTotal;
        if (memTotal) {
            $('#memText').text(fmtKb(memUsed) + ' / ' + fmtKb(memTotal) + ' · 可用 ' + fmtKb(memAvail) + ' · ' + trimPct(memPct) + '%');
            setBar('memBar', memPct, 85, 95);
        }

        var swapTotal = Number(res.swap_total_kb) || 0;
        var swapUsed = Number(res.swap_used_kb) || 0;
        var swapPct = Number(res.swap_used_pct);
        if (!swapPct && swapTotal) swapPct = 100 * swapUsed / swapTotal;
        if (swapTotal > 0) {
            $('#swapRow').show();
            $('#swapText').text(fmtKb(swapUsed) + ' / ' + fmtKb(swapTotal) + ' · ' + trimPct(swapPct) + '%');
            setBar('swapBar', swapPct, 50, 80);
        } else {
            $('#swapRow').hide();
        }

        var cpuPct = Number(res.cpu_usage_pct) || 0;
        var cores = Number(res.cpu_cores) || 0;
        var loadavg = res.loadavg || '-';
        var load1 = Number(res.load_1);
        if (!load1 && loadavg !== '-') load1 = parseFloat(String(loadavg).split(' ')[0]) || 0;
        var loadRatio = cores > 0 ? load1 / cores : load1;
        $('#cpuText').text((cpuPct ? trimPct(cpuPct) + '% · ' : '') + '负载 ' + loadavg + (cores ? ' · ' + cores + ' 核' : ''));
        setBar('cpuBar', cpuPct, 85, 95);
        $('#loadText').html('负载 1/5/15：<span class="mono' + (loadRatio >= 1.5 ? ' warn-text' : '') + '">' + loadavg + '</span>'
            + (cores ? ' · 每核 1 分钟 ' + loadRatio.toFixed(2) : ''));
        $('#statCpuPct').text(res.cpu_usage_pct != null ? trimPct(cpuPct) + '%' : (cores || '-'));
        $('#statCpuLabel').text('CPU' + (cores ? ' · ' + cores + ' 核' : '') + ' · ' + (res.hostname || ''));

        if (res.hostname) $('#hostName').text(res.hostname);
        if (res.os) $('#hostOs').text(res.os);
        if (res.kernel) $('#hostKernel').text(res.kernel);
        if (res.uptime) $('#hostUptime').text(res.uptime);

        var disks = res.disk || [];
        var $tbody = $('#diskBody').empty();
        disks.forEach(function (d, i) {
            var dp = Number(d.use_pct) || 0;
            var filter = 'diskBar' + i;
            $tbody.append(
                '<tr><td class="mono"></td><td></td><td></td><td></td><td>'
                + '<div class="layui-progress" lay-filter="' + filter + '" lay-showpercent="true" style="width:120px">'
                + '<div class="layui-progress-bar ' + gaugeCls(dp, 80, 90) + '" lay-percent="' + parseInt(dp, 10) + '%"></div>'
                + '</div></td></tr>'
            );
            $tbody.find('tr:last td:eq(0)').text(d.fs || '');
            $tbody.find('tr:last td:eq(1)').text(d.size || '');
            $tbody.find('tr:last td:eq(2)').text(d.used || '');
            $tbody.find('tr:last td:eq(3)').text(d.avail || '');
        });
        element.render('progress');
        disks.forEach(function (d, i) { setBar('diskBar' + i, Number(d.use_pct) || 0, 80, 90); });

        var top = res.top || [];
        var $top = $('#topTable tbody').empty();
        if (top.length) {
            top.forEach(function (p) {
                $top.append('<tr><td class="mono"></td><td class="mono"></td></tr>');
                $top.find('tr:last td:eq(0)').text(p.name || '');
                $top.find('tr:last td:eq(1)').text(p.rss_kb != null ? fmtKb(p.rss_kb) : '');
            });
            $('#topTable').show();
            $('#topLabel').show();
        } else {
            $('#topTable').hide();
            $('#topLabel').hide();
        }

        var map = { nginx: 'nginx', mysqld: 'mysql', 'php-fpm': 'phpfpm' };
        [74, 80, 81, 82, 83].forEach(function (v) { map['php' + v + '-php-fpm'] = 'php' + v + 'fpm'; });
        (res.services || []).forEach(function (s) {
            var key = map[s.unit];
            var $tr = $('#svcBody tr[data-name="' + key + '"]');
            var badge = s.status === 'active'
                ? '<span class="layui-badge layui-bg-green">运行中</span>'
                : (s.status === 'inactive' || s.status === 'dead')
                    ? '<span class="layui-badge">已停止</span>'
                    : '<span class="layui-badge layui-bg-orange"></span>';
            $tr.find('td:eq(1)').html(badge);
            if (s.status !== 'active' && s.status !== 'inactive' && s.status !== 'dead') {
                $tr.find('td:eq(1) .layui-badge').text(s.status);
            }
        });

        var now = new Date();
        var hh = ('0' + now.getHours()).slice(-2);
        var mm = ('0' + now.getMinutes()).slice(-2);
        var ss = ('0' + now.getSeconds()).slice(-2);
        $('#monUpdated').text('更新于 ' + hh + ':' + mm + ':' + ss + ' · 每 15 秒自动刷新');
    }

    var refreshTimer = null;
    function refreshSys(showErr) {
        fetch('/sys/info', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) {
                    if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
                    if (showErr) layer.msg('未登录或会话已过期', { icon: 2 });
                    return null;
                }
                return r.json();
            })
            .then(function (res) {
                if (!res) return;
                if (!res.ok) {
                    if (showErr) layer.msg(res.error || '获取失败', { icon: 2 });
                    return;
                }
                applyInfo(res);
            })
            .catch(function () {
                if (showErr) layer.msg('网络错误', { icon: 2 });
            });
    }
    $('#btnRefreshSvc').on('click', function () { refreshSys(true); });
    refreshTimer = setInterval(function () { refreshSys(false); }, 15000);

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
                setTimeout(function () { refreshSys(false); }, 1200);
            });
        });
    });
});
</script>
