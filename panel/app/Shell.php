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
                $raw = $lines[0] ?? '命令执行失败';
                $j = json_decode($raw, true);
                $msg = (is_array($j) && isset($j['error'])) ? (string) $j['error'] : $raw;
            }
            return ['ok' => false, 'data' => $data, 'error' => $msg ?: '命令执行失败'];
        }
        return ['ok' => true, 'data' => $data, 'error' => ''];
    }

    public static function sudoStream(string $script, array $args): array
    {
        // binary-safe passthrough (file download) - returns proc resource + pipes.
        // `exec` so proc_get_status pid is sudo (not an extra sh), which makes
        // the download reaper able to find fs-worker.php as a direct child.
        $cmd = 'exec sudo -n ' . escapeshellarg(PANEL_BIN . '/' . $script);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg((string) $a);
        }
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes);
        if (is_resource($proc) && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
            $pipes[0] = null;
        }
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
            str_starts_with($script, 'wp-fs') && self::isVdbUser((string) ($args[1] ?? '')) && self::vdbForbidden($a)
                => ['ok' => false, 'data' => [], 'error' => 'vdb（/mnt/backup）为只读：不允许此操作'],
            str_starts_with($script, 'wp-fs') && $a === 'stat'
                => ['ok' => true, 'data' => [
                    'ok' => true,
                    'path' => (string) ($args[2] ?? '/'),
                    'name' => basename((string) ($args[2] ?? 'file')),
                    'size' => 284569907,
                    'mtime' => date('Y-m-d H:i:s'),
                ], 'error' => ''],
            str_starts_with($script, 'wp-fs') && $a === 'list'
                => self::dryFsList((string) ($args[2] ?? '/'), (string) ($args[1] ?? '')),
            str_starts_with($script, 'wp-fs') && $a === 'search'
                => self::dryFsSearch((string) ($args[2] ?? '/'), (string) ($args[3] ?? ''), (string) ($args[1] ?? '')),
            str_starts_with($script, 'wp-fs') && ($a === 'extract' || $a === 'unzip')
                => ['ok' => true, 'data' => ['ok' => true, 'extracted' => 3, 'dest' => dirname((string) ($args[2] ?? '/')) ?: '/'], 'error' => ''],
            str_starts_with($script, 'wp-fs') && ($a === 'compress' || $a === 'zip')
                => ['ok' => true, 'data' => ['ok' => true, 'name' => $args[3] ?? 'archive.zip', 'size' => 4096], 'error' => ''],
            str_starts_with($script, 'wp-fs') && $a === 'read'
                => ['ok' => true, 'data' => ['ok' => true, 'content' => self::dryFsRead((string) ($args[2] ?? ''))], 'error' => ''],
            str_starts_with($script, 'wp-fs')
                => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'info'
                => ['ok' => true, 'data' => json_decode('{"ok":true,"hostname":"demo-srv","os":"AlmaLinux 8.10","kernel":"4.18.0-553","uptime":"3d 4h 12m","cpu_cores":4,"cpu_usage_pct":8.2,"loadavg":"0.21 0.18 0.10","load_1":0.21,"load_5":0.18,"load_15":0.10,"mem_total_kb":3880152,"mem_available_kb":2142200,"mem_used_kb":1737952,"mem_used_pct":44.8,"swap_total_kb":4194304,"swap_used_kb":102400,"swap_free_kb":4091904,"swap_used_pct":2.4,"disk":[{"fs":"/","size":"50G","used":"18G","avail":"32G","use_pct":36},{"fs":"/mnt/backup","size":"100G","used":"52G","avail":"48G","use_pct":52},{"fs":"/boot/efi","size":"511M","used":"9.1M","avail":"502M","use_pct":2}],"top":[{"name":"mysqld","rss_kb":412000},{"name":"php-fpm","rss_kb":186000},{"name":"nginx","rss_kb":42000},{"name":"postgres","rss_kb":38000},{"name":"node","rss_kb":28000},{"name":"sshd","rss_kb":12000}],"services":[{"name":"nginx","unit":"nginx","status":"active"},{"name":"mysql","unit":"mysqld","status":"active"},{"name":"postgres","unit":"postgresql-16","status":"active"},{"name":"panel-php","unit":"php-fpm","status":"active"},{"name":"php74-fpm","unit":"php74-php-fpm","status":"active"},{"name":"php82-fpm","unit":"php82-php-fpm","status":"active"},{"name":"node:apidemo01","unit":"wp-node-apidemo01","status":"active"}],"sites":1,"databases":2}', true), 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'logins'
                => ['ok' => true, 'data' => ['ok' => true, 'logins' => [
                    ['time' => 'Sep 23 13:51:08', 'user' => 'root', 'ip' => '203.0.113.10', 'method' => 'publickey'],
                    ['time' => 'Sep 23 12:08:41', 'user' => 'trae_solo', 'ip' => '203.0.113.10', 'method' => 'publickey'],
                    ['time' => 'Sep 23 09:22:15', 'user' => 'demo', 'ip' => '198.51.100.24', 'method' => 'password'],
                    ['time' => 'Sep 22 22:14:03', 'user' => 'root', 'ip' => '203.0.113.10', 'method' => 'publickey'],
                    ['time' => 'Sep 22 18:02:11', 'user' => 'root', 'ip' => '198.51.100.24', 'method' => 'password'],
                    ['time' => 'Sep 22 11:45:30', 'user' => 'trae_solo', 'ip' => '203.0.113.10', 'method' => 'publickey'],
                    ['time' => 'Sep 21 20:17:44', 'user' => 'demo', 'ip' => '203.0.113.55', 'method' => 'password'],
                    ['time' => 'Sep 21 14:03:09', 'user' => 'root', 'ip' => '203.0.113.10', 'method' => 'publickey'],
                    ['time' => 'Sep 20 23:58:01', 'user' => 'trae_solo', 'ip' => '198.51.100.24', 'method' => 'publickey'],
                    ['time' => 'Sep 20 16:21:37', 'user' => 'root', 'ip' => '203.0.113.10', 'method' => 'password'],
                    ['time' => 'Sep 19 08:40:12', 'user' => 'demo', 'ip' => '203.0.113.55', 'method' => 'publickey'],
                    ['time' => 'Sep 18 19:05:55', 'user' => 'root', 'ip' => '203.0.113.10', 'method' => 'publickey'],
                ]], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'access'
                => ['ok' => true, 'data' => ['ok' => true,
                    'total' => 42,
                    'unique_ips' => 5,
                    'recent' => [
                        ['time' => date('d/M/Y:H:i:s O'), 'ip' => '203.0.113.10', 'method' => 'GET', 'uri' => '/', 'status' => 200, 'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'],
                        ['time' => date('d/M/Y:H:i:s O', time() - 3), 'ip' => '203.0.113.10', 'method' => 'POST', 'uri' => '/login', 'status' => 200, 'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'],
                        ['time' => date('d/M/Y:H:i:s O', time() - 20), 'ip' => '198.51.100.24', 'method' => 'POST', 'uri' => '/login', 'status' => 401, 'ua' => 'python-requests/2.31'],
                        ['time' => date('d/M/Y:H:i:s O', time() - 22), 'ip' => '198.51.100.24', 'method' => 'POST', 'uri' => '/login', 'status' => 401, 'ua' => 'python-requests/2.31'],
                        ['time' => date('d/M/Y:H:i:s O', time() - 110), 'ip' => '45.33.22.11', 'method' => 'GET', 'uri' => '/.env', 'status' => 404, 'ua' => 'Mozilla/5.0 (compatible; Nmap Scripting Engine)'],
                        ['time' => date('d/M/Y:H:i:s O', time() - 127), 'ip' => '45.33.22.11', 'method' => 'GET', 'uri' => '/wp-admin/', 'status' => 404, 'ua' => 'Mozilla/5.0 (compatible; Nmap Scripting Engine)'],
                    ],
                    'failed_logins' => [
                        ['ip' => '198.51.100.24', 'count' => 12],
                    ],
                    'suspicious' => [
                        ['ip' => '198.51.100.24', 'reason' => '12 次登录失败', 'count' => 12, 'level' => 'high'],
                        ['ip' => '45.33.22.11', 'reason' => '扫描敏感路径', 'count' => 2, 'level' => 'medium'],
                    ],
                ], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'denylist'
                => ['ok' => true, 'data' => ['ok' => true, 'denied' => ['45.33.22.11', '198.51.100.24']], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'deny'
                => ['ok' => true, 'data' => ['ok' => true, 'action' => 'deny', 'ip' => $args[1] ?? '0.0.0.0'], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'undeny'
                => ['ok' => true, 'data' => ['ok' => true, 'action' => 'undeny', 'ip' => $args[1] ?? '0.0.0.0'], 'error' => ''],
            str_starts_with($script, 'wp-sys') && $a === 'fpm-safe-restart'
                => ['ok' => true, 'data' => [
                    'ok' => true,
                    'restarted' => ['php-fpm', 'php74-php-fpm', 'php83-php-fpm'],
                    'skipped' => ['php80-php-fpm', 'php81-php-fpm', 'php82-php-fpm'],
                    'failed' => [],
                ], 'error' => ''],
            str_starts_with($script, 'wp-wp')
                => ['ok' => true, 'data' => ['ok' => true, 'domain' => $args[2] ?? 'demo', 'admin' => $args[6] ?? 'admin', 'url' => 'http://demo/wp-admin/'], 'error' => ''],
            str_starts_with($script, 'wp-pma') && $a === 'status'
                => ['ok' => true, 'data' => ['ok' => true,
                    'installed' => self::pmaInstalled(),
                    'version' => self::pmaInstalled() ? '5.2.3-dryrun' : '',
                    'url' => '/phpmyadmin/'], 'error' => ''],
            str_starts_with($script, 'wp-pma') && $a === 'install'
                => self::pmaInstall(),
            str_starts_with($script, 'wp-pma') && $a === 'uninstall'
                => self::pmaUninstall(),
            str_starts_with($script, 'wp-backup') && $a === 'stat'
                => ['ok' => true, 'data' => ['ok' => true, 'name' => $args[1] ?? '', 'size' => 284569907], 'error' => ''],
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
            str_starts_with($script, 'wp-atop')
                => self::dryAtop($args),
            default => ['ok' => true, 'data' => ['ok' => true], 'error' => ''],
        };
    }

    private static function isVdbUser(string $user): bool
    {
        return $user === '__vdb';
    }

    private static function vdbForbidden(string $action): bool
    {
        return in_array($action, ['write', 'mkdir', 'rename', 'chmod', 'delete', 'upload', 'compress', 'zip', 'read'], true);
    }

    /** Path-aware demo listing so the file-manager tree/browse UI is clickable. */
    private static function dryFsList(string $rel, string $user = ''): array
    {
        $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
        if ($rel === '//') {
            $rel = '/';
        }
        if (self::isVdbUser($user)) {
            return self::dryVdbList($rel);
        }
        $now = date('Y-m-d H:i:s');
        $file = static function (string $name, int $size = 4096, string $perms = '0644') use ($now): array {
            return ['name' => $name, 'type' => 'file', 'size' => $size, 'mtime' => $now, 'perms' => $perms];
        };
        $dir = static function (string $name, string $perms = '0755') use ($now): array {
            return ['name' => $name, 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => $perms];
        };
        $entries = match ($rel) {
            '/', '' => [$dir('public'), $dir('app'), $dir('logs')],
            '/public' => [
                $dir('account_live_order@t-shirtshanghai.com'),
                $dir('wp-content'),
                $file('index.php', 4521),
                $file('wp-config.php', 3012, '0640'),
                $file('theme.zip', 204800),
            ],
            '/public/account_live_order@t-shirtshanghai.com' => [
                $dir('orderid_5c714d42e445c0a1b2'),
                $file('index.php', 128),
            ],
            '/public/account_live_order@t-shirtshanghai.com/orderid_5c714d42e445c0a1b2' => [
                $file('order.json', 2048),
            ],
            '/public/wp-content' => [$dir('plugins'), $dir('themes'), $dir('uploads')],
            '/public/wp-content/plugins' => [$dir('akismet'), $file('index.php', 28)],
            '/public/wp-content/themes' => [$dir('twentytwentyfour'), $file('index.php', 28)],
            '/public/wp-content/uploads' => [$dir('2026'), $file('.htaccess', 64)],
            '/app' => [$dir('node_modules'), $file('index.js', 1204), $file('package.json', 812)],
            '/app/node_modules' => [$dir('express')],
            '/logs' => [$file('access.log', 2048), $file('error.log', 512)],
            default => [],
        };
        return ['ok' => true, 'data' => ['ok' => true, 'path' => $rel === '' ? '/' : $rel, 'entries' => $entries], 'error' => ''];
    }

    /** Demo file content for the editor (path-aware, extension-matched samples). */
    private static function dryFsRead(string $rel): string
    {
        $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
        $name = basename($rel);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $samples = [
            'php' => "<?php\n/**\n * Demo index.php — dry-run sample content.\n */\ndeclare(strict_types=1);\n\nrequire __DIR__ . '/../vendor/autoload.php';\n\n$app = new \\App\\Application();\n$app->boot();\n$app->run();\n",
            'js' => "// Demo JavaScript — dry-run sample content.\nconst express = require('express');\nconst app = express();\nconst PORT = process.env.PORT || 3000;\n\napp.get('/', (req, res) => {\n  res.json({ ok: true, message: 'Hello from dry-run demo' });\n});\n\napp.listen(PORT, () => {\n  console.log(`Server listening on port ${PORT}`);\n});\n",
            'json' => "{\n  \"name\": \"demo-app\",\n  \"version\": \"1.0.0\",\n  \"description\": \"Dry-run sample package.json\",\n  \"main\": \"index.js\",\n  \"scripts\": {\n    \"start\": \"node index.js\",\n    \"test\": \"jest\"\n  },\n  \"dependencies\": {\n    \"express\": \"^4.18.0\"\n  }\n}\n",
            'html' => "<!DOCTYPE html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <title>Demo Page</title>\n</head>\n<body>\n  <h1>Hello, dry-run demo!</h1>\n  <p>This is sample HTML content.</p>\n</body>\n</html>\n",
            'css' => "/* Demo stylesheet — dry-run sample content. */\n:root {\n  --primary: #90BA1E;\n  --bg: #f0f2f5;\n}\n\nbody {\n  margin: 0;\n  font-family: system-ui, sans-serif;\n  background: var(--bg);\n}\n\n.btn {\n  padding: 8px 16px;\n  border-radius: 6px;\n  background: var(--primary);\n  color: #fff;\n}\n",
            'htaccess' => "# Demo .htaccess — dry-run sample content.\n<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteBase /\n  RewriteRule ^index\\.php$ - [L]\n  RewriteCond %{REQUEST_FILENAME} !-f\n  RewriteCond %{REQUEST_FILENAME} !-d\n  RewriteRule . /index.php [L]\n</IfModule>\n",
            'log' => "[2026-09-24 03:30:01] INFO  Server started on port 80\n[2026-09-24 03:30:05] INFO  GET / 200 12ms\n[2026-09-24 03:31:12] WARN  Slow query: 1280ms\n[2026-09-24 03:32:00] ERROR Connection refused: db\n",
            'md' => "# Demo README\n\nThis is **dry-run** sample markdown content.\n\n## Features\n\n- Syntax highlighting\n- Find & Replace (Ctrl+F / Ctrl+H)\n- Line numbers\n- Minimap\n",
            'sql' => "-- Demo SQL — dry-run sample content.\nCREATE TABLE IF NOT EXISTS users (\n  id INTEGER PRIMARY KEY AUTOINCREMENT,\n  username VARCHAR(64) NOT NULL UNIQUE,\n  email VARCHAR(255) NOT NULL,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP\n);\n\nINSERT INTO users (username, email) VALUES\n  ('admin', 'admin@example.com'),\n  ('demo', 'demo@example.com');\n",
            'sh' => "#!/bin/bash\n# Demo shell script — dry-run sample content.\nset -euo pipefail\n\necho \"Starting backup...\"\nDATE=$(date +%Y%m%d)\ntar -czf \"/tmp/backup-${DATE}.tar.gz\" /www/wwwroot\necho \"Backup complete.\"\n",
            'txt' => "Demo text file — dry-run sample content.\n\nLine 2\nLine 3\nLine 4\n",
        ];
        if ($name === '.htaccess') {
            return $samples['htaccess'];
        }
        return $samples[$ext] ?? "// {$name} — dry-run sample content.\n// Edit me and press Ctrl+S to save.\n";
    }

    /** Filename search over the demo tree (current dir + descendants). */
    private static function dryFsSearch(string $rel, string $q, string $user = ''): array
    {
        $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
        if ($rel === '//') {
            $rel = '/';
        }
        $dirs = self::isVdbUser($user)
            ? ['/', '/archives', '/snapshots', '/snapshots/2026-09']
            : [
                '/',
                '/public',
                '/public/account_live_order@t-shirtshanghai.com',
                '/public/account_live_order@t-shirtshanghai.com/orderid_5c714d42e445c0a1b2',
                '/public/wp-content',
                '/public/wp-content/plugins',
                '/public/wp-content/themes',
                '/public/wp-content/uploads',
                '/app',
                '/app/node_modules',
                '/logs',
            ];
        $hits = [];
        foreach ($dirs as $dir) {
            $listed = self::dryFsList($dir, $user);
            foreach ($listed['data']['entries'] ?? [] as $e) {
                $name = (string) ($e['name'] ?? '');
                $path = $dir === '/' ? '/' . $name : $dir . '/' . $name;
                if ($rel !== '/' && $path !== $rel && !str_starts_with($path, $rel . '/')) {
                    continue;
                }
                if ($name === '' || stripos($name, $q) === false) {
                    continue;
                }
                $hits[] = [
                    'name' => $name,
                    'path' => $path,
                    'dir' => $dir,
                    'type' => $e['type'] ?? 'file',
                    'size' => (int) ($e['size'] ?? 0),
                ];
            }
        }
        return ['ok' => true, 'data' => [
            'ok' => true,
            'path' => $rel === '' ? '/' : $rel,
            'q' => $q,
            'hits' => $hits,
            'truncated' => false,
        ], 'error' => ''];
    }

    /** Fake CVM backup-disk tree so vdb is demoable without /mnt/backup. */
    private static function dryVdbList(string $rel): array
    {
        $now = date('Y-m-d H:i:s');
        $file = static function (string $name, int $size = 4096, string $perms = '0644') use ($now): array {
            return ['name' => $name, 'type' => 'file', 'size' => $size, 'mtime' => $now, 'perms' => $perms];
        };
        $dir = static function (string $name, string $perms = '0755') use ($now): array {
            return ['name' => $name, 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => $perms];
        };
        $entries = match ($rel) {
            '/', '' => [
                $dir('archives'),
                $dir('snapshots'),
                $file('README.txt', 186),
                $file('site-backup-20260924.tar.gz', 284569907),
            ],
            '/archives' => [
                $file('webpanel-full-20260924-033000.tar.gz', 284569907),
                $file('webpanel-db-20260923-033000.tar.gz', 18743296),
                $file('theme-export.zip', 204800),
            ],
            '/snapshots' => [
                $dir('2026-09'),
                $file('notes.txt', 128),
            ],
            '/snapshots/2026-09' => [
                $file('vdb-20260920.img', 10485760),
            ],
            default => [],
        };
        return ['ok' => true, 'data' => ['ok' => true, 'path' => $rel === '' ? '/' : $rel, 'entries' => $entries], 'error' => ''];
    }

    /* ---- phpMyAdmin dry-run demo ----------------------------------------- */

    private const PMA_MARKER = '/tmp/wp-dry-pma-installed';

    private static function pmaInstalled(): bool
    {
        return is_file(self::PMA_MARKER);
    }

    private static function pmaInstall(): array
    {
        @touch(self::PMA_MARKER);
        return ['ok' => true, 'data' => ['ok' => true, 'installed' => true,
            'version' => '5.2.3-dryrun', 'url' => '/phpmyadmin/'], 'error' => ''];
    }

    private static function pmaUninstall(): array
    {
        @unlink(self::PMA_MARKER);
        return ['ok' => true, 'data' => ['ok' => true], 'error' => ''];
    }

    /** Demo atop history so the dashboard card is clickable without host logs. */
    private static function dryAtop(array $args): array
    {
        $today = date('Ymd');
        $yday = date('Ymd', strtotime('-1 day'));
        $now = date('H:i');
        $file = (string) ($args[1] ?? '');
        if ($file === '' || $file === 'auto' || $file === '-') {
            $file = 'atop_' . $today;
        }
        $time = (string) ($args[2] ?? '');
        if ($time === '' || $time === 'latest' || $time === '-') {
            $time = $now;
        }
        $latest = (int) ($args[3] ?? 1);
        if ($latest < 1) {
            $latest = 1;
        }
        if ($latest > 24) {
            $latest = 24;
        }

        $sample = [
            'time' => $time,
            'epoch' => time(),
            'interval_s' => 600,
            'nrcpu' => 4,
            'cpu_busy_pct' => 12.4,
            'cpu_user_pct' => 8.1,
            'cpu_sys_pct' => 3.2,
            'cpu_wait_pct' => 1.1,
            'load_1' => 0.42,
            'load_5' => 0.31,
            'load_15' => 0.22,
            'loadavg' => '0.42 0.31 0.22',
            'mem_total_kb' => 8048576,
            'mem_used_kb' => 3211264,
            'mem_avail_kb' => 4837312,
            'mem_used_pct' => 39.9,
            'cache_kb' => 1048576,
            'swap_total_kb' => 4194304,
            'swap_used_kb' => 102400,
            'swap_used_pct' => 2.4,
            'disk' => [['name' => 'vda', 'busy_pct' => 4.2, 'reads' => 120, 'writes' => 48]],
        ];
        $recent = [];
        for ($i = $latest - 1; $i >= 0; $i--) {
            $row = $sample;
            $row['time'] = date('H:i', strtotime($time) - ($i * 600));
            $row['cpu_busy_pct'] = round(10 + $i * 1.2, 1);
            $row['mem_used_pct'] = round(38 + $i * 0.4, 1);
            $recent[] = $row;
        }
        $times = ['00:00', '06:00', '12:00', '18:00', $time];
        $times = array_values(array_unique($times));

        return ['ok' => true, 'data' => [
            'ok' => true,
            'installed' => true,
            'version' => '2.7.1-dryrun',
            'service' => 'active',
            'enabled' => 'enabled',
            'log_path' => '/var/log/atop',
            'interval_s' => 600,
            'last_log_mtime' => date('Y-m-d H:i:s'),
            'logs' => [
                ['name' => 'atop_' . $today, 'size' => 1843200, 'mtime' => date('Y-m-d H:i:s')],
                ['name' => 'atop_' . $yday, 'size' => 2105344, 'mtime' => date('Y-m-d', strtotime('-1 day')) . ' 23:50:00'],
            ],
            'file' => $file,
            'time' => $time,
            'times' => $times,
            'sample' => $sample,
            'recent' => $recent,
            'top_cpu' => [
                ['pid' => 1842, 'name' => 'mysqld', 'cpu_pct' => 6.2, 'rss_kb' => 412000, 'disk_kb' => 8192],
                ['pid' => 2201, 'name' => 'php-fpm', 'cpu_pct' => 3.1, 'rss_kb' => 186000, 'disk_kb' => 1024],
                ['pid' => 991, 'name' => 'nginx', 'cpu_pct' => 0.8, 'rss_kb' => 42000, 'disk_kb' => 256],
            ],
            'top_mem' => [
                ['pid' => 1842, 'name' => 'mysqld', 'cpu_pct' => 6.2, 'rss_kb' => 412000, 'disk_kb' => 8192],
                ['pid' => 2201, 'name' => 'php-fpm', 'cpu_pct' => 3.1, 'rss_kb' => 186000, 'disk_kb' => 1024],
                ['pid' => 1, 'name' => 'systemd', 'cpu_pct' => 0.1, 'rss_kb' => 9800, 'disk_kb' => 0],
            ],
            'error' => '',
        ], 'error' => ''];
    }
}
