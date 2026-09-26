<?php
/** @var array $sites @var array $phpVersions */
?>
<style>
.wp-sites-wrap { padding: 0; }
.wp-sites-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0 14px;
    flex-wrap: wrap;
}
.wp-sites-search {
    position: relative;
    flex: 0 0 260px;
}
.wp-sites-search input {
    width: 100%;
    height: 34px;
    padding: 0 12px 0 34px;
    border: 1px solid var(--wp-border);
    border-radius: 6px;
    background: var(--wp-surface);
    color: var(--wp-text);
    font-size: 13px;
    box-sizing: border-box;
}
.wp-sites-search .wp-srch-ico {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--wp-text-muted);
    font-size: 15px;
}
.wp-sites-toolbar .wp-tb-btn {
    background: transparent;
    border: 1px solid var(--wp-border);
    color: var(--wp-text-secondary);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.wp-sites-toolbar .wp-tb-btn:hover { background: var(--wp-surface-soft); }
.wp-sites-add {
    margin-left: auto;
    background: #ff6b35 !important;
    border: 1px solid #ff6b35 !important;
    color: #fff !important;
    font-weight: 600;
}
.wp-sites-add:hover { background: #e85a28 !important; }
.wp-sites-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--wp-border);
    margin-bottom: 0;
}
.wp-sites-tab {
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    color: var(--wp-text-secondary);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    margin-bottom: -1px;
}
.wp-sites-tab.is-active {
    color: #ff6b35;
    border-bottom-color: #ff6b35;
}
.wp-sites-table { width: 100%; border-collapse: collapse; margin: 0; }
.wp-sites-table thead th {
    text-align: left;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 600;
    color: var(--wp-text-muted);
    text-transform: uppercase;
    letter-spacing: .03em;
    border-bottom: 1px solid var(--wp-border);
    background: var(--wp-surface-soft);
    position: sticky;
    top: 0;
}
.wp-sites-table tbody td {
    padding: 12px;
    font-size: 13px;
    color: var(--wp-text);
    border-bottom: 1px solid var(--wp-border);
    vertical-align: middle;
}
.wp-sites-table tbody tr:hover td { background: var(--wp-surface-soft); }
.wp-sites-row .wp-site-cell { display: flex; align-items: center; gap: 10px; }
.wp-sites-row .wp-site-folder {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: #2a2f3a;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.wp-sites-row .wp-site-folder svg { width: 18px; height: 18px; }
.wp-sites-row .wp-site-name {
    font-weight: 600;
    color: var(--wp-text);
}
.wp-sites-row .wp-site-link {
    color: var(--wp-text-muted);
    font-size: 14px;
    text-decoration: none;
    margin-left: 4px;
}
.wp-sites-row.is-sub .wp-site-cell { padding-left: 36px; }
.wp-site-ssl {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}
.wp-site-ssl-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #16a34a;
}
.wp-site-ssl-dot.off { background: #94a3b8; }
.wp-site-manage {
    background: var(--wp-surface-soft);
    border: 1px solid var(--wp-border);
    color: var(--wp-text);
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.wp-site-manage:hover { background: var(--wp-accent-soft); }
.wp-sites-pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    font-size: 12px;
    color: var(--wp-text-muted);
}
.wp-sites-pager .pager-btns { display: flex; gap: 6px; }
.wp-sites-pager button {
    min-width: 30px;
    height: 30px;
    border: 1px solid var(--wp-border);
    background: var(--wp-surface);
    color: var(--wp-text);
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
}
.wp-sites-pager button:disabled { opacity: .4; cursor: not-allowed; }
.wp-sites-ops-popup {
    position: absolute;
    z-index: 100;
    background: var(--wp-surface);
    border: 1px solid var(--wp-border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
    padding: 6px;
    display: none;
    min-width: 160px;
}
.wp-sites-ops-popup button {
    display: block;
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    color: var(--wp-text);
    padding: 7px 10px;
    border-radius: 5px;
    font-size: 13px;
    cursor: pointer;
}
.wp-sites-ops-popup button:hover { background: var(--wp-surface-soft); }
.wp-sites-ops-popup button.danger { color: #dc2626; }

/* Create-site: PHP / Node.js type tabs */
.wp-site-type-tabs {
    display: flex;
    border-bottom: 1px solid var(--wp-border);
    margin: 4px 0 18px;
    gap: 4px;
}
.wp-site-type-tabs .wp-tab {
    padding: 10px 20px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--wp-text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color .2s, border-color .2s;
    user-select: none;
}
.wp-site-type-tabs .wp-tab:hover { color: var(--wp-text-secondary); }
.wp-site-type-tabs .wp-tab.active {
    color: var(--wp-accent);
    border-bottom-color: var(--wp-accent);
}

/* Create-site: Node.js fields (no overflow, theme-aware) */
#nodeFields {
    background: var(--wp-surface-soft);
    border: 1px solid var(--wp-border);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 14px;
    box-sizing: border-box;
    max-width: 100%;
}
.wp-node-row {
    display: flex;
    gap: 14px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.wp-node-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.wp-node-field:first-child { flex: 0 0 120px; }
.wp-node-field.wp-node-flex { flex: 1 1 200px; min-width: 160px; }
.wp-node-label {
    font-size: 13px;
    color: var(--wp-text-secondary);
    font-weight: 500;
}
.wp-node-field .layui-input { width: 100%; box-sizing: border-box; }
.wp-node-hint {
    margin-top: 12px;
    font-size: 12px;
    line-height: 1.6;
    color: var(--wp-text-muted);
}
.wp-node-hint .mono { font-family: ui-monospace, Menlo, Consolas, monospace; color: var(--wp-text-secondary); }

html[data-theme="dark"] #nodeFields {
    background: rgba(144, 186, 30, 0.06);
    border-color: rgba(144, 186, 30, 0.2);
}

/* Create-site: "同时创建数据库" checkbox */
.wp-db-chk-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 4px 0 12px;
    cursor: pointer;
}
.wp-db-chk {
    display: inline-block !important;
    width: 16px;
    height: 16px;
    accent-color: var(--wp-accent);
    cursor: pointer;
    flex-shrink: 0;
}
.wp-db-chk-label {
    font-size: 13px;
    color: var(--wp-text-secondary);
    cursor: pointer;
    user-select: none;
}

/* Create-site: database fields panel — theme-aware */
#dbFields {
    background: var(--wp-surface-soft);
    border: 1px solid var(--wp-border);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 14px;
}
#dbFields .layui-form-label {
    color: var(--wp-text-secondary);
    font-size: 13px;
}
#dbFields .layui-input {
    background: var(--wp-surface);
    border-color: var(--wp-border);
    color: var(--wp-text);
}
#dbFields .wp-db-hint {
    color: var(--wp-text-muted);
    font-size: 12px;
    padding-left: 110px;
}
html[data-theme="dark"] #dbFields {
    background: rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.1);
}
html[data-theme="dark"] #dbFields .layui-input {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
    color: #fff;
}

