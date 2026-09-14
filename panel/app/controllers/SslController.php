<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Db;
use WebPanel\Shell;

class SslController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $sites = Db::all('SELECT * FROM sites ORDER BY id DESC');
        $certs = [];
        $res = Shell::sudo('wp-ssl.sh', ['list']);
        if ($res['ok']) {
            foreach ($res['data']['certs'] ?? [] as $c) {
                $certs[$c['domain']] = $c;
            }
        }
        $this->render('ssl/index', ['sites' => $sites, 'certs' => $certs, 'listError' => $res['ok'] ? '' : $res['error']]);
    }

    public function issue(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        $domains = $this->domainsCsv($site);

        $r = Shell::sudo('wp-ssl.sh', ['issue', $site['sysuser'], $domains]);
        if (!$r['ok']) {
            $this->fail('SSL 签发失败：' . $r['error'] . '（请确认所有域名已解析到本机，且 80 端口可从公网访问）');
        }
        Db::run('UPDATE sites SET ssl = 1, hsts = 0 WHERE id = ?', [$site['id']]);
        Auth::log('ssl.issue', $domains);
        $this->ok([
            'domain' => $r['data']['domain'] ?? $site['domain'],
            'not_after' => $r['data']['not_after'] ?? '',
        ]);
    }

    public function remove(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        $r = Shell::sudo('wp-ssl.sh', ['remove', $site['sysuser'], $this->domainsCsv($site)]);
        if (!$r['ok']) {
            $this->fail('删除证书失败：' . $r['error']);
        }
        Db::run('UPDATE sites SET ssl = 0, hsts = 0 WHERE id = ?', [$site['id']]);
        Auth::log('ssl.remove', $site['domain']);
        $this->ok();
    }

    public function toggleHsts(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }
        if ((int) $site['ssl'] !== 1) {
            $this->fail('请先签发并启用 SSL 证书');
        }
        $hsts = (int) $this->input('hsts', 0) === 1 ? 1 : 0;
        $r = Shell::sudo('wp-site.sh', ['render', $site['sysuser'], $this->domainsCsv($site), '1', (string) $hsts]);
        if (!$r['ok']) {
            $this->fail('切换 HSTS 失败：' . $r['error']);
        }
        Db::run('UPDATE sites SET hsts = ? WHERE id = ?', [$hsts, $site['id']]);
        Auth::log('ssl.hsts', "{$site['domain']} hsts=$hsts");
        $this->ok(['hsts' => $hsts]);
    }
}
