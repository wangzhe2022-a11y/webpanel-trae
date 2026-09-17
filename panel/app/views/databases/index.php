<?php /** @var array $databases */ ?>
<div class="panel-card">
    <h3>
        数据库（MySQL / PostgreSQL）
        <button class="layui-btn layui-btn-sm" style="float:right" id="btnCreateDb">
            <span class="layui-icon layui-icon-add-1"></span> 创建数据库
        </button>
    </h3>
    <table class="layui-table" style="margin:0">
        <thead>
        <tr>
            <th width="50">ID</th>
            <th>数据库名</th>
            <th>用户名</th>
            <th width="90">引擎</th>
            <th>所属站点</th>
            <th width="170">创建时间</th>
            <th width="220">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$databases): ?>
            <tr><td colspan="7" style="text-align:center;color:#999;padding:40px">暂无数据库</td></tr>
        <?php endif; ?>
        <?php foreach ($databases as $d): ?>
        <tr data-id="<?= (int) $d['id'] ?>">
            <td><?= (int) $d['id'] ?></td>
            <td class="mono"><b><?= e($d['name']) ?></b></td>
            <td class="mono"><?= e($d['username']) ?></td>
            <td>
                <?= ($d['engine'] ?? 'mysql') === 'postgres'
                    ? '<span class="layui-badge layui-bg-cyan">PostgreSQL</span>'
                    : '<span class="layui-badge layui-bg-blue">MySQL</span>' ?>
            </td>
            <td><?= $d['site_domain'] ? '<span class="layui-badge-rim">' . e($d['site_domain']) . '</span>' : '<span style="color:#999">未关联</span>' ?></td>
            <td class="mono" style="font-size:12px"><?= e($d['created_at']) ?></td>
            <td>
                <button class="layui-btn layui-btn-xs btn-pw">改密</button>
                <button class="layui-btn layui-btn-xs layui-btn-danger btn-del">删除</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script type="text/html" id="tpl-createdb">
<form style="padding:20px 20px 0" id="createDbForm">
    <div class="layui-form-item">
        <label class="layui-form-label">数据库引擎 <span style="color:red">*</span></label>
        <div class="layui-input-block">
            <select name="engine" lay-ignore class="layui-input" style="width:260px">
                <option value="mysql">MySQL 8（WordPress 默认）</option>
                <option value="postgres">PostgreSQL 16</option>
            </select>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">数据库名 <span style="color:red">*</span></label>
        <div class="layui-input-block"><input name="name" class="layui-input" placeholder="字母/数字/下划线，2-64 位，建议 wp_ 开头"></div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">用户名 <span style="color:red">*</span></label>
        <div class="layui-input-block"><input name="username" class="layui-input" placeholder="字母/数字/下划线，2-32 位"></div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">关联站点</label>
        <div class="layui-input-block">
            <select name="site_id" lay-ignore class="layui-input">
                <option value="0">不关联</option>
                <?php foreach ($this->sites() as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= e($s['domain']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div style="color:#999;font-size:12px;padding-left:110px">
        MySQL 主机：<b>localhost</b>；PostgreSQL 主机：<b>127.0.0.1:5432</b>。密码自动生成。
    </div>
</form>
</script>

<script>
layui.use(['layer'], function () {
    var layer = layui.layer, $ = layui.$;

    function showSecret(title, name, user, pass, engine) {
        var host = engine === 'postgres' ? '127.0.0.1:5432' : 'localhost';
        layer.open({
            type: 1, title: title, area: ['480px', 'auto'], btn: ['我已保存'],
            content: '<div style="padding:18px 20px">'
                + '<div class="secret-box">'
                + '<div>数据库：<b>' + layui.util.escape(name) + '</b></div>'
                + '<div>用户名：<b>' + layui.util.escape(user) + '</b></div>'
                + '<div>主机：<b>' + host + '</b></div>'
                + '<div>密码：<span class="v mono" id="secPw">' + layui.util.escape(pass) + '</span></div>'
                + '<div style="margin-top:8px"><button class="layui-btn layui-btn-xs" id="btnCp">复制密码</button></div>'
                + '<div style="color:#ff5722;font-size:12px;margin-top:6px">密码仅显示这一次，关闭后无法找回。</div>'
                + '</div></div>',
            success: function () {
                $('#btnCp').on('click', function () {
                    WP.copy($('#secPw').text()).then(function () { layer.msg('已复制', { icon: 1 }); });
                });
            },
            yes: function (i) { layer.close(i); location.reload(); }
        });
    }

    $('#btnCreateDb').on('click', function () {
        layer.open({
            type: 1, title: '创建数据库', area: ['520px', '400px'],
            content: $('#tpl-createdb').html(),
            btn: ['创建', '取消'],
            yes: function (idx, layero) {
                var f = layero.find('#createDbForm')[0];
                if (!f.name.value.trim() || !f.username.value.trim()) {
                    layer.msg('请填写数据库名和用户名', { icon: 2 }); return;
                }
                WP.post('/databases/create', {
                    engine: f.engine.value,
                    name: f.name.value.trim(),
                    username: f.username.value.trim(),
                    site_id: f.site_id.value
                }).then(function (res) {
                    if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
                    layer.close(idx);
                    showSecret('创建成功', res.name, res.username, res.password, res.engine);
                });
            }
        });
    });

    $('.btn-pw').on('click', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        layer.confirm('将重置该数据库用户的密码，使用旧密码的网站（wp-config.php）需要同步更新。确认？', {
            title: '重置数据库密码'
        }, function (idx) {
            layer.close(idx);
            WP.post('/databases/passwd', { id: id }).then(function (res) {
                if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
                showSecret('密码已重置', res.name, res.username, res.password);
            });
        });
    });

    $('.btn-del').on('click', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        var name = $tr.find('td:eq(1)').text();
        layer.confirm('数据库 <b>' + name + '</b> 中的全部数据将被永久删除，确认？', {
            title: '删除数据库'
        }, function (idx) {
            layer.close(idx);
            WP.post('/databases/delete', { id: id }).then(function (res) {
                if (res.ok) { layer.msg('已删除', { icon: 1 }); $tr.remove(); }
                else { layer.alert(res.error, { icon: 2 }); }
            });
        });
    });
});
</script>
