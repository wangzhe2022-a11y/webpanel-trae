<?php
declare(strict_types=1);

use WebPanel\Db;
use WebPanel\Shell;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();

        $info = panel_sys_info();

        $stats = [
            'sites' => (int) Db::one('SELECT COUNT(*) c FROM sites')['c'],
            'databases' => (int) Db::one('SELECT COUNT(*) c FROM databases')['c'],
            'ssl' => (int) Db::one('SELECT COUNT(*) c FROM sites WHERE ssl = 1')['c'],
        ];

        $recentLogins = Db::all(
            "SELECT actor, ip, ts FROM action_log WHERE action = 'login' ORDER BY id DESC LIMIT 10"
        );

        $this->render('dashboard', [
            'info' => $info,
            'stats' => $stats,
            'recentLogins' => $recentLogins,
            'sshLogins' => $this->sshLogins(),
        ]);
    }

    /**
     * Recent SSH authentication successes for ALL system users (root,
     * trae_solo, site users, ...), including non-interactive exec sessions
     * that never appear in wtmp. Data comes from wp-sys.sh `logins` which
     * parses /var/log/secure as root.
     *
     * @return list<array{user: string, ip: string, method: string, time: string}>
     */
    private function sshLogins(int $limit = 10): array
    {
        $res = Shell::sudo('wp-sys.sh', ['logins', (string) $limit]);
        if (!$res['ok'] || !is_array($res['data']['logins'] ?? null)) {
            return [];
        }

        return array_map(static function (array $r): array {
            return [
                'user' => (string) ($r['user'] ?? '-'),
                'ip' => (string) ($r['ip'] ?? '-'),
                'method' => (string) ($r['method'] ?? '-'),
                'time' => (string) ($r['time'] ?? '-'),
            ];
        }, $res['data']['logins']);
    }
}
