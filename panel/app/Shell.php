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
            str_starts_with($script, 'wp-db') && $a === 'create'
                => ['ok' => true, 'data' => ['ok' => true, 'database' => $args[1], 'user' => $args[2]], 'error' => ''],
            str_starts_with($script, 'wp-db')
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
                => ['ok' => true, 'data' => json_decode('{"ok":true,"hostname":"demo-srv","os":"AlmaLinux 8.10","kernel":"4.18.0-553","uptime":"3d 4h 12m","cpu_cores":4,"loadavg":"0.21 0.18 0.10","mem_total_kb":8167020,"mem_available_kb":5980432,"disk":[{"fs":"/","size":"80G","used":"21G","avail":"59G","use_pct":27}],"services":[{"name":"nginx","unit":"nginx","status":"active"},{"name":"mysql","unit":"mysqld","status":"active"},{"name":"panel-php","unit":"php-fpm","status":"active"},{"name":"php74-fpm","unit":"php74-php-fpm","status":"active"},{"name":"php82-fpm","unit":"php82-php-fpm","status":"active"}],"sites":1,"databases":1}', true), 'error' => ''],
            str_starts_with($script, 'wp-wp')
                => ['ok' => true, 'data' => ['ok' => true, 'domain' => $args[2] ?? 'demo', 'admin' => $args[6] ?? 'admin', 'url' => 'http://demo/wp-admin/'], 'error' => ''],
            default => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
        };
    }
}
