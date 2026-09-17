<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Shell;

class BackupController extends Controller
{
    private const NAME_RE = '/^webpanel-(full|files|db)-\d{8}-\d{6}(-\d{1,3})?\.tar\.gz$/';

    public function index(): void
    {
        $this->requireLogin();
        $r = Shell::sudo('wp-backup.sh', ['list']);
        $this->render('backup/index', [
            'backups'   => $r['ok'] ? ($r['data']['backups'] ?? []) : [],
            'backupDir' => $r['ok'] ? ($r['data']['dir'] ?? '') : '',
            'keep'      => $r['ok'] ? (int) ($r['data']['keep'] ?? 0) : 0,
            'job'       => $this->jobStatus(),
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $scope = (string) $this->input('scope', 'full');
        if (!in_array($scope, ['full', 'files', 'db'], true)) {
            $this->fail('无效的备份类型');
        }
        $r = Shell::sudo('wp-backup.sh', ['create', $scope]);
        if (!$r['ok']) {
            $this->fail('备份任务启动失败：' . $r['error']);
        }
        Auth::log('backup.create', $scope);
        $this->ok(['name' => $r['data']['name'] ?? '', 'scope' => $scope]);
    }

    public function status(): void
    {
        $this->requireLogin();
        $this->ok(['job' => $this->jobStatus()]);
    }

    public function delete(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $name = (string) $this->input('name', '');
        if (!preg_match(self::NAME_RE, $name)) {
            $this->fail('无效的备份文件名');
        }
        $r = Shell::sudo('wp-backup.sh', ['delete', $name]);
        if (!$r['ok']) {
            $this->fail('删除失败：' . $r['error']);
        }
        Auth::log('backup.delete', $name);
        $this->ok();
    }

    public function restore(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $name = (string) $this->input('name', '');
        $confirm = (string) $this->input('confirm', '');
        if (!preg_match(self::NAME_RE, $name)) {
            $this->fail('无效的备份文件名');
        }
        if ($confirm !== 'RESTORE') {
            $this->fail('请输入 RESTORE 以确认恢复操作');
        }
        $r = Shell::sudo('wp-backup.sh', ['restore', $name]);
        if (!$r['ok']) {
            $this->fail('恢复任务启动失败：' . $r['error']);
        }
        Auth::log('backup.restore', $name);
        $this->ok(['name' => $name]);
    }

    public function download(): void
    {
        $this->requireLogin();
        if (!\WebPanel\Csrf::verify()) {
            http_response_code(419);
            echo 'invalid csrf token';
            return;
        }
        $name = (string) ($_GET['name'] ?? '');
        if (!preg_match(self::NAME_RE, $name)) {
            http_response_code(400);
            echo 'invalid backup name';
            return;
        }

        if (PANEL_DRY) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            echo "dry-run placeholder for $name\n";
            return;
        }

        [$proc, $pipes] = Shell::sudoStream('wp-backup.sh', ['download', $name]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $head = fread($pipes[1], 1);
        $running = (bool) (proc_get_status($proc)['running'] ?? false);
        if ($head === '' && !$running) {
            $code = proc_close($proc);
            http_response_code(500);
            echo '下载失败：' . htmlspecialchars(trim($err) ?: "exit $code");
            return;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        echo $head;
        fpassthru($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        proc_close($proc);
    }

    private function jobStatus(): array
    {
        $r = Shell::sudo('wp-backup.sh', ['status']);
        return $r['ok'] ? ($r['data']['job'] ?? ['state' => 'idle']) : ['state' => 'idle'];
    }
}
