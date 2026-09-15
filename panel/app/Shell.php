<?php
declare(strict_types=1);

namespace WebPanel;

/**
 * Privileged execution boundary. The panel user can only call the fixed
 * wrapper scripts under PANEL_BIN through passwordless sudo.
 *
 * Result shape: ['ok' => bool, 'data' => array, 'error' => string]
 */
final class Shell
{
    public static function sudo(string $script, array $args = [], string $stdin = ''): array
    {
        if (PANEL_DRY) {
            return self::dryRun($script, $args, $stdin);
        }

        $cmd = 'sudo -n ' . escapeshellarg(PANEL_BIN . '/' . $script);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg((string) $a);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return ['ok' => false, 'data' => [], 'error' => '无法执行特权命令（proc_open）'];
        }
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        $data = json_decode(trim((string) $stdout), true);
        if (!is_array($data)) {
            $data = [];
        }
        if ($code !== 0 || !($data['ok'] ?? false)) {
            $msg = $data['error'] ?? '';
            if ($msg === '' && $stderr !== '') {
                $lines = array_values(array_filter(array_map('trim', explode("\n", $stderr))));
                $msg = $lines[0] ?? '命令执行失败';
            }
            return ['ok' => false, 'data' => $data, 'error' => $msg ?: '命令执行失败'];
        }
        return ['ok' => true, 'data' => $data, 'error' => ''];
    }

    public static function sudoStream(string $script, array $args): array
    {
        // binary-safe passthrough (file download) - returns proc resource + pipes
        $cmd = 'sudo -n ' . escapeshellarg(PANEL_BIN . '/' . $script);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg((string) $a);
        }
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $desc, $pipes);
        return [$proc, $pipes];
    }

    private static function dryRun(string $script, array $args, string $stdin): array
    {
        $a = $args[0] ?? '';
        // Simulated but realistic responses so the whole UI is clickable.
        return match (true) {
            str_starts_with($script, 'wp-site') && $a === 'create'
                => ['ok' => true, 'data' => ['ok' => true, 'user' => $args[1], 'docroot' => '/www/wwwroot/' . $args[1] . '/public', 'php' => $args[2]], 'error' => ''],
            str_starts_with($script, 'wp-site')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-node') && $a === 'create'
                => ['ok' => true, 'data' => ['ok' => true, 'user' => $args[1], 'appdir' => '/www/wwwroot/' . $args[1] . '/app', 'port' => (int) $args[3], 'unit' => 'wp-node-' . $args[1] . '.service'], 'error' => ''],
            str_starts_with($script, 'wp-node')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-db') && $a === 'create'
                => ['ok' => true, 'data' => ['ok' => true, 'database' => $args[1], 'user' => $args[2]], 'error' => ''],
            str_starts_with($script, 'wp-db')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-pg') && $a === 'create'
                => ['ok' => true, 'data' => ['ok' => true, 'database' => $args[1], 'user' => $args[2], 'engine' => 'postgres'], 'error' => ''],
            str_starts_with($script, 'wp-pg') && $a === 'list'
                => ['ok' => true, 'data' => ['ok' => true, 'dbs' => [['name' => 'pg_demo', 'size' => '12 MB', 'owner' => 'pg_demo_user']]], 'error' => ''],
            str_starts_with($script, 'wp-pg')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-ssl') && $a === 'issue'
                => ['ok' => true, 'data' => ['ok' => true, 'domain' => $args[2] ?? 'demo', 'not_before' => 'dryrun', 'not_after' => 'dryrun'], 'error' => ''],
            str_starts_with($script, 'wp-ssl') && $a === 'deploy'
                => ['ok' => true, 'data' => ['ok' => true, 'domain' => explode(',', (string) ($args[2] ?? ''))[0], 'not_after' => 'dryrun', 'missing' => ''], 'error' => ''],
            str_starts_with($script, 'wp-ssl') && $a === 'list'
                => ['ok' => true, 'data' => ['ok' => true, 'certs' => [['domain' => 'demo.example.com', 'not_before' => 'dryrun', 'not_after' => 'dryrun']]], 'error' => ''],
            str_starts_with($script, 'wp-ssl')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-fs') && $a === 'list'
                => ['ok' => true, 'data' => ['ok' => true, 'path' => $args[2] ?? '/', 'entries' => [
                    ['name' => 'index.php', 'type' => 'file', 'size' => 4521, 'mtime' => date('Y-m-d H:i:s'), 'perms' => '0644'],
                    ['name' => 'wp-config.php', 'type' => 'file', 'size' => 3012, 'mtime' => date('Y-m-d H:i:s'), 'perms' => '0640'],
                    ['name' => 'wp-content', 'type' => 'dir', 'size' => 0, 'mtime' => date('Y-m-d H:i:s'), 'perms' => '0755'],
                ]], 'error' => ''],
            str_starts_with($script, 'wp-fs')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'info'
                => ['ok' => true, 'data' => json_decode('{"ok":true,"hostname":"demo-srv","os":"AlmaLinux 8.10","kernel":"4.18.0-553","uptime":"3d 4h 12m","cpu_cores":4,"loadavg":"0.21 0.18 0.10","mem_total_kb":8167020,"mem_available_kb":5980432,"disk":[{"fs":"/","size":"80G","used":"21G","avail":"59G","use_pct":27}],"services":[{"name":"nginx","unit":"nginx","status":"active"},{"name":"mysql","unit":"mysqld","status":"active"},{"name":"postgres","unit":"postgresql-16","status":"active"},{"name":"panel-php","unit":"php-fpm","status":"active"},{"name":"php74-fpm","unit":"php74-php-fpm","status":"active"},{"name":"php82-fpm","unit":"php82-php-fpm","status":"active"},{"name":"node:apidemo01","unit":"wp-node-apidemo01","status":"active"}],"sites":1,"databases":2}', true), 'error' => ''],
            str_starts_with($script, 'wp-wp')
                => ['ok' => true, 'data' => ['ok' => true, 'domain' => $args[2] ?? 'demo', 'admin' => $args[6] ?? 'admin', 'url' => 'http://demo/wp-admin/'], 'error' => ''],
            str_starts_with($script, 'wp-installatron') && $a === 'status'
                => ['ok' => true, 'data' => ['ok' => true,
                    'installed' => self::itronInstalled(),
                    'version' => self::itronInstalled() ? '5.0.1-dryrun' : ''], 'error' => ''],
            str_starts_with($script, 'wp-installatron') && $a === 'install'
                => self::itronJobStart('install'),
            str_starts_with($script, 'wp-installatron') && $a === 'upgrade'
                => self::itronJobStart('upgrade'),
            str_starts_with($script, 'wp-installatron') && $a === 'uninstall'
                => self::itronUninstall(),
            str_starts_with($script, 'wp-installatron') && $a === 'job'
                => ['ok' => true, 'data' => ['ok' => true, 'job' => self::itronJobPoll()], 'error' => ''],
            str_starts_with($script, 'wp-installatron') && $a === 'login'
                => ['ok' => true, 'data' => ['ok' => true, 'url' => 'https://ip-127-0-0-1.is.direct/dryrun-session-token'], 'error' => ''],
            str_starts_with($script, 'wp-backup') && $a === 'list'
                => ['ok' => true, 'data' => ['ok' => true, 'dir' => '/www/server/backup', 'keep' => 10, 'backups' => [
                    ['name' => 'webpanel-full-' . date('Ymd') . '-033000.tar.gz', 'scope' => 'full', 'size' => 284569907, 'mtime' => date('Y-m-d') . ' 03:30:00'],
                    ['name' => 'webpanel-db-' . date('Ymd', strtotime('-1 day')) . '-033000.tar.gz', 'scope' => 'db', 'size' => 18743296, 'mtime' => date('Y-m-d', strtotime('-1 day')) . ' 03:30:00'],
                ]], 'error' => ''],
            str_starts_with($script, 'wp-backup') && $a === 'status'
                => ['ok' => true, 'data' => ['ok' => true, 'job' => ['state' => 'idle']], 'error' => ''],
            str_starts_with($script, 'wp-backup') && $a === 'create'
                => ['ok' => true, 'data' => ['ok' => true, 'name' => 'webpanel-' . ($args[1] ?? 'full') . '-' . date('Ymd-His') . '.tar.gz', 'scope' => $args[1] ?? 'full'], 'error' => ''],
            str_starts_with($script, 'wp-backup')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            default => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
        };
    }

    /* ---- Installatron dry-run demo state machine ------------------------- *
     * Mirrors wp-installatron.sh dry-run: /tmp marker drives the installed
     * state, a /tmp json file drives the async install/upgrade job so the UI
     * can demo both page states end-to-end. Not used in production mode. */

    private const ITRON_MARKER = '/tmp/wp-dry-installatron-installed';

    private static function itronInstalled(): bool
    {
        return is_file(self::ITRON_MARKER);
    }

    private static function itronJobStart(string $kind): array
    {
        if ($kind === 'install' && self::itronInstalled()) {
            return ['ok' => false, 'data' => [], 'error' => 'Installatron 已安装，如需重装请先卸载'];
        }
        if ($kind === 'upgrade' && !self::itronInstalled()) {
            return ['ok' => false, 'data' => [], 'error' => 'Installatron 未安装'];
        }
        @file_put_contents('/tmp/wp-dry-installatron-job.json', json_encode([
            'state' => 'running', 'kind' => $kind, 'started' => time(),
        ]));
        return ['ok' => true, 'data' => ['ok' => true], 'error' => ''];
    }

    private static function itronUninstall(): array
    {
        @unlink(self::ITRON_MARKER);
        @unlink('/tmp/wp-dry-installatron-job.json');
        return ['ok' => true, 'data' => ['ok' => true], 'error' => ''];
    }

    private static function itronJobPoll(): array
    {
        $job = @json_decode((string) @file_get_contents('/tmp/wp-dry-installatron-job.json'), true);
        if (!is_array($job) || ($job['state'] ?? 'idle') !== 'running') {
            return ['state' => is_array($job) && (($job['state'] ?? '') === 'done') ? 'done' : 'idle'];
        }
        $upgrade = ($job['kind'] ?? '') === 'upgrade';
        $total = $upgrade ? 4 : 6;
        $elapsed = max(0, time() - (int) ($job['started'] ?? time()));
        $phases = $upgrade
            ? ['备份 Nginx 配置', '下载官方安装器', '执行升级', '校验与验证']
            : ['备份 Nginx 配置', '准备数据库', '下载官方安装器', '执行安装（约数分钟）', '校验 Nginx 配置', '验证安装'];
        if ($elapsed >= 4) { // demo job "completes" after 4s
            @touch(self::ITRON_MARKER);
            @file_put_contents('/tmp/wp-dry-installatron-job.json', json_encode([
                'state' => 'done', 'kind' => $job['kind'], 'started' => $job['started'],
            ]));
            return ['state' => 'done', 'kind' => $job['kind'], 'name' => 'Installatron Server',
                'phase' => '完成', 'progress' => $total, 'total' => $total,
                'started' => date('Y-m-d H:i:s', (int) $job['started']),
                'finished' => date('Y-m-d H:i:s'), 'error' => ''];
        }
        $step = min($total, (int) floor($elapsed / 4 * $total) + 1);
        return ['state' => 'running', 'kind' => $job['kind'], 'name' => 'Installatron Server',
            'phase' => $phases[$step - 1], 'progress' => $step, 'total' => $total,
            'started' => date('Y-m-d H:i:s', (int) $job['started']), 'finished' => '', 'error' => ''];
    }
}
