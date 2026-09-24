<?php
/** @var array $sites @var array $conn */
$phpLabels = panel_php_versions();
$host = (string) ($conn['suggested_host'] ?? '');
$port = (int) ($conn['ssh_port'] ?? 22);
$hostname = (string) ($conn['hostname'] ?? '');
$publicIp = (string) ($conn['public_ip'] ?? '');
?>
<div class="panel-card">
    <h3>
        Installatron Remote —— 云端一键应用安装器
        <span class="layui-badge layui-bg-blue" style="margin-left:8px">Remote</span>
    </h3>
    <p style="color:#666;font-size:13px;line-height:1.9;margin:0 0 14px">
        用 <b>Installatron Remote</b>（云端控制台）给本机站点安装 WordPress、Joomla、Drupal、PrestaShop、phpMyAdmin 等
        <b>320+</b> 应用。本页只提供连接参数，<b>不会</b>在服务器上安装 Installatron Server，也<b>不会</b>保存 installatron.com 账号或密码。
        WordPress 若只想本机一键部署，仍可走「网站管理」里的 WP-CLI。
    </p>
    <a class="layui-btn" href="https://installatron.com/apps" target="_blank" rel="noopener noreferrer">
        <span class="layui-icon layui-icon-release"></span> 打开 Installatron Remote
    </a>
    <a class="layui-btn layui-btn-primary" href="https://installatron.com/remote" target="_blank" rel="noopener noreferrer">
        产品说明 / 注册
    </a>
    <a class="layui-btn layui-btn-primary" href="https://installatron.com/docs/faq/ir" target="_blank" rel="noopener noreferrer">
        Remote FAQ
    </a>
</div>

<div class="panel-card">
    <h3>连接本机站点</h3>
    <ol class="itron-steps">
        <li>在 Installatron.com 登录后进入 <span class="mono">https://installatron.com/apps</span>，添加网站。</li>
        <li>协议选 <b>SFTP 或 SSH</b>（不要用明文 FTP：本机未装 FTP 服务，且数据库只监听本机）。</li>
        <li>填入下表主机、端口、用户与文档根路径；网站 URL 填站点域名。</li>
        <li>应用需要数据库时，先到面板 <a href="/databases">数据库</a> 页建库（密码只显示一次），安装向导里主机填 <span class="mono">127.0.0.1</span>（MySQL）或 <span class="mono">127.0.0.1:5432</span>（PostgreSQL）。</li>
    </ol>
    <table class="layui-table" lay-skin="line" style="margin:0">
        <tr>
            <td width="140">建议主机</td>
            <td>
                <span class="mono" id="itronHost"><?= $host !== '' ? e($host) : '本机公网 IP 或已解析域名' ?></span>
                <?php if ($host !== ''): ?>
                    <a href="javascript:;" class="btn-copy" data-copy="<?= e($host) ?>">复制</a>
                <?php endif; ?>
                <div style="color:#999;font-size:12px;margin-top:4px">
                    填腾讯云公网 IP，或已指向本机的域名。
                    <?php if ($hostname !== ''): ?>本机主机名 <span class="mono"><?= e($hostname) ?></span>。<?php endif; ?>
                    <?php if ($publicIp !== ''): ?>当前面板访问 IP <span class="mono"><?= e($publicIp) ?></span>。<?php endif; ?>
                </div>
            </td>
        </tr>
        <tr>
            <td>端口</td>
            <td>
                <span class="mono"><?= $port ?></span>
                <a href="javascript:;" class="btn-copy" data-copy="<?= $port ?>">复制</a>
                <span style="color:#999;font-size:12px;margin-left:8px">默认 SSH/SFTP；若改过 sshd 端口请按实际填写</span>
            </td>
        </tr>
        <tr>
            <td>协议</td>
            <td>SFTP 或 SSH（优先）；明文 FTP 不可用</td>
        </tr>
        <tr>
            <td>文档根路径</td>
            <td>PHP 站点 <span class="mono">/www/wwwroot/&lt;站点用户&gt;/public</span>；见下方各站点卡片</td>
        </tr>
        <tr>
            <td>数据库</td>
            <td>不在本页保存密码。到 <a href="/databases">数据库</a> 查看库名/用户名，主机用 <span class="mono">127.0.0.1</span>（库只监听本机，必须走 SSH/SFTP 在服务器上连）</td>
        </tr>
    </table>
    <p style="color:#888;font-size:12.5px;line-height:1.8;margin:12px 0 0">
        <b>账号说明：</b>建议用户填该站点的系统用户（<span class="mono">sysuser</span>）。面板创建的站点用户默认 shell 为
        <span class="mono">/sbin/nologin</span>，不能交互式 SSH。要用 Installatron 连上，请先为该用户配置 OpenSSH 内部 SFTP
        （chroot 到 <span class="mono">/www/wwwroot/&lt;sysuser&gt;</span>），或改用已有运维 SSH 账号并把路径写成对应
        <span class="mono">public</span> 目录。腾讯云安全组需放行入站 TCP <b>22</b>，否则 installatron.com 云端连不上本机。
    </p>
</div>

