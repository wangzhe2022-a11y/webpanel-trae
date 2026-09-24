<?php
/** @var array $sites @var array $sitesClient @var array|null $selected @var string $defaultPath @var bool $vdbSelected */
$vdbSelected = !empty($vdbSelected);
$siteId = $vdbSelected ? 'vdb' : (int) ($selected['id'] ?? 0);
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<style>
    .fm-card { display: flex; flex-direction: column; min-height: calc(100vh - 92px); padding-bottom: 12px; }
    .fm-card > h3 { overflow: hidden; margin-bottom: 10px; }
    .fm-site-switch { float: right; display: flex; align-items: center; gap: 8px; font-weight: 400; font-size: 13px; color: #666; }
    .fm-site-switch select { height: 30px; max-width: 280px; }
    .fm-toolbar { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .fm-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 13px; }
    .fm-nav .layui-btn { margin: 0; }
    .fm-pathbox { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 220px; }
    .fm-pathbox input { flex: 1; height: 30px; line-height: 30px; border: 1px solid #e6e6e6; border-radius: 2px; padding: 0 8px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; }
    .fm-crumb { color: #666; }
    .fm-crumb a { color: #1e9fff; }
    .fm-crumb .sep { color: #bbb; margin: 0 2px; }
    .fm-split { flex: 1; display: flex; min-height: 380px; border: 1px solid #e6e6e6; border-radius: 4px; overflow: hidden; background: #fff; }
    .fm-tree { width: 260px; min-width: 200px; flex: 0 0 auto; background: #f7f8fa; display: flex; flex-direction: column; }
    .fm-splitter {
        display: block; align-self: stretch; width: 6px; flex: 0 0 6px;
        margin: 0; padding: 0; border: 0; height: auto; min-height: 0;
        font-size: 0; line-height: 0; background: #e6e6e6;
        cursor: col-resize; position: relative; z-index: 2;
        touch-action: none; user-select: none; appearance: none; -webkit-appearance: none;
    }
    .fm-splitter:hover, .fm-splitter:focus-visible, body.fm-resizing .fm-splitter { background: #1e9fff; }
    body.fm-resizing, body.fm-resizing * { cursor: col-resize !important; user-select: none !important; }
    .fm-tree-head { display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; border-bottom: 1px solid #ececec; font-size: 12px; color: #666; background: #f0f2f5; }
    .fm-tree-body { flex: 1; overflow: auto; padding: 6px 0 12px; }
    .fm-tree-ul { list-style: none; margin: 0; padding: 0 0 0 14px; }
    .fm-tree-ul.root { padding-left: 6px; }
    .fm-tree-row { display: flex; align-items: center; gap: 4px; padding: 3px 8px 3px 4px; border-radius: 3px; cursor: pointer; white-space: nowrap; user-select: none; }
    .fm-tree-row:hover { background: #e8f3ff; }
    .fm-tree-row.active { background: #d4ebff; font-weight: 600; }
    .fm-twist { width: 16px; color: #888; font-size: 12px; text-align: center; flex-shrink: 0; }
    .fm-twist.empty { visibility: hidden; }
    .fm-main { flex: 1; overflow: auto; min-width: 0; }
    .fm-table { margin: 0 !important; }
    .fm-table thead th { position: sticky; top: 0; background: #f8f8f8; z-index: 1; }
    .fm-table td, .fm-table th { font-size: 13px; }
    .fm-empty { text-align: center; color: #666; padding: 72px 20px; }
    .fm-empty .layui-icon { font-size: 42px; color: #c0c4cc; display: block; margin-bottom: 12px; }
    .fm-root-hint { color: #999; font-size: 12px; margin-left: 4px; }
    .fm-vdb-banner {
        margin: 0 0 8px; padding: 8px 12px; border-radius: 4px;
        background: #fff7e6; border: 1px solid #ffe58f; color: #8c6d1f; font-size: 12.5px;
    }
    .fm-card.fm-vdb .fm-write { display: none !important; }
    @media (max-width: 800px) {
        .fm-split { flex-direction: column; }
        .fm-tree { width: 100% !important; min-width: 0; flex-basis: auto !important; max-height: 200px; border-bottom: 1px solid #e6e6e6; }
        .fm-splitter { display: none; }
    }
</style>

<div class="panel-card fm-card<?= $vdbSelected ? ' fm-vdb' : '' ?>">
    <h3>
        文件管理
        <div class="fm-site-switch">
            <span>位置</span>
            <select id="siteSelect" lay-ignore title="切换浏览位置">
                <?php foreach ($sites as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (!$vdbSelected && $siteId === (int) $s['id']) ? 'selected' : '' ?>>
                    <?= e($s['domain']) ?>（<?= e($s['sysuser']) ?>）
                </option>
                <?php endforeach; ?>
                <option value="vdb" <?= $vdbSelected ? 'selected' : '' ?>>vdb (/mnt/backup)</option>
            </select>
        </div>
    </h3>

    <div class="fm-vdb-banner" id="vdbBanner"<?= $vdbSelected ? '' : ' hidden' ?>>
        只读浏览 CVM 备份盘 <span class="mono">/mnt/backup</span>：可列表、下载、解压；不可上传、编辑、新建、重命名、改权限、删除或压缩。
    </div>
    <div class="fm-toolbar">
        <button class="layui-btn layui-btn-sm fm-write" id="btnUpload"><span class="layui-icon layui-icon-upload"></span> 上传</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary fm-write" id="btnNewFile">新建文件</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary fm-write" id="btnNewDir">新建文件夹</button>
        <button class="layui-btn layui-btn-sm layui-btn-normal" id="btnExtract" title="解压选中的压缩包">
            <span class="layui-icon layui-icon-screen-full"></span> 解压
        </button>
        <button class="layui-btn layui-btn-sm layui-btn-normal fm-write" id="btnCompress" title="压缩选中的文件/文件夹">
            <span class="layui-icon layui-icon-screen-restore"></span> 压缩
        </button>
        <button class="layui-btn layui-btn-sm layui-btn-danger fm-write" id="btnDelSel" title="删除勾选的项目">删除</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary" id="btnRefresh"><span class="layui-icon layui-icon-refresh"></span> 刷新</button>
        <input type="file" id="fileInput" style="display:none">
    </div>

    <div class="fm-nav">
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnHome" title="<?= $vdbSelected ? '备份盘根目录' : '站点根目录' ?>"><?= $vdbSelected ? '备份盘根目录' : '站点根目录' ?></button>
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnUp" title="上一级">上一级</button>
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnBack" title="后退">后退</button>
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnFwd" title="前进">前进</button>
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnReloadNav" title="刷新当前目录">刷新</button>
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnSelAllNav">全选</button>
        <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnUnselAll">取消全选</button>
        <div class="fm-pathbox">
            <input id="pathInput" spellcheck="false" autocomplete="off" title="当前路径">
            <button class="layui-btn layui-btn-xs" id="btnGo">转到</button>
        </div>
    </div>
    <div class="fm-crumb" style="margin-bottom:8px;font-size:13px">
        当前目录：<span id="crumb"></span>
        <span class="fm-root-hint mono" id="rootHint"></span>
    </div>

    <div class="fm-split">
        <aside class="fm-tree">
            <div class="fm-tree-head">
                <span>目录</span>
                <button class="layui-btn layui-btn-xs layui-btn-primary" id="btnCollapseAll">折叠全部</button>
            </div>
            <div class="fm-tree-body">
                <ul class="fm-tree-ul root" id="treeRoot"></ul>
            </div>
        </aside>
        <button type="button" class="fm-splitter" id="fmSplitter" role="separator"
                aria-orientation="vertical" aria-valuemin="200" aria-valuemax="800"
                aria-label="拖动调整目录栏宽度" title="拖动调整目录栏宽度"></button>
        <div class="fm-main">
            <table class="layui-table fm-table">
                <thead>
                <tr>
                    <th width="36"><input type="checkbox" id="selAll" title="全选"></th>
                    <th width="36"></th>
                    <th>名称</th>
                    <th width="80">类型</th>
                    <th width="110">大小</th>
                    <th width="90">权限</th>
                    <th width="160">修改时间</th>
                    <th width="370">操作</th>
                </tr>
                </thead>
                <tbody id="fileBody">
                <tr><td colspan="8" style="text-align:center;color:#999;padding:30px">加载中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
layui.use(['layer', 'upload'], function () {
    var layer = layui.layer, $ = layui.$;
    var SITES = <?= json_encode($sitesClient, $jsFlags) ?>;
    var SITE = <?= json_encode($siteId, $jsFlags) ?>;
    var DEFAULT_PATH = <?= json_encode($defaultPath, $jsFlags) ?>;
    var curPath = '/';
    var listGen = 0;
    var hist = [];
    var histIdx = -1;
    var suppressHist = false;
    var expanded = { '/': true };
    var treeKids = {};

    function siteOf(id) {
        var key = String(id);
        for (var i = 0; i < SITES.length; i++) {
            if (String(SITES[i].id) === key) return SITES[i];
        }
        return SITES[0] || null;
    }
    function isVdb() {
        var s = currentSite();
        return !!(s && s.root === 'vdb');
    }
    function refuseIfVdb() {
        if (!isVdb()) return false;
        layer.msg('vdb（/mnt/backup）为只读，不允许此操作', { icon: 0 });
        return true;
    }
    function applyRootMode() {
        var vdb = isVdb();
        $('.fm-card').toggleClass('fm-vdb', vdb);
        if (vdb) $('#vdbBanner').removeAttr('hidden');
        else $('#vdbBanner').attr('hidden', 'hidden');
        $('#btnHome').text(vdb ? '备份盘根目录' : '站点根目录')
            .attr('title', vdb ? '备份盘根目录 /mnt/backup' : '站点根目录');
    }
    function esc(s) { return layui.util.escape(String(s == null ? '' : s)); }
    function joinPath(dir, name) {
        if (dir === '/') return '/' + name;
        return dir.replace(/\/$/, '') + '/' + name;
    }
    function dirOf(p) {
        var i = String(p).lastIndexOf('/');
        return i <= 0 ? '/' : p.substring(0, i);
    }
    function normPath(p) {
        p = String(p || '/').replace(/\\/g, '/');
        if (p.indexOf('..') !== -1) return null;
        if (p.charAt(0) !== '/') p = '/' + p;
        p = p.replace(/\/+/g, '/');
        if (p.length > 1) p = p.replace(/\/$/, '');
        return p || '/';
    }
    function rememberSite(id) {
        try { localStorage.setItem('wp.files.lastSite', String(id)); } catch (e) {}
        document.cookie = 'wp_files_site=' + encodeURIComponent(String(id)) + ';path=/;samesite=lax;max-age=31536000';
    }
    function rememberPath(id, path) {
        try { localStorage.setItem('wp.files.lastPath.' + id, path); } catch (e) {}
    }
    function lastPath(id) {
        try { return localStorage.getItem('wp.files.lastPath.' + id) || ''; } catch (e) { return ''; }
    }

    /* directory-tree width: drag the splitter; persist like lastSite / lastPath */
    var TREE_W_KEY = 'wp.files.treeWidth';
    var TREE_W_MIN = 200;
    var TREE_W_MAX = 800;
    var TREE_W_DEFAULT = 260;
    var TREE_MAIN_MIN = 320;
    function treeNarrow() {
        return window.matchMedia && window.matchMedia('(max-width: 800px)').matches;
    }
    function clampTreeW(w) {
        var split = document.querySelector('.fm-split');
        var max = TREE_W_MAX;
        if (split) {
            max = Math.min(TREE_W_MAX, Math.max(TREE_W_MIN, split.clientWidth - TREE_MAIN_MIN));
        }
        w = parseInt(w, 10);
        if (!isFinite(w)) w = TREE_W_DEFAULT;
        return Math.max(TREE_W_MIN, Math.min(max, w));
    }
    function readTreeW() {
        try {
            var raw = localStorage.getItem(TREE_W_KEY);
            if (raw) return clampTreeW(raw);
        } catch (e) {}
        return TREE_W_DEFAULT;
    }
    function writeTreeW(w) {
        try { localStorage.setItem(TREE_W_KEY, String(w)); } catch (e) {}
    }
    function applyTreeW(w, persist) {
        var tree = document.querySelector('.fm-tree');
        var handle = document.getElementById('fmSplitter');
        if (!tree) return 0;
        if (treeNarrow()) {
            tree.style.width = '';
            tree.style.flexBasis = '';
            if (handle) handle.removeAttribute('aria-valuenow');
            return 0;
        }
        w = clampTreeW(w);
        tree.style.width = w + 'px';
        tree.style.flexBasis = w + 'px';
        if (handle) handle.setAttribute('aria-valuenow', String(w));
        if (persist) writeTreeW(w);
        return w;
    }
    function bindTreeResize() {
        var handle = document.getElementById('fmSplitter');
        var tree = document.querySelector('.fm-tree');
        if (!handle || !tree) return;
        applyTreeW(readTreeW(), false);
        var dragging = false, startX = 0, startW = 0;
        function pointX(e) {
            if (e.touches && e.touches[0]) return e.touches[0].clientX;
            if (e.changedTouches && e.changedTouches[0]) return e.changedTouches[0].clientX;
            return e.clientX;
        }
        function onMove(e) {
            if (!dragging) return;
            applyTreeW(startW + (pointX(e) - startX), false);
            if (e.cancelable) e.preventDefault();
        }
        function onUp() {
            if (!dragging) return;
            dragging = false;
            document.body.classList.remove('fm-resizing');
            applyTreeW(tree.getBoundingClientRect().width, true);
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onUp);
        }
        function onDown(e) {
            if (treeNarrow() || (e.button != null && e.button !== 0)) return;
            dragging = true;
            startX = pointX(e);
            startW = tree.getBoundingClientRect().width;
            document.body.classList.add('fm-resizing');
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onUp);
            if (e.cancelable) e.preventDefault();
        }
        handle.addEventListener('mousedown', onDown);
        handle.addEventListener('touchstart', onDown, { passive: false });
        handle.addEventListener('dblclick', function () { applyTreeW(TREE_W_DEFAULT, true); });
        handle.addEventListener('keydown', function (e) {
            if (treeNarrow()) return;
            var cur = tree.getBoundingClientRect().width;
            if (e.key === 'ArrowLeft') { applyTreeW(cur - 16, true); e.preventDefault(); }
            else if (e.key === 'ArrowRight') { applyTreeW(cur + 16, true); e.preventDefault(); }
            else if (e.key === 'Home') { applyTreeW(TREE_W_MIN, true); e.preventDefault(); }
            else if (e.key === 'End') { applyTreeW(TREE_W_MAX, true); e.preventDefault(); }
        });
        window.addEventListener('resize', function () {
            applyTreeW(treeNarrow() ? TREE_W_DEFAULT : (parseInt(tree.style.width, 10) || readTreeW()), false);
        });
    }
    function currentSite() { return siteOf(SITE); }
    function defaultFor(id) {
        var s = siteOf(id);
        return s ? s.defaultPath : '/';
    }
    function updateUrl() {
        try { history.replaceState(null, '', '/files?site=' + encodeURIComponent(String(SITE))); } catch (e) {}
    }
    function updateRootHint() {
        var s = currentSite();
        if (!s) return;
        if (s.root === 'vdb') {
            $('#rootHint').text('备份盘：/mnt/backup　只读（可下载 / 解压）');
            return;
        }
        var sub = s.type === 'node' ? 'app' : 'public';
        $('#rootHint').text('站点根目录：/www/wwwroot/' + s.sysuser + '　默认：/' + sub);
    }
    function renderCrumb(path) {
        var html = '<a href="javascript:;" data-path="/">/</a>';
        if (path && path !== '/') {
            var parts = path.replace(/^\//, '').split('/');
            var acc = '';
            parts.forEach(function (p) {
                acc += '/' + p;
                html += '<span class="sep">/</span><a href="javascript:;" data-path="' + esc(acc) + '">' + esc(p) + '</a>';
            });
        }
        $('#crumb').html(html);
        $('#pathInput').val(path || '/');
    }

    function refresh() { load(curPath, { keepHist: true }); }

    function load(path, opts) {
        opts = opts || {};
        var n = normPath(path);
        if (!n) { layer.msg('路径不合法', { icon: 2 }); return; }
        var gen = ++listGen;
        WP.post('/files/list', { site_id: SITE, path: n }).then(function (res) {
            if (gen !== listGen) return;
            if (res.ok) {
                curPath = res.path || n;
                renderCrumb(curPath);
                render(res.entries || []);
                rememberPath(SITE, curPath);
                updateTreeFromList(curPath, res.entries || []);
                highlightTree(curPath);
                if (!opts.keepHist && !suppressHist) pushHist(curPath);
            } else if (opts.fallback && n !== '/') {
                load('/', { fallback: false, keepHist: opts.keepHist });
            } else {
                $('#fileBody').html('<tr><td colspan="8" style="color:#ff5722;padding:20px">' + esc(res.error) + '</td></tr>');
            }
        });
    }

    function pushHist(p) {
        hist = hist.slice(0, histIdx + 1);
        if (hist[hist.length - 1] === p) return;
        hist.push(p);
        histIdx = hist.length - 1;
    }

    function iconOf(t, n) {
        if (t === 'dir') return '<span class="layui-icon layui-icon-folder" style="color:#ffb800;font-size:18px"></span>';
        if (/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(n)) return '<span class="layui-icon layui-icon-picture" style="color:#16baaa;font-size:16px"></span>';
        if (/\.(zip|tar\.gz|tgz|gz)$/i.test(n)) return '<span class="layui-icon layui-icon-file-b" style="color:#ff5722;font-size:16px"></span>';
        if (/\.(php|html?|js|css|json)$/i.test(n)) return '<span class="layui-icon layui-icon-file" style="color:#1e9fff;font-size:16px"></span>';
        return '<span class="layui-icon layui-icon-file" style="font-size:16px"></span>';
    }
    function typeLabel(t) {
        if (t === 'dir') return '文件夹';
        if (t === 'link') return '链接';
        return '文件';
    }

    var EDITABLE = /(\.(php|txt|html?|css|js|json|xml|ya?ml|ini|conf|log|md|sql|svg|po|mo)$|(^|\/)\.htaccess$)/i;
    var ARCHIVE = /\.(zip|tar\.gz|tgz)$/i;

    function selectedNames() {
        var names = [];
        $('#fileBody input.sel:checked').each(function () {
            names.push($(this).closest('tr').attr('data-name'));
        });
        return names;
    }

    function extractPath(p, name) {
        layer.confirm(
            '将 <b>' + esc(name) + '</b> 解压到<strong>当前目录</strong>（与压缩包同级）？<br>' +
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
    }

    function render(entries) {
        var rows = '';
        $('#selAll').prop('checked', false);
        if (curPath !== '/') {
            rows += '<tr class="is-dir" data-name=".."><td></td><td></td>'
                 + '<td style="cursor:pointer;color:#1e9fff">..</td>'
                 + '<td></td><td></td><td></td><td></td><td></td></tr>';
        }
        entries.forEach(function (f) {
            var p = joinPath(curPath, f.name);
            var nameHtml = f.type === 'dir'
                ? '<span style="cursor:pointer;color:#1e9fff" class="go">' + esc(f.name) + '</span>'
                : esc(f.name);
            var size = f.type === 'dir' ? '-' : (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB'
                : f.size > 1024 ? (f.size / 1024).toFixed(1) + ' KB' : f.size + ' B');
            var acts = '';
            var vdb = isVdb();
            if (!vdb && f.type !== 'dir' && EDITABLE.test(f.name)) {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-primary act-edit">编辑</button> ';
            }
            if (f.type !== 'dir' && ARCHIVE.test(f.name)) {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-normal act-extract">解压</button> ';
            }
            if (f.type !== 'dir') {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-primary act-dl">下载</button> ';
            }
            if (!vdb) {
                acts += '<button class="layui-btn layui-btn-xs act-rename">重命名</button> '
                     +  '<button class="layui-btn layui-btn-xs act-chmod">权限</button> '
                     +  '<button class="layui-btn layui-btn-xs layui-btn-danger act-del">删除</button>';
            }
            rows += '<tr data-path="' + esc(p) + '" data-name="' + esc(f.name) + '" data-type="' + f.type + '">'
                 +  '<td><input type="checkbox" class="sel"></td>'
                 +  '<td>' + iconOf(f.type, f.name) + '</td>'
                 +  '<td>' + nameHtml + '</td>'
                 +  '<td>' + typeLabel(f.type) + '</td>'
                 +  '<td class="mono" style="font-size:12px">' + size + '</td>'
                 +  '<td class="mono fm-perms" style="font-size:12px">' + esc(f.perms) + '</td>'
                 +  '<td class="mono" style="font-size:12px">' + esc(f.mtime) + '</td>'
                 +  '<td>' + acts + '</td></tr>';
        });
        if (!rows) rows = '<tr><td colspan="8" style="text-align:center;color:#999;padding:30px">空目录</td></tr>';
        $('#fileBody').html(rows);
    }

    /* ---------- directory tree ---------- */
    function treeLabel() {
        var s = currentSite();
        if (!s) return '/';
        return s.root === 'vdb' ? 'vdb' : s.domain;
    }
    function twistHtml(path) {
        return expanded[path]
            ? '<span class="fm-twist layui-icon layui-icon-down"></span>'
            : '<span class="fm-twist layui-icon layui-icon-right"></span>';
    }
    function renderTreeNode(path, name, kids) {
        var open = !!expanded[path];
        var html = '<li data-path="' + esc(path) + '">';
        html += '<div class="fm-tree-row' + (path === curPath ? ' active' : '') + '" data-path="' + esc(path) + '">';
        html += twistHtml(path);
        html += expanded[path]
            ? '<span class="layui-icon layui-icon-folder-open" style="color:#ffb800"></span>'
            : '<span class="layui-icon layui-icon-folder" style="color:#ffb800"></span>';
        html += '<span class="fm-tname">' + esc(name) + '</span></div>';
        html += '<ul class="fm-tree-ul"' + (open ? '' : ' style="display:none"') + '>';
        if (open && kids) {
            kids.forEach(function (d) {
                var cp = path === '/' ? '/' + d.name : path + '/' + d.name;
                html += renderTreeNode(cp, d.name, treeKids[cp]);
            });
        }
        html += '</ul></li>';
        return html;
    }
    function drawTree() {
        var s = currentSite();
        var rootKids = treeKids['/'] || [];
        var html = '<li data-path="/"><div class="fm-tree-row' + (curPath === '/' ? ' active' : '') + '" data-path="/">';
        html += twistHtml('/');
        html += '<span class="layui-icon" style="color:#ffb800">&#xe68e;</span>';
        html += '<span class="fm-tname">' + esc(treeLabel()) + '</span></div>';
        html += '<ul class="fm-tree-ul"' + (expanded['/'] ? '' : ' style="display:none"') + '>';
        if (expanded['/']) {
            rootKids.forEach(function (d) {
                html += renderTreeNode('/' + d.name, d.name, treeKids['/' + d.name]);
            });
        }
        html += '</ul></li>';
        $('#treeRoot').html(html);
        if (s) {
            $('#treeRoot .fm-tree-row[data-path="/"]').attr('title',
                s.root === 'vdb' ? '/mnt/backup' : '/www/wwwroot/' + s.sysuser);
        }
    }
    function highlightTree(path) {
        $('#treeRoot .fm-tree-row').removeClass('active').each(function () {
            if ($(this).attr('data-path') === path) $(this).addClass('active');
        });
    }
    function updateTreeFromList(path, entries) {
        treeKids[path] = (entries || []).filter(function (e) { return e.type === 'dir'; });
        expanded[path] = true;
        drawTree();
    }
    function fetchDirs(path) {
        return WP.post('/files/list', { site_id: SITE, path: path }).then(function (res) {
            if (!res.ok) return [];
            var dirs = (res.entries || []).filter(function (e) { return e.type === 'dir'; });
            treeKids[res.path || path] = dirs;
            return dirs;
        });
    }
    function ancestors(path) {
        var out = ['/'];
        if (!path || path === '/') return out;
        var acc = '';
        path.replace(/^\//, '').split('/').forEach(function (p) {
            acc += '/' + p;
            out.push(acc);
        });
        return out;
    }
    function ensureTreePath(path) {
        var chain = ancestors(path);
        var i = 0;
        function next() {
            if (i >= chain.length) { drawTree(); highlightTree(curPath); return; }
            var p = chain[i++];
            expanded[p] = true;
            if (treeKids[p]) { next(); return; }
            fetchDirs(p).then(next);
        }
        next();
    }
    function toggleTree(path) {
        if (expanded[path]) {
            expanded[path] = false;
            drawTree();
            return;
        }
        expanded[path] = true;
        if (treeKids[path]) { drawTree(); return; }
        fetchDirs(path).then(function () { drawTree(); });
    }
    function resetTree() {
        expanded = { '/': true };
        treeKids = {};
        drawTree();
    }

    $('#treeRoot').on('click', '.fm-twist', function (e) {
        e.stopPropagation();
        toggleTree($(this).closest('.fm-tree-row').attr('data-path'));
    });
    $('#treeRoot').on('click', '.fm-tree-row', function () {
        var p = $(this).attr('data-path');
        expanded[p] = true;
        if (!treeKids[p]) fetchDirs(p).then(function () { drawTree(); highlightTree(p); });
        load(p);
    });
    $('#btnCollapseAll').on('click', function () {
        var keepKids = treeKids['/'];
        expanded = { '/': true };
        var next = {};
        if (keepKids) next['/'] = keepKids;
        treeKids = next;
        drawTree();
    });

    /* navigation */
    $('#fileBody').on('click', '.go, .is-dir', function (e) {
        var $tr = $(this).closest('tr');
        if ($tr.attr('data-name') === '..') { load(dirOf(curPath)); return; }
        if ($(e.target).hasClass('go') || $(this).hasClass('is-dir')) {
            if ($tr.attr('data-type') === 'dir') load(joinPath(curPath, $tr.attr('data-name')));
        }
    });
    $('#crumb').on('click', 'a', function () { load($(this).attr('data-path')); });
    $('#btnRefresh, #btnReloadNav').on('click', refresh);
    $('#btnHome').on('click', function () { load('/'); });
    $('#btnUp').on('click', function () {
        if (curPath === '/') return;
        load(dirOf(curPath));
    });
    $('#btnBack').on('click', function () {
        if (histIdx <= 0) return;
        histIdx--;
        suppressHist = true;
        load(hist[histIdx], { keepHist: true });
        suppressHist = false;
    });
    $('#btnFwd').on('click', function () {
        if (histIdx >= hist.length - 1) return;
        histIdx++;
        suppressHist = true;
        load(hist[histIdx], { keepHist: true });
        suppressHist = false;
    });
    $('#btnGo').on('click', function () { load($('#pathInput').val(), { fallback: false }); });
    $('#pathInput').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#btnGo').trigger('click'); }
    });
    $('#btnSelAllNav, #selAll').on('click', function () {
        var on = this.id === 'selAll' ? this.checked : true;
        $('#fileBody input.sel').prop('checked', on);
        $('#selAll').prop('checked', on);
    });
    $('#btnUnselAll').on('click', function () {
        $('#fileBody input.sel').prop('checked', false);
        $('#selAll').prop('checked', false);
    });

    /* edit */
    $('#fileBody').on('click', '.act-edit', function () {
        if (refuseIfVdb()) return;
        var p = $(this).closest('tr').attr('data-path');
        var loadI = layer.load(2);
        WP.post('/files/read', { site_id: SITE, path: p }).then(function (res) {
            layer.close(loadI);
            if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
            layer.open({
                type: 1, title: '编辑：' + p, area: ['820px', '600px'],
                content: '<div style="padding:14px"><textarea id="editorArea" class="layui-textarea mono" style="height:460px">'
                    + esc(res.content) + '</textarea></div>',
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

    $('#fileBody').on('click', 'input.sel', function (e) { e.stopPropagation(); });

    $('#btnExtract').on('click', function () {
        var names = selectedNames();
        if (names.length !== 1 || !ARCHIVE.test(names[0])) {
            layer.msg('请勾选一个 zip / tar.gz / tgz 压缩包', { icon: 0 });
            return;
        }
        extractPath(joinPath(curPath, names[0]), names[0]);
    });

    $('#btnCompress').on('click', function () {
        if (refuseIfVdb()) return;
        var names = selectedNames();
        if (!names.length) {
            layer.msg('请先勾选要压缩的文件或文件夹', { icon: 0 });
            return;
        }
        layer.prompt({ title: '压缩到当前目录（zip 文件名）', value: 'archive.zip' }, function (val, idx) {
            if (!/^[A-Za-z0-9._ -]+\.zip$/i.test(val)) {
                layer.msg('文件名须为 .zip，且只含字母、数字、点、下划线、空格和连字符', { icon: 2 });
                return;
            }
            var loadI = layer.load(2);
            WP.post('/files/compress', {
                site_id: SITE, path: curPath, name: val, files: JSON.stringify(names)
            }).then(function (r) {
                layer.close(loadI);
                if (r.ok) {
                    layer.close(idx);
                    layer.msg('已压缩：' + r.name + (r.size ? '（' + r.size + '）' : ''), { icon: 1 });
                    refresh();
                } else {
                    layer.alert(r.error || '压缩失败', { icon: 2, title: '压缩失败' });
                }
            });
        });
    });

    $('#btnDelSel').on('click', function () {
        if (refuseIfVdb()) return;
        var names = selectedNames();
        if (!names.length) {
            layer.msg('请先勾选要删除的文件或文件夹', { icon: 0 });
            return;
        }
        layer.confirm('删除选中的 <b>' + names.length + '</b> 个项目？<br><span style="color:#ff5722">目录将递归删除。</span>', {
            title: '删除确认'
        }, function (idx) {
            var i = 0;
            function next() {
                if (i >= names.length) {
                    layer.close(idx);
                    layer.msg('已删除', { icon: 1 });
                    refresh();
                    return;
                }
                var name = names[i++];
                WP.post('/files/delete', { site_id: SITE, path: joinPath(curPath, name) }).then(function (r) {
                    if (!r.ok) { layer.alert(r.error, { icon: 2 }); return; }
                    next();
                });
            }
            next();
        });
    });

    /* extract (zip / tar.gz / tgz) into the current directory */
    $('#fileBody').on('click', '.act-extract', function () {
        var $tr = $(this).closest('tr');
        extractPath($tr.attr('data-path'), $tr.attr('data-name'));
    });

    /* download */
    $('#fileBody').on('click', '.act-dl', function () {
        var p = $(this).closest('tr').attr('data-path');
        var url = '/files/download?site_id=' + encodeURIComponent(String(SITE)) + '&path=' + encodeURIComponent(p) + '&_csrf=' + WP.csrf;
        var a = document.createElement('a');
        a.href = url; a.download = ''; document.body.appendChild(a); a.click(); a.remove();
    });

    /* rename */
    $('#fileBody').on('click', '.act-rename', function () {
        if (refuseIfVdb()) return;
        var $tr = $(this).closest('tr'), p = $tr.attr('data-path'), name = $tr.attr('data-name');
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
        if (refuseIfVdb()) return;
        var $tr = $(this).closest('tr'), p = $tr.attr('data-path');
        var cur = $tr.find('.fm-perms').text().trim().replace(/^0?/, '');
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
        if (refuseIfVdb()) return;
        var $tr = $(this).closest('tr'), p = $tr.attr('data-path');
        layer.confirm('删除 <b>' + esc($tr.attr('data-name')) + '</b>？' +
            ($tr.attr('data-type') === 'dir' ? '<br><span style="color:#ff5722">目录内所有内容将被递归删除。</span>' : ''), {
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
        if (refuseIfVdb()) return;
        layer.prompt({ title: '在当前目录新建文件（相对名称）', value: 'new.txt' }, function (val, idx) {
            if (!/^[A-Za-z0-9._ -]+$/.test(val)) { layer.msg('名称含非法字符', { icon: 2 }); return; }
            WP.post('/files/write', { site_id: SITE, path: joinPath(curPath, val), content: '' }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已创建', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });
    $('#btnNewDir').on('click', function () {
        if (refuseIfVdb()) return;
        layer.prompt({ title: '在当前目录新建文件夹', value: 'newdir' }, function (val, idx) {
            if (!/^[A-Za-z0-9._ -]+$/.test(val)) { layer.msg('名称含非法字符', { icon: 2 }); return; }
            WP.post('/files/mkdir', { site_id: SITE, path: joinPath(curPath, val) }).then(function (r) {
                if (r.ok) { layer.close(idx); layer.msg('已创建', { icon: 1 }); refresh(); }
                else { layer.alert(r.error, { icon: 2 }); }
            });
        });
    });

    /* upload */
    $('#btnUpload').on('click', function () {
        if (refuseIfVdb()) return;
        $('#fileInput').trigger('click');
    });
    $('#fileInput').on('change', function () {
        var f = this.files[0];
        if (!f) return;
        var fd = new FormData();
        fd.append('site_id', SITE);
        fd.append('path', curPath);
        fd.append('file', f);
        var loadI = layer.load(2);
        WP.post('/files/upload', fd).then(function (res) {
            layer.close(loadI);
            if (res.ok) { layer.msg('上传完成：' + res.name + '（' + res.size + '）', { icon: 1 }); refresh(); }
            else { layer.alert(res.error, { icon: 2, title: '上传失败' }); }
        });
        this.value = '';
    });

    $('#siteSelect').on('change', function () {
        var raw = this.value;
        var s = siteOf(raw);
        if (!s || String(s.id) === String(SITE)) return;
        switchSite(s.id);
    });

    function switchSite(id) {
        var s = siteOf(id);
        if (!s) return;
        SITE = s.id;
        rememberSite(SITE);
        updateUrl();
        $('#siteSelect').val(String(SITE));
        applyRootMode();
        updateRootHint();
        resetTree();
        hist = [];
        histIdx = -1;
        var start = lastPath(SITE) || defaultFor(SITE);
        load(start, { fallback: true });
        ensureTreePath(start === '/' ? '/' : dirOf(start));
    }

    function boot() {
        var urlSite = <?= json_encode((string) ($_GET['site'] ?? ''), $jsFlags) ?>;
        if (!urlSite) {
            var last = '';
            try { last = localStorage.getItem('wp.files.lastSite') || ''; } catch (e) { last = ''; }
            var lastSite = last ? siteOf(last) : null;
            if (lastSite && String(lastSite.id) !== String(SITE)) {
                SITE = lastSite.id;
                $('#siteSelect').val(String(SITE));
            }
        }
        rememberSite(SITE);
        updateUrl();
        applyRootMode();
        updateRootHint();
        resetTree();
        var start = lastPath(SITE) || defaultFor(SITE) || DEFAULT_PATH;
        load(start, { fallback: true });
        ensureTreePath(start === '/' ? '/' : dirOf(start));
    }

    bindTreeResize();
    boot();
});
</script>
