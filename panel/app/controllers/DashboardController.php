<?php
declare(strict_types=1);

use WebPanel\Db;

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

        $this->render('dashboard', ['info' => $info, 'stats' => $stats]);
    }
}
