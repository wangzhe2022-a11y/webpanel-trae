<?php
declare(strict_types=1);

use WebPanel\Db;
use WebPanel\Shell;

class FileController extends Controller
{
    /** Synthetic file-manager root: CVM backup disk, jailed to /mnt/backup. */
    public const VDB_ID = 'vdb';
    /** Sentinel passed to wp-fs.sh (not a real Linux user; fs-worker special-cases it). */
    public const VDB_SYSUSER = '__vdb';

    private function requestSiteKey(string $key = 'site_id'): string
    {
        $raw = $_POST[$key] ?? $_GET[$key] ?? '';
        return is_string($raw) ? trim($raw) : (string) $raw;
    }

    private function isVdbRequest(string $key = 'site_id'): bool
    {
        return $this->requestSiteKey($key) === self::VDB_ID;
    }

    private function vdbSite(): array
    {
        return [
            'id' => self::VDB_ID,
            'domain' => 'vdb (/mnt/backup)',
            'sysuser' => self::VDB_SYSUSER,
            'type' => 'vdb',
            'root' => 'vdb',
            'aliases' => '',
        ];
    }

    private function siteFromRequest(string $key = 'site_id'): array
    {
        if ($this->isVdbRequest($key)) {
            return $this->vdbSite();
        }
        $site = $this->findSite((int) $this->requestSiteKey($key));
        if (!$site) {
            $this->fail('请先选择一个站点');
        }
        return $site;
    }

    private function refuseVdbWrite(): void
    {
        if ($this->isVdbRequest()) {
            $this->fail('vdb（/mnt/backup）为只读：不允许上传、编辑、新建、重命名、改权限、删除或压缩');
        }
    }

    public function index(): void
    {
        $this->requireLogin();
        $sites = $this->sites();
        $vdb = $this->vdbSite();
        $selected = null;
        $want = (string) ($_GET['site'] ?? '');
        if ($want === '') {
            $want = (string) ($_COOKIE['wp_files_site'] ?? '');
        }
        if ($want === self::VDB_ID) {
            $selected = $vdb;
        } elseif ($want !== '' && ctype_digit($want)) {
            $selected = $this->findSite((int) $want);
        }
        if (!$selected && $sites) {
            $selected = $sites[0];
        }
        if (!$selected) {
            $selected = $vdb;
        }

        $sitesClient = [];
        foreach ($sites as $s) {
            $sitesClient[] = [
                'id' => (int) $s['id'],
                'domain' => (string) $s['domain'],
                'sysuser' => (string) $s['sysuser'],
                'type' => (($s['type'] ?? 'php') === 'node') ? 'node' : 'php',
                'defaultPath' => site_default_rel($s),
                'root' => 'site',
            ];
        }
        $sitesClient[] = [
            'id' => self::VDB_ID,
            'domain' => 'vdb (/mnt/backup)',
            'sysuser' => 'vdb',
            'type' => 'vdb',
            'defaultPath' => '/',
            'root' => 'vdb',
        ];

        $isVdb = (($selected['root'] ?? '') === 'vdb') || (($selected['id'] ?? '') === self::VDB_ID);
        $this->render('files/index', [
            'sites' => $sites,
            'sitesClient' => $sitesClient,
            'selected' => $selected,
            'defaultPath' => $isVdb ? '/' : site_default_rel($selected),
            'vdbSelected' => $isVdb,
        ]);
    }

    public function ls(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->siteFromRequest();
        $rel = (string) $this->input('path', '/');
        $r = Shell::sudo('wp-fs.sh', ['list', $site['sysuser'], $rel]);
        if (!$r['ok']) {
            $this->fail($this->fsErrorZh($r['error']));
        }
        $this->ok(['path' => $r['data']['path'] ?? $rel, 'entries' => $r['data']['entries'] ?? []]);
    }

    public function read(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $r = Shell::sudo('wp-fs.sh', ['read', $site['sysuser'], (string) $this->input('path', '')]);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $this->ok(['content' => $r['data']['content'] ?? '']);
    }

    public function write(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $path = (string) $this->input('path', '');
        $content = (string) ($_POST['content'] ?? '');
        if (strlen($content) > 5 * 1024 * 1024) {
            $this->fail('文件内容不能超过 5MB');
        }
        $r = Shell::sudo('wp-fs.sh', ['write', $site['sysuser'], $path], $content);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $this->ok();
    }

    public function mkdir(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $r = Shell::sudo('wp-fs.sh', ['mkdir', $site['sysuser'], (string) $this->input('path', '')]);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $this->ok();
    }

    public function rename(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $r = Shell::sudo('wp-fs.sh', [
            'rename', $site['sysuser'],
            (string) $this->input('path', ''),
            (string) $this->input('to', ''),
        ]);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $this->ok();
    }

    public function chmod(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $r = Shell::sudo('wp-fs.sh', [
            'chmod', $site['sysuser'],
            (string) $this->input('path', ''),
            (string) $this->input('mode', ''),
        ]);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $this->ok(['perms' => $r['data']['perms'] ?? '']);
    }

    public function delete(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $r = Shell::sudo('wp-fs.sh', ['delete', $site['sysuser'], (string) $this->input('path', '')]);
        if (!$r['ok']) {
            $this->fail($r['error']);
        }
        $this->ok();
    }

