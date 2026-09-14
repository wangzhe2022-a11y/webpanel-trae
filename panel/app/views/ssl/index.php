<?php
/** @var array $sites @var array $certs @var string $listError */
?>
<div class="layui-card" style="margin-bottom:15px;border-radius:8px">
    <div class="layui-card-body" style="color:#666;font-size:13px">
        <span class="layui-icon layui-icon-tips" style="color:#1e9fff"></span>
        证书由 <b>Let's Encrypt</b> 通过 acme.sh 自动签发（http-01 验证）。签发前请确认：
        ① 所有域名（含别名）已添加 A 记录解析到本服务器公网 IP；
        ② 腾讯云安全组与防火墙已放行 <b>80、443</b> 端口。证书有效期 90 天，系统每天检查并自动续期。
        <?php if ($listError): ?><br><span style="color:#ff5722">证书列表读取异常：<?= e($listError) ?></span><?php endif; ?>
    </div>
</div>

<div class="panel-card">
    <h3>站点证书</h3>
    <table class="layui-table" style="margin:0">
        <thead>
        <tr>
            <th width="50">ID</th>
            <th>域名</th>
            <th width="180">有效期至</th>
            <th width="90">状态</th>
            <th width="120">强制 HTTPS</th>
            <th width="260">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$sites): ?>
            <tr><td colspan="6" style="text-align:center;color:#999;padding:40px">请先在「网站管理」创建站点</td></tr>
        <?php endif; ?>
        <?php foreach ($sites as $s):
            $cert = $certs[$s['domain']] ?? null; ?>
        <tr data-id="<?= (int) $s['id'] ?>" data-domain="<?= e($s['domain']) ?>">
            <td><?= (int) $s['id'] ?></td>
            <td>
                <b><?= e($s['domain']) ?></b>
                <?php foreach (array_filter(explode(',', (string) $s['aliases'])) as $a): ?>
                    <span class="layui-badge-rim tag-alias"><?= e($a) ?></span>
                <?php endforeach; ?>
            </td>
            <td class="mono" style="font-size:12px"><?= $cert ? e($cert['not_after']) : '-' ?></td>
            <td>
                <?php if ((int) $s['ssl'] === 1): ?>
                    <span class="layui-badge layui-bg-green">已启用</span>
                <?php else: ?>
                    <span class="layui-badge layui-bg-gray">未启用</span>
                <?php endif; ?>
            </td>
            <td>
                <input type="checkbox" class="hsts-switch" lay-ignore
                       <?= (int) $s['hsts'] === 1 ? 'checked' : '' ?>
                       <?= (int) $s['ssl'] === 1 ? '' : 'disabled' ?>>
                <span style="font-size:12px;color:#888">HSTS</span>
            </td>
            <td>
                <button class="layui-btn layui-btn-xs layui-btn-normal btn-issue">
                    <span class="layui-icon layui-icon-auz"></span>
                    <?= (int) $s['ssl'] === 1 ? '续签' : '申请证书' ?>
                </button>
                <?php if ((int) $s['ssl'] === 1): ?>
                <button class="layui-btn layui-btn-xs layui-btn-danger btn-remove">删除</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
layui.use(['layer'], function () {
    var layer = layui.layer, $ = layui.$;

    $('.btn-issue').on('click', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id'), domain = $tr.data('domain');
        var load;
        layer.confirm('将为 <b>' + domain + '</b> 及其全部别名签发 Let\'s Encrypt 证书。<br>'
            + '请确认域名已解析到本机且 80 端口可公网访问，签发过程约需 10-60 秒。', {
            title: '申请/续签 SSL 证书', area: ['460px', 'auto']
        }, function (idx) {
            layer.close(idx);
            load = layer.load(2);
            WP.post('/ssl/issue', { id: id }).then(function (res) {
                layer.close(load);
                if (!res.ok) { layer.alert(res.error, { icon: 2, title: '签发失败' }); return; }
                layer.msg('证书已部署，站点已切换到 HTTPS', { icon: 1, time: 2500 }, function () { location.reload(); });
            });
        });
    });

    $('.btn-remove').on('click', function () {
        var $tr = $(this).closest('tr');
        layer.confirm('删除证书后站点将回退到 HTTP，确认？', { title: '删除证书' }, function (idx) {
            layer.close(idx);
            var load = layer.load(2);
            WP.post('/ssl/remove', { id: $tr.data('id') }).then(function (res) {
                layer.close(load);
                if (res.ok) { layer.msg('已删除', { icon: 1 }); location.reload(); }
                else { layer.alert(res.error, { icon: 2 }); }
            });
        });
    });

    $('.hsts-switch').on('change', function () {
        var $sw = $(this), $tr = $sw.closest('tr');
        var on = this.checked ? 1 : 0;
        WP.post('/ssl/hsts', { id: $tr.data('id'), hsts: on }).then(function (res) {
            if (!res.ok) { $sw.prop('checked', !this.checked); layer.msg(res.error, { icon: 2 }); return; }
            layer.msg(on ? 'HSTS 已启用' : 'HSTS 已关闭', { icon: 1 });
        });
    });
});
</script>
