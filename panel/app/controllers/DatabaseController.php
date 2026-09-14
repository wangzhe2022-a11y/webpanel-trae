<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Db;
use WebPanel\Shell;

class DatabaseController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $rows = Db::all(
            'SELECT d.*, s.domain AS site_domain FROM databases d
             LEFT JOIN sites s ON s.id = d.site_id ORDER BY d.id DESC'
        );
        $this->render('databases/index', ['databases' => $rows]);
    }

    public function create(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $name = (string) $this->input('name', '');
        $user = (string) $this->input('username', '');
        $siteId = (int) $this->input('site_id', 0) ?: null;

        if (!valid_mysql_name($name, 64)) {
            $this->fail('数据库名只能含字母、数字、下划线（2-64 位）');
        }
        if (!valid_mysql_name($user, 32)) {
            $this->fail('数据库用户名只能含字母、数字、下划线（2-32 位）');
        }
        if (Db::one('SELECT 1 FROM databases WHERE name = ? OR username = ?', [$name, $user])) {
            $this->fail('数据库名或用户名已存在');
        }
        if ($siteId && !$this->findSite($siteId)) {
            $this->fail('关联站点不存在');
        }

        $password = random_password(20);
        $r = Shell::sudo('wp-db.sh', ['create', $name, $user], $password);
        if (!$r['ok']) {
            $this->fail('创建失败：' . $r['error']);
        }
        $id = Db::insert('INSERT INTO databases (site_id, name, username) VALUES (?,?,?)', [$siteId, $name, $user]);
        Auth::log('db.create', "$name ($user)");

        // password is returned exactly once and never stored
        $this->ok(['id' => $id, 'name' => $name, 'username' => $user, 'password' => $password]);
    }

    public function resetPassword(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $row = Db::one('SELECT * FROM databases WHERE id = ?', [(int) $this->input('id', 0)]);
        if (!$row) {
            $this->fail('数据库不存在');
        }
        $password = random_password(20);
        $r = Shell::sudo('wp-db.sh', ['passwd', $row['name'], $row['username']], $password);
        if (!$r['ok']) {
            $this->fail('重置失败：' . $r['error']);
        }
        Auth::log('db.passwd', $row['name']);
        $this->ok(['name' => $row['name'], 'username' => $row['username'], 'password' => $password]);
    }

    public function delete(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $row = Db::one('SELECT * FROM databases WHERE id = ?', [(int) $this->input('id', 0)]);
        if (!$row) {
            $this->fail('数据库不存在');
        }
        $r = Shell::sudo('wp-db.sh', ['delete', $row['name'], $row['username']]);
        if (!$r['ok']) {
            $this->fail('删除失败：' . $r['error']);
        }
        Db::run('DELETE FROM databases WHERE id = ?', [$row['id']]);
        Auth::log('db.delete', $row['name']);
        $this->ok();
    }
}
