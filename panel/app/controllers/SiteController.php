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
        $type = $this->input('type') === 'node' ? 'node' : 'php';
        $phpver = (string) $this->input('php_version', '82');
        $maxChildren = max(5, min(100, (int) $this->input('max_children', 20)));
        $port = (int) $this->input('app_port', 3000);
        $startCmd = trim((string) $this->input('start_cmd', 'npm start'));

        if (!valid_domain_list($primary)) {
            $this->fail('主域名格式不正确');
        }
        $aliases = valid_domain_list($aliasesRaw);
        if ($aliasesRaw !== '' && !$aliases) {
            $this->fail('别名域名格式不正确（多个域名用逗号分隔）');
        }
        $all = array_unique(array_merge([$primary], $aliases));
        if (Db::one('SELECT 1 FROM sites WHERE domain = ?', [$primary])) {
            $this->fail('该主域名已存在');
        }

        if ($type === 'php' && !isset(panel_php_versions()[$phpver])) {
            $this->fail('PHP 版本不支持');
        }
        if ($type === 'node') {
            if ($port < 1024 || $port > 65535) {
                $this->fail('应用端口需为 1024-65535');
            }
            if (!preg_match('#^(/usr/(local/)?bin/)?(node|npm|npx|yarn|pnpm|bun|deno)([0-9.]+)?( [A-Za-z0-9._/=@:-]+)*$#', $startCmd) || $startCmd === '' || strlen($startCmd) > 200) {
                $this->fail('启动命令不合法（如 npm start / node dist/server.js，参数仅限字母数字和 ._/=:@-）');
            }
        }

        // optional database created together with the site
        $wantDb = ($this->input('with_db') === '1');
        $engine = $this->input('db_engine') === 'postgres' ? 'postgres' : 'mysql';
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

        if ($type === 'php') {
            $res = Shell::sudo('wp-site.sh', ['create', $sysuser, $phpver, implode(',', $all), (string) $maxChildren]);
        } else {
            $res = Shell::sudo('wp-node.sh', ['create', $sysuser, implode(',', $all), (string) $port, $startCmd]);
        }
        if (!$res['ok']) {
            $this->fail('创建站点失败：' . $res['error']);
        }

        $siteId = Db::insert(
            'INSERT INTO sites (sysuser, domain, aliases, php_version, type, app_port, start_cmd) VALUES (?,?,?,?,?,?,?)',
            [$sysuser, $primary, implode(',', $aliases), $type === 'php' ? $phpver : '', $type, $type === 'node' ? $port : null, $type === 'node' ? $startCmd : null]
        );
        Auth::log('site.create', "$primary ($sysuser, $type" . ($type === 'php' ? ", php$phpver" : ", :$port") . ')');

        $secret = null;
        if ($wantDb) {
            $secret = random_password(20);
            $script = $engine === 'postgres' ? 'wp-pg.sh' : 'wp-db.sh';
            $r = Shell::sudo($script, ['create', $dbName, $dbUser], $secret);
            if (!$r['ok']) {
                $this->fail("站点已创建，但数据库创建失败：{$r['error']}（站点 ID {$siteId}，可稍后在数据库页补建）", 500);
            }
            Db::insert('INSERT INTO databases (site_id, name, username, engine) VALUES (?,?,?,?)', [$siteId, $dbName, $dbUser, $engine]);
            Auth::log('db.create', "$dbName ($dbUser, $engine) for site#$siteId");
        }

        $this->ok([
            'id' => $siteId,
            'sysuser' => $sysuser,
            'type' => $type,
            'docroot' => $type === 'node'
                ? ($res['data']['appdir'] ?? '/www/wwwroot/' . $sysuser . '/app')
                : ($res['data']['docroot'] ?? ''),
            'port' => $type === 'node' ? $port : null,
            'db_name' => $wantDb ? $dbName : null,
            'db_user' => $wantDb ? $dbUser : null,
            'db_engine' => $wantDb ? $engine : null,
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
                $script = ($db['engine'] ?? 'mysql') === 'postgres' ? 'wp-pg.sh' : 'wp-db.sh';
                $r = Shell::sudo($script, ['delete', $db['name'], $db['username']]);
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
        if (($site['type'] ?? 'php') !== 'php') {
            $this->fail('该站点是 Node.js 应用，不支持切换 PHP');
        }
        if (!isset(panel_php_versions()[$phpver])) {
            $this->fail('PHP 版本不支持');
        }
        if ((string) $site['php_version'] === (string) $phpver) {
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

    public function nodeSvc(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        if (($site['type'] ?? 'php') !== 'node') {
            $this->fail('该站点不是 Node.js 应用');
        }
        $act = (string) $this->input('action', '');
        if (!in_array($act, ['start', 'stop', 'restart'], true)) {
            $this->fail('不支持的操作');
        }
        $r = Shell::sudo('wp-node.sh', [$act, $site['sysuser']]);
        if (!$r['ok']) {
            $this->fail('操作失败：' . $r['error']);
        }
        Auth::log('node.' . $act, "{$site['domain']} ({$site['sysuser']})");
        $this->ok();
    }

    public function nodeNpmInstall(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        if (($site['type'] ?? 'php') !== 'node') {
            $this->fail('该站点不是 Node.js 应用');
        }
        $r = Shell::sudo('wp-node.sh', ['npmi', $site['sysuser']]);
        if (!$r['ok']) {
            $this->fail('npm install 失败：' . $r['error']);
        }
        Auth::log('node.npmi', "{$site['domain']} ({$site['sysuser']})");
        $this->ok();
    }

    public function installWordPress(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        if (($site['type'] ?? 'php') !== 'php') {
            $this->fail('该站点是 Node.js 应用，WordPress 仅支持 PHP 站点');
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
            if (($dbRow['engine'] ?? 'mysql') === 'postgres') {
                $this->fail('WordPress 部署暂不支持 PostgreSQL 数据库，请选择/新建 MySQL 数据库');
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
            $dbRow = ['name' => $newDbName, 'username' => $newDbUser, 'engine' => 'mysql'];
            $dbPass = random_password(20);
            $r = Shell::sudo('wp-db.sh', ['create', $newDbName, $newDbUser], $dbPass);
            if (!$r['ok']) {
                $this->fail('创建数据库失败：' . $r['error']);
            }
            Db::insert('INSERT INTO databases (site_id, name, username, engine) VALUES (?,?,?,?)', [$site['id'], $newDbName, $newDbUser, 'mysql']);
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
