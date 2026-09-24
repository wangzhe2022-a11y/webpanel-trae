<?php
/** @var array $sites @var array $phpVersions */
?>
<div class="panel-card">
    <h3>
        网站列表
        <button class="layui-btn layui-btn-sm" style="float:right" id="btnCreate">
            <span class="layui-icon layui-icon-add-1"></span> 创建网站
        </button>
    </h3>
    <table class="layui-table" style="margin:0">
        <thead>
        <tr>
            <th width="50">ID</th>
            <th>域名</th>
            <th width="200">运行目录 / 用户</th>
            <th width="150">PHP 版本</th>
            <th width="90">SSL</th>
            <th width="150">创建时间</th>
            <th width="280">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$sites): ?>
            <tr><td colspan="7" style="text-align:center;color:#999;padding:40px">
                还没有网站，点击右上角「创建网站」开始
            </td></tr>
        <?php endif; ?>
        <?php foreach ($sites as $s): ?>
        <tr data-id="<?= (int) $s['id'] ?>">
            <td><?= (int) $s['id'] ?></td>
            <td>
                <?php $isNode = ($s['type'] ?? 'php') === 'node'; ?>
                <?= $isNode ? '<span class="layui-badge layui-bg-cyan">Node</span>' : '<span class="layui-badge layui-bg-green">PHP</span>' ?>
                <b><?= e($s['domain']) ?></b>
                <?php foreach (array_filter(explode(',', (string) $s['aliases'])) as $a): ?>
                    <span class="layui-badge-rim tag-alias"><?= e($a) ?></span>
                <?php endforeach; ?>
                <br>
                <a href="http://<?= e($s['domain']) ?>" target="_blank" style="font-size:12px">
                    <span class="layui-icon layui-icon-link"></span> http(s)://<?= e($s['domain']) ?>
                </a>
            </td>
            <td>
                <div class="mono">/www/wwwroot/<?= e($s['sysuser']) ?>/<?= $isNode ? 'app' : 'public' ?></div>
                <div class="mono" style="color:#999">user: <?= e($s['sysuser']) ?></div>
            </td>
            <?php if ($isNode): ?>
            <td>
                <div><span class="layui-badge-rim">端口 <?= (int) $s['app_port'] ?></span></div>
                <div class="mono" style="color:#999;font-size:12px;margin-top:4px"><?= e($s['start_cmd'] ?? '') ?></div>
            </td>
            <?php else: ?>
            <td>
                <select class="phpsel" lay-ignore data-id="<?= (int) $s['id'] ?>" style="height:30px">
                    <?php foreach ($phpVersions as $v => $label): ?>
                        <option value="<?= e((string) $v) ?>" <?= panel_php_version_eq($s['php_version'], $v) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <?php endif; ?>
            <td>
                <?php if ((int) $s['ssl'] === 1): ?>
                    <span class="layui-badge layui-bg-green">HTTPS</span>
                <?php else: ?>
                    <span class="layui-badge layui-bg-gray">HTTP</span>
                <?php endif; ?>
            </td>
            <td class="mono" style="font-size:12px"><?= e($s['created_at']) ?></td>
            <td>
                <div class="site-ops">
                <a class="layui-btn layui-btn-xs layui-btn-primary" href="/files?site=<?= (int) $s['id'] ?>">
                    <span class="layui-icon layui-icon-file"></span> 文件
                </a>
                <?php if (!($isNode ?? false)): ?>
                <button class="layui-btn layui-btn-xs btn-wp"><span class="layui-icon layui-icon-template"></span> WP</button>
                <?php else: ?>
                <button class="layui-btn layui-btn-xs layui-btn-warm btn-npmi"><span class="layui-icon layui-icon-download"></span> npm i</button>
                <button class="layui-btn layui-btn-xs btn-nrestart"><span class="layui-icon layui-icon-refresh"></span> 重启</button>
                <?php endif; ?>
                <a class="layui-btn layui-btn-xs layui-btn-normal" href="/ssl">
                    <span class="layui-icon layui-icon-auz"></span> SSL
                </a>
                <button class="layui-btn layui-btn-xs layui-btn-danger btn-del">删除</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 创建网站表单模板 -->
