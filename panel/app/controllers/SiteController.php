<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Db;
use WebPanel\Shell;

class SiteController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $sites = Db::all('SELECT * FROM sites ORDER BY id DESC');
        foreach ($sites as &$s) {
            $s['db_list'] = Db::all('SELECT id, name, username FROM databases WHERE site_id = ?', [$s['id']]);
        }
        unset($s);
        $this->render('sites/index', [
            'sites' => $sites,
            'phpVersions' => panel_php_versions(),
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $primary = mb_strtolower((string) $this->input('domain', ''));
        $aliasesRaw = mb_strtolower((string) $this->input('aliases', ''));
        $phpver = (string) $this->input('php_version', '82');
        $maxChildren = max(5, min(100, (int) $this->input('max_children', 20)));

        if (!valid_domain_list($primary)) {
            $this->fail('主域名格式不正确');
        }
        $aliases = valid_domain_list($aliasesRaw);
        if ($aliasesRaw !== '' && !$aliases) {
            $this->fail('别名域名格式不正确（多个域名用逗号分隔）');
        }
        $all = array_unique(array_merge([$primary], $aliases));
        if (!isset(panel_php_versions()[$phpver])) {
            $this->fail('PHP 版本不支持');
        }
        if (Db::one('SELECT 1 FROM sites WHERE domain = ?', [$primary])) {
            $this->fail('该主域名已存在');
        }

        // optional database created together with the site
        $wantDb = ($this->input('with_db') === '1');
        $dbName = (string) $this->input('db_name', '');
        $dbUser = (string) $this->input('db_user', '');
        if ($wantDb) {
            if (!valid_mysql_name($dbName, 64) || !valid_mysql_name($dbUser, 32)) {
                $this->fail('数据库名/用户名只能含字母、数字、下划线（长度 2 位以上）');
            }
            if (Db::one('SELECT 1 FROM databases WHERE name = ? OR username = ?', [$dbName, $dbUser])) {
                $this->fail('数据库名或数据库用户名已存在');
            }
        }

        $sysuser = make_sysuser($primary);

        $res = Shell::sudo('wp-site.sh', ['create', $sysuser, $phpver, implode(',', $all), (string) $maxChildren]);
        if (!$res['ok']) {
            $this->fail('创建站点失败：' . $res['error']);
        }

        $siteId = Db::insert(
            'INSERT INTO sites (sysuser, domain, aliases, php_version) VALUES (?,?,?,?)',
            [$sysuser, $primary, implode(',', $aliases), $phpver]
        );
        Auth::log('site.create', "$primary ($sysuser, php$phpver)");

        $secret = null;
        if ($wantDb) {
            $secret = random_password(20);
            $r = Shell::sudo('wp-db.sh', ['create', $dbName, $dbUser], $secret);
            if (!$r['ok']) {
                $this->fail("站点已创建，但数据库创建失败：{$r['error']}（站点 ID {$siteId}，可稍后在数据库页补建）", 500);
            }
            Db::insert('INSERT INTO databases (site_id, name, username) VALUES (?,?,?)', [$siteId, $dbName, $dbUser]);
            Auth::log('db.create', "$dbName ($dbUser) for site#$siteId");
        }

        $this->ok([
            'id' => $siteId,
            'sysuser' => $sysuser,
            'docroot' => $res['data']['docroot'] ?? '',
            'db_name' => $wantDb ? $dbName : null,
            'db_user' => $wantDb ? $dbUser : null,
            'db_password' => $secret,
        ]);
    }

    public function delete(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        $purge = $this->input('purge') === '1' ? 'purge' : '';
        $dropDb = $this->input('drop_db') === '1';

        if ($dropDb) {
            foreach (Db::all('SELECT * FROM databases WHERE site_id = ?', [$site['id']]) as $db) {
                $r = Shell::sudo('wp-db.sh', ['delete', $db['name'], $db['username']]);
                if (!$r['ok']) {
                    $this->fail('删除数据库失败：' . $r['error']);
                }
                Db::run('DELETE FROM databases WHERE id = ?', [$db['id']]);
            }
        }

        $r = Shell::sudo('wp-site.sh', array_filter(['delete', $site['sysuser'], $purge]));
        if (!$r['ok']) {
            $this->fail('删除站点失败：' . $r['error']);
        }
        Db::run('DELETE FROM sites WHERE id = ?', [$site['id']]);
        if (!$dropDb) {
            Db::run('UPDATE databases SET site_id = NULL WHERE site_id = ?', [$site['id']]);
        }
        Auth::log('site.delete', "{$site['domain']} ({$site['sysuser']}) purge=" . ($purge ? '1' : '0'));
        $this->ok();
    }

    public function setPhp(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $site = $this->findSite((int) $this->input('id', 0));
        $phpver = (string) $this->input('php_version', '');
        if (!$site) {
            $this->fail('站点不存在');
        }
        if (!isset(panel_php_versions()[$phpver])) {
            $this->fail('PHP 版本不支持');
        }
        if ($site['php_version'] === $phpver) {
            $this->ok();
        }

        $r = Shell::sudo('wp-site.sh', ['php-set', $site['sysuser'], $phpver]);
        if (!$r['ok']) {
            $this->fail('切换 PHP 失败：' . $r['error']);
        }
        Db::run('UPDATE sites SET php_version = ? WHERE id = ?', [$phpver, $site['id']]);
        Auth::log('site.php', "{$site['domain']} -> php$phpver");
        $this->ok(['php' => $phpver]);
    }

    public function installWordPress(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }

        $title = trim((string) $this->input('wp_title', 'WordPress'));
        $admin = trim((string) $this->input('wp_admin', ''));
        $email = trim((string) $this->input('wp_email', ''));
        $existingDbId = (int) $this->input('db_id', 0);

        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $admin)) {
            $this->fail('管理员账号不合法（3-60 位字母数字 ._- 空格）');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('管理员邮箱不合法');
        }

        // resolve database: existing row linked to site, or create a new one
        $newDbName = trim((string) $this->input('new_db_name', ''));
        $newDbUser = trim((string) $this->input('new_db_user', ''));

        if ($existingDbId > 0) {
            $dbRow = Db::one('SELECT * FROM databases WHERE id = ? AND site_id = ?', [$existingDbId, $site['id']]);
            if (!$dbRow) {
                $this->fail('所选数据库不存在或不属于该站点');
            }
            $dbPass = random_password(20);
            $r = Shell::sudo('wp-db.sh', ['passwd', $dbRow['name'], $dbRow['username']], $dbPass);
            if (!$r['ok']) {
                $this->fail('重置数据库密码失败：' . $r['error']);
            }
        } else {
            if (!valid_mysql_name($newDbName, 64) || !valid_mysql_name($newDbUser, 32)) {
                $this->fail('新数据库名/用户名不合法');
            }
            if (Db::one('SELECT 1 FROM databases WHERE name = ? OR username = ?', [$newDbName, $newDbUser])) {
                $this->fail('数据库名或用户名已存在');
            }
            $dbRow = ['name' => $newDbName, 'username' => $newDbUser];
            $dbPass = random_password(20);
            $r = Shell::sudo('wp-db.sh', ['create', $newDbName, $newDbUser], $dbPass);
            if (!$r['ok']) {
                $this->fail('创建数据库失败：' . $r['error']);
            }
            Db::insert('INSERT INTO databases (site_id, name, username) VALUES (?,?,?)', [$site['id'], $newDbName, $newDbUser]);
        }

        $wpPass = random_password(18);
        $stdin = $dbPass . "\n" . $wpPass . "\n";
        $r = Shell::sudo('wp-wp.sh', [
            'install', $site['sysuser'], $site['domain'],
            $dbRow['name'], $dbRow['username'], $title, $admin, $email,
        ], $stdin);
        if (!$r['ok']) {
            $this->fail('WordPress 部署失败：' . $r['error']);
        }
        Auth::log('site.wp', "{$site['domain']} admin=$admin");

        $this->ok([
            'admin_url' => 'http://' . $site['domain'] . '/wp-admin/',
            'wp_admin' => $admin,
            'wp_password' => $wpPass,
            'db_name' => $dbRow['name'],
            'db_user' => $dbRow['username'],
            'db_password' => $dbPass,
        ]);
    }
}
