# BUGFIXES

Changelog of **non-obvious** bugs and version-related pitfalls fixed in this project. Use it when the UI or ops layer disagrees with the database or config, or when a regression looks familiar.

**Convention:** Start each entry with a date (e.g. `2026-09-20`) when you add it so fixes are easy to scan over time.

Planned work and known deferrals: see [FUTURE.md](FUTURE.md) (e.g. hashed Linux usernames / `make_sysuser` left as-is).

---

### PHP version dropdown always shows 7.4 after refresh

*Fixed in PR #4; live hotfix on AlmaLinux CVM.*

- **Symptom:** Selecting PHP 8.2/8.3 (or DB already having `82`/`83`) still showed PHP 7.4 after refresh in the sites list.
- **Cause:** `panel_php_versions()` uses numeric-string keys (`'74'`, `'83'`, …). PHP coerces those array keys to integers. In `panel/app/views/sites/index.php`, `$s['php_version'] === $v` compared SQLite string `"83"` to int `83`, so no `<option selected>` matched and the browser fell back to the first option (7.4). Same issue made the create form’s default `=== '82'` fail.
- **Fix:** Cast both sides to string in the view comparisons, e.g. `(string)$s['php_version'] === (string)$v` and `(string)$v === '82'`. Option `value` should use `(string)$v`.
- **Also related:** Earlier UI snap-back on cancel/failure (PR #3) — remember previous select value; do not reset to `selectedIndex = 0`.
- **Verify:** SQLite `sites.php_version` and FPM pool files under `/etc/opt/remi/php*/php-fpm.d/` can be correct while the UI still lies if this comparison bug is present.
