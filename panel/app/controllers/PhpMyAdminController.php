<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Shell;

class PhpMyAdminController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $r = Shell::sudo('wp-pma.sh', ['status']);
        $installed = $r['ok'] && !empty($r['data']['installed']);
        $this->render('phpmyadmin/index', [
            'installed' => $installed,
            'version'   => $r['ok'] ? (string) ($r['data']['version'] ?? '') : '',
            'pmaUrl'    => $r['ok'] ? (string) ($r['data']['url'] ?? '/phpmyadmin/') : '/phpmyadmin/',
            'error'     => $r['ok'] ? '' : (string) $r['error'],
        ]);
    }

    /**
     * Internal nginx auth_request target. Must not redirect: 200 = allow,
     * 401 = deny (nginx then 302s the browser to /login). Release the
     * session lock immediately so concurrent phpMyAdmin requests do not block.
     */
    public function auth(): void
    {
        $ok = Auth::check();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        if (!$ok) {
            http_response_code(401);
            exit;
        }
        http_response_code(200);
        exit;
    }

    public function install(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        set_time_limit(180);
        $r = Shell::sudo('wp-pma.sh', ['install']);
        if (!$r['ok']) {
            $this->fail('phpMyAdmin 安装失败：' . $r['error']);
        }
        Auth::log('pma.install', (string) ($r['data']['version'] ?? ''));
        $this->ok([
            'version' => (string) ($r['data']['version'] ?? ''),
            'url'     => (string) ($r['data']['url'] ?? '/phpmyadmin/'),
        ]);
    }
}
