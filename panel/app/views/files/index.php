<?php
/** @var array $sites @var array|null $selected */
$siteId = $selected['id'] ?? 0;
?>
<div class="panel-card">
    <h3>
        文件管理
        <div style="float:right">
            <select id="siteSelect" lay-ignore style="height:30px;width:260px">
                <option value="0">— 请选择站点 —</option>
                <?php foreach ($sites as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) $siteId === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= e($s['domain']) ?>（/www/wwwroot/<?= e($s['sysuser']) ?>）
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </h3>

    <?php if (!$siteId): ?>
    <div style="text-align:center;color:#999;padding:60px">请先在右上角选择一个站点</div>
    <?php else: ?>
    <div style="margin-bottom:12px">
        <button class="layui-btn layui-btn-sm" id="btnUpload"><span class="layui-icon layui-icon-upload"></span> 上传到当前目录</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnNewFile">新建文件</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnNewDir">新建文件夹</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnRefresh"><span class="layui-icon layui-icon-refresh"></span> 刷新</button>
        <input type="file" id="fileInput" style="display:none">
    </div>

    <div style="margin-bottom:10px;font-size:13px">
        当前目录：<span class="mono" id="crumb" style="color:#1e9fff;cursor:pointer">/</span>
        <span style="color:#999;margin-left:12px" class="mono">站点根目录：/www/wwwroot/<?= e($selected['sysuser']) ?></span>
    </div>

    <table class="layui-table" style="margin:0">
        <thead>
        <tr><th width="36"></th><th>名称</th><th width="110">大小</th><th width="90">权限</th><th width="160">修改时间</th><th width="370">操作</th></tr>
        </thead>
        <tbody id="fileBody">
        <tr><td colspan="6" style="text-align:center;color:#999;padding:30px">加载中...</td></tr>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if ($siteId): ?>
