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
$cpuRing = max(0.0, min(100.0, $cpuPct));
$memRing = max(0.0, min(100.0, $memPct));
$swapRing = max(0.0, min(100.0, $swapPct));
$diskMounts = is_array($info['disk'] ?? null) ? $info['disk'] : [];
$pctLabel = static function (float $pct): string {
    return rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.') ?: '0';
};
$ringClass = static function (float $pct, float $warn, float $crit): string {
    if ($pct >= $crit) {
        return ' is-crit';
    }
    if ($pct >= $warn) {
        return ' is-warn';
    }
    return '';
};
$storeState = static function (float $pct, float $warn, float $crit): array {
    if ($pct >= $crit) {
        return ['Critical', ' is-crit'];
    }
    if ($pct >= $warn) {
        return ['Attention', ' is-warn'];
    }
    return ['Healthy', ''];
};
$driveIcon = '<svg class="wp-drive-svg" viewBox="0 0 48 40" aria-hidden="true">'
    . '<rect class="wp-drive-case" x="4" y="8" width="40" height="24" rx="6"></rect>'
    . '<rect class="wp-drive-lid" x="4" y="8" width="40" height="8" rx="4"></rect>'
    . '<circle class="wp-drive-led" cx="12" cy="26" r="2.2"></circle>'
    . '</svg>';
$renderStoreCard = static function (
    string $id,
    string $title,
    string $used,
    string $total,
    float $pct,
    float $warn,
    float $crit
) use ($driveIcon, $storeState, $pctLabel): string {
    $pct = max(0.0, min(100.0, $pct));
    [$label, $cls] = $storeState($pct, $warn, $crit);
    $w = $pctLabel($pct);
    return '<article class="wp-store-card' . e($cls) . '" data-store="' . e($id) . '">'
        . '<div class="wp-store-head"><span class="wp-store-title">' . e($title) . '</span></div>'
        . '<div class="wp-store-body">' . $driveIcon
        . '<div class="wp-store-info">'
        . '<div class="wp-store-state">' . e($label) . '</div>'
        . '<div class="wp-store-used">Used: ' . e($used !== '' ? $used : '—') . '</div>'
        . '<div class="wp-store-total">Total: ' . e($total !== '' ? $total : '—') . '</div>'
        . '</div></div>'
        . '<div class="wp-store-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'
        . e($w) . '" aria-label="' . e($title) . ' ' . e($w) . '%">'
        . '<div class="wp-store-bar-fill" style="width:' . e($w) . '%"></div></div>'
        . '</article>';
};
?>
<link rel="stylesheet" href="/static/vendor/gridstack/gridstack.min.css">
<div class="wp-dash-edit-bar">
    <span class="wp-dash-edit-hint" id="dashEditHint">拖拽卡片可自由排列布局</span>
    <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnDashEdit">
        <span class="layui-icon layui-icon-edit"></span> 编辑布局
    </button>
    <button class="layui-btn layui-btn-sm layui-btn-normal" id="btnDashReset" style="display:none">
        <span class="layui-icon layui-icon-refresh"></span> 重置
    </button>
    <button class="layui-btn layui-btn-sm" id="btnDashDone" style="display:none">
        <span class="layui-icon layui-icon-ok"></span> 完成
    </button>