<script type="text/html" id="tpl-create">
<form class="layui-form" style="padding:20px 20px 0" id="createForm">
    <div class="layui-form-item">
        <label class="layui-form-label">主域名 <span style="color:red">*</span></label>
        <div class="layui-input-block">
            <input name="domain" required class="layui-input" placeholder="例如 shop.example.com（需先解析到本服务器）">
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">域名别名</label>
        <div class="layui-input-block">
            <input name="aliases" class="layui-input" placeholder="多个用逗号分隔，例如 www.shop.example.com">
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">站点类型</label>
        <div class="layui-input-block" style="padding-top:8px">
            <input type="radio" name="type" value="php" checked lay-ignore id="tPhp" style="vertical-align:middle">
            <label for="tPhp">PHP 网站（WordPress / WooCommerce）</label>
            <input type="radio" name="type" value="node" lay-ignore id="tNode" style="vertical-align:middle;margin-left:18px">
            <label for="tNode">Node.js 应用（反向代理）</label>
        </div>
    </div>
    <div id="phpFields">
        <div class="layui-form-item">
            <label class="layui-form-label">PHP 版本</label>
            <div class="layui-input-inline" style="width:190px">
                <select name="php_version" lay-ignore class="layui-input">
                    <?php foreach ($phpVersions as $v => $label): ?>
                    <option value="<?= e((string) $v) ?>" <?= panel_php_version_eq($v, '82') ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="layui-form-label" style="width:auto;padding:9px 8px">并发数</label>
            <div class="layui-input-inline" style="width:90px">
                <input name="max_children" class="layui-input" value="20">
            </div>
        </div>
    </div>
    <div id="nodeFields" style="display:none;background:#f0f9ff;padding:12px;border-radius:6px;margin-bottom:12px">
        <div class="layui-form-item">
            <label class="layui-form-label">应用端口</label>
            <div class="layui-input-inline" style="width:120px">
                <input name="app_port" class="layui-input" value="3000" placeholder="3000">
            </div>
            <label class="layui-form-label" style="width:auto;padding:9px 8px">启动命令</label>
            <div class="layui-input-inline" style="width:220px">
                <input name="start_cmd" class="layui-input" value="npm start" placeholder="npm start">
            </div>
        </div>
        <div style="color:#666;font-size:12px;padding-left:110px">
            代码放在 /www/wwwroot/&lt;站点用户&gt;/app（创建后用「文件」上传，或先上传 package.json 再点 npm i）；
            应用只需监听 127.0.0.1:&lt;端口&gt;，Nginx 自动反代并支持 WebSocket。
        </div>
    </div>
    <div class="layui-form-item">
        <input type="checkbox" name="with_db" value="1" lay-ignore id="withDbChk" style="vertical-align:middle">
        <label for="withDbChk">同时创建数据库（部署 WordPress 建议勾选）</label>
    </div>
    <div id="dbFields" style="display:none;background:#fafafa;padding:12px;border-radius:6px;margin-bottom:12px">
        <div class="layui-form-item">
            <label class="layui-form-label">数据库引擎</label>
            <div class="layui-input-block">
                <select name="db_engine" lay-ignore class="layui-input" style="width:220px">
                    <option value="mysql">MySQL 8（WordPress 默认）</option>
                    <option value="postgres">PostgreSQL 16</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">数据库名</label>
            <div class="layui-input-block"><input name="db_name" class="layui-input" placeholder="例如 wp_shop"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">数据库用户</label>
            <div class="layui-input-block"><input name="db_user" class="layui-input" placeholder="例如 wp_shop_user"></div>
        </div>
        <div style="color:#999;font-size:12px;padding-left:110px">密码将自动生成并仅显示一次</div>
    </div>
    <div class="layui-form-item" style="text-align:right">
        <button type="button" class="layui-btn" id="btnDoCreate">创建</button>
    </div>
</form>
</script>