/* PHP version select — modern dark-theme dropdown */
.phpsel {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    height: 28px;
    font-size: 12px;
    padding: 0 26px 0 10px;
    border-radius: 6px;
    border: 1px solid var(--wp-border);
    background-color: var(--wp-surface-soft);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 12px;
    color: var(--wp-text);
    cursor: pointer;
    color-scheme: dark;
    transition: border-color .2s, background-color .2s;
    outline: none;
}
.phpsel:hover { border-color: var(--wp-accent); }
.phpsel:focus {
    border-color: var(--wp-accent);
    box-shadow: 0 0 0 2px rgba(144, 186, 30, 0.2);
}
.phpsel option {
    background-color: var(--wp-surface);
    color: var(--wp-text);
}

/* Popup selects also use dark color-scheme for the option panel */
.layui-layer-content select {
    color-scheme: dark;
}
html[data-theme="dark"] .layui-layer-content select {
    background-color: rgba(0, 0, 0, 0.35);
    border-color: rgba(255, 255, 255, 0.16);
    color: #fff;
}
</style>
<div class="panel-card wp-sites-wrap">
    <div class="wp-sites-toolbar">
        <div class="wp-sites-search">
            <span class="layui-icon layui-icon-search wp-srch-ico"></span>
            <input id="siteFilter" type="search" placeholder="Filter by domain…" autocomplete="off">
        </div>
        <button type="button" class="wp-tb-btn" id="btnMinAll">
            <span class="layui-icon layui-icon-shrink-right"></span> Minimize All
        </button>
        <button type="button" class="wp-tb-btn" id="btnColumns">
            <span class="layui-icon layui-icon-list"></span> Columns
        </button>
        <button type="button" class="wp-tb-btn wp-sites-add" id="btnCreate">
            <span class="layui-icon layui-icon-add-1"></span> Add Website
        </button>
    </div>

    <div class="wp-sites-tabs" role="tablist">
        <button type="button" class="wp-sites-tab is-active" data-tab="all" role="tab">All</button>
        <button type="button" class="wp-sites-tab" data-tab="subdomains" role="tab">Subdomains</button>
    </div>

    <div style="overflow-x:auto">
    <table class="wp-sites-table">
        <thead>
        <tr>
            <th style="width:40%"></th>
            <th style="width:10%">Apps</th>
            <th style="width:10%">Features</th>
            <th style="width:12%">SSL</th>
            <th style="width:10%">PHP</th>
            <th style="width:18%"></th>
        </tr>
        </thead>
        <tbody id="siteBody">
        <?php if (!$sites): ?>
            <tr><td colspan="6" style="text-align:center;color:#999;padding:40px">
                No websites yet. Click <b>Add Website</b> to get started.
            </td></tr>
        <?php endif; ?>
        <?php foreach ($sites as $s): ?>
        <?php
            $isNode = ($s['type'] ?? 'php') === 'node';
            $aliases = array_filter(array_map('trim', explode(',', (string) $s['aliases'])), function ($a) {
                return $a !== '' && stripos($a, 'www.') !== 0;
            });
            $phpLabel = $phpVersions[(string) $s['php_version']] ?? ($isNode ? 'Node' : 'PHP');
            $sslOn = (int) $s['ssl'] === 1;
            $folderSvg = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#64748b" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';
        ?>
        <tr class="wp-sites-row" data-id="<?= (int) $s['id'] ?>" data-domain="<?= e($s['domain']) ?>" data-type="<?= $isNode ? 'node' : 'php' ?>">
            <td>
                <div class="wp-site-cell">
                    <span class="wp-site-folder"><?= $folderSvg ?></span>
                    <span class="wp-site-name"><?= e($s['domain']) ?></span>
                    <a href="http://<?= e($s['domain']) ?>" target="_blank" class="wp-site-link" title="Open in new tab">
                        <span class="layui-icon layui-icon-link"></span>
                    </a>
                </div>
            </td>
            <td>
                <?= $isNode ? '<span class="layui-badge layui-bg-cyan">Node</span>' : '<span class="layui-badge layui-bg-green">PHP</span>' ?>
            </td>
            <td>
                <?php if (!$isNode): ?>
                <select class="phpsel" lay-ignore data-id="<?= (int) $s['id'] ?>" style="height:28px;font-size:12px">
                    <?php foreach ($phpVersions as $v => $label): ?>
                        <option value="<?= e($v) ?>" <?= (string) $s['php_version'] === (string) $v ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <span class="mono" style="font-size:12px">port <?= (int) $s['app_port'] ?></span>
                <?php endif; ?>
            </td>
            <td>
                <span class="wp-site-ssl">
                    <span class="wp-site-ssl-dot<?= $sslOn ? '' : ' off' ?>"></span>
                    <?= $sslOn ? 'Valid' : 'Not SSL' ?>
                </span>
            </td>
            <td class="mono" style="font-size:13px"><?= $isNode ? '—' : e($phpLabel) ?></td>
            <td style="text-align:right">
                <button type="button" class="wp-site-manage btn-manage" data-id="<?= (int) $s['id'] ?>">
                    <span class="layui-icon layui-icon-set"></span> Manage
                </button>
            </td>
        </tr>
        <?php foreach ($aliases as $alias): ?>
        <tr class="wp-sites-row is-sub" data-id="<?= (int) $s['id'] ?>" data-domain="<?= e($alias) ?>" data-type="<?= $isNode ? 'node' : 'php' ?>" data-parent="<?= (int) $s['id'] ?>">
            <td>
                <div class="wp-site-cell">
                    <span class="wp-site-folder"><?= $folderSvg ?></span>
                    <span class="wp-site-name"><?= e($alias) ?></span>
                    <a href="http://<?= e($alias) ?>" target="_blank" class="wp-site-link" title="Open in new tab">
                        <span class="layui-icon layui-icon-link"></span>
                    </a>
                </div>
            </td>
            <td><?= $isNode ? '<span class="layui-badge layui-bg-cyan">Node</span>' : '<span class="layui-badge layui-bg-green">PHP</span>' ?></td>
            <td></td>
            <td>
                <span class="wp-site-ssl">
                    <span class="wp-site-ssl-dot<?= $sslOn ? '' : ' off' ?>"></span>
                    <?= $sslOn ? 'Valid' : 'Not SSL' ?>
                </span>
            </td>
            <td class="mono" style="font-size:13px"><?= $isNode ? '—' : e($phpLabel) ?></td>
            <td style="text-align:right">
                <button type="button" class="wp-site-manage btn-manage" data-id="<?= (int) $s['id'] ?>">
                    <span class="layui-icon layui-icon-set"></span> Manage
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="wp-sites-pager">
        <div class="pager-btns">
            <button type="button" id="pagerPrev" disabled>&lsaquo;</button>
            <button type="button" id="pagerNum" disabled>1</button>
            <button type="button" id="pagerNext" disabled>&rsaquo;</button>
        </div>
        <div>
            Rows
            <select id="pagerRows" style="height:26px;font-size:12px;margin:0 6px">
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span id="pagerInfo">1–1 of 1</span>
        </div>
    </div>