    public function upload(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->fail('上传失败（错误码 ' . ($_FILES['file']['error'] ?? -1) . '）');
        }
        $f = $_FILES['file'];
        if ($f['size'] > 1024 * 1024 * 1024) {
            $this->fail('文件不能超过 1GB');
        }
        $name = $f['name'];
        if (!preg_match('/^[A-Za-z0-9._ -]+$/u', $name)) {
            $this->fail('文件名只允许字母、数字、点、下划线、空格和连字符');
        }

        $tmp = PANEL_DATA . '/tmp/up-' . bin2hex(random_bytes(8)) . '-' . $name;
        if (!move_uploaded_file($f['tmp_name'], $tmp)) {
            $this->fail('保存临时文件失败');
        }
        chmod($tmp, 0600);

        $dir = (string) $this->input('path', '/');
        $r = Shell::sudo('wp-fs.sh', ['upload', $site['sysuser'], $dir, $tmp, $name]);
        if (!$r['ok']) {
            @unlink($tmp);
            $this->fail($r['error']);
        }
        $this->ok(['name' => $name, 'size' => human_size((int) ($r['data']['size'] ?? $f['size']))]);
    }

    public function extract(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $site = $this->siteFromRequest();
        $path = (string) $this->input('path', '');
        if ($path === '') {
            $this->fail('请选择要解压的文件');
        }
        @set_time_limit(210);
        $r = Shell::sudo('wp-fs.sh', ['extract', $site['sysuser'], $path]);
        if (!$r['ok']) {
            $this->fail($this->fsErrorZh($r['error'] !== '' ? $r['error'] : '解压失败'));
        }
        $this->ok([
            'extracted' => (int) ($r['data']['extracted'] ?? 0),
            'dest' => (string) ($r['data']['dest'] ?? ''),
        ]);
    }

    public function compress(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $this->refuseVdbWrite();
        $site = $this->siteFromRequest();
        $dir = (string) $this->input('path', '/');
        $name = (string) $this->input('name', '');
        if (!preg_match('/^[A-Za-z0-9._ -]+\.zip$/i', $name)) {
            $this->fail('压缩包名须为 .zip，且只含字母、数字、点、下划线、空格和连字符');
        }
        $filesRaw = $this->input('files', '[]');
        $files = is_array($filesRaw) ? $filesRaw : json_decode((string) $filesRaw, true);
        if (!is_array($files) || $files === []) {
            $this->fail('请先选择要压缩的文件或文件夹');
        }
        $clean = [];
        foreach ($files as $n) {
            if (!is_string($n) || !preg_match('/^[A-Za-z0-9._ -]+$/u', $n) || in_array($n, ['.', '..', $name], true)) {
                $this->fail('选中的名称不合法');
            }
            $clean[] = $n;
        }
        $clean = array_values(array_unique($clean));
        @set_time_limit(210);
        $r = Shell::sudo('wp-fs.sh', ['compress', $site['sysuser'], $dir, $name], json_encode($clean, JSON_UNESCAPED_UNICODE));
        if (!$r['ok']) {
            $this->fail($this->fsErrorZh($r['error'] !== '' ? $r['error'] : '压缩失败'));
        }
        $this->ok([
            'name' => (string) ($r['data']['name'] ?? $name),
            'size' => isset($r['data']['size']) ? human_size((int) $r['data']['size']) : '',
        ]);
    }

    public function download(): void
    {
        $this->requireLogin();
        if (!\WebPanel\Csrf::verify()) {
            http_response_code(419);
            echo 'invalid csrf token';
            return;
        }
        $site = $this->siteFromRequest();
        $rel = (string) ($_GET['path'] ?? '');
        $name = basename($rel);

        // dry-run: emit placeholder without touching sudo
        if (PANEL_DRY) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            echo "dry-run placeholder for $name\n";
            return;
        }

        [$proc, $pipes] = Shell::sudoStream('wp-fs.sh', ['cat', $site['sysuser'], $rel]);
        $meta = stream_get_meta_data($pipes[2]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        // peek whether the worker errored
        $head = fread($pipes[1], 1);
        if ($head === '' && !proc_open_status_running($proc)) {
            $code = proc_close($proc);
            http_response_code(500);
            echo '下载失败：' . htmlspecialchars(trim($err) ?: "exit $code");
            return;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        echo $head;
        fpassthru($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        proc_close($proc);
    }

    /** Map a few fs-worker jail errors into the Chinese UI. */
    private function fsErrorZh(string $msg): string
    {
        $map = [
            'path escapes site jail' => '路径超出站点目录',
            'path escapes backup disk jail' => '路径超出备份盘 /mnt/backup',
            'backup disk /mnt/backup is not available' => '备份盘 /mnt/backup 不可用（未挂载或目录不存在）',
            'vdb is read-only' => 'vdb（/mnt/backup）为只读：不允许此操作',
            'symlink rejected' => '不允许操作符号链接',
            'invalid site user' => '站点用户无效',
            'site user does not exist' => '站点系统用户不存在',
            'parent directory does not exist' => '上级目录不存在',
            'invalid file name' => '文件名不合法',
            'file not found' => '文件不存在',
            'not a directory' => '不是目录',
        ];
        if (str_starts_with($msg, 'unknown action:')) {
            return '当前服务器组件不支持解压，请更新 fs-worker.php';
        }
        return $map[$msg] ?? $msg;
    }
}

function proc_open_status_running($proc): bool
{
    $st = proc_get_status($proc);
    return (bool) ($st['running'] ?? false);
}