<div class="panel-card">
    <h3>站点连接提示</h3>
    <?php if (!$sites): ?>
        <p style="text-align:center;color:#999;padding:36px 12px;margin:0">
            还没有站点。先到 <a href="/sites">网站管理</a> 创建 PHP 站点，再回到本页复制连接参数。
        </p>
    <?php else: ?>
        <div class="layui-row layui-col-space15">
        <?php foreach ($sites as $s): ?>
            <?php
            $snippet = "协议: SFTP\n"
                . '主机: ' . ($host !== '' ? $host : '<公网IP>') . "\n"
                . '端口: ' . $port . "\n"
                . '用户: ' . $s['sysuser'] . "\n"
                . '路径: ' . $s['docroot'] . "\n"
                . '网站 URL: http' . ((int) ($s['ssl'] ?? 0) === 1 ? 's' : '') . '://' . $s['domain'] . "\n"
                . '数据库主机: 127.0.0.1（先在面板「数据库」页建库）';
            ?>
            <div class="layui-col-md6">
                <div style="border:1px solid #eee;border-radius:6px;padding:14px 16px;height:100%;box-sizing:border-box">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                        <div>
                            <?php if (!empty($s['is_node'])): ?>
                                <span class="layui-badge layui-bg-cyan">Node</span>
                            <?php else: ?>
                                <span class="layui-badge layui-bg-green">PHP</span>
                            <?php endif; ?>
                            <b><?= e($s['domain']) ?></b>
                        </div>
                        <a href="javascript:;" class="layui-btn layui-btn-xs layui-btn-primary btn-copy" data-copy="<?= e($snippet) ?>">复制连接参数</a>
                    </div>
                    <?php if (!empty($s['is_node'])): ?>
                        <p style="color:#c21f39;font-size:12px;margin:0 0 8px">Node 站点一般不走 Installatron（面向 PHP 应用）；路径仅供参考。</p>
                    <?php endif; ?>
                    <table class="layui-table" lay-skin="nob" style="margin:0">
                        <tr><td width="90">域名</td><td class="mono"><?= e($s['domain']) ?></td></tr>
                        <tr>
                            <td>SFTP 用户</td>
                            <td class="mono"><?= e($s['sysuser']) ?>
                                <a href="javascript:;" class="btn-copy" data-copy="<?= e($s['sysuser']) ?>">复制</a>
                            </td>
                        </tr>
                        <tr>
                            <td>文档根</td>
                            <td class="mono"><?= e($s['docroot']) ?>
                                <a href="javascript:;" class="btn-copy" data-copy="<?= e($s['docroot']) ?>">复制</a>
                            </td>
                        </tr>
                        <?php if (empty($s['is_node'])): ?>
                        <tr>
                            <td>PHP</td>
                            <td><?= e($phpLabels[$s['php_version']] ?? ('PHP ' . (string) $s['php_version'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>数据库</td>
                            <td>
                                <?php if (empty($s['db_list'])): ?>
                                    <span style="color:#999">未关联 —— 请先到 <a href="/databases">数据库</a> 建库</span>
                                <?php else: ?>
                                    <?php foreach ($s['db_list'] as $d): ?>
                                        <div class="mono">
                                            <?= ($d['engine'] ?? 'mysql') === 'postgres' ? 'PG' : 'MySQL' ?>
                                            <?= e($d['name']) ?> / <?= e($d['username']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <div style="color:#999;font-size:12px">密码不保存在面板，忘记请在数据库页改密</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="panel-card" style="color:#999;font-size:12.5px;line-height:1.8">
    <h3 style="color:#666">与旧版 Installatron Server 的区别</h3>
    <p style="margin:0">
        面板不再提供 License Key 粘贴、本机异步安装、官方 GUI 一次性登录或一键升级/卸载。
        Remote Premium 订阅不能驱动本机 <span class="mono">/usr/local/installatron</span>。
        若旧环境曾装过 Server，可在 shell 手工清理：
        <span class="mono">rpm -e installatron-server</span>，
        再删除 <span class="mono">/usr/local/installatron</span>、<span class="mono">/var/installatron</span>、
        <span class="mono">/etc/cron.d/installatron</span>，并检查 Nginx 是否残留其配置（<span class="mono">nginx -t</span>）。
        从未安装过 Server 的机器无需任何操作。
    </p>
</div>

<style>
    .btn-copy { font-size:12px; margin-left:8px; color: var(--wp-accent); }
    /* layui reset sets li{list-style:none}; restore numbering for this page */
    .itron-steps { padding-left: 22px; margin: 0 0 16px; font-size: 13px; color: #555; line-height: 2; }
    .itron-steps li { list-style: decimal; list-style-position: outside; }
</style>
<script>
layui.use(['layer'], function () {
    var layer = layui.layer, $ = layui.$;
    $(document).on('click', '.btn-copy', function () {
        var t = $(this).attr('data-copy') || '';
        if (!t) { layer.msg('没有可复制的内容', { icon: 0 }); return; }
        WP.copy(t).then(function () { layer.msg('已复制', { icon: 1, time: 1000 }); });
    });
});
</script>