<!-- WP 部署表单模板 -->
<script type="text/html" id="tpl-wp">
<form class="layui-form" style="padding:20px 20px 0" id="wpForm">
    <input type="hidden" name="id">
    <div class="layui-form-item">
        <label class="layui-form-label">数据库</label>
        <div class="layui-input-block">
            <select name="db_source" lay-ignore class="layui-input wp-dbsource">
                <option value="new">新建数据库</option>
            </select>
        </div>
    </div>
    <div class="wp-newdb">
        <div class="layui-form-item">
            <label class="layui-form-label">数据库名</label>
            <div class="layui-input-block"><input name="new_db_name" class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">数据库用户</label>
            <div class="layui-input-block"><input name="new_db_user" class="layui-input"></div>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">站点标题</label>
        <div class="layui-input-block"><input name="wp_title" class="layui-input" value="我的 WordPress 站点"></div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">管理员账号</label>
        <div class="layui-input-block"><input name="wp_admin" class="layui-input" value="webmaster" required></div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">管理员邮箱</label>
        <div class="layui-input-block"><input name="wp_email" class="layui-input" placeholder="you@example.com" required></div>
    </div>
    <div style="color:#999;font-size:12px;padding:0 0 10px 110px">管理员密码自动生成，部署成功后仅显示一次；WP-CLI 自动下载中文版 WordPress 并配置好 WooCommerce 所需参数。</div>
    <div class="layui-form-item" style="text-align:right">
        <button type="button" class="layui-btn" id="btnDoWp">开始部署</button>
    </div>
</form>
</script>

