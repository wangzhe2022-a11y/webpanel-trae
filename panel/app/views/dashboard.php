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
$rootDisk = null;
foreach (($info['disk'] ?? []) as $d) {
    if (($d['fs'] ?? '') === '/') {
        $rootDisk = $d;
        break;
    }
}
if ($rootDisk === null && !empty($info['disk'][0]) && is_array($info['disk'][0])) {
    $rootDisk = $info['disk'][0];
}
$storagePct = (float) (is_array($rootDisk) ? ($rootDisk['use_pct'] ?? 0) : 0);
$cpuRing = max(0.0, min(100.0, $cpuPct));
$memRing = max(0.0, min(100.0, $memPct));
?>
<div class="wp-dash-clock-row">
    <div class="panel-card wp-dash-clock" id="wpDashClock">
        <div class="wp-clock-time" id="wpDashClockTime">--:--:--</div>
        <div class="wp-clock-date" id="wpDashClockDate"></div>
        <div class="wp-clock-tz">本地时间</div>
    </div>
</div>
<div class="layui-row layui-col-space15 wp-stat-row">
    <div class="layui-col-sm6 layui-col-md6">
        <div class="stat-card c-blue">
            <span class="layui-icon layui-icon-website"></span>
            <div class="num"><?= (int) $stats['sites'] ?></div><div class="label">托管网站</div>
        </div>
    </div>
    <div class="layui-col-sm6 layui-col-md6">
        <div class="stat-card c-green">
            <span class="layui-icon layui-icon-table"></span>
            <div class="num"><?= (int) $stats['databases'] ?></div><div class="label">MySQL 数据库</div>
        </div>
    </div>
    <div class="layui-col-sm6 layui-col-md6">
        <div class="stat-card c-orange">
            <span class="layui-icon layui-icon-auz"></span>
            <div class="num"><?= (int) $stats['ssl'] ?></div><div class="label">已启用 HTTPS</div>
        </div>
    </div>
    <div class="layui-col-sm6 layui-col-md6">
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
            <div class="wp-perf" id="wpPerf">
                <div class="wp-ring-wrap">
                    <div class="wp-ring<?= $cpuRing >= 95 ? ' is-crit' : ($cpuRing >= 85 ? ' is-warn' : '') ?>" id="ringCpu">
                        <svg viewBox="0 0 36 36" aria-hidden="true">
                            <circle class="wp-ring-track" cx="18" cy="18" r="15.9155"></circle>
                            <circle class="wp-ring-value" cx="18" cy="18" r="15.9155"
                                stroke-dasharray="<?= e(rtrim(rtrim(number_format($cpuRing, 1, '.', ''), '0'), '.') ?: '0') ?> 100"></circle>
                        </svg>
                        <div class="wp-ring-center">
                            <span class="wp-ring-num" id="ringCpuNum"><?= e(rtrim(rtrim(number_format($cpuRing, 1, '.', ''), '0'), '.') ?: '0') ?>%</span>
                        </div>
                    </div>
                    <div class="wp-ring-label">CPU</div>
                    <div class="wp-ring-sub mono" id="ringCpuSub"><?= e($loadavg) ?><?= $cpuCores ? ' · ' . $cpuCores . ' 核' : '' ?></div>
                </div>
                <div class="wp-ring-wrap">
                    <div class="wp-ring<?= $memRing >= 95 ? ' is-crit' : ($memRing >= 85 ? ' is-warn' : '') ?>" id="ringMem">
                        <svg viewBox="0 0 36 36" aria-hidden="true">
                            <circle class="wp-ring-track" cx="18" cy="18" r="15.9155"></circle>
                            <circle class="wp-ring-value" cx="18" cy="18" r="15.9155"
                                stroke-dasharray="<?= e(rtrim(rtrim(number_format($memRing, 1, '.', ''), '0'), '.') ?: '0') ?> 100"></circle>
                        </svg>
                        <div class="wp-ring-center">
                            <span class="wp-ring-num" id="ringMemNum"><?= e(rtrim(rtrim(number_format($memRing, 1, '.', ''), '0'), '.') ?: '0') ?>%</span>
                        </div>
                    </div>
                    <div class="wp-ring-label">RAM</div>
                    <div class="wp-ring-sub mono" id="ringMemSub"><?= $memTotal ? e(format_bytes($memUsed) . ' / ' . format_bytes($memTotal)) : '-' ?></div>
                </div>
            </div>
            <div class="wp-host-meta">
                <div class="wp-host-line"><span>主机</span><b id="hostName"><?= e((string) ($info['hostname'] ?? '-')) ?></b></div>
                <div class="wp-host-line"><span>系统</span><b id="hostOs"><?= e((string) ($info['os'] ?? '-')) ?></b></div>
                <div class="wp-host-line"><span>内核</span><b class="mono" id="hostKernel"><?= e((string) ($info['kernel'] ?? '-')) ?></b></div>
                <div class="wp-host-line"><span>运行</span><b id="hostUptime"><?= e((string) ($info['uptime'] ?? '-')) ?></b></div>
                <div class="wp-host-line" style="grid-column:1 / -1">
                    <span>负载</span>
                    <span id="loadText">
                        1/5/15：<span class="mono <?= $loadRatio >= 1.5 ? 'warn-text' : '' ?>"><?= e($loadavg) ?></span>
                        <?php if ($cpuCores): ?> · 每核 1 分钟 <?= e(number_format($loadRatio, 2)) ?><?php endif; ?>
                    </span>
                    <span id="cpuText" hidden><?= e(($cpuPct > 0 ? rtrim(rtrim(number_format($cpuPct, 1, '.', ''), '0'), '.') . '% · ' : '') . '负载 ' . $loadavg . ($cpuCores ? ' · ' . $cpuCores . ' 核' : '')) ?></span>
                    <span id="memText" hidden><?= $memTotal ? e(format_bytes($memUsed) . ' / ' . format_bytes($memTotal) . ' · 可用 ' . format_bytes($memAvail) . ' · ' . rtrim(rtrim(number_format($memPct, 1, '.', ''), '0'), '.') . '%') : '-' ?></span>
                </div>
            </div>
            <div class="mon-row" id="swapRow" <?= $swapTotal > 0 ? '' : 'style="display:none"' ?>>
                <div class="mon-k">交换分区 <span class="right mono" id="swapText">
                    <?= $swapTotal ? e(format_bytes($swapUsed) . ' / ' . format_bytes($swapTotal) . ' · ' . rtrim(rtrim(number_format($swapPct, 1, '.', ''), '0'), '.') . '%') : '' ?>
                </span></div>
                <div class="layui-progress" lay-filter="swapBar">
                    <div class="layui-progress-bar <?= e(panel_gauge_class($swapPct, 50, 80)) ?>" lay-percent="<?= e((string) $swapPct) ?>%"></div>
                </div>
            </div>
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
            <div class="wp-storage" id="wpStorage">
                <div class="wp-storage-head">
                    <span class="wp-storage-state" id="storageState"><?= ($storagePct >= 90 ? '紧张' : ($storagePct >= 80 ? '注意' : '正常')) . ' · ' . (int) $storagePct . '%' ?></span>
                    <span class="wp-storage-meta mono" id="storageMeta"><?php
                        if ($rootDisk) {
                            echo e('已用 ' . (string) ($rootDisk['used'] ?? '') . ' / 总量 ' . (string) ($rootDisk['size'] ?? ''));
                        } else {
                            echo '暂无磁盘数据';
                        }
                    ?></span>
                </div>
                <div class="layui-progress layui-progress-big" lay-filter="storageBar">
                    <div class="layui-progress-bar <?= e(panel_gauge_class($storagePct, 80, 90)) ?>" lay-percent="<?= (int) $storagePct ?>%"></div>
                </div>
            </div>
            <div id="diskMounts">
                <?php if (empty($info['disk'])): ?>
                    <div class="mon-meta">暂无磁盘数据</div>
                <?php endif; ?>
                <?php foreach (($info['disk'] ?? []) as $i => $d): ?>
                    <?php $dp = (float) ($d['use_pct'] ?? 0); ?>
                    <div class="wp-disk-row">
                        <div class="wp-disk-head">
                            <span class="mono wp-disk-fs"><?= e((string) ($d['fs'] ?? '')) ?></span>
                            <span class="wp-disk-state"><?= ($dp >= 90 ? '紧张' : ($dp >= 80 ? '注意' : '正常')) . ' · ' . (int) $dp . '%' ?></span>
                            <span class="mono wp-disk-meta"><?= e('已用 ' . (string) ($d['used'] ?? '') . ' / 总量 ' . (string) ($d['size'] ?? '') . ' · 可用 ' . (string) ($d['avail'] ?? '')) ?></span>
                        </div>
                        <div class="layui-progress layui-progress-big" lay-filter="diskBar<?= (int) $i ?>">
                            <div class="layui-progress-bar <?= e(panel_gauge_class($dp, 80, 90)) ?>" lay-percent="<?= (int) $dp ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
    function setRing(id, pct, warn, crit) {
        pct = Math.max(0, Math.min(100, Number(pct) || 0));
        var $el = $('#' + id);
        $el.removeClass('is-warn is-crit');
        if (pct >= crit) $el.addClass('is-crit');
        else if (pct >= warn) $el.addClass('is-warn');
        $el.find('.wp-ring-value').attr('stroke-dasharray', trimPct(pct) + ' 100');
        $el.find('.wp-ring-num').text(trimPct(pct) + '%');
    }
    function applyStorage(disks) {
        disks = disks || [];
        var d = disks[0] || null;
        disks.forEach(function (row) {
            if (row && row.fs === '/') d = row;
        });
        if (!d) {
            $('#storageMeta').text('暂无磁盘数据');
            $('#storageState').text('—');
            return;
        }
        var pct = Number(d.use_pct) || 0;
        $('#storageMeta').text('已用 ' + (d.used || '') + ' / 总量 ' + (d.size || ''));
        $('#storageState').text((pct >= 90 ? '紧张' : (pct >= 80 ? '注意' : '正常')) + ' · ' + parseInt(pct, 10) + '%');
        setBar('storageBar', pct, 80, 90);
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
        setRing('ringCpu', cpuPct, 85, 95);
        $('#ringCpuSub').text(loadavg + (cores ? ' · ' + cores + ' 核' : ''));
        setRing('ringMem', memPct, 85, 95);
        $('#ringMemSub').text(memTotal ? (fmtKb(memUsed) + ' / ' + fmtKb(memTotal)) : '-');

        if (res.hostname) $('#hostName').text(res.hostname);
        if (res.os) $('#hostOs').text(res.os);
        if (res.kernel) $('#hostKernel').text(res.kernel);
        if (res.uptime) $('#hostUptime').text(res.uptime);

        var disks = res.disk || [];
        var $mounts = $('#diskMounts').empty();
        if (!disks.length) {
            $mounts.append('<div class="mon-meta">暂无磁盘数据</div>');
        }
        disks.forEach(function (d, i) {
            var dp = Number(d.use_pct) || 0;
            var filter = 'diskBar' + i;
            var state = (dp >= 90 ? '紧张' : (dp >= 80 ? '注意' : '正常')) + ' · ' + parseInt(dp, 10) + '%';
            var $row = $('<div class="wp-disk-row">');
            $row.append(
                '<div class="wp-disk-head">'
                + '<span class="mono wp-disk-fs"></span>'
                + '<span class="wp-disk-state"></span>'
                + '<span class="mono wp-disk-meta"></span>'
                + '</div>'
                + '<div class="layui-progress layui-progress-big" lay-filter="' + filter + '">'
                + '<div class="layui-progress-bar ' + gaugeCls(dp, 80, 90) + '" lay-percent="' + parseInt(dp, 10) + '%"></div>'
                + '</div>'
            );
            $row.find('.wp-disk-fs').text(d.fs || '');
            $row.find('.wp-disk-state').text(state);
            $row.find('.wp-disk-meta').text('已用 ' + (d.used || '') + ' / 总量 ' + (d.size || '') + ' · 可用 ' + (d.avail || ''));
            $mounts.append($row);
        });
        element.render('progress');
        disks.forEach(function (d, i) { setBar('diskBar' + i, Number(d.use_pct) || 0, 80, 90); });
        applyStorage(disks);

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

<div class="layui-row layui-col-space15 dash-atop-row">
    <div class="layui-col-md6">
        <div class="panel-card" id="atopCard">
            <h3>
                atop 历史
                <span class="mon-updated" id="atopUpdated">从 /var/log/atop 读取 · 不替代上方实时监控</span>
            </h3>
            <div id="atopStatus" class="atop-empty">正在检查 atop 服务与日志…</div>
            <div class="atop-toolbar" id="atopToolbar" style="display:none">
                <label>日志
                    <select id="atopFile" class="layui-input" style="display:inline-block;width:170px;padding:0 8px"></select>
                </label>
                <label>采样时间
                    <select id="atopTime" class="layui-input" style="display:inline-block;width:110px;padding:0 8px"></select>
                </label>
                <label>最近 N 条
                    <select id="atopLatest" class="layui-input" style="display:inline-block;width:70px;padding:0 8px">
                        <option value="1">1</option>
                        <option value="3" selected>3</option>
                        <option value="6">6</option>
                        <option value="12">12</option>
                    </select>
                </label>
                <button class="layui-btn layui-btn-sm layui-btn-normal" id="btnAtopLoad">查看</button>
                <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnAtopRefresh">
                    <span class="layui-icon layui-icon-refresh"></span> 刷新
                </button>
            </div>
            <div id="atopErr" class="atop-empty" style="display:none"></div>
            <div id="atopBody" style="display:none">
                <div class="mon-meta" style="margin:0 0 8px">日志文件</div>
                <table class="layui-table atop-logs" lay-skin="line" style="margin:0 0 14px">
                    <thead><tr><th>文件</th><th>大小</th><th>修改时间</th></tr></thead>
                    <tbody id="atopLogs"></tbody>
                </table>
                <div id="atopRecentWrap" style="display:none">
                    <div class="mon-meta" style="margin:0 0 8px">最近采样</div>
                    <table class="layui-table atop-logs" lay-skin="line" style="margin:0 0 14px">
                        <thead><tr><th>时间</th><th>CPU</th><th>内存</th><th>交换</th><th>负载</th><th>磁盘忙</th></tr></thead>
                        <tbody id="atopRecent"></tbody>
                    </table>
                </div>
                <div class="mon-meta" id="atopSampleLabel" style="margin:0 0 10px">采样详情</div>
                <div class="mon-row">
                    <div class="mon-k">CPU <span class="right mono" id="atopCpuText">-</span></div>
                    <div class="layui-progress" lay-filter="atopCpuBar">
                        <div class="layui-progress-bar" lay-percent="0%"></div>
                    </div>
                </div>
                <div class="mon-row">
                    <div class="mon-k">内存 <span class="right mono" id="atopMemText">-</span></div>
                    <div class="layui-progress" lay-filter="atopMemBar">
                        <div class="layui-progress-bar" lay-percent="0%"></div>
                    </div>
                </div>
                <div class="mon-row" id="atopSwapRow" style="display:none">
                    <div class="mon-k">交换分区 <span class="right mono" id="atopSwapText">-</span></div>
                    <div class="layui-progress" lay-filter="atopSwapBar">
                        <div class="layui-progress-bar" lay-percent="0%"></div>
                    </div>
                </div>
                <div class="mon-row">
                    <div class="mon-k">负载 / 磁盘 <span class="right mono" id="atopLoadText">-</span></div>
                    <div class="layui-progress" lay-filter="atopDiskBar">
                        <div class="layui-progress-bar" lay-percent="0%"></div>
                    </div>
                    <div class="mon-meta" id="atopDiskText"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="panel-card" id="atopProcCard">
            <h3>
                占用最高进程
                <span class="mon-updated" id="atopProcUpdated">所选采样的进程快照</span>
            </h3>
            <div class="mon-meta">CPU 占用最高</div>
            <table class="atop-proc" id="atopTopCpu">
                <thead><tr><th>PID</th><th>名称</th><th>CPU</th><th>内存</th><th>磁盘</th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="mon-meta" style="margin-top:16px">内存占用最高</div>
            <table class="atop-proc" id="atopTopMem">
                <thead><tr><th>PID</th><th>名称</th><th>CPU</th><th>内存</th><th>磁盘</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
layui.use(['element', 'layer'], function () {
    var layer = layui.layer, $ = layui.$, element = layui.element;

    function fmtBytes(n) {
        n = Number(n) || 0;
        if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GB';
        if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
        if (n >= 1024) return (n / 1024).toFixed(1) + ' KB';
        return n + ' B';
    }
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
    function badge(cls, text) {
        return $('<span>').addClass('atop-badge ' + cls).text(text)[0].outerHTML;
    }
    function diskBusy(sample) {
        var disks = (sample && sample.disk) || [];
        var max = 0, parts = [];
        disks.forEach(function (d) {
            var p = Number(d.busy_pct) || 0;
            if (p > max) max = p;
            parts.push((d.name || '?') + ' ' + trimPct(p) + '%');
        });
        return { max: max, text: parts.join(' · ') || '-' };
    }
    function asProcRows(rows) {
        if (!rows) return [];
        if (typeof rows === 'string') {
            try { rows = JSON.parse(rows); } catch (e) { return []; }
        }
        return Array.isArray(rows) ? rows : [];
    }
    function fillProc($tb, rows, err) {
        $tb.empty();
        rows = asProcRows(rows);
        if (!rows.length) {
            var msg = err ? String(err) : '该采样没有进程记录';
            $tb.append('<tr><td class="atop-empty" colspan="5"></td></tr>');
            $tb.find('td').text(msg);
            return;
        }
        rows.forEach(function (p) {
            $tb.append('<tr><td class="mono"></td><td class="mono"></td><td class="mono"></td><td class="mono"></td><td class="mono"></td></tr>');
            var $td = $tb.find('tr:last td');
            $td.eq(0).text(p.pid != null ? p.pid : '');
            $td.eq(1).text(p.name || '');
            $td.eq(2).text(trimPct(p.cpu_pct) + '%');
            $td.eq(3).text(p.rss_kb != null ? fmtKb(p.rss_kb) : '-');
            $td.eq(4).text(p.disk_kb != null ? fmtKb(p.disk_kb) : '-');
        });
    }

    var filling = false;

    function applyAtop(res) {
        var html = '';
        if (res.installed) {
            html += badge('ok', '已安装' + (res.version ? ' ' + res.version : ''));
        } else {
            html += badge('err', '未安装 atop');
        }
        if (res.service === 'active') html += badge('ok', '服务运行中');
        else if (res.service) html += badge('warn', '服务 ' + res.service);
        if (res.enabled === 'enabled') html += badge('mute', '开机自启');
        if (res.last_log_mtime) html += badge('mute', '最近日志 ' + res.last_log_mtime);
        if (res.interval_s) html += badge('mute', '间隔 ' + res.interval_s + 's');
        if (res.log_path) html += '<span class="mono" style="color:#888;font-size:12px">' + $('<div>').text(res.log_path).html() + '</span>';
        $('#atopStatus').html(html || '无状态');

        filling = true;
        var $file = $('#atopFile').empty();
        (res.logs || []).forEach(function (l) {
            $file.append($('<option>').val(l.name).text(l.name));
        });
        if (res.file) $file.val(res.file);
        var $time = $('#atopTime').empty();
        $time.append($('<option>').val('latest').text('最新一条'));
        (res.times || []).forEach(function (t) {
            $time.append($('<option>').val(t).text(t));
        });
        if (res.time) $time.val(res.time);
        else $time.val('latest');
        filling = false;
        $('#atopToolbar').toggle(!!res.installed);

        if (res.error) {
            $('#atopErr').text(res.error).show();
        } else {
            $('#atopErr').hide().text('');
        }

        var $logs = $('#atopLogs').empty();
        (res.logs || []).forEach(function (l) {
            $logs.append('<tr><td class="mono"></td><td class="mono"></td><td class="mono"></td></tr>');
            var $td = $logs.find('tr:last td');
            $td.eq(0).text(l.name || '');
            $td.eq(1).text(l.size != null ? fmtBytes(l.size) : '-');
            $td.eq(2).text(l.mtime || '');
        });

        var recent = res.recent || [];
        if (recent.length > 1) {
            var $rb = $('#atopRecent').empty();
            recent.forEach(function (s) {
                var db = diskBusy(s);
                $rb.append('<tr><td class="mono"></td><td></td><td></td><td></td><td class="mono"></td><td></td></tr>');
                var $td = $rb.find('tr:last td');
                $td.eq(0).text(s.time || '');
                $td.eq(1).text(trimPct(s.cpu_busy_pct) + '%');
                $td.eq(2).text(trimPct(s.mem_used_pct) + '%');
                $td.eq(3).text(trimPct(s.swap_used_pct) + '%');
                $td.eq(4).text(s.loadavg || '-');
                $td.eq(5).text(db.text);
            });
            $('#atopRecentWrap').show();
        } else {
            $('#atopRecentWrap').hide();
        }

        var s = res.sample;
        if (!s) {
            $('#atopBody').toggle(!!(res.logs && res.logs.length));
            $('#atopCpuText,#atopMemText,#atopSwapText,#atopLoadText,#atopDiskText').text('-');
            fillProc($('#atopTopCpu tbody'), [], res.proc_error);
            fillProc($('#atopTopMem tbody'), [], res.proc_error);
            $('#atopProcUpdated').text('所选采样的进程快照');
            return;
        }

        $('#atopSampleLabel').text('采样详情 · ' + (res.file || '') + ' · ' + (s.time || '') + (s.interval_s ? ' · 间隔 ' + s.interval_s + 's' : ''));
        $('#atopProcUpdated').text((s.time || '所选采样') + (res.file ? ' · ' + res.file : '') + (s.interval_s ? ' · 间隔 ' + s.interval_s + 's' : ''));
        $('#atopCpuText').text(trimPct(s.cpu_busy_pct) + '%（user ' + trimPct(s.cpu_user_pct) + '% / sys ' + trimPct(s.cpu_sys_pct) + '% / wait ' + trimPct(s.cpu_wait_pct) + '%）' + (s.nrcpu ? ' · ' + s.nrcpu + ' 核' : ''));
        setBar('atopCpuBar', Number(s.cpu_busy_pct) || 0, 85, 95);
        $('#atopMemText').text(fmtKb(s.mem_used_kb) + ' / ' + fmtKb(s.mem_total_kb) + ' · 可用 ' + fmtKb(s.mem_avail_kb) + ' · ' + trimPct(s.mem_used_pct) + '%');
        setBar('atopMemBar', Number(s.mem_used_pct) || 0, 85, 95);
        if (Number(s.swap_total_kb) > 0) {
            $('#atopSwapRow').show();
            $('#atopSwapText').text(fmtKb(s.swap_used_kb) + ' / ' + fmtKb(s.swap_total_kb) + ' · ' + trimPct(s.swap_used_pct) + '%');
            setBar('atopSwapBar', Number(s.swap_used_pct) || 0, 50, 80);
        } else {
            $('#atopSwapRow').hide();
        }
        var db = diskBusy(s);
        $('#atopLoadText').text('负载 ' + (s.loadavg || '-') + ' · 磁盘忙 ' + trimPct(db.max) + '%');
        setBar('atopDiskBar', db.max, 80, 90);
        $('#atopDiskText').text(db.text);
        fillProc($('#atopTopCpu tbody'), res.top_cpu, res.proc_error);
        fillProc($('#atopTopMem tbody'), res.top_mem, res.proc_error);
        $('#atopBody').show();
        element.render('progress');
        setBar('atopCpuBar', Number(s.cpu_busy_pct) || 0, 85, 95);
        setBar('atopMemBar', Number(s.mem_used_pct) || 0, 85, 95);
        if (Number(s.swap_total_kb) > 0) setBar('atopSwapBar', Number(s.swap_used_pct) || 0, 50, 80);
        setBar('atopDiskBar', db.max, 80, 90);
    }

    function loadAtop(showErr) {
        var q = new URLSearchParams();
        var f = $('#atopFile').val();
        var t = $('#atopTime').val();
        var n = $('#atopLatest').val() || '3';
        if (f) q.set('file', f);
        if (t) q.set('time', t);
        q.set('latest', n);
        fetch('/sys/atop?' + q.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) {
                    if (showErr) layer.msg('未登录或会话已过期', { icon: 2 });
                    return null;
                }
                return r.json();
            })
            .then(function (res) {
                if (!res) return;
                if (!res.ok && res.error) {
                    $('#atopStatus').html(badge('err', res.error));
                    if (showErr) layer.msg(res.error, { icon: 2 });
                    return;
                }
                applyAtop(res);
            })
            .catch(function () {
                if (showErr) layer.msg('网络错误', { icon: 2 });
                $('#atopStatus').html(badge('err', '无法连接 /sys/atop'));
            });
    }

    $('#btnAtopLoad, #btnAtopRefresh').on('click', function () { loadAtop(true); });
    $('#atopFile').on('change', function () {
        if (filling) return;
        $('#atopTime').val('latest');
        loadAtop(false);
    });
    $('#atopTime, #atopLatest').on('change', function () {
        if (filling) return;
        loadAtop(false);
    });
    loadAtop(false);
});
</script>

