# BUGFIXES

Log of fixed bugs. **Append a dated entry for every bugfix** (newest first): symptom, cause, fix, and PR.

Planned work (e.g. cleaner site Linux usernames / `make_sysuser`) is tracked in `FUTURE.md`. That file is not on `main` yet — see [PR #2](https://github.com/wangzhe2022-a11y/webpanel-trae/pull/2).

---

### 2026-09-20 — File manager site select does nothing

*PR [#6](https://github.com/wangzhe2022-a11y/webpanel-trae/pull/6) / `cursor/fix-file-manager-site-select-355a`. Hotfixed live on CVM.*

- **Symptom:** 文件管理 stayed on “请先在右上角选择一个站点” after picking a site.
- **Cause:** `#siteSelect` had no `change` handler; listing JS only rendered after `?site=` was already set.
- **Fix:** Always bind change → `/files?site=<id>`; cast site id to int.

### 2026-09-20 — PHP version dropdown snaps back to 7.4

*PR [#4](https://github.com/wangzhe2022-a11y/webpanel-trae/pull/4). Hotfixed live. Related: [PR #3](https://github.com/wangzhe2022-a11y/webpanel-trae/pull/3) restore previous selection on cancel/API failure.*

- **Symptom:** Sites list PHP selector always showed 7.4 even when DB/FPM had another version.
- **Cause:** Strict `===` between SQLite string `php_version` and integer keys from `panel_php_versions()` (PHP coerces numeric-string keys like `'83'` to int `83`).
- **Fix:** Cast both sides to string in `panel/app/views/sites/index.php`.
