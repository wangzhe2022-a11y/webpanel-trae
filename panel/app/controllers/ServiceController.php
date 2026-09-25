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
        $info = panel_sys_info();
        if (isset($info['error']) && !isset($info['hostname'])) {
            $this->fail((string) $info['error'], 502);
        }
        unset($info['ok']);
        $this->ok($info);
    }

    public function atop(): void
    {
        $this->requireLogin();

        $file = trim((string) ($_GET['file'] ?? 'auto'));
        $time = trim((string) ($_GET['time'] ?? 'latest'));
        $latest = (int) ($_GET['latest'] ?? 1);
        $top = (int) ($_GET['top'] ?? 8);

        if ($file === '') {
            $file = 'auto';
        }
        if ($file !== 'auto' && !preg_match('/^atop_\d{8}$/', $file)) {
            $this->fail('无效的 atop 日志文件名');
        }
        if ($time === '') {
            $time = 'latest';
        }
        if ($time !== 'latest' && !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $this->fail('无效的采样时间（HH:MM）');
        }
        if ($latest < 1) {
            $latest = 1;
        }
        if ($latest > 24) {
            $latest = 24;
        }
        if ($top < 3) {
            $top = 3;
        }
        if ($top > 20) {
            $top = 20;
        }

        $r = Shell::sudo('wp-atop.sh', ['info', $file, $time, (string) $latest, (string) $top]);
        if (!$r['ok']) {
            $this->ok([
                'installed' => false,
                'version' => '',
                'service' => 'unknown',
                'enabled' => 'unknown',
                'log_path' => '/var/log/atop',
                'interval_s' => 600,
                'last_log_mtime' => '',
                'logs' => [],
                'file' => $file === 'auto' ? '' : $file,
                'time' => '',
                'times' => [],
                'sample' => null,
                'recent' => [],
                'top_cpu' => [],
                'top_mem' => [],
                'proc_error' => '',
                'error' => '无法读取 atop：' . ($r['error'] !== '' ? $r['error'] : '请确认已部署 wp-atop.sh 并更新 sudoers'),
            ]);
        }

        $data = $r['data'];
        unset($data['ok']);
        if (!isset($data['installed'])) {
            $data['installed'] = false;
        }
        $this->ok($data);
    }

    public function svc(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $name = (string) $this->input('service', '');
        $action = (string) $this->input('action', '');

        // node sites: "node-<siteuser>" maps to wp-node-<siteuser>.service
        $unit = self::UNITS[$name] ?? null;
        if ($unit === null && ($name === 'postgresql' || $name === 'postgresql-16')) {
            $name = 'postgres';
            $unit = self::UNITS['postgres'];
        }
        if ($unit === null && preg_match('/^(?:wp-)?node-([a-z][a-z0-9_]{2,30})$/', $name, $m)) {
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
        panel_sys_info_forget();
        Auth::log('sys.svc', $unit . ' ' . $action);
        $this->ok();
    }

    /**
     * Restart panel php-fpm, then try-restart installed Remi site pools.
     * POST /sys/fpm-safe-restart
     */
    public function fpmSafeRestart(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $r = Shell::sudo('wp-sys.sh', ['fpm-safe-restart']);
        if (!$r['ok']) {
            $this->fail('PHP-FPM 安全重启失败：' . ($r['error'] !== '' ? $r['error'] : '未知错误'));
        }
        panel_sys_info_forget();

        $restarted = $r['data']['restarted'] ?? [];
        $skipped = $r['data']['skipped'] ?? [];
        $failed = $r['data']['failed'] ?? [];
        if (!is_array($restarted)) {
            $restarted = [];
        }
        if (!is_array($skipped)) {
            $skipped = [];
        }
        if (!is_array($failed)) {
            $failed = [];
        }

        $detail = 'restarted=' . implode(',', array_map('strval', $restarted));
        if ($failed !== []) {
            $detail .= ' failed=' . implode(',', array_map('strval', $failed));
        }
        Auth::log('sys.fpm-safe-restart', $detail);

        $this->ok([
            'restarted' => array_values($restarted),
            'skipped' => array_values($skipped),
            'failed' => array_values($failed),
        ]);
    }

    /**
     * Panel access log + suspicious-IP detection.
     * GET /sys/access?limit=30
     */
    public function access(): void
    {
        $this->requireLogin();

        $limit = (int) ($_GET['limit'] ?? 30);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $r = Shell::sudo('wp-sys.sh', ['access', (string) $limit]);
        if (!$r['ok']) {
            $this->ok([
                'total' => 0,
                'unique_ips' => 0,
                'recent' => [],
                'failed_logins' => [],
                'suspicious' => [],
                'error' => '无法读取面板访问日志：' . ($r['error'] !== '' ? $r['error'] : '请确认已部署 wp-sys.sh'),
            ]);
        }

        $data = $r['data'];
        unset($data['ok']);
        $this->ok($data);
    }

    /**
     * Block an IP from accessing the panel (writes to deny-ips.conf + nginx reload).
     * POST /sys/deny { ip: "1.2.3.4" }
     */
    public function deny(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $ip = trim((string) $this->input('ip', ''));
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->fail('无效的 IPv4 地址');
        }

        $r = Shell::sudo('wp-sys.sh', ['deny', $ip]);
        if (!$r['ok']) {
            $this->fail('封禁失败：' . ($r['error'] ?: '未知错误'));
        }
        Auth::log('panel.deny', 'blocked ' . $ip);
        $this->ok(['ip' => $ip]);
    }

    /**
     * Unblock a previously denied IP.
     * POST /sys/undeny { ip: "1.2.3.4" }
     */
    public function undeny(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $ip = trim((string) $this->input('ip', ''));
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->fail('无效的 IPv4 地址');
        }

        $r = Shell::sudo('wp-sys.sh', ['undeny', $ip]);
        if (!$r['ok']) {
            $this->fail('解封失败：' . ($r['error'] ?: '未知错误'));
        }
        Auth::log('panel.undeny', 'unblocked ' . $ip);
        $this->ok(['ip' => $ip]);
    }

    /**
     * List currently denied IPs.
     * GET /sys/denylist
     */
    public function denylist(): void
    {
        $this->requireLogin();

        $r = Shell::sudo('wp-sys.sh', ['denylist']);
        if (!$r['ok']) {
            $this->ok(['denied' => []]);
            return;
        }
        $this->ok(['denied' => $r['data']['denied'] ?? []]);
    }
}
