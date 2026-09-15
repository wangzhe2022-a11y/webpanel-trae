<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Shell;

class InstallatronController extends Controller
{
    private const KEY_RE = '/^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/';

    public function index(): void
    {
        $this->requireLogin();
        $r = Shell::sudo('wp-installatron.sh', ['status']);
        $this->render('installatron/index', [
            'installed' => $r['ok'] && !empty($r['data']['installed']),
            'version'  => $r['ok'] ? (string) ($r['data']['version'] ?? '') : '',
            'job'      => $this->jobStatus(),
        ]);
    }

    public function status(): void
    {
        $this->requireLogin();
        $r = Shell::sudo('wp-installatron.sh', ['status']);
        $this->ok([
            'installed' => $r['ok'] && !empty($r['data']['installed']),
            'version'   => $r['ok'] ? (string) ($r['data']['version'] ?? '') : '',
            'job'       => $this->jobStatus(),
        ]);
    }

    public function install(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $key = (string) $this->input('key', '');
        if (!preg_match(self::KEY_RE, $key)) {
            $this->fail('无效的 License Key（在 installatron.com → My Account → License Key 获取）');
        }
        // key travels to the wrapper via stdin - never in argv
        $r = Shell::sudo('wp-installatron.sh', ['install'], $key);
        if (!$r['ok']) {
            $this->fail('安装任务启动失败：' . $r['error']);
        }
        Auth::log('installatron.install', 'key ' . substr($key, 0, 4) . '***');
        $this->ok();
    }

    public function login(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $r = Shell::sudo('wp-installatron.sh', ['login']);
        if (!$r['ok']) {
            $this->fail($r['error'] ?: '无法创建控制台会话');
        }
        Auth::log('installatron.login', 'console session');
        $this->ok(['url' => (string) ($r['data']['url'] ?? '')]);
    }

    public function upgrade(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $r = Shell::sudo('wp-installatron.sh', ['upgrade']);
        if (!$r['ok']) {
            $this->fail('升级任务启动失败：' . $r['error']);
        }
        Auth::log('installatron.upgrade', 'manual upgrade');
        $this->ok();
    }

    public function uninstall(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $confirm = (string) $this->input('confirm', '');
        $purge = $this->input('purge', '') === '1';
        if ($confirm !== 'UNINSTALL') {
            $this->fail('请输入 UNINSTALL 以确认卸载');
        }
        $r = Shell::sudo('wp-installatron.sh', $purge ? ['uninstall', '--purge'] : ['uninstall']);
        if (!$r['ok']) {
            $this->fail('卸载失败：' . $r['error']);
        }
        Auth::log('installatron.uninstall', $purge ? 'purge' : 'keep app data');
        $this->ok();
    }

    private function jobStatus(): array
    {
        $r = Shell::sudo('wp-installatron.sh', ['job']);
        return $r['ok'] ? ($r['data']['job'] ?? ['state' => 'idle']) : ['state' => 'idle'];
    }
}