</div>
<div class="wp-dashboard-grid" id="dashGrid">
    <div class="wp-dash-widget grid-stack-item" data-widget="sys-status" gs-x="0" gs-w="4" gs-min-w="3" gs-size-to-content="true">
        <div class="grid-stack-item-content">
        <div class="panel-card wp-sys-status" id="wpSysStatus">
            <h3>System Status</h3>
            <div class="wp-sys-status-body">
                <div class="wp-dash-clock" id="wpDashClock">
                    <div class="wp-clock-time" id="wpDashClockTime">--:--:--</div>
                    <div class="wp-clock-date" id="wpDashClockDate"></div>
                    <div class="wp-clock-tz">本地时间</div>
                </div>
                <div class="wp-perf wp-perf-row wp-sys-gauges" id="wpPerf">
                    <div class="wp-ring-wrap">
                        <div class="wp-ring<?= e($ringClass($cpuRing, 85, 95)) ?>" id="ringCpu">
                            <svg viewBox="0 0 36 36" aria-hidden="true">
                                <circle class="wp-ring-track" cx="18" cy="18" r="15.9155"></circle>
                                <circle class="wp-ring-value" cx="18" cy="18" r="15.9155"
                                    stroke-dasharray="<?= e($pctLabel($cpuRing)) ?> 100"></circle>
                            </svg>
                            <div class="wp-ring-center">
                                <span class="wp-ring-num" id="ringCpuNum"><?= e($pctLabel($cpuRing)) ?>%</span>
                            </div>
                        </div>
                        <div class="wp-ring-label">CPU</div>
                        <div class="wp-ring-sub mono" id="ringCpuSub"><?= e($loadavg) ?><?= $cpuCores ? ' · ' . $cpuCores . ' 核' : '' ?></div>
                    </div>
                    <div class="wp-ring-wrap">
                        <div class="wp-ring<?= e($ringClass($memRing, 85, 95)) ?>" id="ringMem">
                            <svg viewBox="0 0 36 36" aria-hidden="true">
                                <circle class="wp-ring-track" cx="18" cy="18" r="15.9155"></circle>
                                <circle class="wp-ring-value" cx="18" cy="18" r="15.9155"
                                    stroke-dasharray="<?= e($pctLabel($memRing)) ?> 100"></circle>
                            </svg>
                            <div class="wp-ring-center">
                                <span class="wp-ring-num" id="ringMemNum"><?= e($pctLabel($memRing)) ?>%</span>
                            </div>
                        </div>
                        <div class="wp-ring-label">RAM</div>
                        <div class="wp-ring-sub mono" id="ringMemSub"><?= $memTotal ? e(format_bytes($memUsed) . ' / ' . format_bytes($memTotal)) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <div class="wp-dash-widget grid-stack-item" data-widget="host-monitor" gs-x="4" gs-w="4" gs-min-w="3" gs-size-to-content="true">
        <div class="grid-stack-item-content">
        <div class="panel-card">
            <h3>
                主机监控
                <span class="mon-updated" id="monUpdated">每 15 秒自动刷新</span>
            </h3>
            <div class="wp-host-meta">
                <div class="wp-host-line"><span>主机</span><b id="hostName"><?= e((string) ($info['hostname'] ?? '-')) ?></b></div>
                <div class="wp-host-line"><span>内核</span><b class="mono" id="hostKernel"><?= e((string) ($info['kernel'] ?? '-')) ?></b></div>
                <div class="wp-host-line">
                    <span>负载</span>
                    <span id="loadText">
                        1/5/15：<span class="mono <?= $loadRatio >= 1.5 ? 'warn-text' : '' ?>"><?= e($loadavg) ?></span>
                        <?php if ($cpuCores): ?> · 每核 1 分钟 <?= e(number_format($loadRatio, 2)) ?><?php endif; ?>
                    </span>
                    <span id="cpuText" hidden><?= e(($cpuPct > 0 ? rtrim(rtrim(number_format($cpuPct, 1, '.', ''), '0'), '.') . '% · ' : '') . '负载 ' . $loadavg . ($cpuCores ? ' · ' . $cpuCores . ' 核' : '')) ?></span>
                    <span id="memText" hidden><?= $memTotal ? e(format_bytes($memUsed) . ' / ' . format_bytes($memTotal) . ' · 可用 ' . format_bytes($memAvail) . ' · ' . rtrim(rtrim(number_format($memPct, 1, '.', ''), '0'), '.') . '%') : '-' ?></span>
                </div>
                <div class="wp-host-line"><span>系统</span><b id="hostOs"><?= e((string) ($info['os'] ?? '-')) ?></b></div>
                <div class="wp-host-line"><span>运行</span><b id="hostUptime"><?= e((string) ($info['uptime'] ?? '-')) ?></b></div>
            </div>
            <div class="mon-meta" id="topLabel" <?= empty($info['top']) ? 'style="display:none"' : '' ?>>占用内存最多</div>
            <div class="wp-top-mem" id="topTable" <?= empty($info['top']) ? 'style="display:none"' : '' ?>>
                <?php foreach (array_slice($info['top'] ?? [], 0, 6) as $p): ?>
                    <div class="wp-top-mem-item">
                        <span class="wp-top-mem-name mono"><?= e((string) ($p['name'] ?? '')) ?></span>
                        <span class="wp-top-mem-rss mono"><?= isset($p['rss_kb']) ? e(format_bytes((int) $p['rss_kb'])) : '' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="wp-host-stats">
                <div class="stat-card c-blue">
                    <span class="layui-icon layui-icon-website"></span>
                    <div class="num"><?= (int) $stats['sites'] ?></div><div class="label">托管网站</div>
                </div>
                <div class="stat-card c-green">
                    <span class="layui-icon layui-icon-table"></span>
                    <div class="num"><?= (int) $stats['databases'] ?></div><div class="label">MySQL 数据库</div>
                </div>
                <div class="stat-card c-orange">
                    <span class="layui-icon layui-icon-auz"></span>
                    <div class="num"><?= (int) $stats['ssl'] ?></div><div class="label">已启用 HTTPS</div>
                </div>
                <div class="stat-card c-purple">
                    <span class="layui-icon layui-icon-cpu"></span>
                    <div class="num" id="statCpuPct"><?= isset($info['cpu_usage_pct']) ? e(rtrim(rtrim(number_format($cpuPct, 1, '.', ''), '0'), '.')) . '%' : e((string) ($cpuCores ?: '-')) ?></div>
                    <div class="label" id="statCpuLabel">CPU<?= $cpuCores ? ' · ' . $cpuCores . ' 核' : '' ?> · <?= e((string) ($info['hostname'] ?? '')) ?></div>
                </div>
            </div>
            <div class="wp-host-extra" id="hostExtra">
                <div class="wp-eq-tabs wp-host-tabs" role="tablist">
                    <button type="button" class="wp-host-tab" role="tab" id="hostTabAtop" data-tab="atop" aria-controls="atopCard" aria-selected="false" aria-expanded="false">atop 历史</button>
                    <button type="button" class="wp-host-tab" role="tab" id="hostTabProc" data-tab="proc" aria-controls="atopProcCard" aria-selected="false" aria-expanded="false">占用最高进程</button>
                </div>
                <div class="wp-host-tab-panels">
                    <div id="atopCard" class="wp-host-tab-panel" role="tabpanel" data-tab="atop" aria-labelledby="hostTabAtop" hidden>
                        <div class="wp-host-tab-head">
                            <span class="mon-updated" id="atopUpdated">从 /var/log/atop 读取 · 不替代上方实时监控</span>
                        </div>
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
                            <div class="wp-atop-sample">
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
                    <div id="atopProcCard" class="wp-host-tab-panel" role="tabpanel" data-tab="proc" aria-labelledby="hostTabProc" hidden>
                        <div class="wp-host-tab-head">
                            <span class="mon-updated" id="atopProcUpdated">所选采样的进程快照</span>
                        </div>
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
        </div>
        </div>
    </div>
    <div class="wp-dash-widget grid-stack-item" data-widget="svc-status" gs-x="8" gs-w="4" gs-min-w="4" gs-size-to-content="true">
        <div class="grid-stack-item-content">
        <div class="panel-card wp-svc-card">
            <h3>
                服务状态
                <button class="layui-btn layui-btn-sm layui-btn-primary" style="float:right" id="btnRefreshSvc">
                    <span class="layui-icon layui-icon-refresh"></span> 刷新
                </button>
            </h3>
            <table class="layui-table" style="margin:0">
                <thead><tr><th>服务</th><th>状态</th><th>操作</th></tr></thead>
                <tbody id="svcBody">
                <?php foreach (($info['services'] ?? []) as $s): ?>
                    <tr data-name="<?= e(str_replace(['postgresql-16','postgresql','nginx','mysqld','php-fpm','wp-node-'], ['postgres','postgres','nginx','mysql','phpfpm','node-'], str_replace(['php74-php-fpm','php80-php-fpm','php81-php-fpm','php82-php-fpm','php83-php-fpm'], ['php74fpm','php80fpm','php81fpm','php82fpm','php83fpm'], $s['unit']))) ?>">
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
    <div class="wp-dash-widget grid-stack-item" data-widget="disk" gs-x="0" gs-w="4" gs-min-w="3" gs-size-to-content="true">
        <div class="grid-stack-item-content">
        <div class="panel-card wp-disk-card">
            <h3>磁盘</h3>
            <div class="wp-storage-grid" id="diskMounts">
            <?= $renderStoreCard(
                'swap',
                '交换',
                $swapTotal ? format_bytes($swapUsed) : '0 KB',
                $swapTotal ? format_bytes($swapTotal) : '0 KB',
                $swapRing,
                50,
                80
            ) ?>
            <?php foreach ($diskMounts as $i => $d): ?>
                <?= $renderStoreCard(
                    'disk-' . (int) $i,
                    (string) (($d['fs'] ?? '') !== '' ? $d['fs'] : '磁盘'),
                    (string) ($d['used'] ?? ''),
                    (string) ($d['size'] ?? ''),
                    (float) ($d['use_pct'] ?? 0),
                    80,
                    90
                ) ?>
            <?php endforeach; ?>
            <?php if (empty($diskMounts) && $swapTotal <= 0): ?>
                <div class="mon-meta wp-disk-empty">暂无磁盘数据</div>
            <?php endif; ?>
            </div>
        </div>
        </div>
    </div>
    <div class="wp-dash-widget grid-stack-item" data-widget="login" gs-x="4" gs-w="8" gs-min-w="4" gs-size-to-content="true">
        <div class="grid-stack-item-content">
        <div class="panel-card wp-login-card">
            <div class="wp-host-extra is-open" id="loginExtra">
                <div class="wp-eq-tabs wp-host-tabs wp-login-tabs" role="tablist">
                    <button type="button" class="wp-host-tab is-active" role="tab" id="loginTabRecent" data-tab="recent" aria-controls="recentLoginCard" aria-selected="true" aria-expanded="true">最近登录</button>
                    <button type="button" class="wp-host-tab" role="tab" id="loginTabSsh" data-tab="ssh" aria-controls="sshLoginCard" aria-selected="false" aria-expanded="false">SSH / 系统登录</button>
                    <button type="button" class="wp-host-tab" role="tab" id="loginTabAccess" data-tab="access" aria-controls="accessCard" aria-selected="false" aria-expanded="false">面板访问 / 安全</button>
                </div>
                <div class="wp-host-tab-panels">
                    <div id="recentLoginCard" class="wp-host-tab-panel" role="tabpanel" data-tab="recent" aria-labelledby="loginTabRecent">
                        <div class="wp-host-tab-head">
                            <label class="wp-login-limit-wrap">
                                显示
                                <select id="recentLoginLimit" class="wp-login-limit" aria-label="最近登录显示条数">
                                    <option value="6" selected>6</option>
                                    <option value="12">12</option>
                                </select>
                            </label>
                            <span class="mon-updated" id="recentLoginHint">最近 6 条登录记录</span>
                        </div>
                        <table class="layui-table wp-login-table" style="margin:0">
                            <thead><tr><th>用户</th><th>IP 地址</th><th>登录时间</th></tr></thead>
                            <tbody id="recentLoginBody">
                            <?php if (empty($recentLogins)): ?>
                                <tr class="wp-login-empty"><td colspan="3" style="text-align:center;color:#999">暂无登录记录</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLogins as $i => $log): ?>
                                    <tr class="wp-login-row" data-i="<?= (int) $i ?>"<?= $i >= 6 ? ' hidden' : '' ?>>
                                        <td><?= e($log['actor'] ?? '-') ?></td>
                                        <td class="mono"><?= e($log['ip'] ?? '-') ?></td>
                                        <td class="mono"><?= e($log['ts'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="sshLoginCard" class="wp-host-tab-panel" role="tabpanel" data-tab="ssh" aria-labelledby="loginTabSsh" hidden>
                        <div class="wp-host-tab-head">
                            <label class="wp-login-limit-wrap">
                                显示
                                <select id="sshLoginLimit" class="wp-login-limit" aria-label="SSH 登录显示条数">
                                    <option value="6" selected>6</option>
                                    <option value="12">12</option>
                                </select>
                            </label>
                            <span class="mon-updated" id="sshLoginHint">SSH 认证成功 · 最近 6 条</span>
                        </div>
                        <table class="layui-table wp-login-table" style="margin:0">
                            <thead><tr><th>用户</th><th>来源 IP</th><th style="width:80px">认证</th><th>登录时间</th></tr></thead>
                            <tbody id="sshLoginBody">
                            <?php if (empty($sshLogins)): ?>
                                <tr class="wp-login-empty"><td colspan="4" style="text-align:center;color:#999">暂无 SSH 登录记录</td></tr>
                            <?php else: ?>
                                <?php foreach ($sshLogins as $i => $sl): ?>
                                    <tr class="wp-login-row" data-i="<?= (int) $i ?>"<?= $i >= 6 ? ' hidden' : '' ?>>
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
                    <div id="accessCard" class="wp-host-tab-panel" role="tabpanel" data-tab="access" aria-labelledby="loginTabAccess" hidden>
                        <div class="wp-host-tab-head">
                            <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnAccessRefresh">
                                <span class="layui-icon layui-icon-refresh"></span> 刷新
                            </button>
                            <span class="mon-updated" id="accessHint">点击「刷新」加载面板访问日志</span>
                        </div>
                        <div id="accessAlert" style="display:none;margin-bottom:12px"></div>
                        <div id="accessStats" class="wp-host-stats" style="margin-bottom:12px;display:none">
                            <div class="stat-card c-blue">
                                <span class="layui-icon layui-icon-log"></span>
                                <div class="num" id="accessTotal">0</div><div class="label">总请求数</div>
                            </div>
                            <div class="stat-card c-green">
                                <span class="layui-icon layui-icon-group"></span>
                                <div class="num" id="accessUnique">0</div><div class="label">独立 IP</div>
                            </div>
                            <div class="stat-card c-orange">
                                <span class="layui-icon layui-icon-auz"></span>
                                <div class="num" id="accessFailed">0</div><div class="label">登录失败 IP</div>
                            </div>
                            <div class="stat-card c-red">
                                <span class="layui-icon layui-icon-about"></span>
                                <div class="num" id="accessSusp">0</div><div class="label">可疑 IP</div>
                            </div>
                        </div>
                        <div class="mon-meta" style="margin:0 0 8px">可疑 / 攻击 IP</div>
                        <table class="layui-table wp-login-table" style="margin:0 0 14px">
                            <thead><tr><th>IP 地址</th><th>原因</th><th style="width:70px">次数</th><th style="width:60px">等级</th><th style="width:80px">操作</th></tr></thead>
                            <tbody id="suspiciousBody">
                                <tr class="wp-login-empty"><td colspan="5" style="text-align:center;color:#999">暂无可疑 IP</td></tr>
                            </tbody>
                        </table>
                        <div class="mon-meta" style="margin:0 0 8px">已封禁 IP</div>
                        <table class="layui-table wp-login-table" style="margin:0 0 14px">
                            <thead><tr><th>IP 地址</th><th style="width:80px">操作</th></tr></thead>
                            <tbody id="deniedBody">
                                <tr class="wp-login-empty"><td colspan="2" style="text-align:center;color:#999">暂无封禁 IP</td></tr>
                            </tbody>
                        </table>
                        <div class="mon-meta" style="margin:0 0 8px">最近访问记录</div>
                        <table class="layui-table wp-login-table" style="margin:0">
                            <thead><tr><th>时间</th><th>IP 地址</th><th>方法</th><th>路径</th><th>状态</th><th style="width:80px">操作</th></tr></thead>
                            <tbody id="accessBody">
                                <tr class="wp-login-empty"><td colspan="6" style="text-align:center;color:#999">暂无访问记录</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<script>
layui.use(['element', 'layer', 'table'], function () {
    var layer = layui.layer, $ = layui.$, element = layui.element;
    element.render('progress');

    function applyLoginLimit(bodyId, limit, hintId, hintTpl) {
        limit = Number(limit) === 12 ? 12 : 6;
        var $rows = $('#' + bodyId + ' > tr.wp-login-row');
        $rows.each(function () {
            var i = Number(this.getAttribute('data-i')) || 0;
            if (i < limit) this.removeAttribute('hidden');
            else this.setAttribute('hidden', 'hidden');
        });
        if (hintId && hintTpl) {
            $('#' + hintId).text(hintTpl.replace('%n', String(limit)));
        }
    }
    $('#recentLoginLimit').on('change', function () {
        applyLoginLimit('recentLoginBody', this.value, 'recentLoginHint', '最近 %n 条登录记录');
    });
    $('#sshLoginLimit').on('change', function () {
        applyLoginLimit('sshLoginBody', this.value, 'sshLoginHint', 'SSH 认证成功 · 最近 %n 条');
    });

    // ---- Panel access / security monitoring --------------------------------
    function statusBadge(status) {
        var s = Number(status) || 0;
        var cls, text;
        if (s >= 500) { cls = 'layui-bg-red'; text = s + ' 错误'; }
        else if (s >= 400) { cls = 'layui-bg-orange'; text = s + ' 拒绝'; }
        else if (s >= 300) { cls = 'layui-bg-blue'; text = s + ' 跳转'; }
        else if (s >= 200) { cls = 'layui-bg-green'; text = s + ' 成功'; }
        else { cls = ''; text = String(s); }
        return '<span class="layui-badge ' + cls + '">' + text + '</span>';
    }
    function levelBadge(level) {
        if (level === 'high') return '<span class="layui-badge layui-bg-red">高危</span>';
        if (level === 'medium') return '<span class="layui-badge layui-bg-orange">中危</span>';
        return '<span class="layui-badge">低危</span>';
    }
    function loadAccess(showErr) {
        fetch('/sys/access?limit=30', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) { if (showErr) layer.msg('未登录或会话已过期', { icon: 2 }); return null; }
                return r.json();
            })
            .then(function (res) {
                if (!res) return;
                if (!res.ok) {
                    if (showErr) layer.msg(res.error || '获取失败', { icon: 2 });
                    return;
                }
                $('#accessStats').show();
                $('#accessTotal').text(res.total || 0);
                $('#accessUnique').text(res.unique_ips || 0);
                var failedIps = res.failed_logins || [];
                $('#accessFailed').text(failedIps.length);
                var susp = res.suspicious || [];
                $('#accessSusp').text(susp.length);

                // Alert banner
                var $alert = $('#accessAlert');
                if (susp.length) {
                    var highCount = susp.filter(function (s) { return s.level === 'high'; }).length;
                    var msg = susp.length + ' 个可疑 IP';
                    if (highCount) msg += '（' + highCount + ' 个高危）';
                    msg += '正在访问面板，请在腾讯云安全组或 /etc/nginx 中封禁。';
                    $alert.show().html('<div class="layui-bg-red" style="padding:10px 14px;border-radius:4px;color:#fff">'
                        + '<i class="layui-icon layui-icon-about"></i> <b>安全告警：</b>' + msg + '</div>');
                } else {
                    $alert.hide();
                }

                // Suspicious IP table
                var $susp = $('#suspiciousBody').empty();
                if (susp.length) {
                    susp.forEach(function (s) {
                        $susp.append('<tr><td class="mono"></td><td></td><td class="mono"></td><td></td><td></td></tr>');
                        var $td = $susp.find('tr:last td');
                        $td.eq(0).text(s.ip);
                        $td.eq(1).text(s.reason);
                        $td.eq(2).text(s.count);
                        $td.eq(3).html(levelBadge(s.level));
                        $td.eq(4).html('<button class="layui-btn layui-btn-xs layui-btn-danger btn-deny" data-ip="' + s.ip + '">封禁</button>');
                    });
                } else {
                    $susp.append('<tr class="wp-login-empty"><td colspan="5" style="text-align:center;color:#999">暂无可疑 IP</td></tr>');
                }

                // Recent access table
                var $body = $('#accessBody').empty();
                var recent = res.recent || [];
                if (recent.length) {
                    recent.forEach(function (r) {
                        $body.append('<tr><td class="mono"></td><td class="mono"></td><td></td><td class="mono"></td><td></td><td></td></tr>');
                        var $td = $body.find('tr:last td');
                        $td.eq(0).text(r.time || '');
                        $td.eq(1).text(r.ip || '');
                        $td.eq(2).text(r.method || '');
                        $td.eq(3).text(r.uri || '');
                        $td.eq(4).html(statusBadge(r.status));
                        $td.eq(5).html('<button class="layui-btn layui-btn-xs layui-btn-danger btn-deny" data-ip="' + (r.ip || '') + '">封禁</button>');
                    });
                } else {
                    $body.append('<tr class="wp-login-empty"><td colspan="6" style="text-align:center;color:#999">暂无访问记录</td></tr>');
                }

                loadDenied();

                var now = new Date();
                var hh = ('0' + now.getHours()).slice(-2);
                var mm = ('0' + now.getMinutes()).slice(-2);
                var ss = ('0' + now.getSeconds()).slice(-2);
                $('#accessHint').text('更新于 ' + hh + ':' + mm + ':' + ss);
            })
            .catch(function () {
                if (showErr) layer.msg('网络错误', { icon: 2 });
            });
    }
    $('#btnAccessRefresh').on('click', function () { loadAccess(true); });

    // ---- IP deny / undeny ----------------------------------------------------
    function denyIp(ip) {
        if (!ip) return;
        layer.confirm('确定要封禁 IP ' + ip + ' 吗？封禁后该 IP 将无法访问面板。', {
            icon: 3, title: '确认封禁', btn: ['封禁', '取消']
        }, function (idx) {
            layer.close(idx);
            var loadIdx = layer.load(2);
            fetch('/sys/deny', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ ip: ip, _csrf: WP.csrf })
            }).then(function (r) { return r.json(); }).then(function (res) {
                layer.close(loadIdx);
                if (res.ok) {
                    layer.msg('已封禁 ' + ip, { icon: 1 });
                    loadDenied();
                } else {
                    layer.msg(res.error || '封禁失败', { icon: 2 });
                }
            }).catch(function () { layer.close(loadIdx); layer.msg('网络错误', { icon: 2 }); });
        });
    }
    function undenyIp(ip) {
        if (!ip) return;
        var loadIdx = layer.load(2);
        fetch('/sys/undeny', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: new URLSearchParams({ ip: ip, _csrf: WP.csrf })
        }).then(function (r) { return r.json(); }).then(function (res) {
            layer.close(loadIdx);
            if (res.ok) {
                layer.msg('已解封 ' + ip, { icon: 1 });
                loadDenied();
            } else {
                layer.msg(res.error || '解封失败', { icon: 2 });
            }
        }).catch(function () { layer.close(loadIdx); layer.msg('网络错误', { icon: 2 }); });
    }
    function loadDenied() {
        fetch('/sys/denylist', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var $db = $('#deniedBody').empty();
                var denied = (res && res.ok && res.denied) ? res.denied : [];
                if (denied.length) {
                    denied.forEach(function (ip) {
                        $db.append('<tr><td class="mono"></td><td></td></tr>');
                        var $td = $db.find('tr:last td');
                        $td.eq(0).text(ip);
                        $td.eq(1).html('<button class="layui-btn layui-btn-xs layui-btn-primary btn-undeny" data-ip="' + ip + '">解封</button>');
                    });
                } else {
                    $db.append('<tr class="wp-login-empty"><td colspan="2" style="text-align:center;color:#999">暂无封禁 IP</td></tr>');
                }
            })
            .catch(function () {});
    }
    $('#accessCard').on('click', '.btn-deny', function () { denyIp(this.getAttribute('data-ip')); });
    $('#accessCard').on('click', '.btn-undeny', function () { undenyIp(this.getAttribute('data-ip')); });

    $('.wp-host-extra').on('click', '.wp-host-tab', function () {
        var $extra = $(this).closest('.wp-host-extra');
        var tab = this.getAttribute('data-tab');
        var wasActive = $(this).hasClass('is-active');
        $extra.find('.wp-host-tab').removeClass('is-active')
            .attr({ 'aria-selected': 'false', 'aria-expanded': 'false' });
        $extra.find('.wp-host-tab-panel').attr('hidden', true);
        if (wasActive) {
            $extra.removeClass('is-open');
            return;
        }
        $(this).addClass('is-active').attr({ 'aria-selected': 'true', 'aria-expanded': 'true' });
        $extra.find('.wp-host-tab-panel[data-tab="' + tab + '"]').removeAttr('hidden');
        $extra.addClass('is-open');
        if (tab === 'atop') {
            element.render('progress');
        }
        if (tab === 'access') {
            loadAccess(false);
        }
    });

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
        if (!$el.length) return;
        $el.removeClass('is-warn is-crit');
        if (pct >= crit) $el.addClass('is-crit');
        else if (pct >= warn) $el.addClass('is-warn');
        $el.find('.wp-ring-value').attr('stroke-dasharray', trimPct(pct) + ' 100');
        $el.find('.wp-ring-num').text(trimPct(pct) + '%');
    }
    function storeState(pct, warn, crit) {
        if (pct >= crit) return { label: 'Critical', cls: ' is-crit' };
        if (pct >= warn) return { label: 'Attention', cls: ' is-warn' };
        return { label: 'Healthy', cls: '' };
    }
    function driveIcon() {
        return '<svg class="wp-drive-svg" viewBox="0 0 48 40" aria-hidden="true">'
            + '<rect class="wp-drive-case" x="4" y="8" width="40" height="24" rx="6"></rect>'
            + '<rect class="wp-drive-lid" x="4" y="8" width="40" height="8" rx="4"></rect>'
            + '<circle class="wp-drive-led" cx="12" cy="26" r="2.2"></circle>'
            + '</svg>';
    }
    function storeCard(id, title, used, total, pct, warn, crit) {
        var st = storeState(pct, warn, crit);
        var w = trimPct(Math.max(0, Math.min(100, Number(pct) || 0)));
        var $card = $('<article class="wp-store-card' + st.cls + '" data-store="' + id + '">');
        $card.append(
            '<div class="wp-store-head"><span class="wp-store-title"></span></div>'
            + '<div class="wp-store-body">' + driveIcon()
            + '<div class="wp-store-info">'
            + '<div class="wp-store-state"></div>'
            + '<div class="wp-store-used"></div>'
            + '<div class="wp-store-total"></div>'
            + '</div></div>'
            + '<div class="wp-store-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">'
            + '<div class="wp-store-bar-fill"></div></div>'
        );
        $card.find('.wp-store-title').text(title);
        $card.find('.wp-store-state').text(st.label);
        $card.find('.wp-store-used').text('Used: ' + (used || '—'));
        $card.find('.wp-store-total').text('Total: ' + (total || '—'));
        $card.find('.wp-store-bar').attr({
            'aria-valuenow': w,
            'aria-label': title + ' ' + w + '%'
        });
        $card.find('.wp-store-bar-fill').css('width', w + '%');
        return $card;
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
        $mounts.append(storeCard(
            'swap',
            '交换',
            swapTotal ? fmtKb(swapUsed) : '0 KB',
            swapTotal ? fmtKb(swapTotal) : '0 KB',
            swapPct,
            50,
            80
        ));
        disks.forEach(function (d, i) {
            $mounts.append(storeCard(
                'disk-' + i,
                d.fs || '磁盘',
                d.used || '',
                d.size || '',
                Number(d.use_pct) || 0,
                80,
                90
            ));
        });
        if (!disks.length && !swapTotal) {
            $mounts.empty().append('<div class="mon-meta wp-disk-empty">暂无磁盘数据</div>');
        }

        var top = res.top || [];
        var $top = $('#topTable').empty();
        if (top.length) {
            top.slice(0, 6).forEach(function (p) {
                var $item = $('<div class="wp-top-mem-item"><span class="wp-top-mem-name mono"></span><span class="wp-top-mem-rss mono"></span></div>');
                $item.find('.wp-top-mem-name').text(p.name || '');
                $item.find('.wp-top-mem-rss').text(p.rss_kb != null ? fmtKb(p.rss_kb) : '');
                $top.append($item);
            });
            $('#topTable').show();
            $('#topLabel').show();
        } else {
            $('#topTable').hide();
            $('#topLabel').hide();
        }

        var map = { nginx: 'nginx', mysqld: 'mysql', 'php-fpm': 'phpfpm', 'postgresql-16': 'postgres', postgresql: 'postgres' };
        [74, 80, 81, 82, 83].forEach(function (v) { map['php' + v + '-php-fpm'] = 'php' + v + 'fpm'; });
        (res.services || []).forEach(function (s) {
            var key = map[s.unit];
            if (!key && s.unit && /^wp-node-/.test(s.unit)) {
                key = s.unit.replace(/^wp-node-/, 'node-');
            }
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
        if (!$tb || !$tb.length) return;
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
            if ($('#atopProcUpdated').length) $('#atopProcUpdated').text('所选采样的进程快照');
            return;
        }

        $('#atopSampleLabel').text('采样详情 · ' + (res.file || '') + ' · ' + (s.time || '') + (s.interval_s ? ' · 间隔 ' + s.interval_s + 's' : ''));
        if ($('#atopProcUpdated').length) {
            $('#atopProcUpdated').text((s.time || '所选采样') + (res.file ? ' · ' + res.file : '') + (s.interval_s ? ' · 间隔 ' + s.interval_s + 's' : ''));
        }
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

<script src="/static/vendor/gridstack/gridstack-all.min.js"></script>
<script>
/* 仪表盘卡片自由排列：基于 GridStack（磁吸补位 + 宽度可调），布局存 localStorage */
layui.use(['layer'], function () {
    var layer = layui.layer;
    var STORAGE_KEY = 'wp.dash.grid';
    var grid = null;

    // GridStack 构造函数依赖 ResizeObserver，旧浏览器缺失时用空实现兜底（仅失去自动高度监听）
    if (typeof window.ResizeObserver === 'undefined') {
        window.ResizeObserver = function () {
            this.observe = function () {};
            this.unobserve = function () {};
            this.disconnect = function () {};
        };
    }

    function saveLayout() {
        if (!grid || window.innerWidth < 768) return; // 单列响应式下不覆盖已存布局
        try {
            var nodes = (grid.engine && grid.engine.nodes) ? grid.engine.nodes : [];
            var data = nodes.map(function (n) {
                return { id: n.el ? n.el.getAttribute('data-widget') : '', x: n.x, y: n.y, w: n.w };
            });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {}
    }

    function loadLayout() {
        var raw = null, data;
        try { raw = localStorage.getItem(STORAGE_KEY); } catch (e) { return; }
        if (!raw) return;
        try { data = JSON.parse(raw); } catch (e) { return; }
        if (!Array.isArray(data)) return;
        data.forEach(function (it) {
            if (!it || !it.id) return;
            var el = document.querySelector('#dashGrid > [data-widget="' + it.id + '"]');
            if (!el) return;
            try { grid.update(el, { x: it.x, y: it.y, w: it.w }); } catch (e) {}
        });
    }

    function setEdit(on) {
        document.body.classList.toggle('wp-dash-editing', on);
        try { grid.staticGrid(!on); } catch (e) {}
        document.getElementById('btnDashEdit').style.display = on ? 'none' : '';
        document.getElementById('btnDashDone').style.display = on ? '' : 'none';
        document.getElementById('btnDashReset').style.display = on ? '' : 'none';
        document.getElementById('dashEditHint').textContent = on
            ? '拖动卡片到任意位置（松手后其他卡片自动磁吸补位），拖右下角可调整宽度'
            : '点击「编辑布局」可自由排列卡片';
    }

    if (window.GridStack) {
        try {
            grid = GridStack.init({
                column: 12,
                cellHeight: 24,
                margin: 10,
                float: false,      // 磁吸模式：卡片移动后其余卡片自动上浮补位
                staticGrid: true,  // 默认锁定，编辑布局时才放开
                animate: true
            }, document.getElementById('dashGrid'));
            loadLayout();
            grid.on('change', saveLayout);
            grid.on('resizestop', saveLayout);
            grid.on('dropped', saveLayout);
        } catch (e) { /* GridStack 初始化失败时回退为静态 3 列布局 */ }

        document.getElementById('btnDashEdit').addEventListener('click', function () { setEdit(true); });
        document.getElementById('btnDashDone').addEventListener('click', function () {
            saveLayout();
            setEdit(false);
            layer.msg('布局已保存', { icon: 1 });
        });
        document.getElementById('btnDashReset').addEventListener('click', function () {
            layer.confirm('恢复为默认布局？', { icon: 3, title: '重置布局' }, function (idx) {
                layer.close(idx);
                try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
                location.reload();
            });
        });
    }
});
</script>

