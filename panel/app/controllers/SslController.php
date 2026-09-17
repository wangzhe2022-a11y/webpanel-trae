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
        $nodePort = (string) ($site['app_port'] ?? '');

        $r = Shell::sudo('wp-ssl.sh', ['issue', $site['sysuser'], $domains, $site['type'] ?? 'php', $nodePort]);
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

    public function upload(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->findSite((int) $this->input('id', 0));
        if (!$site) {
            $this->fail('站点不存在');
        }

        $cert = (string) ($_POST['fullchain'] ?? '');
        $key = (string) ($_POST['privkey'] ?? '');
        if (!str_contains($cert, '-----BEGIN CERTIFICATE-----')) {
            $this->fail('证书内容不正确：需为 PEM 格式（以 -----BEGIN CERTIFICATE----- 开头，含中间证书链）');
        }
        if (!str_contains($key, 'PRIVATE KEY-----')) {
            $this->fail('私钥内容不正确：需为 PEM 格式（以 -----BEGIN ... PRIVATE KEY----- 开头）');
        }
        if (str_contains($key, 'ENCRYPTED PRIVATE KEY')) {
            $this->fail('私钥已加密，请提供未加密的私钥（腾讯云下载时不要设置私钥密码）');
        }

        // stage files for the root worker (0700/0600, panel temp area)
        $dir = PANEL_DATA . '/tmp/cert-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700, true)) {
            $this->fail('无法创建临时目录');
        }
        file_put_contents("$dir/fullchain.pem", $cert);
        file_put_contents("$dir/privkey.pem", $key);
        chmod("$dir/fullchain.pem", 0600);
        chmod("$dir/privkey.pem", 0600);

        $cleanup = function () use ($dir): void {
            @unlink("$dir/fullchain.pem");
            @unlink("$dir/privkey.pem");
            @rmdir($dir);
        };

        $r = Shell::sudo('wp-ssl.sh', [
            'deploy', $site['sysuser'], $this->domainsCsv($site),
            (string) (int) $site['hsts'], $dir, $site['type'] ?? 'php', (string) ($site['app_port'] ?? ''),
        ]);
        if (!$r['ok']) {
            $cleanup();
            $this->fail('证书部署失败：' . $r['error']);
        }
        if (PANEL_DRY) {
            $cleanup(); // real mode: worker already removed the dir
        }

        Db::run('UPDATE sites SET ssl = 1 WHERE id = ?', [$site['id']]);
        Auth::log('ssl.upload', "{$site['domain']} (third-party cert)");

        $missing = trim((string) ($r['data']['missing'] ?? ''));
        $this->ok([
            'domain' => $r['data']['domain'] ?? $site['domain'],
            'not_after' => $r['data']['not_after'] ?? '',
            'missing' => $missing,
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
        $r = Shell::sudo('wp-ssl.sh', ['remove', $site['sysuser'], $this->domainsCsv($site), $site['type'] ?? 'php', (string) ($site['app_port'] ?? '')]);
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
        $r = Shell::sudo('wp-site.sh', ['render', $site['sysuser'], $this->domainsCsv($site), '1', (string) $hsts, $site['type'] ?? 'php', (string) ($site['app_port'] ?? '')]);
        if (!$r['ok']) {
            $this->fail('切换 HSTS 失败：' . $r['error']);
        }
        Db::run('UPDATE sites SET hsts = ? WHERE id = ?', [$hsts, $site['id']]);
        Auth::log('ssl.hsts', "{$site['domain']} hsts=$hsts");
        $this->ok(['hsts' => $hsts]);
    }
}
