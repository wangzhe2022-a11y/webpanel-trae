<?php
declare(strict_types=1);

use WebPanel\Db;

/**
 * Installatron Remote helper page.
 *
 * The panel does not install Installatron Server locally and never stores
 * installatron.com credentials. Operators connect sites from
 * https://installatron.com/apps using SFTP/SSH plus the hints on this page.
 */
class InstallatronController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();

        $sites = Db::all(
            "SELECT * FROM sites ORDER BY CASE WHEN COALESCE(type,'php') = 'node' THEN 1 ELSE 0 END, id DESC"
        );
        $dbs = Db::all(
            'SELECT id, site_id, name, username, engine FROM databases ORDER BY id DESC'
        );
        $dbsBySite = [];
        foreach ($dbs as $d) {
            $sid = (int) ($d['site_id'] ?? 0);
            if ($sid > 0) {
                $dbsBySite[$sid][] = $d;
            }
        }
        foreach ($sites as &$s) {
            $isNode = ($s['type'] ?? 'php') === 'node';
            $s['is_node'] = $isNode;
            $s['docroot'] = '/www/wwwroot/' . $s['sysuser'] . '/' . ($isNode ? 'app' : 'public');
            $s['db_list'] = $dbsBySite[(int) $s['id']] ?? [];
        }
        unset($s);

        $this->render('installatron/index', [
            'sites' => $sites,
            'conn'  => $this->connectionHints(),
        ]);
    }

    /**
     * Host/port hints for the operator. Values are derived from this request
     * and the dashboard cache — no privileged script is invoked.
     */
    private function connectionHints(): array
    {
        $hostname = gethostname() ?: '';
        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $httpHost = (string) preg_replace('/:\d+$/', '', $httpHost);

        $publicIp = '';
        if (filter_var($httpHost, FILTER_VALIDATE_IP)) {
            $publicIp = $httpHost;
        }

        $cacheFile = PANEL_DATA . '/cache/sys-info.json';
        if ($hostname === '' && is_file($cacheFile)) {
            $info = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($info) && !empty($info['hostname'])) {
                $hostname = (string) $info['hostname'];
            }
        }

        $suggestedHost = $publicIp !== '' ? $publicIp : ($httpHost !== '' ? $httpHost : $hostname);

        return [
            'hostname'       => $hostname,
            'public_ip'      => $publicIp,
            'http_host'      => $httpHost,
            'suggested_host' => $suggestedHost,
            'ssh_port'       => 22,
        ];
    }
}
