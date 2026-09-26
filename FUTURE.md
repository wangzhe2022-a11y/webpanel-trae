# Future improvements / 规划改进

Planned work for WebPanel. Not a commitment or roadmap with dates—extend this file as ideas mature.

---

## Site Linux usernames / 站点系统用户名

**Today / 现状:** `make_sysuser()` in `panel/app/functions.php` builds each site’s system user as a domain prefix (first label, alphanumeric, max 10 chars) plus 6 random hex digits, e.g. `shop.example.com` → `shop50387e` (or `folio50387e` for `folio.example.com`). The suffix is always random, mainly to avoid collisions in the panel DB.

**Desired / 目标:** Use a readable base name derived from the domain when possible (e.g. `shop`, `folio`, `myblog`). Append a short suffix only when that base name is already taken on the host or collides in the panel. Existing sites keep their current `sysuser` unless the site is deleted and recreated.

**Out of scope for this doc:** Implementation details, migration, or changes to `make_sysuser()`—tracked here only as intent.

---

## Other improvements / 其他改进

_Add bullets below as needed._

- _(placeholder)_
- _(placeholder)_