<div class="layui-row layui-col-space15 dash-login-row">
    <div class="layui-col-md6">
        <div class="panel-card">
            <h3>
                最近登录
                <span class="mon-updated">最近 10 条登录记录</span>
            </h3>
            <table class="layui-table" style="margin:0">
                <thead><tr><th>用户</th><th>IP 地址</th><th>登录时间</th></tr></thead>
                <tbody>
                <?php if (empty($recentLogins)): ?>
                    <tr><td colspan="3" style="text-align:center;color:#999">暂无登录记录</td></tr>
                <?php else: ?>
                    <?php foreach ($recentLogins as $log): ?>
                        <tr>
                            <td><?= e($log['actor'] ?? '-') ?></td>
                            <td class="mono"><?= e($log['ip'] ?? '-') ?></td>
                            <td class="mono"><?= e($log['ts'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="panel-card">
            <h3>
                SSH / 系统登录
                <span class="mon-updated">SSH 认证成功记录 · 最近 10 条</span>
            </h3>
            <table class="layui-table" style="margin:0">
                <thead><tr><th>用户</th><th>来源 IP</th><th style="width:80px">认证</th><th>登录时间</th></tr></thead>
                <tbody>
                <?php if (empty($sshLogins)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#999">暂无 SSH 登录记录</td></tr>
                <?php else: ?>
                    <?php foreach ($sshLogins as $sl): ?>
                        <tr>
                            <td><?= e($sl['user'] ?? '-') ?></td>
                            <td class="mono"><?= e($sl['ip'] ?? '-') ?></td>
                            <td>
                                <?php if (($sl['method'] ?? '') === 'publickey'): ?>
                                    <span class="layui-badge layui-bg-blue">密钥</span>
                                <?php elseif (($sl['method'] ?? '') === 'password'): ?>
                                    <span class="layui-badge layui-bg-orange">密码</span>
                                <?php else: ?>
                                    <?= e($sl['method'] ?? '-') ?>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= e($sl['time'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
