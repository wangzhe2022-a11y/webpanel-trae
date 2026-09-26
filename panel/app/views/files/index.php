<?php
/** @var array $sites @var array $sitesClient @var array|null $selected @var string $defaultPath @var bool $vdbSelected */
$vdbSelected = !empty($vdbSelected);
$siteId = $vdbSelected ? 'vdb' : (int) ($selected['id'] ?? 0);
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<style>
    .fm-card { display: flex; flex-direction: column; min-height: calc(100vh - 92px); padding-bottom: 12px; width: 100%; box-sizing: border-box; min-width: 0; max-width: 100%; overflow: hidden; }
    .fm-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 8px; }
    .fm-nav { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-start; gap: 6px; margin-bottom: 8px; font-size: 13px; }
    .fm-nav .layui-btn { margin: 0; }
    /* ~1/3 of the former full-bleed path row; left-aligned under the toolbar. */
    .fm-pathbox { display: flex; align-items: center; gap: 6px; flex: 0 1 33%; width: 33%; max-width: 33%; min-width: 180px; box-sizing: border-box; }
    .fm-pathbox input { flex: 1 1 auto; min-width: 0; height: 30px; line-height: 30px; border: 1px solid var(--wp-border); border-radius: 2px; padding: 0 8px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; }
    .fm-site-switch { flex: 0 0 auto; min-width: 0; padding: 8px 10px; border-bottom: 1px solid var(--wp-border); background: #ffffff; }
    .fm-site-switch label {
        display: block; margin: 0 0 5px; font-size: 11px; line-height: 1; color: var(--wp-text-secondary);
        letter-spacing: 0.02em;
    }
    .fm-site-switch select {
        display: block; width: 100%; max-width: 100%; min-width: 0; height: 32px; box-sizing: border-box;
        padding: 0 28px 0 10px; border: 1px solid var(--wp-border); border-radius: 6px;
        background-color: #f8fafc; color: var(--wp-text); font-size: 12.5px; line-height: 30px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer;
        appearance: none; -webkit-appearance: none; -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 8px center; background-size: 12px;
    }
    .fm-site-switch select:hover { border-color: #cbd5e1; background-color: #ffffff; }
    .fm-site-switch select:focus { outline: none; border-color: #90BA1E; box-shadow: 0 0 0 2px rgba(144,186,30,.18); }
    .fm-split { flex: 1; display: flex; min-height: 380px; border: 1px solid var(--wp-border); border-radius: 4px; overflow: hidden; background: var(--wp-surface); min-width: 0; }
    .fm-tree { width: 260px; min-width: 0; flex: 0 0 260px; background: #ffffff; display: flex; flex-direction: column; overflow: hidden; }
    .fm-splitter {
        display: block; align-self: stretch; width: 6px; flex: 0 0 6px;
        margin: 0; padding: 0; border: 0; height: auto; min-height: 0;
        font-size: 0; line-height: 0; background: var(--wp-border);
        cursor: col-resize; position: relative; z-index: 2;
        touch-action: none; user-select: none; appearance: none; -webkit-appearance: none;
    }
    .fm-splitter:hover, .fm-splitter:focus-visible, body.fm-resizing .fm-splitter { background: var(--wp-accent); }
    body.fm-resizing, body.fm-resizing * { cursor: col-resize !important; user-select: none !important; }
    .fm-tree-head { display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; border-bottom: 1px solid var(--wp-border); font-size: 11px; color: var(--wp-text-secondary); background: #ffffff; }
    .fm-tree-body { flex: 1; overflow: auto; padding: 6px 0 12px; }
    .fm-tree-ul { list-style: none; margin: 0; padding: 0 0 0 14px; }
    .fm-tree-ul.root { padding-left: 6px; }
    .fm-tree-row { display: flex; align-items: center; gap: 4px; padding: 3px 8px 3px 4px; border-radius: 3px; cursor: pointer; white-space: nowrap; user-select: none; overflow: hidden; font-size: 14px; }
    .fm-tree-row .fm-tree-name { overflow: hidden; text-overflow: ellipsis; min-width: 0; }
    .fm-tree-row:hover { background: var(--wp-accent-soft); }
    .fm-tree-row.active { background: var(--wp-accent-soft); color: var(--wp-accent); font-weight: 600; }
    .fm-twist { width: 16px; color: var(--wp-text-muted); font-size: 14px; font-weight: 700; line-height: 1; text-align: center; flex-shrink: 0; }
    .fm-twist.empty { visibility: hidden; }
    .fm-main { flex: 1; overflow: hidden; min-width: 0; display: flex; flex-direction: column; }
    .fm-main-bar { flex: 0 0 auto; padding: 8px 10px 6px; border-bottom: 1px solid var(--wp-border); background: var(--wp-surface-soft); }
    .fm-main-bar .fm-toolbar { margin-bottom: 6px; }
    .fm-main-bar .fm-nav { margin-bottom: 0; }
    .fm-main-scroll { flex: 1; overflow: auto; min-height: 0; min-width: 0; }
    .fm-table { margin: 0 !important; min-width: 100% !important; width: auto !important; table-layout: auto; }
    .fm-table thead th { position: sticky; top: 0; background: var(--wp-surface-soft); z-index: 1; white-space: nowrap; }
    .fm-table td, .fm-table th { font-size: 13px; font-family: inherit; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    /* Beat cached 12px/11px and .layui-layout-admin .layui-table 15px.
       Size/perms/mtime stay 13px inherit; 名称/类型 are 14px via .fm-col-*. */
    .wp-page-files .fm-table td,
    .wp-page-files .fm-table th,
    .wp-page-files .fm-table td.mono { font-size: 13px; font-family: inherit; }
    .wp-page-files .fm-table td.fm-col-name,
    .wp-page-files .fm-table th.fm-col-name,
    .wp-page-files .fm-table td.fm-col-type,
    .wp-page-files .fm-table th.fm-col-type { font-size: 14px; }
    .fm-table td:last-child, .fm-table th:last-child { white-space: normal; overflow: visible; }
    .fm-table tbody tr:hover { background: #dbeafe !important; box-shadow: inset 3px 0 0 #3b82f6 !important; }
    .fm-table tbody tr:hover td { background: transparent !important; }
    .fm-empty { text-align: center; color: var(--wp-text-secondary); padding: 72px 20px; }
    .fm-empty .layui-icon { font-size: 42px; color: var(--wp-text-muted); display: block; margin-bottom: 12px; }
    .fm-icon-dir { display: inline-block; vertical-align: middle; }
    .fm-ico { font-size: 16px; vertical-align: middle; }
    svg.fm-ico { display: inline-block; }
    .fm-ico-img { color: #16a34a; }
    .fm-ico-arc { color: #ea580c; }
    .fm-ico-code { color: #0891b2; }
    .fm-ico-txt { color: #64748b; }
    .fm-ico-av { color: #db2777; }
    .fm-ico-file { color: #94a3b8; }
    .fm-name-link { cursor: pointer; color: var(--wp-text); }
    .fm-vdb-banner {
        margin: 0 0 8px; padding: 8px 12px; border-radius: 4px;
        background: #fff7e6; border: 1px solid #ffe58f; color: #8c6d1f; font-size: 12.5px;
    }
    /* vdb 只读模式下保留写入按钮但置灰禁用（不再 display:none） */
    .fm-card.fm-vdb .fm-write {
        opacity: .5 !important;
        cursor: not-allowed !important;
    }
    .fm-search { position: relative; margin-left: auto; flex: 1 1 240px; min-width: 200px; max-width: 420px; }
    .fm-search input {
        width: 100%; height: 30px; line-height: 30px; box-sizing: border-box;
        border: 1px solid var(--wp-border); border-radius: 2px; padding: 0 28px 0 8px;
        background: var(--wp-surface); color: var(--wp-text); font-size: 12.5px;
    }
    .fm-search .fm-search-ico { position: absolute; right: 8px; top: 6px; color: var(--wp-text-muted); pointer-events: none; }
    .fm-search-drop {
        display: none; position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 30;
        max-height: 360px; overflow: auto; background: var(--wp-surface);
        border: 1px solid var(--wp-border); border-radius: 4px;
        box-shadow: 0 8px 24px rgba(0,0,0,.18);
    }
    .fm-search-drop.open { display: block; }
    .fm-search-item { display: block; width: 100%; text-align: left; border: 0; background: transparent; padding: 7px 10px; cursor: pointer; }
    .fm-search-item:hover, .fm-search-item.active { background: var(--wp-accent-soft); }
    .fm-search-name { font-size: 13px; color: var(--wp-text); }
    .fm-search-path { font-size: 11px; color: var(--wp-text-muted); font-family: ui-monospace, Menlo, Consolas, monospace; margin-top: 2px; }
    .fm-search-meta { padding: 6px 10px; font-size: 12px; color: var(--wp-text-secondary); border-top: 1px solid var(--wp-border); }
    .fm-search-empty { padding: 14px 10px; font-size: 12px; color: var(--wp-text-secondary); text-align: center; }
    .fm-table tbody tr.fm-hit { background: var(--wp-accent-soft); box-shadow: inset 3px 0 0 var(--wp-accent); }
    @media (max-width: 800px) {
        .fm-split { flex-direction: column; }
        .fm-tree { width: 100% !important; min-width: 0; flex-basis: auto !important; max-height: 200px; border-bottom: 1px solid var(--wp-border); }
        .fm-splitter { display: none; }
    }

    /* ===== 浅色文件管理界面（覆盖暗色主题，始终保持浅灰背景） ===== */
    .fm-card {
        background: #f0f2f5 !important;
        color: #1e293b !important;
    }
    .fm-card > h3 { color: #1e293b !important; }
    .fm-split {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        color: #1e293b !important;
    }
    .fm-tree, .fm-tree-head, .fm-site-switch {
        background: #ffffff !important;
        border-color: #e2e8f0 !important;
        color: #475569 !important;
    }
    .fm-tree-row:hover { background: #dcedc8 !important; color: #1e293b !important; }
    .fm-tree-row.active {
        background: #c5e1a5 !important;
        color: #1e293b !important;
        font-weight: 600;
    }
    .fm-twist { color: #94a3b8 !important; }
    .fm-splitter { background: #e2e8f0 !important; }
    .fm-splitter:hover, .fm-splitter:focus-visible,
    body.fm-resizing .fm-splitter { background: #90BA1E !important; }
    .fm-main-bar {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .fm-site-switch label { color: #64748b !important; }
    .fm-site-switch select, .fm-pathbox input, .fm-search input {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        color: #1e293b !important;
        color-scheme: light;
    }
    .fm-site-switch select { background-color: #f8fafc !important; }
    .fm-site-switch select:hover { background-color: #ffffff !important; border-color: #cbd5e1 !important; }
    .fm-site-switch select option {
        background: #ffffff !important;
        color: #1e293b !important;
    }
    .fm-search-ico { color: #94a3b8 !important; }
    .fm-name-link { color: #1e293b !important; }
    .fm-vdb-banner {
        background: #fff7e6 !important;
        border: 1px solid #ffe58f !important;
        color: #8c6d1f !important;
    }
    .fm-empty, .fm-empty .layui-icon { color: #94a3b8 !important; }

    /* 统一浅灰按钮风格（工具栏 + 导航 + 行内操作） */
    .fm-card .layui-btn {
        background-color: #f0f2f5 !important;
        color: #334155 !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 6px !important;
        font-weight: 500;
    }
    .fm-card .layui-btn:hover {
        background-color: #e2e8f0 !important;
        color: #1e293b !important;
        border-color: #cbd5e1 !important;
    }
    .fm-card .layui-btn-primary {
        background-color: #f0f2f5 !important;
        color: #334155 !important;
        border: 1px solid #e2e8f0 !important;
    }
    .fm-card .layui-btn-primary:hover {
        background-color: #e2e8f0 !important;
        color: #1e293b !important;
        border-color: #cbd5e1 !important;
    }
    .fm-card .layui-btn-danger {
        background-color: #fef2f2 !important;
        color: #dc2626 !important;
        border: 1px solid #fecaca !important;
    }
    .fm-card .layui-btn-danger:hover {
        background-color: #fee2e2 !important;
        color: #b91c1c !important;
        border-color: #fca5a5 !important;
    }
    .fm-card .layui-btn-normal {
        background-color: #f0f2f5 !important;
        color: #334155 !important;
        border: 1px solid #e2e8f0 !important;
    }
    .fm-card .layui-btn-normal:hover {
        background-color: #e2e8f0 !important;
        color: #1e293b !important;
        border-color: #cbd5e1 !important;
    }
    .fm-card .layui-btn[disabled],
    .fm-card .layui-btn-disabled {
        background-color: #f8fafc !important;
        color: #cbd5e1 !important;
        border-color: #e2e8f0 !important;
        cursor: not-allowed;
    }

    /* 工具栏扁平化按钮（截图 1 样式：无背景无边框，按钮间用 | 分隔） */
    .fm-card .fm-tb-btn {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        color: #475569 !important;
        padding: 4px 6px !important;
        margin: 0 !important;
        height: auto !important;
        line-height: 1.6 !important;
        font-size: 13px !important;
        font-weight: 500;
        border-radius: 4px !important;
    }
    .fm-card .fm-tb-btn:hover {
        background: #ecfccb !important;
        color: #33691e !important;
    }
    .fm-card .fm-tb-btn.fm-tb-danger {
        color: #dc2626 !important;
    }
    .fm-card .fm-tb-btn.fm-tb-danger:hover {
        background: #fef2f2 !important;
        color: #b91c1c !important;
    }
    .fm-card .fm-tb-btn[disabled] {
        background: transparent !important;
        color: #cbd5e1 !important;
        cursor: not-allowed;
    }
    .fm-card .fm-tb-btn .layui-icon { font-size: 14px; }
    .fm-card .fm-tb-btn .fm-tb-ico {
        display: inline-block;
        width: 14px;
        height: 14px;
        vertical-align: -2px;
        margin-right: 1px;
    }
    /* cPanel-like blue for Home / Up / Back / Forward / Refresh (+ select-all icons). */
    .fm-card .fm-tb-btn.fm-tb-nav .layui-icon { color: #1a73e8 !important; }
    .fm-tb-sep {
        display: inline-flex;
        align-items: center;
        color: #cbd5e1;
        font-size: 14px;
        line-height: 1;
        margin: 0 1px;
        user-select: none;
    }

    /* 文件夹图标：内联 SVG 实心金黄色，fill 已在 SVG path 中设定 */

    /* 表格：浅灰表头 + 斑马纹行（cPanel 风格交替底色） */
    .fm-table { background: #ffffff !important; color: #1e293b !important; }
    .fm-table thead th {
        background: #f8fafc !important;
        color: #475569 !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .fm-table td, .fm-table th {
        border-color: #c1c6cb !important;
        border-left: none !important;
        border-right: none !important;
        color: #1e293b !important;
    }
    .fm-table tbody tr:nth-child(odd) td { background: #ffffff !important; }
    .fm-table tbody tr:nth-child(even) td { background: #eaeaea !important; }
    .fm-table tbody tr:hover td { background: #dbeafe !important; }
    .fm-table tbody tr:hover { box-shadow: inset 3px 0 0 #3b82f6 !important; }
    .fm-table tbody tr.fm-hit td { background: #bfdbfe !important; }
    .fm-table tbody tr.fm-hit { box-shadow: inset 3px 0 0 #3b82f6 !important; }

    /* File-list checkboxes: white + slate when off, cPanel blue when on (never black). */
    .fm-table input[type="checkbox"] {
        -webkit-appearance: none;
        appearance: none;
        width: 15px;
        height: 15px;
        margin: 0;
        box-sizing: border-box;
        border: 1.5px solid #94a3b8;
        border-radius: 3px;
        background-color: #ffffff;
        background-image: none;
        cursor: pointer;
        vertical-align: middle;
        color-scheme: light;
        accent-color: #1a73e8;
    }
    .fm-table input[type="checkbox"]:hover { border-color: #1a73e8; }
    .fm-table input[type="checkbox"]:focus-visible {
        outline: 2px solid rgba(26, 115, 232, 0.35);
        outline-offset: 1px;
    }
    .fm-table input[type="checkbox"]:checked {
        background-color: #1a73e8;
        border-color: #1a73e8;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.2l3 3 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: 11px 11px;
    }

    /* 搜索下拉 */
    .fm-search-drop {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 8px 24px rgba(0,0,0,.10) !important;
    }
    .fm-search-item:hover, .fm-search-item.active { background: #dcedc8 !important; color: #33691e !important; }
    .fm-search-name { color: #1e293b !important; }
    .fm-search-path { color: #94a3b8 !important; }
    .fm-search-meta { color: #64748b !important; border-top-color: #e2e8f0 !important; }
    .fm-search-empty { color: #64748b !important; }

    /* 右键上下文菜单 */
    .fm-ctx {
        position: fixed;
        z-index: 9999;
        min-width: 150px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.15);
        padding: 4px;
        display: flex;
        flex-direction: column;
    }
    .fm-ctx[hidden] { display: none; }
    .fm-ctx-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 7px 12px;
        border: none;
        background: transparent;
        color: #334155;
        font-size: 13px;
        text-align: left;
        border-radius: 5px;
        cursor: pointer;
    }
    .fm-ctx-item:hover {
        background: #ecfccb;
        color: #33691e;
    }
    .fm-ctx-item.fm-ctx-danger { color: #dc2626; }
    .fm-ctx-item.fm-ctx-danger:hover { background: #fef2f2; color: #b91c1c; }
    .fm-ctx-item.fm-ctx-disabled {
        color: #cbd5e1 !important;
        cursor: not-allowed;
        background: transparent !important;
    }
    .fm-ctx-item.fm-ctx-disabled:hover { background: transparent !important; color: #cbd5e1 !important; }
    .fm-ctx-item .layui-icon { font-size: 14px; }

    /* Monaco 编辑器容器 */
    .fm-monaco-wrap { width: 100%; height: 520px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
    .fm-editor-status { display: flex; justify-content: space-between; align-items: center; padding: 4px 12px; font-size: 12px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 6px 6px; font-family: ui-monospace, Menlo, Consolas, monospace; }

    /* cPanel-style upload dialog */
    .wp-upload-dialog { padding: 22px 24px; }
    .wp-upload-dropzone {
        border: 2px dashed var(--wp-border);
        border-radius: 12px;
        padding: 40px 20px 32px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        background: var(--wp-surface-soft);
    }
    .wp-upload-dropzone:hover,
    .wp-upload-dropzone.is-dragover {
        border-color: var(--wp-accent);
        background: var(--wp-accent-soft);
    }
    .wp-upload-icon { margin-bottom: 12px; color: var(--wp-text-muted); line-height: 0; }
    .wp-upload-icon svg { width: 44px; height: 44px; }
    .wp-upload-text { font-size: 15px; font-weight: 600; color: var(--wp-text); margin-bottom: 6px; }
    .wp-upload-path { font-size: 12px; color: var(--wp-text-muted); }
    .wp-upload-path .wp-up-path-val { color: var(--wp-text-secondary); font-family: ui-monospace, Menlo, Consolas, monospace; }
    .wp-upload-fileinfo {
        margin-top: 14px; padding: 10px 12px; border-radius: 8px;
        background: var(--wp-surface-soft); border: 1px solid var(--wp-border);
        font-size: 13px; color: var(--wp-text); display: none; align-items: center; gap: 8px;
    }
    .wp-upload-fileinfo.show { display: flex; }
    .wp-upload-fileinfo .layui-icon { color: var(--wp-accent); flex-shrink: 0; }
    .wp-upload-overwrite {
        display: flex; align-items: center; gap: 8px;
        margin-top: 16px; font-size: 13px; color: var(--wp-text-secondary);
        cursor: pointer; user-select: none;
    }
    .wp-upload-overwrite input[type="checkbox"] { width: 16px; height: 16px; flex-shrink: 0; }

    /* Upload dialog buttons — cPanel warm-brown primary */
    .wp-upload-layer .layui-layer-btn { padding: 12px 24px; border-top: 1px solid var(--wp-border); background: transparent; }
    .wp-upload-layer .layui-layer-btn a { height: 34px; line-height: 32px; padding: 0 20px; border-radius: 8px; font-size: 13px; font-weight: 600; }
    .wp-upload-layer .layui-layer-btn .layui-layer-btn0 { background: #c2703e; border-color: #c2703e; color: #fff; }
    .wp-upload-layer .layui-layer-btn .layui-layer-btn0:hover { background: #a85e30; border-color: #a85e30; }
    .wp-upload-layer .layui-layer-btn .layui-layer-btn1 { background: var(--wp-surface-soft); border-color: var(--wp-border); color: var(--wp-text); }
    .wp-upload-layer .layui-layer-btn .layui-layer-btn1:hover { background: var(--wp-accent-soft); border-color: var(--wp-accent); color: var(--wp-accent); }
    html[data-theme="dark"] .wp-upload-layer .layui-layer-btn .layui-layer-btn1 { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.14); color: var(--wp-text); }
    html[data-theme="dark"] .wp-upload-layer .layui-layer-btn .layui-layer-btn1:hover { background: rgba(144,186,30,0.16); border-color: var(--wp-accent); color: var(--wp-accent); }
</style>

<div class="panel-card fm-card<?= $vdbSelected ? ' fm-vdb' : '' ?>">
    <div class="fm-vdb-banner" id="vdbBanner"<?= $vdbSelected ? '' : ' hidden' ?>>
        只读浏览 CVM 备份盘 <span class="mono">/mnt/backup</span>：可列表、搜索、下载、解压；不可上传、编辑、新建、重命名、改权限、删除或压缩。
    </div>

    <div class="fm-split">
        <aside class="fm-tree">
            <div class="fm-site-switch">
                <label for="siteSelect">位置</label>
                <select id="siteSelect" lay-ignore title="切换浏览位置">
                    <?php foreach ($sites as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (!$vdbSelected && $siteId === (int) $s['id']) ? 'selected' : '' ?>>
                        <?= e($s['domain']) ?>（<?= e($s['sysuser']) ?>）
                    </option>
                    <?php endforeach; ?>
                    <option value="vdb" <?= $vdbSelected ? 'selected' : '' ?>>vdb (/mnt/backup)</option>
                </select>
            </div>
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
            <div class="fm-main-bar">
                <div class="fm-toolbar">
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnHome" title="<?= $vdbSelected ? '备份盘根目录' : '站点根目录' ?>">
                        <span class="layui-icon layui-icon-home"></span> <span class="fm-tb-label"><?= $vdbSelected ? '备份盘根目录' : '站点根目录' ?></span>
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnUp" title="上一级">
                        <span class="layui-icon layui-icon-up"></span> 上一级
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnBack" title="后退">
                        <span class="layui-icon layui-icon-left"></span> 后退
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnFwd" title="前进">
                        <span class="layui-icon layui-icon-right"></span> 前进
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnRefresh" title="刷新当前目录">
                        <span class="layui-icon layui-icon-refresh"></span> 刷新
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnSelAllNav" title="全选">
                        <svg class="fm-tb-ico" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                            <rect x="1.5" y="1.5" width="13" height="13" rx="2" fill="#1a73e8"/>
                            <path d="M4.2 8.2l2.4 2.4 5.2-5.2" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        全选
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-nav" id="btnUnselAll" title="取消全选">
                        <svg class="fm-tb-ico" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                            <rect x="1.5" y="1.5" width="13" height="13" rx="2" fill="#fff" stroke="#1a73e8" stroke-width="1.6"/>
                        </svg>
                        取消全选
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-write" id="btnUpload"><span class="layui-icon layui-icon-upload"></span> 上传</button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-write" id="btnNewFile"><span class="layui-icon layui-icon-file"></span> 新建文件</button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-write" id="btnNewDir"><span class="layui-icon layui-icon-folder"></span> 新建文件夹</button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn" id="btnExtract" title="解压选中的压缩包">
                        <span class="layui-icon layui-icon-release"></span> 解压
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-write" id="btnCompress" title="压缩选中的文件/文件夹">
                        <span class="layui-icon layui-icon-404"></span> 压缩
                    </button>
                    <span class="fm-tb-sep">|</span>
                    <button type="button" class="fm-tb-btn fm-tb-danger fm-write" id="btnDelSel" title="删除勾选的项目">
                        <span class="layui-icon layui-icon-delete"></span> 删除
                    </button>
                    <div class="fm-search">
                        <input id="fmSearch" type="search" spellcheck="false" autocomplete="off"
                               placeholder="搜索当前目录及子目录…" title="按文件名搜索当前目录及子目录，支持部分匹配">
                        <span class="layui-icon layui-icon-search fm-search-ico"></span>
                        <div class="fm-search-drop" id="fmSearchDrop"></div>
                    </div>
                </div>

                <div class="fm-nav">
                    <div class="fm-pathbox">
                        <input id="pathInput" spellcheck="false" autocomplete="off" title="当前路径">
                        <button class="layui-btn layui-btn-xs" id="btnGo" title="转到" aria-label="转到">转到</button>
                    </div>
                </div>
            </div>
            <div class="fm-main-scroll">
            <table class="layui-table fm-table">
                <thead>
                <tr>
                    <th width="36"><input type="checkbox" id="selAll" title="全选"></th>
                    <th width="36"></th>
                    <th class="fm-col-name">名称</th>
                    <th class="fm-col-type" width="80">类型</th>
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
</div>

<!-- 右键上下文菜单 -->
<div class="fm-ctx" id="fmCtx" hidden>
    <button type="button" class="fm-ctx-item" data-act="edit">
        <span class="layui-icon layui-icon-edit"></span> 编辑
    </button>
    <button type="button" class="fm-ctx-item" data-act="download">
        <span class="layui-icon layui-icon-download-circle"></span> 下载
    </button>
    <button type="button" class="fm-ctx-item" data-act="extract">
        <span class="layui-icon layui-icon-release"></span> 解压
    </button>
    <button type="button" class="fm-ctx-item" data-act="rename">
        <span class="layui-icon layui-icon-rmb"></span> 重命名
    </button>
    <button type="button" class="fm-ctx-item" data-act="chmod">
        <span class="layui-icon layui-icon-key"></span> 权限
    </button>
    <button type="button" class="fm-ctx-item fm-ctx-danger" data-act="delete">
        <span class="layui-icon layui-icon-delete"></span> 删除
    </button>
</div>

<!-- Monaco Editor（VS Code 同款）：行号 / 语法高亮 / 查找替换（Ctrl+F 查找 / Ctrl+H 替换） / 多光标 -->
<script src="/static/vendor/monaco/vs/loader.js"></script>
<script>
    require.config({ paths: { 'vs': '/static/vendor/monaco/vs' } });
    // 使用静态 worker bootstrap 文件，避免 data URL worker 的跨域/同源限制
    window.MonacoEnvironment = {
        getWorkerUrl: function (workerId, label) {
            return '/static/vendor/monaco/vs/worker-bootstrap.js';
        }
    };
    // 预加载 Monaco 主模块，避免首次打开编辑器时延迟
    require(['vs/editor/editor.main'], function () {
        window.__monacoReady = true;
    });
</script>

<script>
layui.use(['layer', 'upload'], function () {
    var layer = layui.layer, $ = layui.$;
    var SITES = <?= json_encode($sitesClient, $jsFlags) ?>;
    var SITE = <?= json_encode($siteId, $jsFlags) ?>;
    var DEFAULT_PATH = <?= json_encode($defaultPath, $jsFlags) ?>;
    var curPath = '/';
    var lastEntries = [];
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
        // 只读模式保留写入按钮但禁用（置灰、不可点击）
        $('.fm-write').prop('disabled', vdb);
        if (vdb) $('#vdbBanner').removeAttr('hidden');
        else $('#vdbBanner').attr('hidden', 'hidden');
        $('#btnHome .fm-tb-label').text(vdb ? '备份盘根目录' : '站点根目录');
        $('#btnHome').attr('title', vdb ? '备份盘根目录 /mnt/backup' : '站点根目录');
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
    var TREE_MAIN_MIN = 640;
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
        var html = '';
        if (path && path !== '/') {
            // 每级路径前置一个 "/" 分隔符，即自然形成 /migration/logs 的形式，避免根 "/" 与分隔符叠加成 "//"
            var parts = path.replace(/^\//, '').split('/');
            var acc = '';
            parts.forEach(function (p) {
                acc += '/' + p;
                html += '<span class="sep">/</span><a href="javascript:;" data-path="' + esc(acc) + '">' + esc(p) + '</a>';
            });
        } else {
            html = '<a href="javascript:;" data-path="/">/</a>';
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
                if (opts.highlight) highlightRow(opts.highlight);
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

    // 实心黄色文件夹图标（cPanel 风格），统一用于表格与目录树
    function folderSvg() {
        return '<svg class="fm-icon-dir" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
             + '<path fill="#f5b841" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>'
             + '</svg>';
    }
    // 实心紫色折角文档 + 4 条白线（cPanel classic PHP）
    function phpFileSvg() {
        return '<svg class="fm-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
             + '<path fill="#7c3aed" d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6z"/>'
             + '<path fill="#a78bfa" d="M14 2v6h6"/>'
             + '<rect fill="#fff" x="6.4" y="10.3" width="9.2" height="1.3" rx=".45"/>'
             + '<rect fill="#fff" x="6.4" y="12.8" width="11" height="1.3" rx=".45"/>'
             + '<rect fill="#fff" x="6.4" y="15.3" width="10.2" height="1.3" rx=".45"/>'
             + '<rect fill="#fff" x="6.4" y="17.8" width="8.4" height="1.3" rx=".45"/>'
             + '</svg>';
    }
    // 白底描边折角文档 + 蓝色 &lt;/&gt;（cPanel classic HTML/HTM）
    function htmlFileSvg() {
        return '<svg class="fm-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
             + '<path fill="#fff" stroke="#334155" stroke-width="1.25" stroke-linejoin="round" d="M14 2.7H6.3c-.9 0-1.6.7-1.6 1.6v15.4c0 .9.7 1.6 1.6 1.6h11.4c.9 0 1.6-.7 1.6-1.6V8.7L14 2.7z"/>'
             + '<path fill="#e2e8f0" stroke="#334155" stroke-width="1.25" stroke-linejoin="round" d="M14 2.7v6h6"/>'
             + '<path fill="none" stroke="#2563eb" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="M9.2 10.6L6.7 13.5 9.2 16.4"/>'
             + '<path fill="none" stroke="#2563eb" stroke-width="1.7" stroke-linecap="round" d="M12.8 10.2L10.8 16.8"/>'
             + '<path fill="none" stroke="#2563eb" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="M14.8 10.6L17.3 13.5 14.8 16.4"/>'
             + '</svg>';
    }
    function iconOf(t, n) {
        if (t === 'dir') return folderSvg();
        if (/\.(jpg|jpeg|png|gif|webp|bmp|svg|ico)$/i.test(n)) return '<span class="layui-icon layui-icon-picture fm-ico fm-ico-img"></span>';
        if (/\.(zip|tar|gz|tgz|bz2|rar|7z|xz)$/i.test(n)) return '<span class="layui-icon layui-icon-file-b fm-ico fm-ico-arc"></span>';
        if (/\.php$/i.test(n)) return phpFileSvg();
        if (/\.html?$/i.test(n)) return htmlFileSvg();
        if (/\.(js|css|json|xml|ya?ml)$/i.test(n)) return '<span class="layui-icon layui-icon-file fm-ico fm-ico-code"></span>';
        if (/\.(txt|log|md|ini|conf|cfg|env)$/i.test(n)) return '<span class="layui-icon layui-icon-file fm-ico fm-ico-txt"></span>';
        if (/\.(mp3|wav|flac|aac|ogg|m4a)$/i.test(n)) return '<span class="layui-icon layui-icon-speaker fm-ico fm-ico-av"></span>';
        if (/\.(mp4|mkv|avi|mov|webm|flv)$/i.test(n)) return '<span class="layui-icon layui-icon-video fm-ico fm-ico-av"></span>';
        return '<span class="layui-icon layui-icon-file fm-ico fm-ico-file"></span>';
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
        lastEntries = entries || [];
        var rows = '';
        $('#selAll').prop('checked', false);
        if (curPath !== '/') {
            rows += '<tr class="is-dir" data-name=".."><td></td><td></td>'
                 + '<td class="fm-col-name fm-name-link">..</td>'
                 + '<td class="fm-col-type"></td><td></td><td></td><td></td><td></td></tr>';
        }
        lastEntries.forEach(function (f) {
            var p = joinPath(curPath, f.name);
            var nameHtml = f.type === 'dir'
                ? '<span class="go fm-name-link">' + esc(f.name) + '</span>'
                : esc(f.name);
            var size = f.type === 'dir' ? '-' : (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB'
                : f.size > 1024 ? (f.size / 1024).toFixed(1) + ' KB' : f.size + ' B');
            var acts = '';
            var vdb = isVdb();
            // 只读模式下保留所有操作按钮，但写入相关按钮置灰禁用
            var dis = vdb ? ' disabled' : '';
            if (f.type !== 'dir' && EDITABLE.test(f.name)) {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-primary act-edit"' + dis + '>编辑</button> ';
            }
            if (f.type !== 'dir' && ARCHIVE.test(f.name)) {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-normal act-extract">解压</button> ';
            }
            if (f.type !== 'dir') {
                acts += '<button class="layui-btn layui-btn-xs layui-btn-primary act-dl">下载</button> ';
            }
            acts += '<button class="layui-btn layui-btn-xs act-rename"' + dis + '>重命名</button> '
                 +  '<button class="layui-btn layui-btn-xs act-chmod"' + dis + '>权限</button> '
                 +  '<button class="layui-btn layui-btn-xs layui-btn-danger act-del"' + dis + '>删除</button>';
            rows += '<tr data-path="' + esc(p) + '" data-name="' + esc(f.name) + '" data-type="' + f.type + '">'
                 +  '<td><input type="checkbox" class="sel"></td>'
                 +  '<td>' + iconOf(f.type, f.name) + '</td>'
                 +  '<td class="fm-col-name">' + nameHtml + '</td>'
                 +  '<td class="fm-col-type">' + typeLabel(f.type) + '</td>'
                 +  '<td>' + size + '</td>'
                 +  '<td class="fm-perms">' + esc(f.perms) + '</td>'
                 +  '<td>' + esc(f.mtime) + '</td>'
                 +  '<td>' + acts + '</td></tr>';
        });
        if (!rows) rows = '<tr><td colspan="8" style="text-align:center;color:#999;padding:30px">空目录</td></tr>';
        $('#fileBody').html(rows);
    }

    function highlightRow(name) {
        $('#fileBody tr.fm-hit').removeClass('fm-hit');
        $('#fileBody tr').each(function () {
            if ($(this).attr('data-name') === name) {
                $(this).addClass('fm-hit');
                var el = this;
                setTimeout(function () {
                    if (el.scrollIntoView) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }, 0);
            }
        });
    }

    /* ---------- directory tree ---------- */
    function treeLabel() {
        var s = currentSite();
        if (!s) return '/';
        return s.root === 'vdb' ? 'vdb' : s.domain;
    }
    function twistHtml(path) {
        return expanded[path]
            ? '<span class="fm-twist">−</span>'
            : '<span class="fm-twist">+</span>';
    }
    function renderTreeNode(path, name, kids) {
        var open = !!expanded[path];
        var html = '<li data-path="' + esc(path) + '">';
        html += '<div class="fm-tree-row' + (path === curPath ? ' active' : '') + '" data-path="' + esc(path) + '">';
        html += twistHtml(path);
        html += folderSvg();
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
        html += folderSvg();
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

    /* Monaco 语言检测（monaco-editor 支持的 language id） */
    function monacoLang(name) {
        var ext = String(name || '').split('.').pop().toLowerCase();
        var map = {
            php: 'php', html: 'html', htm: 'html', js: 'javascript', mjs: 'javascript',
            cjs: 'javascript', ts: 'typescript', tsx: 'typescript', jsx: 'javascript',
            css: 'css', scss: 'scss', less: 'less', json: 'json', xml: 'xml',
            yml: 'yaml', yaml: 'yaml', md: 'markdown', markdown: 'markdown',
            sql: 'sql', svg: 'xml', sh: 'shell', bash: 'shell', zsh: 'shell',
            py: 'python', rb: 'ruby', go: 'go', java: 'java',
            c: 'cpp', cpp: 'cpp', h: 'cpp', hpp: 'cpp', cc: 'cpp',
            cs: 'csharp', rs: 'rust', swift: 'swift', vue: 'html',
            ini: 'ini', conf: 'ini', env: 'ini', cfg: 'ini',
            dockerfile: 'dockerfile', tf: 'ini', toml: 'ini',
            lua: 'lua', pl: 'perl', r: 'r', kt: 'kotlin', scala: 'scala'
        };
        if (name === '.htaccess') return 'ini';
        return map[ext] || 'plaintext';
    }

    var fmMonacoEditor = null;
    function openEditor(p, content) {
        var lang = monacoLang(p.split('/').pop());
        // 先打开 dialog，再在 success 中等待 Monaco 就绪后创建编辑器
        layer.open({
            type: 1,
            title: '编辑：' + p + '　<small style="color:#999;font-weight:400">Ctrl+S 保存 · Ctrl+F 查找 · Ctrl+H 替换</small>',
            area: ['920px', '680px'],
            content: '<div style="padding:12px">'
                   +   '<div id="fmMonaco" class="fm-monaco-wrap">加载编辑器中…</div>'
                   +   '<div class="fm-editor-status">'
                   +     '<span id="fmEditStatus">就绪</span>'
                   +     '<span><span id="fmEditLang">' + esc(lang) + '</span> · UTF-8 · LF</span>'
                   +   '</div>'
                   + '</div>',
            btn: ['保存 (Ctrl+S)', '取消'],
            success: function (layero, idx) {
                var el = document.getElementById('fmMonaco');
                function createEditor() {
                    if (!window.monaco || !el) {
                        // Monaco 尚未就绪，稍后重试
                        setTimeout(createEditor, 100);
                        return;
                    }
                    el.innerHTML = '';
                    fmMonacoEditor = monaco.editor.create(el, {
                        value: content || '',
                        language: lang,
                        theme: 'vs',
                        fontSize: 13,
                        lineNumbers: 'on',
                        minimap: { enabled: true },
                        scrollBeyondLastLine: false,
                        automaticLayout: true,
                        tabSize: 4,
                        wordWrap: 'on',
                        renderLineHighlight: 'all',
                        smoothScrolling: true,
                        cursorSmoothCaretAnimation: 'on',
                        formatOnPaste: true,
                        bracketPairColorization: { enabled: true },
                        guides: { bracketPairs: true, indentation: true },
                        suggest: { showWords: true }
                    });
                    // 状态栏：行列、选中字符数
                    var statusEl = document.getElementById('fmEditStatus');
                    function updateStatus() {
                        if (!fmMonacoEditor || !statusEl) return;
                        var pos = fmMonacoEditor.getPosition();
                        var sel = fmMonacoEditor.getSelection();
                        var selLen = 0;
                        if (sel && !sel.isEmpty()) {
                            var model = fmMonacoEditor.getModel();
                            if (model) selLen = model.getValueInRange(sel).length;
                        }
                        var txt = '行 ' + pos.lineNumber + '，列 ' + pos.column;
                        if (selLen > 0) txt += '　|　选中 ' + selLen + ' 字符';
                        statusEl.textContent = txt;
                    }
                    fmMonacoEditor.onDidChangeCursorPosition(updateStatus);
                    fmMonacoEditor.onDidChangeCursorSelection(updateStatus);
                    updateStatus();
                    // Ctrl+S 保存（拦截浏览器默认行为）
                    fmMonacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function () {
                        doSave(idx);
                    });
                    fmMonacoEditor.focus();
                }
                createEditor();
            },
            yes: function (idx) { doSave(idx); },
            cancel: function () {
                if (fmMonacoEditor) {
                    fmMonacoEditor.dispose();
                    fmMonacoEditor = null;
                }
            }
        });
        function doSave(idx) {
            var val = fmMonacoEditor ? fmMonacoEditor.getValue() : '';
            WP.post('/files/write', { site_id: SITE, path: p, content: val })
                .then(function (r) {
                    if (r.ok) {
                        if (fmMonacoEditor) { fmMonacoEditor.dispose(); fmMonacoEditor = null; }
                        layer.close(idx);
                        layer.msg('已保存', { icon: 1 });
                    } else {
                        layer.alert(r.error, { icon: 2 });
                    }
                });
        }
    }

    /* edit */
    function editFile(p) {
        if (refuseIfVdb()) return;
        var loadI = layer.load(2);
        WP.post('/files/read', { site_id: SITE, path: p }).then(function (res) {
            layer.close(loadI);
            if (!res.ok) { layer.alert(res.error, { icon: 2 }); return; }
            openEditor(p, res.content || '');
        });
    }
    $('#fileBody').on('click', '.act-edit', function () {
        editFile($(this).closest('tr').attr('data-path'));
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

    /* upload — confirm before replacing a same-name regular file */
    function listingEntry(name) {
        for (var i = 0; i < lastEntries.length; i++) {
            if (lastEntries[i] && lastEntries[i].name === name) return lastEntries[i];
        }
        return null;
    }
    function confirmOverwrite(name, onYes) {
        layer.confirm(
            '当前目录已存在 <b>' + esc(name) + '</b>。<br>' +
            '<span style="color:#ff5722">确定后将覆盖该文件。</span>',
            { title: '覆盖确认' },
            function (idx) {
                layer.close(idx);
                onYes();
            }
        );
    }
    function uploadFile(f, overwrite) {
        var fd = new FormData();
        fd.append('site_id', SITE);
        fd.append('path', curPath);
        fd.append('file', f);
        if (overwrite) fd.append('overwrite', '1');
        var loadI = layer.load(2);
        WP.post('/files/upload', fd).then(function (res) {
            layer.close(loadI);
            if (res.ok) {
                layer.msg('上传完成：' + res.name + '（' + res.size + '）', { icon: 1 });
                refresh();
                return;
            }
            var err = res.error || '上传失败';
            if (!overwrite && /already exists|已存在/.test(err)) {
                confirmOverwrite(f.name, function () { uploadFile(f, true); });
                return;
            }
            layer.alert(err, { icon: 2, title: '上传失败' });
        });
    }
    function fmtUpSize(n) {
        n = Number(n) || 0;
        if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GB';
        if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
        if (n >= 1024) return (n / 1024).toFixed(1) + ' KB';
        return n + ' B';
    }
    var upCloudSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M12 13v8"/><path d="m8 17 4-4 4 4"/><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"/></svg>';
    $('#btnUpload').on('click', function () {
        if (refuseIfVdb()) return;
        var selFile = null;
        var upHtml = '<div class="wp-upload-dialog">' +
            '<div class="wp-upload-dropzone" id="upDropzone">' +
                '<div class="wp-upload-icon">' + upCloudSvg + '</div>' +
                '<div class="wp-upload-text">Drop files here or click to browse</div>' +
                '<div class="wp-upload-path">Files will be uploaded to <span class="wp-up-path-val">' + esc(curPath) + '</span>.</div>' +
            '</div>' +
            '<input type="file" id="upFileInput" style="display:none">' +
            '<div class="wp-upload-fileinfo" id="upFileInfo"><span class="layui-icon layui-icon-file"></span><span class="up-name"></span></div>' +
            '<label class="wp-upload-overwrite"><input type="checkbox" id="upOverwrite"> Overwrite existing files</label>' +
        '</div>';
        layer.open({
            type: 1,
            title: 'Upload Files',
            skin: 'wp-upload-layer',
            area: ['520px', 'auto'],
            shadeClose: false,
            content: upHtml,
            btn: ['Upload', 'Cancel'],
            btnAlign: 'r',
            success: function (layero, index) {
                var $box = layero;
                var $drop = $box.find('#upDropzone');
                var $fileInput = $box.find('#upFileInput');
                var $fileInfo = $box.find('#upFileInfo');
                var $overwrite = $box.find('#upOverwrite');
                function pickFile(f) {
                    if (!f) return;
                    selFile = f;
                    $fileInfo.find('.up-name').text(f.name + ' (' + fmtUpSize(f.size) + ')');
                    $fileInfo.addClass('show');
                }
                $drop.on('click', function () { $fileInput.trigger('click'); });
                $fileInput.on('change', function () {
                    var f = this.files[0];
                    this.value = '';
                    pickFile(f);
                });
                $drop.on('dragover', function (e) {
                    e.preventDefault();
                    $drop.addClass('is-dragover');
                });
                $drop.on('dragleave', function () { $drop.removeClass('is-dragover'); });
                $drop.on('drop', function (e) {
                    e.preventDefault();
                    $drop.removeClass('is-dragover');
                    var f = e.originalEvent.dataTransfer.files[0];
                    pickFile(f);
                });
            },
            yes: function (index, layero) {
                if (!selFile) {
                    layer.msg('Please select a file to upload', { icon: 0 });
                    return false;
                }
                var overwrite = layero.find('#upOverwrite').prop('checked');
                layer.close(index);
                var hit = listingEntry(selFile.name);
                if (hit && !overwrite) {
                    if (hit.type === 'dir') {
                        layer.alert('A folder with the same name already exists and cannot be overwritten.', { icon: 2, title: 'Upload failed' });
                        return;
                    }
                    if (hit.type === 'link') {
                        layer.alert('A symlink with the same name already exists and cannot be overwritten.', { icon: 2, title: 'Upload failed' });
                        return;
                    }
                    confirmOverwrite(selFile.name, function () { uploadFile(selFile, true); });
                    return;
                }
                uploadFile(selFile, overwrite);
            }
        });
    });

    /* filename search (current directory + descendants) */
    var searchTimer = null;
    var searchGen = 0;
    var searchHits = [];
    var searchActive = -1;
    function searchDrop() { return document.getElementById('fmSearchDrop'); }
    function closeSearchDrop() {
        var el = searchDrop();
        if (el) el.classList.remove('open');
        searchActive = -1;
    }
    function openSearchDrop() {
        var el = searchDrop();
        if (el) el.classList.add('open');
    }
    function clearSearch() {
        $('#fmSearch').val('');
        searchHits = [];
        closeSearchDrop();
        if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
        searchGen++;
    }
    function jumpToHit(hit) {
        if (!hit || !hit.path) return;
        closeSearchDrop();
        if (hit.type === 'dir') {
            load(hit.path);
            return;
        }
        load(hit.dir || dirOf(hit.path), { highlight: hit.name });
    }
    function paintSearchActive() {
        $('#fmSearchDrop .fm-search-item').removeClass('active').each(function (i) {
            if (i === searchActive) $(this).addClass('active');
        });
    }
    function renderSearchHits(res, q) {
        var hits = res.hits || [];
        searchHits = hits;
        searchActive = hits.length ? 0 : -1;
        var html = '';
        if (!hits.length) {
            html = '<div class="fm-search-empty">未找到匹配「' + esc(q) + '」的文件</div>';
        } else {
            hits.forEach(function (h, i) {
                var kind = h.type === 'dir' ? '文件夹' : (h.type === 'link' ? '链接' : '文件');
                html += '<button type="button" class="fm-search-item' + (i === 0 ? ' active' : '') + '" data-i="' + i + '">'
                     +  '<div class="fm-search-name">' + iconOf(h.type, h.name) + ' ' + esc(h.name)
                     +  ' <span style="color:var(--wp-text-muted);font-weight:400">· ' + kind + '</span></div>'
                     +  '<div class="fm-search-path">' + esc(h.path) + '</div></button>';
            });
            html += '<div class="fm-search-meta">在 ' + esc(res.path || curPath) + ' 下找到 ' + hits.length + ' 项'
                 +  (res.truncated ? '（结果已截断，请缩小范围或改用更具体的名称）' : '')
                 +  '</div>';
        }
        $('#fmSearchDrop').html(html);
        openSearchDrop();
    }
    function runSearch(q) {
        q = String(q || '').trim();
        if (q.length < 1) {
            closeSearchDrop();
            return;
        }
        var gen = ++searchGen;
        WP.post('/files/search', { site_id: SITE, path: curPath, q: q }).then(function (res) {
            if (gen !== searchGen) return;
            if (!res.ok) {
                $('#fmSearchDrop').html('<div class="fm-search-empty">' + esc(res.error || '搜索失败') + '</div>');
                openSearchDrop();
                return;
            }
            renderSearchHits(res, q);
        });
    }
    $('#fmSearch').on('input', function () {
        var q = this.value;
        if (searchTimer) clearTimeout(searchTimer);
        if (String(q || '').trim().length < 2) { closeSearchDrop(); return; }
        searchTimer = setTimeout(function () { runSearch(q); }, 300);
    });
    $('#fmSearch').on('keydown', function (e) {
        if (e.key === 'Escape') { closeSearchDrop(); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
            if (searchHits.length && searchActive >= 0 && searchHits[searchActive]) {
                jumpToHit(searchHits[searchActive]);
            } else {
                runSearch(this.value);
            }
            return;
        }
        if (!$('#fmSearchDrop').hasClass('open') || !searchHits.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            searchActive = (searchActive + 1) % searchHits.length;
            paintSearchActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            searchActive = (searchActive - 1 + searchHits.length) % searchHits.length;
            paintSearchActive();
        }
    });
    $('#fmSearchDrop').on('mousedown', '.fm-search-item', function (e) {
        e.preventDefault();
        var i = parseInt($(this).attr('data-i'), 10);
        if (searchHits[i]) jumpToHit(searchHits[i]);
    });
    $(document).on('mousedown', function (e) {
        if (!$(e.target).closest('.fm-search').length) closeSearchDrop();
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
        clearSearch();
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

    /* 右键上下文菜单 */
    var ctxTarget = null;
    var $ctx = $('#fmCtx');
    function hideCtx() { $ctx.attr('hidden', ''); ctxTarget = null; }
    function showCtx(x, y) {
        $ctx.removeAttr('hidden');
        var w = $ctx.outerWidth(), h = $ctx.outerHeight();
        var vw = window.innerWidth, vh = window.innerHeight;
        if (x + w > vw) x = vw - w - 4;
        if (y + h > vh) y = vh - h - 4;
        $ctx.css({ left: x + 'px', top: y + 'px' });
    }
    $('#fileBody').on('contextmenu', 'tr[data-name]', function (e) {
        e.preventDefault();
        ctxTarget = $(this);
        var name = ctxTarget.attr('data-name') || '';
        var type = ctxTarget.attr('data-type') || '';
        var isDir = type === 'dir';
        var isArchive = !isDir && ARCHIVE.test(name);
        var isEditable = !isDir && EDITABLE.test(name);
        var vdb = isVdb();
        // 根据文件类型显示菜单项；只读模式下写入项保留但置灰禁用
        $ctx.find('.fm-ctx-item').each(function () {
            var act = $(this).attr('data-act');
            var show = true;
            var dis = false;
            if (act === 'edit') { show = isEditable; dis = vdb; }
            else if (act === 'extract') show = isArchive;
            else if (act === 'download') show = !isDir;
            else if (act === 'chmod' || act === 'rename' || act === 'delete') { dis = vdb; }
            $(this).toggle(show).toggleClass('fm-ctx-disabled', dis).prop('disabled', dis);
        });
        showCtx(e.clientX, e.clientY);
    });
    $ctx.on('click', '.fm-ctx-item', function () {
        if ($(this).is(':disabled')) return;
        var act = $(this).attr('data-act');
        if (!ctxTarget) { hideCtx(); return; }
        var $tr = ctxTarget;
        var p = $tr.attr('data-path');
        var name = $tr.attr('data-name');
        hideCtx();
        switch (act) {
            case 'edit': editFile(p); break;
            case 'download':
                if ($tr.attr('data-type') !== 'dir') {
                    var url = '/files/download?site_id=' + encodeURIComponent(String(SITE)) + '&path=' + encodeURIComponent(p) + '&_csrf=' + WP.csrf;
                    var a = document.createElement('a');
                    a.href = url; a.download = ''; document.body.appendChild(a); a.click(); a.remove();
                }
                break;
            case 'extract': extractPath(p, name); break;
            case 'rename':
                layer.prompt({ title: '重命名', value: name, formType: 0 }, function (val, idx) {
                    if (!/^[A-Za-z0-9._ -]+$/.test(val)) { layer.msg('名称含非法字符', { icon: 2 }); return; }
                    WP.post('/files/rename', { site_id: SITE, path: p, to: joinPath(dirOf(p), val) }).then(function (r) {
                        if (r.ok) { layer.close(idx); layer.msg('已重命名', { icon: 1 }); refresh(); }
                        else { layer.alert(r.error, { icon: 2 }); }
                    });
                });
                break;
            case 'chmod':
                var cur = $tr.find('.fm-perms').text().trim().replace(/^0?/, '');
                layer.prompt({ title: '权限（三位八进制，如 644 / 755）', value: cur.slice(-3), formType: 0 }, function (val, idx) {
                    if (!/^[0-7]{3}$/.test(val)) { layer.msg('格式不正确', { icon: 2 }); return; }
                    WP.post('/files/chmod', { site_id: SITE, path: p, mode: val }).then(function (r) {
                        if (r.ok) { layer.close(idx); layer.msg('已修改', { icon: 1 }); refresh(); }
                        else { layer.alert(r.error, { icon: 2 }); }
                    });
                });
                break;
            case 'delete':
                layer.confirm('删除 <b>' + esc(name) + '</b>？' +
                    ($tr.attr('data-type') === 'dir' ? '<br><span style="color:#ff5722">目录内所有内容将被递归删除。</span>' : ''), {
                    title: '删除确认'
                }, function (idx) {
                    WP.post('/files/delete', { site_id: SITE, path: p }).then(function (r) {
                        if (r.ok) { layer.close(idx); layer.msg('已删除', { icon: 1 }); refresh(); }
                        else { layer.alert(r.error, { icon: 2 }); }
                    });
                });
                break;
        }
    });
    $(document).on('mousedown', function (e) {
        if (!$(e.target).closest('.fm-ctx').length && !$(e.target).closest('#fileBody tr').length) {
            hideCtx();
        }
    });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') hideCtx(); });

    bindTreeResize();
    boot();
});
</script>
