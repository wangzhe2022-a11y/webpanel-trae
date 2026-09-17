<?php
/** @var array $sites @var array $certs @var string $listError */
?>
<div class="layui-card" style="margin-bottom:15px;border-radius:8px">
    <div class="layui-card-body" style="color:#666;font-size:13px">
        <span class="layui-icon layui-icon-tips" style="color:#1e9fff"></span>
        <b>方式一（推荐）：自动签发</b> — Let's Encrypt 通过 acme.sh 自动签发（http-01 验证）并每天检查自动续期，无需人工干预。签发前请确认：
        ① 所有域名（含别名）已添加 A 记录解析到本服务器公网 IP；
        ② 腾讯云安全组与防火墙已放行 <b>80、443</b> 端口。证书有效期 90 天。<br>
        <span class="layui-icon layui-icon-tips" style="color:#ffb800"></span>
        <b>方式二：上传第三方证书</b> — 如腾讯云免费证书（TrustAsia，DV 单域名，有效期 90 天，单账号每年最多 50 张）。
        在 <a href="https://console.cloud.tencent.com/ssl" target="_blank">腾讯云 SSL 控制台</a> 申请下载后，点站点行「上传证书」粘贴部署。
        注意：第三方证书<b>不会自动续期</b>，到期前需重新申请并再次上传。
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
            <td class="mono" style="font-size:12px">
                <?= $cert ? e($cert['not_after']) : '-' ?>
                <?php if ($cert): ?>
                    <br>
                    <?php if (($cert['source'] ?? 'acme') === 'manual'): ?>
                        <span class="layui-badge layui-bg-orange">手动部署</span>
                    <?php else: ?>
                        <span class="layui-badge layui-bg-blue">自动续期</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
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
                <button class="layui-btn layui-btn-xs layui-btn-warm btn-upload">
                    <span class="layui-icon layui-icon-upload"></span> 上传证书
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

<script type="text/html" id="tpl-upload">
<form style="padding:18px 20px 0" id="certUploadForm">
    <div style="background:#fff8e6;border:1px solid #ffe2a3;border-radius:6px;padding:10px 12px;font-size:12.5px;color:#8d6a1f;margin-bottom:12px">
        适用于<b>腾讯云 TrustAsia / 亚洲诚信</b>等第三方证书：控制台下载 zip → 解压 → 打开 <b>Nginx</b> 目录，
        把 <b>.crt/.pem</b>（证书，含中间链）和 <b>.key</b>（私钥）的内容分别粘贴到下面两个框。
        也可点击「选择文件」直接读取（.pem/.crt/.key/txt）。私钥请勿设置密码。
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">证书</label>
        <div class="layui-input-block">
            <textarea name="fullchain" rows="7" class="layui-textarea mono" style="font-size:12px"
                      placeholder="-----BEGIN CERTIFICATE-----&#10;MIIF...（fullchain / .crt 内容）"></textarea>
            <button type="button" class="layui-btn layui-btn-xs layui-btn-primary" style="margin-top:6px" id="pickCert">选择文件</button>
            <input type="file" id="fileCert" accept=".pem,.crt,.cer,.txt" style="display:none">
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">私钥</label>
        <div class="layui-input-block">
            <textarea name="privkey" rows="5" class="layui-textarea mono" style="font-size:12px"
                      placeholder="-----BEGIN PRIVATE KEY-----&#10;MIIE...（.key 内容）"></textarea>
            <button type="button" class="layui-btn layui-btn-xs layui-btn-primary" style="margin-top:6px" id="pickKey">选择文件</button>
            <input type="file" id="fileKey" accept=".pem,.key,.txt" style="display:none">
        </div>
    </div>
</form>
</script>

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

    /* ---------- third-party certificate upload (e.g. Tencent TrustAsia) ---------- */
    $('.btn-upload').on('click', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id'), domain = $tr.data('domain');
        layer.open({
            type: 1, title: '上传第三方证书：' + domain, area: ['640px', '600px'],
            content: $('#tpl-upload').html(),
            btn: ['部署证书', '取消'],
            yes: function (idx, layero) {
                var f = layero.find('#certUploadForm')[0];
                var cert = f.fullchain.value.trim(), key = f.privkey.value.trim();
                if (!cert || !key) { layer.msg('请填写证书和私钥内容', { icon: 2 }); return; }
                var load = layer.load(2);
                WP.post('/ssl/upload', { id: id, fullchain: cert, privkey: key }).then(function (res) {
                    layer.close(load);
                    if (!res.ok) { layer.alert(res.error, { icon: 2, title: '部署失败' }); return; }
                    var msg = '证书部署成功，站点已切换到 HTTPS。<br>有效期至：<b>' + layui.util.escape(res.not_after || '-') + '</b>'
                        + '<br><span style="color:#ff5722">第三方证书不会自动续期，到期前请重新申请并上传。</span>';
                    if (res.missing) {
                        msg += '<br><span style="color:#e6a23c">注意：证书未覆盖域名 ' + layui.util.escape(res.missing)
                            + '，通过该域名访问时浏览器会告警。</span>';
                    }
                    layer.open({
                        type: 1, title: '部署成功', area: ['480px', 'auto'], btn: ['完成'],
                        content: '<div style="padding:16px 20px;font-size:13.5px">' + msg + '</div>',
                        yes: function (i2) { layer.close(i2); layer.close(idx); location.reload(); }
                    });
                });
            },
            success: function (layero) {
                function wire(pick, file, target) {
                    layero.find(pick).on('click', function () { layero.find(file).trigger('click'); });
                    layero.find(file).on('change', function () {
                        var f = this.files[0];
                        if (!f) return;
                        if (f.size > 1024 * 1024) { layer.msg('文件过大，请检查是否选错文件', { icon: 2 }); return; }
                        var reader = new FileReader();
                        reader.onload = function (e) { layero.find(target).val(String(e.target.result).trim()); };
                        reader.readAsText(f);
                    });
                }
                wire('#pickCert', '#fileCert', 'textarea[name=fullchain]');
                wire('#pickKey', '#fileKey', 'textarea[name=privkey]');
            }
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