<script>
layui.use(['layer', 'upload'], function () {
    var layer = layui.layer, $ = layui.$;
    var SITE = <?= (int) $siteId ?>;
    var curPath = '/';

    function joinPath(dir, name) {
        if (dir === '/') return '/' + name;
        return dir.replace(/\/$/, '') + '/' + name;
    }

    function dirOf(p) {
        var i = p.lastIndexOf('/');
        return i <= 0 ? '/' : p.substring(0, i);
    }

    function refresh() { load(curPath); }

    function load(path) {
        curPath = path;
        $('#crumb').text(path === '/' ? '/' + '' : path || '/');
        WP.post('/files/list', { site_id: SITE, path: path }).then(function (res) {
            if (res.ok) {
                curPath = res.path || path;
                $('#crumb').text(curPath);
                render(res.entries || []);
            } else {
                $('#fileBody').html('<tr><td colspan="6" style="color:#ff5722;padding:20px">' + layui.util.escape(res.error) + '</td></tr>');
            }
        });
    }

    function iconOf(t, n) {
        if (t === 'dir') return '<span class="layui-icon" style="color:#ffb800">&#xe64e;</span>';
        if (/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(n)) return '<span class="layui-icon" style="color:#16baaa">&#xe64d;</span>';
        if (/\.(php|html?|js|css|json)$/i.test(n)) return '<span class="layui-icon" style="color:#1e9fff">&#xe64d;</span>';
        return '<span class="layui-icon">&#xe64d;</span>';
    }

    var EDITABLE = /(\.(php|txt|html?|css|js|json|xml|ya?ml|ini|conf|log|md|sql|svg|po|mo)$|(^|\/)\.htaccess$)/i;
    var ARCHIVE = /\.(zip|tar\.gz|tgz)$/i;

    function render(entries) {
        var rows = '';
        if (curPath !== '/') {
            rows += '<tr class="is-dir" data-name=".."><td></td><td style="cursor:pointer;color:#1e9fff">..</td>'
                 + '<td></td><td></td><td></td><td></td></tr>';
        }
        entries.forEach(function (f) {
            var p = joinPath(curPath, f.name);
            var nameHtml = f.type === 'dir'
                ? '<span style="cursor:pointer;color:#1e9fff" class="go">' + layui.util.escape(f.name) + '</span>'
                : layui.util.escape(f.name);
            var size = f.type === 'dir' ? '-' : (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB'
                : f.size > 1024 ? (f.size / 1024).toFixed(1) + ' KB' : f.size + ' B');
            var acts = '';
            if (f.type !== 'dir' && EDITABLE.test(f.name)) {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-primary act-edit">编辑</button> ';
            }
            if (f.type !== 'dir' && ARCHIVE.test(f.name)) {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-normal act-extract">解压</button> ';
            }
            if (f.type !== 'dir') {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-primary act-dl">下载</button> ';
            }
            acts += '<button class="layui-btn layui-btn-xs act-rename">重命名</button> '
                 +  '<button class="layui-btn layui-btn-xs act-chmod">权限</button> '
                 +  '<button class="layui-btn layui-btn-xs layui-btn-danger act-del">删除</button>';
            rows += '<tr data-path="' + layui.util.escape(p) + '" data-name="' + layui.util.escape(f.name) + '" data-type="' + f.type + '">'
                 +  '<td>' + iconOf(f.type, f.name) + '</td>'
                 +  '<td>' + nameHtml + '</td>'
                 +  '<td class="mono" style="font-size:12px">' + size + '</td>'
                 +  '<td class="mono" style="font-size:12px">' + layui.util.escape(f.perms) + '</td>'
                 +  '<td class="mono" style="font-size:12px">' + layui.util.escape(f.mtime) + '</td>'
                 +  '<td>' + acts + '</td></tr>';
        });
        if (!rows) rows = '<tr><td colspan="6" style="text-align:center;color:#999;padding:30px">空目录</td></tr>';
        $('#fileBody').html(rows);
    }

    /* navigation */
    $('#fileBody').on('click', '.go, .is-dir', function (e) {
        var $tr = $(this).closest('tr');
        if ($tr.data('name') === '..') { load(dirOf(curPath)); return; }
        if ($(e.target).hasClass('go') || $(this).hasClass('is-dir')) {
            if ($tr.data('type') === 'dir') load(joinPath(curPath, $tr.data('name')));
        }
    });
    $('#crumb').on('click', function () { load('/'); });
    $('#btnRefresh').on('click', refresh);

    /* edit */
    $('#fileBody').on('click', '.act-edit', function () {
        var p = $(this).closest('tr').data('path');
        var loadI = layer.load(2);
        WP.post('/files/read', { site_id: SITE, path: p }).then(function (res) {
            layer.close(loadI);
            if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
            layer.open({
                type: 1, title: '编辑：' + p, area: ['820px', '600px'],
                content: '<div style="padding:14px"><textarea id="editorArea" class="layui-textarea mono" style="height:460px">'
                    + layui.util.escape(res.content) + '</textarea></div>',
                btn: ['保存', '取消'],
                yes: function (idx) {
                    WP.post('/files/write', { site_id: SITE, path: p, content: $('#editorArea').val() })
                        .then(function (r) {
                            if (r.ok) { layer.close(idx); layer.msg('已保存', { icon: 1 }); }
                            else { layer.alert(r.error, { icon: 2 }); }
                        });
                }
            });
        });
    });

    /* extract (zip / tar.gz / tgz) into the current directory */
    $('#fileBody').on('click', '.act-extract', function () {
        var $tr = $(this).closest('tr'), p = $tr.data('path');
        layer.confirm(
            '将 <b>' + layui.util.escape($tr.data('name')) + '</b> 解压到<strong>当前目录</strong>（与压缩包同级）？<br>' +
            '<span style="color:#ff5722">已存在的同名文件将被覆盖。</span>',
            { title: '解压确认' },
            function (idx) {
                var loadI = layer.load(2);
                WP.post('/files/extract', { site_id: SITE, path: p }).then(function (r) {
                    layer.close(loadI);
                    if (r.ok) {
                        layer.close(idx);
                        layer.msg('已解压 ' + (r.extracted || 0) + ' 个条目到当前目录', { icon: 1 });
                        refresh();
                    } else {
                        layer.alert(r.error || '解压失败', { icon: 2, title: '解压失败' });
                    }
                });
            }
        );
    });

    /* download */
    $('#fileBody').on('click', '.act-dl', function () {
        var p = $(this).closest('tr').data('path');
        var url = '/files/download?site_id=' + SITE + '&path=' + encodeURIComponent(p) + '&_csrf=' + WP.csrf;
        var a = document.createElement('a');
        a.href = url; a.download = ''; document.body.appendChild(a); a.click(); a.remove();
    });

    /* rename */
    $('#fileBody').on('click', '.act-rename', function () {
        var $tr = $(this).closest('tr'), p = $tr.data('path'), name = $tr.data('name');
        layer.prompt({ title: '重命名', value: name, formType: 0 }, function (val, idx) {
            if (!/^[A-Za-z0-9._ -]+$/.test(val)) { layer.msg('名称含非法字符', { icon: 2 }); return; }
            WP.post('/files/rename', { site_id: SITE, path: p, to: joinPath(dirOf(p), val) }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已重命名', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });

    /* chmod */
    $('#fileBody').on('click', '.act-chmod', function () {
        var $tr = $(this).closest('tr'), p = $tr.data('path');
        var cur = $tr.find('td:eq(3)').text().trim().replace(/^0?/, '');
        layer.prompt({ title: '权限（三位八进制，如 644 / 755）', value: cur.slice(-3), formType: 0 }, function (val, idx) {
            if (!/^[0-7]{3}$/.test(val)) { layer.msg('格式不正确', { icon: 2 }); return; }
            WP.post('/files/chmod', { site_id: SITE, path: p, mode: val }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已修改', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });

    /* delete */
    $('#fileBody').on('click', '.act-del', function () {
        var $tr = $(this).closest('tr'), p = $tr.data('path');
        layer.confirm('删除 <b>' + layui.util.escape($tr.data('name')) + '</b>？' +
            ($tr.data('type') === 'dir' ? '<br><span style="color:#ff5722">目录内所有内容将被递归删除。</span>' : ''), {
            title: '删除确认'
        }, function (idx) {
            WP.post('/files/delete', { site_id: SITE, path: p }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已删除', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });

    /* new file / dir */
    $('#btnNewFile').on('click', function () {
        layer.prompt({ title: '在当前目录新建文件（相对名称）', value: 'new.txt' }, function (val, idx) {
            if (!/^[A-Za-z0-9._ -]+$/.test(val)) { layer.msg('名称含非法字符', { icon: 2 }); return; }
            WP.post('/files/write', { site_id: SITE, path: joinPath(curPath, val), content: '' }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已创建', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });
    $('#btnNewDir').on('click', function () {
        layer.prompt({ title: '在当前目录新建文件夹', value: 'newdir' }, function (val, idx) {
            if (!/^[A-Za-z0-9._ -]+$/.test(val)) { layer.msg('名称含非法字符', { icon: 2 }); return; }
            WP.post('/files/mkdir', { site_id: SITE, path: joinPath(curPath, val) }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已创建', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });

    /* upload */
    $('#btnUpload').on('click', function () { $('#fileInput').trigger('click'); });
    $('#fileInput').on('change', function () {
        var f = this.files[0];
        if (!f) return;
        var fd = new FormData();
        fd.append('site_id', SITE);
        fd.append('path', curPath);
        fd.append('file', f);
        var load = layer.load(2);
        fetch('/files/upload', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                layer.close(load);
                if (res.ok) { layer.msg('上传完成：' + res.name + '（' + res.size + '）', { icon: 1 }); refresh(); }
                else { layer.alert(res.error, { icon: 2, title: '上传失败' }); }
            });
        this.value = '';
    });

    load('/');
});
</script>
<?php endif; ?>