</div>

<!-- Manage operations popup -->
<div class="wp-sites-ops-popup" id="siteOpsPopup">
    <button type="button" data-act="files"><span class="layui-icon layui-icon-file"></span> 文件管理</button>
    <button type="button" data-act="wp"><span class="layui-icon layui-icon-template"></span> 部署 WordPress</button>
    <button type="button" data-act="ssl"><span class="layui-icon layui-icon-auz"></span> SSL 证书</button>
    <button type="button" data-act="npmi" style="display:none"><span class="layui-icon layui-icon-download"></span> npm install</button>
    <button type="button" data-act="nrestart" style="display:none"><span class="layui-icon layui-icon-refresh"></span> 重启应用</button>
    <button type="button" data-act="delete" class="danger"><span class="layui-icon layui-icon-delete"></span> 删除</button>
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
    <div class="wp-site-type-tabs">
        <div class="wp-tab active" data-type="php">PHP 网站</div>
        <div class="wp-tab" data-type="node">Node.js 应用</div>
    </div>
    <input type="hidden" name="type" value="php" id="siteTypeInput">
    <div id="phpFields">
        <div class="layui-form-item">
            <label class="layui-form-label">PHP 版本</label>
            <div class="layui-input-inline" style="width:190px">
                <select name="php_version" lay-ignore class="layui-input">
                    <?php foreach ($phpVersions as $v => $label): ?>
                    <option value="<?= e($v) ?>" <?= $v === '82' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="layui-form-label" style="width:auto;padding:9px 8px">并发数</label>
            <div class="layui-input-inline" style="width:90px">
                <input name="max_children" class="layui-input" value="20">
            </div>
        </div>
    </div>
    <div id="nodeFields" style="display:none">
        <div class="wp-node-row">
            <div class="wp-node-field">
                <label class="wp-node-label">应用端口</label>
                <input name="app_port" class="layui-input" value="3000" placeholder="3000">
            </div>
            <div class="wp-node-field wp-node-flex">
                <label class="wp-node-label">启动命令</label>
                <input name="start_cmd" class="layui-input" value="npm start" placeholder="npm start">
            </div>
        </div>
        <div class="wp-node-hint">
            代码放在 <span class="mono">/www/wwwroot/&lt;站点用户&gt;/app</span>（创建后用「文件」上传，或先上传 package.json 再点 npm i）；
            应用只需监听 <span class="mono">127.0.0.1:&lt;端口&gt;</span>，Nginx 自动反代并支持 WebSocket。
        </div>
    </div>
    <div class="wp-db-chk-row">
        <input type="checkbox" name="with_db" value="1" lay-ignore id="withDbChk" class="wp-db-chk">
        <label for="withDbChk" class="wp-db-chk-label">同时创建数据库（部署 WordPress 建议勾选）</label>
    </div>
    <div id="dbFields" style="display:none">
        <div class="layui-form-item">
            <label class="layui-form-label">数据库引擎</label>
            <div class="layui-input-block">
                <select name="db_engine" lay-ignore class="phpsel" style="width:220px;height:38px;font-size:13px">
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
        <div class="wp-db-hint">密码将自动生成并仅显示一次</div>
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
                $(document).off('click.wptype').on('click.wptype', '.wp-site-type-tabs .wp-tab', function () {
                    var $tab = $(this);
                    if ($tab.hasClass('active')) return;
                    $tab.addClass('active').siblings('.wp-tab').removeClass('active');
                    var type = $tab.data('type');
                    $('#siteTypeInput').val(type);
                    var isNode = type === 'node';
                    $('#phpFields').toggle(!isNode);
                    $('#nodeFields').toggle(isNode);
                    $('#createForm select[name="db_engine"]').val(isNode ? 'postgres' : 'mysql');
                });
                $('#btnDoCreate').on('click', function () {
                    var f = $('#createForm')[0];
                    var type = $('#siteTypeInput').val();
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

    /* ---------- Manage popup ---------- */
    var $popup = $('#siteOpsPopup');
    var curManageId = null;
    var curManageType = 'php';
    var curManageDomain = '';

    function openManagePopup(btn) {
        var $tr = $(btn).closest('tr');
        curManageId = $tr.data('id');
        curManageType = $tr.data('type') || 'php';
        curManageDomain = $tr.data('domain') || '';
        // show/hide node-specific actions
        $popup.find('[data-act="npmi"]').toggle(curManageType === 'node');
        $popup.find('[data-act="nrestart"]').toggle(curManageType === 'node');
        $popup.find('[data-act="wp"]').toggle(curManageType !== 'node');
        var rect = btn.getBoundingClientRect();
        $popup.css({
            display: 'block',
            top: (window.scrollY + rect.bottom + 4) + 'px',
            left: (window.scrollX + rect.right - 170) + 'px'
        });
    }
    function closeManagePopup() { $popup.hide(); }

    $(document).on('click', '.btn-manage', function (e) {
        e.stopPropagation();
        var id = $(this).data('id');
        if (id) window.location.href = '/files?site=' + id;
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#siteOpsPopup, .btn-manage').length) closeManagePopup();
    });

    $popup.on('click', 'button', function () {
        var act = this.getAttribute('data-act');
        var id = curManageId;
        var domain = curManageDomain;
        closeManagePopup();
        if (act === 'files') {
            window.location.href = '/files?site=' + id;
        } else if (act === 'ssl') {
            window.location.href = '/ssl';
        } else if (act === 'npmi') {
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
        } else if (act === 'nrestart') {
            layer.confirm('重启该 Node.js 应用？', { title: '重启应用' }, function (idx) {
                layer.close(idx);
                var load = layer.load(2);
                WP.post('/sites/node-svc', { id: id, action: 'restart' }).then(function (res) {
                    layer.close(load);
                    res.ok ? layer.msg('已重启', { icon: 1 }) : layer.msg(res.error, { icon: 2 });
                });
            });
        } else if (act === 'delete') {
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
                        if (res.ok) { layer.close(idx); layer.msg('已删除', { icon: 1 }); location.reload(); }
                        else { layer.alert(res.error, { icon: 2 }); }
                    });
                }
            });
        } else if (act === 'wp') {
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
        }
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

    /* ---------- tabs: All / Subdomains ---------- */
    $('.wp-sites-tab').on('click', function () {
        $('.wp-sites-tab').removeClass('is-active');
        $(this).addClass('is-active');
        var tab = this.getAttribute('data-tab');
        if (tab === 'subdomains') {
            $('.wp-sites-row:not(.is-sub)').hide();
            $('.wp-sites-row.is-sub').show();
        } else {
            $('.wp-sites-row').show();
        }
    });

    /* ---------- search filter ---------- */
    $('#siteFilter').on('input', function () {
        var q = this.value.trim().toLowerCase();
        $('.wp-sites-row').each(function () {
            var domain = ($(this).data('domain') || '').toString().toLowerCase();
            $(this).toggle(domain.indexOf(q) !== -1);
        });
    });

    /* ---------- Minimize All (collapse subdomains) ---------- */
    var subsCollapsed = false;
    $('#btnMinAll').on('click', function () {
        subsCollapsed = !subsCollapsed;
        $('.wp-sites-row.is-sub').toggle(!subsCollapsed);
        $(this).html(subsCollapsed
            ? '<span class="layui-icon layui-icon-spread-left"></span> Expand All'
            : '<span class="layui-icon layui-icon-shrink-right"></span> Minimize All');
    });

    /* ---------- Columns (placeholder) ---------- */
    $('#btnColumns').on('click', function () {
        layer.msg('列设置功能开发中', { icon: 0 });
    });
});
</script>
<?php
// expose site rows to the WP modal (db list) without a separate endpoint
echo '<script>window.__SITES__=' . json_encode(array_column($sites, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
?>
