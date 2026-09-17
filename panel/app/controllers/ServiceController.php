<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Shell;

class ServiceController extends Controller
{
    private const UNITS = [
        'nginx'    => 'nginx',
        'mysql'    => 'mysqld',
        'postgres' => 'postgresql-16',
        'phpfpm'   => 'php-fpm',
        'php74fpm' => 'php74-php-fpm',
        'php80fpm' => 'php80-php-fpm',
        'php81fpm' => 'php81-php-fpm',
        'php82fpm' => 'php82-php-fpm',
        'php83fpm' => 'php83-php-fpm',
    ];

    public function info(): void
    {
        $this->requireLogin();
        $r = Shell::sudo('wp-sys.sh', ['info']);
        if (!$r['ok']) {
            $this->fail($r['error'], 502);
        }
        unset($r['data']['ok']);
        $this->ok($r['data']);
    }

    public function svc(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $name = (string) $this->input('service', '');
        $action = (string) $this->input('action', '');

        // node sites: "node-<siteuser>" maps to wp-node-<siteuser>.service
        $unit = self::UNITS[$name] ?? null;
        if ($unit === null && preg_match('/^node-([a-z][a-z0-9_]{2,30})$/', $name, $m)) {
            $unit = 'wp-node-' . $m[1];
        }
        if ($unit === null) {
            $this->fail('未知服务');
        }
        if (!in_array($action, ['start', 'stop', 'restart', 'reload'], true)) {
            $this->fail('不支持的操作');
        }
        $r = Shell::sudo('wp-sys.sh', ['svc', $name, $action]);
        if (!$r['ok']) {
            $this->fail('操作失败：' . $r['error']);
        }
        Auth::log('sys.svc', $unit . ' ' . $action);
        $this->ok();
    }
}
