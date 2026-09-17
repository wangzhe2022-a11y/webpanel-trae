<?php
declare(strict_types=1);

use WebPanel\Db;
use WebPanel\Shell;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();

        $cacheFile = PANEL_DATA . '/cache/sys-info.json';
        $info = null;
        if (is_file($cacheFile) && time() - filemtime($cacheFile) < 5) {
            $info = json_decode((string) file_get_contents($cacheFile), true);
        }
        if (!is_array($info)) {
            $res = Shell::sudo('wp-sys.sh', ['info']);
            if ($res['ok']) {
                $info = $res['data'];
                @file_put_contents($cacheFile, json_encode($info, JSON_UNESCAPED_UNICODE));
            } else {
                $info = ['error' => $res['error']];
            }
        }

        $stats = [
            'sites' => (int) Db::one('SELECT COUNT(*) c FROM sites')['c'],
            'databases' => (int) Db::one('SELECT COUNT(*) c FROM databases')['c'],
            'ssl' => (int) Db::one('SELECT COUNT(*) c FROM sites WHERE ssl = 1')['c'],
        ];

        $this->render('dashboard', ['info' => $info, 'stats' => $stats]);
    }
}
