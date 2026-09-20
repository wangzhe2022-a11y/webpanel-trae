# 缺陷修复记录

## 2026-09-20 — 仪表盘 stat-card 对比度

Layui 或其他 CSS 会覆盖弱选择器 `.c-orange` / `.c-purple` 的背景，HTTPS（已启用 HTTPS）和 CPU 卡片出现白字浅灰底，几乎不可读。蓝/绿卡有时还能过，橙/紫在 CSS 顺序回退时必挂。

- `panel/app/views/header.php`：改用 `.stat-card.c-blue|green|orange|purple`，对卡片及 `.num` / `.label` / 图标强制 `background: linear-gradient(...) !important` 与 `color: #fff !important`。橙色 `#ff9800 → #e65100`，紫色 `#9c27b0 → #6a1b9a`（比浅黄更强）。
- `panel/app/views/dashboard.php`：四张卡片补内联 `style="background:linear-gradient(...);color:#fff"`，避免仅靠 stylesheet 顺序。