<script>
layui.use(['layer', 'form'], function () {
    var layer = layui.layer, $ = layui.$;

    /* ---------- create site ---------- */
    $('#btnCreate').on('click', function () {
        layer.open({
            type: 1, title: '创建网站', area: ['620px', '620px'],
            content: $('#tpl-create').html(),
            success: function () {
                $(document).off('change.wpdb').on('change.wpdb', '#withDbChk', function () {
                    $('#dbFields').toggle(this.checked);
                });
                $(document).off('change.wptype').on('change.wptype', 'input[name=type]', function () {
                    var isNode = this.value === 'node';
                    if (this.checked) {
                        $('#phpFields').toggle(!isNode);
                        $('#nodeFields').toggle(isNode);
                    }
                });
                $('#btnDoCreate').on('click', function () {
                    var f = $('#createForm')[0];
                    var type = $('input[name=type]:checked').val();
                    var data = {
                        type: type,
                        domain: f.domain.value.trim(),
                        aliases: f.aliases.value.trim(),
                        php_version: f.php_version.value,
                        max_children: f.max_children.value,
                        app_port: f.app_port.value.trim(),
                        start_cmd: f.start_cmd.value.trim(),
                        with_db: f.with_db.checked ? '1' : '',
                        db_engine: f.db_engine.value,
                        db_name: f.db_name.value.trim(),
                        db_user: f.db_user.value.trim()
                    };
                    if (!data.domain) { layer.msg('请填写主域名', { icon: 2 }); return; }
                    if (type === 'node') {
                        var p = parseInt(data.app_port, 10);
                        if (!p || p < 1024 || p > 65535) { layer.msg('应用端口需为 1024-65535', { icon: 2 }); return; }
                        if (!data.start_cmd) { layer.msg('请填写启动命令', { icon: 2 }); return; }
                    }
                    if (data.with_db && (!data.db_name || !data.db_user)) {
                        layer.msg('请填写数据库名和用户名', { icon: 2 }); return;
                    }
                    var load = layer.load(2);
                    WP.post('/sites/create', data).then(function (res) {
                        layer.close(load);
                        if (!res.ok) { layer.alert(res.error, { icon: 2, title: '创建失败' }); return; }
                        var html = '<div style="padding:10px 20px">'
                            + '<p>站点 <b>' + layui.util.escape(data.domain) + '</b> 创建成功'
                            + (res.type === 'node' ? '（Node.js，端口 ' + layui.util.escape(String(res.port || data.app_port)) + '）' : '')
                            + '</p>'
                            + '<p class="mono" style="color:#666">运行目录：' + layui.util.escape(res.docroot) + '</p>';
                        if (res.db_name) {
                            html += '<div class="secret-box">'
                                + '<div>数据库引擎：<b>' + (res.db_engine === 'postgres' ? 'PostgreSQL' : 'MySQL') + '</b></div>'
                                + '<div>数据库名：<b>' + layui.util.escape(res.db_name) + '</b></div>'
                                + '<div>用户名：<b>' + layui.util.escape(res.db_user) + '</b></div>'
                                + '<div>密码：<span class="v mono" id="secDbPw">' + layui.util.escape(res.db_password) + '</span></div>'
                                + '<div style="margin-top:8px"><button class="layui-btn layui-btn-xs" id="btnCopyDb">复制密码</button></div>'
                                + '<div style="color:#ff5722;font-size:12px;margin-top:6px">密码仅显示这一次，请立即保存！</div>'
                                + '</div>';
                        }
                        html += '</div>';
                        layer.open({
                            type: 1, title: '创建成功', area: ['520px', 'auto'], btn: ['完成'],
                            content: html,
                            success: function () {
                                $('#btnCopyDb').on('click', function () {
                                    WP.copy($('#secDbPw').text()).then(function () { layer.msg('已复制', { icon: 1 }); });
                                });
                            },
                            yes: function (i) { layer.closeAll(); location.reload(); }
                        });
                    });
                });
            }
        });
    });

    /* ---------- node: restart / npm install ---------- */
    $('.btn-nrestart').on('click', function () {
        var id = $(this).closest('tr').data('id');
        layer.confirm('重启该 Node.js 应用？', { title: '重启应用' }, function (idx) {
            layer.close(idx);
            var load = layer.load(2);
            WP.post('/sites/node-svc', { id: id, action: 'restart' }).then(function (res) {
                layer.close(load);
                res.ok ? layer.msg('已重启', { icon: 1 }) : layer.msg(res.error, { icon: 2 });
            });
        });
    });

    $('.btn-npmi').on('click', function () {
        var id = $(this).closest('tr').data('id');
        layer.confirm('在应用目录执行 <b>npm install</b> 并重启应用？<br>请先通过「文件」上传 package.json。', {
            title: 'npm install'
        }, function (idx) {
            layer.close(idx);
            var load = layer.load(2, { time: 300000 });
            WP.post('/sites/node-npmi', { id: id }).then(function (res) {
                layer.close(load);
                res.ok ? layer.msg('安装完成并已重启', { icon: 1 }) : layer.alert(res.error, { icon: 2 });
            });
        });
    });

    /* ---------- switch php ---------- */
    $('.phpsel').on('change', function () {
        var sel = this, id = $(this).data('id'), ver = this.value;
        layer.confirm('将该站点切换到 <b>' + $(this).find('option:selected').text() + '</b>？切换期间有短暂中断。', {
            title: '切换 PHP 版本'
        }, function (idx) {
            layer.close(idx);
            var load = layer.load(2);
            WP.post('/sites/php', { id: id, php_version: ver }).then(function (res) {
                layer.close(load);
                if (!res.ok) { layer.msg(res.error, { icon: 2 }); sel.selectedIndex = sel.dataset.oldIdx || 0; return; }
                layer.msg('已切换', { icon: 1 });
            });
        });
    });

    /* ---------- delete site ---------- */
    $('.btn-del').on('click', function () {
        var $tr = $(this).closest('tr'), id = $tr.data('id');
        var domain = $tr.find('td:eq(1) b').text();
        layer.open({
            type: 1, title: '删除网站：' + domain, area: ['500px', '300px'],
            content: '<form style="padding:20px">'
                + '<div style="color:#ff5722;margin-bottom:12px">该操作不可恢复，请谨慎确认。</div>'
                + '<div><input type="checkbox" id="dPurge" style="vertical-align:middle"> <label for="dPurge">同时删除网站文件（/www/wwwroot 下的目录）</label></div>'
                + '<div style="margin-top:8px"><input type="checkbox" id="dDropDb" style="vertical-align:middle"> <label for="dDropDb">同时删除关联的数据库</label></div>'
                + '</form>',
            btn: ['确认删除', '取消'],
            yes: function (idx) {
                WP.post('/sites/delete', {
                    id: id,
                    purge: $('#dPurge').prop('checked') ? '1' : '',
                    drop_db: $('#dDropDb').prop('checked') ? '1' : ''
                }).then(function (res) {
                    if (res.ok) { layer.close(idx); layer.msg('已删除', { icon: 1 }); $tr.remove(); }
                    else { layer.alert(res.error, { icon: 2 }); }
                });
            }
        });
    });

    /* ---------- wordpress deploy ---------- */
    $('.btn-wp').on('click', function () {
        var id = $(this).closest('tr').data('id');
        var domain = $(this).closest('tr').find('td:eq(1) b').text();

        // site rows (incl. database list) embedded by the view at page bottom
        var SITES = window.__SITES__ || {};
        layer.open({
            type: 1, title: '一键部署 WordPress：' + domain, area: ['600px', '580px'],
            content: $('#tpl-wp').html(),
            success: function (layero) {
                var f = layero.find('#wpForm')[0];
                f.id.value = id;
                f.new_db_name.value = 'wp_' + domain.split('.')[0].replace(/[^a-z0-9_]/gi, '').substring(0, 12);
                f.new_db_user.value = (f.new_db_name.value + '_u').substring(0, 24);
                var dbs = (SITES[id] && SITES[id].db_list) || [];
                var sel = layero.find('.wp-dbsource');
                dbs.forEach(function (d) {
                    sel.append('<option value="' + d.id + '">' + d.name + '（' + d.username + '，将重置密码）</option>');
                });
                sel.on('change', function () {
                    layero.find('.wp-newdb').toggle(this.value === 'new');
                });

                layero.find('#btnDoWp').on('click', function () {
                    if (!f.wp_admin.value.trim() || !f.wp_email.value.trim()) {
                        layer.msg('请填写管理员账号和邮箱', { icon: 2 }); return;
                    }
                    var data = {
                        id: id,
                        db_id: sel.val() === 'new' ? 0 : sel.val(),
                        new_db_name: f.new_db_name.value.trim(),
                        new_db_user: f.new_db_user.value.trim(),
                        wp_title: f.wp_title.value.trim(),
                        wp_admin: f.wp_admin.value.trim(),
                        wp_email: f.wp_email.value.trim()
                    };
                    layer.confirm('部署会在网站根目录下载 WordPress，根目录必须为空。确认继续？', function (c) {
                        layer.close(c);
                        var load = layer.load(2, { time: 120000 });
                        WP.post('/sites/wp', data).then(function (res) {
                            layer.close(load);
                            if (!res.ok) { layer.alert(res.error, { icon: 2, title: '部署失败' }); return; }
                            var html = '<div style="padding:10px 20px">'
                                + '<p>WordPress 部署完成！</p>'
                                + '<div class="secret-box">'
                                + '<div>后台地址：<a href="' + layui.util.escape(res.admin_url) + '" target="_blank"><b>' + layui.util.escape(res.admin_url) + '</b></a></div>'
                                + '<div>管理员：<b>' + layui.util.escape(res.wp_admin) + '</b></div>'
                                + '<div>密码：<span class="v mono" id="secWpPw">' + layui.util.escape(res.wp_password) + '</span></div>'
                                + '<div style="margin-top:10px">数据库：<b>' + layui.util.escape(res.db_name) + '</b> / ' + layui.util.escape(res.db_user) + '</div>'
                                + '<div>数据库密码：<span class="v mono" id="secDbPw2">' + layui.util.escape(res.db_password) + '</span></div>'
                                + '<div style="margin-top:8px"><button class="layui-btn layui-btn-xs" id="cp1">复制WP密码</button> '
                                + '<button class="layui-btn layui-btn-xs" id="cp2">复制数据库密码</button></div>'
                                + '<div style="color:#ff5722;font-size:12px;margin-top:6px">密码仅显示这一次，请立即保存！建议随后在 SSL 页面签发证书并启用 HTTPS。</div>'
                                + '</div></div>';
                            layer.open({
                                type: 1, title: '部署成功', area: ['560px', 'auto'], btn: ['完成'],
                                content: html,
                                success: function () {
                                    $('#cp1').on('click', function () { WP.copy($('#secWpPw').text()).then(function () { layer.msg('已复制', { icon: 1 }); }); });
                                    $('#cp2').on('click', function () { WP.copy($('#secDbPw2').text()).then(function () { layer.msg('已复制', { icon: 1 }); }); });
                                }
                            });
                        });
                    });
                });
            }
        });
    });
});
</script>
<?php
// expose site rows to the WP modal (db list) without a separate endpoint
echo '<script>window.__SITES__=' . json_encode(array_column($sites, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
?>
