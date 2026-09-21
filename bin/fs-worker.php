#!/usr/bin/php
<?php
/**
 * fs-worker.php - root file manager backend for WebPanel
 *
 * Invoked (via sudo) as: wp-fs.sh <action> <siteUser> <relPath> [extra...]
 *
 * Hard rules:
 *  - every path must resolve inside /www/wwwroot/<siteUser>
 *  - symlinks pointing outside the jail are rejected
 *  - writes are chown()ed back to the site user
 *  - only a narrow set of text files is editable in the browser
 *  - setuid/setgid bits are never allowed
 *  - extract (zip / tar.gz / tgz) stays inside the jail; zip-slip / symlinks rejected
 */

const DRY_MARKER = '/usr/local/webpanel/.dryrun';

$WEB_ROOT = getenv('WEB_ROOT') ?: '/www/wwwroot';
$DRY = file_exists(DRY_MARKER);

function out(array $a): void { fwrite(STDOUT, json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"); exit(0); }
function err(string $m, int $c = 1): void { fwrite(STDERR, json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE) . "\n"); exit($c); }

function valid_user(string $u): bool { return (bool) preg_match('/^[a-z][a-z0-9_]{2,30}$/', $u); }

function jail(string $user, string $rel): string {
    global $WEB_ROOT;
    $base = $WEB_ROOT . '/' . $user;
    $abs  = realpath($base . '/' . ltrim($rel, '/'));
    if ($abs === false) {
        // target does not exist yet - resolve the parent
        $rel2 = ltrim($rel, '/');
        $parent = realpath($base . '/' . dirname($rel2 === '' ? '.' : $rel2));
        $name = basename($rel2);
        if ($parent === false) err('parent directory does not exist');
        if ($parent !== $base && strpos($parent . '/', $base . '/') !== 0) err('path escapes site jail');
        if (!preg_match('/^[A-Za-z0-9._ -]+$/u', $name)) err('invalid file name');
        return $parent . '/' . $name;
    }
    if ($abs !== $base && strpos($abs . '/', $base . '/') !== 0) err('path escapes site jail');
    if (is_link($base . '/' . ltrim($rel, '/'))) {
        $target = readlink($base . '/' . ltrim($rel, '/'));
        err('symlink rejected');
    }
    return $abs;
}

function site_user_id(string $user): array {
    $info = posix_getpwnam($user);
    if (!$info) err('site user does not exist');
    return [$info['uid'], $info['gid']];
}

function chown_to(string $path, string $user): void {
    [$uid, $gid] = site_user_id($user);
    @chown($path, $uid);
    @chgrp($path, $gid);
}

function perm_octal(string $path): string { return substr(sprintf('%o', fileperms($path)), -4); }

/* ------------------------------ extract helpers ------------------------- */
const EXTRACT_MAX_ARCHIVE = 1073741824;      // 1 GiB (same as upload)
const EXTRACT_MAX_UNCOMP  = 8589934592;      // 8 GiB uncompressed
const EXTRACT_MAX_FILES   = 20000;
const EXTRACT_TIMEOUT     = 180;

function cmd_capture(array $argv, int $timeout = 60, ?string $cwd = null): array {
    if ($argv === [] || ($argv[0][0] ?? '') !== '/' || !is_file($argv[0])) {
        err('内部错误：命令不可用');
    }
    $cmd = implode(' ', array_map('escapeshellarg', $argv));
    if (is_executable('/usr/bin/timeout')) {
        $cmd = '/usr/bin/timeout --signal=KILL ' . (int) $timeout . ' ' . $cmd;
    }
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes, $cwd);
    if (!is_resource($proc)) err('无法执行压缩/解压命令');
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
}

function cmd_timed_out(int $code): bool { return $code === 124 || $code === 137; }

function archive_kind(string $path): string {
    $base = strtolower(basename($path));
    if (str_ends_with($base, '.tar.gz') || str_ends_with($base, '.tgz')) return 'tar';
    if (str_ends_with($base, '.zip')) return 'zip';
    return '';
}

function archive_magic_ok(string $path, string $kind): bool {
    $fh = fopen($path, 'rb');
    if (!$fh) return false;
    $head = (string) fread($fh, 4);
    fclose($fh);
    if ($kind === 'zip') {
        return str_starts_with($head, "PK\x03\x04")
            || str_starts_with($head, "PK\x05\x06")
            || str_starts_with($head, "PK\x07\x08");
    }
    // gzip header (tar.gz / tgz)
    return strlen($head) >= 2 && $head[0] === "\x1f" && $head[1] === "\x8b";
}

function find_bin(array $cands): ?string {
    foreach ($cands as $p) {
        if (is_executable($p)) return $p;
    }
    return null;
}

/** Normalize archive member name; reject zip-slip / absolute / control chars. */
function safe_member_rel(string $name): string {
    $name = str_replace('\\', '/', $name);
    if ($name === '' || strpos($name, "\0") !== false) err('压缩包含有非法路径，已拒绝');
    if (preg_match('#^(?:[a-zA-Z]:)?/#', $name)) err('压缩包含有绝对路径，已拒绝');
    $parts = [];
    foreach (explode('/', $name) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') err('压缩包含有越界路径（zip-slip），已拒绝');
        if (preg_match('/[\x00-\x1F]/', $p)) err('压缩包含有非法文件名，已拒绝');
        $parts[] = $p;
    }
    if ($parts === []) err('压缩包含有非法路径，已拒绝');
    return implode('/', $parts);
}

function member_target(string $dest, string $rel, string $jailBase): string {
    $full = $dest . '/' . $rel;
    if (strpos($full, $dest . '/') !== 0) err('压缩包含有越界路径（zip-slip），已拒绝');
    if ($full !== $jailBase && strpos($full . '/', $jailBase . '/') !== 0) {
        err('压缩包含有越界路径（zip-slip），已拒绝');
    }
    return $full;
}

function path_prefixes(string $rel): array {
    $acc = [];
    $cur = '';
    foreach (explode('/', $rel) as $p) {
        $cur = $cur === '' ? $p : ($cur . '/' . $p);
        $acc[] = $cur;
    }
    return $acc;
}

function zip_list_members(string $archive): array {
    $unzip = find_bin(['/usr/bin/unzip', '/bin/unzip']);
    if ($unzip !== null) {
        $listed = cmd_capture([$unzip, '-Z1', $archive], 30);
        if (cmd_timed_out($listed['code'])) err('读取压缩包超时');
        if ($listed['code'] !== 0) {
            $hint = trim($listed['stderr'] ?: $listed['stdout']);
            err('无法读取 zip 压缩包' . ($hint !== '' ? '：' . $hint : '（文件已损坏或已加密）'));
        }
        $names = preg_split("/\r\n|\n|\r/", trim($listed['stdout'])) ?: [];
        $names = array_values(array_filter($names, fn($n) => $n !== ''));
        if (count($names) > EXTRACT_MAX_FILES) err('压缩包内文件过多（最多 ' . EXTRACT_MAX_FILES . ' 个）');

        $info = cmd_capture([$unzip, '-Z', $archive], 30);
        if ($info['code'] === 0) {
            foreach (explode("\n", $info['stdout']) as $line) {
                if (preg_match('/^([bcdlps-])[r-][w-][xsStT-]{7}\s/', $line, $m)) {
                    if ($m[1] !== '-' && $m[1] !== 'd') err('压缩包含有符号链接或特殊文件，已拒绝');
                }
            }
        }

        $lst = cmd_capture([$unzip, '-l', $archive], 30);
        if ($lst['code'] === 0) {
            $lines = preg_split("/\r\n|\n|\r/", trim($lst['stdout'])) ?: [];
            $last = (string) end($lines);
            if (preg_match('/^\s*(\d+)\s+\d+\s+files?\s*$/', $last, $m) && (int) $m[1] > EXTRACT_MAX_UNCOMP) {
                err('解压后体积超过 8GB，已拒绝');
            }
        }
        return $names;
    }
    if (!class_exists('ZipArchive')) err('系统未安装 unzip，无法解压 zip');
    return zip_list_via_php($archive);
}

function zip_list_via_php(string $archive): array {
    $z = new ZipArchive();
    $opened = @$z->open($archive);
    if ($opened !== true) err('无法打开 zip 压缩包（文件已损坏或已加密）');
    if ($z->numFiles > EXTRACT_MAX_FILES) {
        $z->close();
        err('压缩包内文件过多（最多 ' . EXTRACT_MAX_FILES . ' 个）');
    }
    $names = [];
    $uncomp = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $stat = $z->statIndex($i);
        if ($stat === false) continue;
        $uncomp += (int) ($stat['size'] ?? 0);
        if ($uncomp > EXTRACT_MAX_UNCOMP) {
            $z->close();
            err('解压后体积超过 8GB，已拒绝');
        }
        $opsys = 0;
        $attr = 0;
        if (method_exists($z, 'getExternalAttributesIndex') && $z->getExternalAttributesIndex($i, $opsys, $attr)) {
            if (defined('ZipArchive::OPSYS_UNIX') && $opsys === ZipArchive::OPSYS_UNIX) {
                $mode = ($attr >> 16) & 0170000;
                // 0120000 symlink, 0010000 fifo, 002/006 device, 0140000 socket
                if (in_array($mode, [0120000, 0010000, 0020000, 0060000, 0140000], true)) {
                    $z->close();
                    err('压缩包含有符号链接或特殊文件，已拒绝');
                }
            }
        }
        $names[] = (string) $stat['name'];
    }
    $z->close();
    return $names;
}

function tar_list_members(string $archive): array {
    $tar = find_bin(['/usr/bin/tar', '/bin/tar']);
    if ($tar === null) err('系统未安装 tar，无法解压 tar.gz');
    $gzip = find_bin(['/usr/bin/gzip', '/bin/gzip']);
    if ($gzip !== null) {
        $gl = cmd_capture([$gzip, '-l', $archive], 15);
        if ($gl['code'] === 0 && preg_match('/^\s*\d+\s+(\d+)\s+/m', $gl['stdout'], $m) && (int) $m[1] > EXTRACT_MAX_UNCOMP) {
            err('解压后体积超过 8GB，已拒绝');
        }
    }
    $listed = cmd_capture([$tar, '-tzf', $archive], 30);
    if (cmd_timed_out($listed['code'])) err('读取压缩包超时');
    if ($listed['code'] !== 0) {
        $hint = trim($listed['stderr'] ?: $listed['stdout']);
        err('无法读取 tar.gz 压缩包' . ($hint !== '' ? '：' . $hint : ''));
    }
    $names = preg_split("/\r\n|\n|\r/", trim($listed['stdout'])) ?: [];
    $names = array_values(array_filter($names, fn($n) => $n !== ''));
    if (count($names) > EXTRACT_MAX_FILES) err('压缩包内文件过多（最多 ' . EXTRACT_MAX_FILES . ' 个）');

    $verbose = cmd_capture([$tar, '-tzvf', $archive], 30);
    if ($verbose['code'] === 0) {
        foreach (explode("\n", $verbose['stdout']) as $line) {
            $c = $line[0] ?? '';
            if ($c !== '' && $c !== '-' && $c !== 'd' && preg_match('/^[bcdlps]/', $line)) {
                err('压缩包含有符号链接或特殊文件，已拒绝');
            }
        }
    }
    return $names;
}

function extract_zip_archive(string $archive, string $dest): void {
    $unzip = find_bin(['/usr/bin/unzip', '/bin/unzip']);
    if ($unzip !== null) {
        // never pass "-:" (that flag allows ".." in member names)
        $r = cmd_capture([$unzip, '-o', '-qq', '-d', $dest, $archive], EXTRACT_TIMEOUT);
        if (cmd_timed_out($r['code'])) err('解压超时（最多 ' . EXTRACT_TIMEOUT . ' 秒），请缩小压缩包后重试');
        // 0 = ok, 1 = warning (e.g. skipped) — still treat as success if files landed
        if ($r['code'] !== 0 && $r['code'] !== 1) {
            $hint = trim($r['stderr'] ?: $r['stdout']);
            err('解压失败' . ($hint !== '' ? '：' . $hint : ''));
        }
        return;
    }
    if (!class_exists('ZipArchive')) err('系统未安装 unzip，无法解压 zip');
    $z = new ZipArchive();
    if ($z->open($archive) !== true) err('无法打开 zip 压缩包');
    $ok = @$z->extractTo($dest);
    $z->close();
    if (!$ok) err('解压失败');
}

function extract_tar_archive(string $archive, string $dest): void {
    $tar = find_bin(['/usr/bin/tar', '/bin/tar']);
    if ($tar === null) err('系统未安装 tar，无法解压 tar.gz');
    $r = cmd_capture([
        $tar, '--overwrite', '--no-same-owner',
        '-xzf', $archive, '-C', $dest,
    ], EXTRACT_TIMEOUT);
    if (cmd_timed_out($r['code'])) err('解压超时（最多 ' . EXTRACT_TIMEOUT . ' 秒），请缩小压缩包后重试');
    if ($r['code'] !== 0) {
        $hint = trim($r['stderr'] ?: $r['stdout']);
        err('解压失败' . ($hint !== '' ? '：' . $hint : ''));
    }
}

function harden_extracted(string $dest, array $rels, string $user, string $jailBase): void {
    $seen = [];
    foreach ($rels as $rel) {
        foreach (path_prefixes($rel) as $pre) $seen[$pre] = true;
    }
    foreach (array_keys($seen) as $rel) {
        $p = $dest . '/' . $rel;
        if (is_link($p)) {
            @unlink($p);
            err('解压结果包含符号链接，已删除并拒绝');
        }
        if (!file_exists($p)) continue;
        $real = realpath($p);
        if ($real === false
            || ($real !== $jailBase && strpos($real . '/', $jailBase . '/') !== 0)
            || ($real !== $dest && strpos($real . '/', $dest . '/') !== 0)
        ) {
            if (is_dir($p)) @rmdir($p); else @unlink($p);
            err('解压结果超出站点目录，已拒绝');
        }
        $mode = fileperms($p) & 0777; // strip setuid/setgid/sticky
        if ($mode === 0) $mode = is_dir($p) ? 0755 : 0644;
        @chmod($p, $mode);
        chown_to($p, $user);
    }
}

/** Recursively measure a tree; reject symlinks and jail escapes. */
function tree_size_safe(string $p, string $jailBase, int &$files): int {
    if (is_link($p)) err('不允许压缩符号链接');
    $real = realpath($p);
    if ($real === false || ($real !== $jailBase && strpos($real . '/', $jailBase . '/') !== 0)) {
        err('路径超出站点目录');
    }
    if (is_file($p)) {
        $files++;
        if ($files > EXTRACT_MAX_FILES) err('选中的文件过多（最多 ' . EXTRACT_MAX_FILES . ' 个）');
        return (int) filesize($p);
    }
    if (!is_dir($p)) err('无法压缩该类型的文件');
    $sum = 0;
    foreach (scandir($p) as $n) {
        if ($n === '.' || $n === '..') continue;
        $sum += tree_size_safe($p . '/' . $n, $jailBase, $files);
        if ($sum > EXTRACT_MAX_UNCOMP) err('待压缩内容超过 8GB，已拒绝');
    }
    return $sum;
}

function zip_add_tree(ZipArchive $z, string $abs, string $local, string $jailBase): void {
    if (is_link($abs)) err('不允许压缩符号链接');
    $real = realpath($abs);
    if ($real === false || ($real !== $jailBase && strpos($real . '/', $jailBase . '/') !== 0)) {
        err('路径超出站点目录');
    }
    if (is_dir($abs)) {
        if ($local !== '') $z->addEmptyDir($local);
        foreach (scandir($abs) as $n) {
            if ($n === '.' || $n === '..') continue;
            $childLocal = $local === '' ? $n : ($local . '/' . $n);
            zip_add_tree($z, $abs . '/' . $n, $childLocal, $jailBase);
        }
        return;
    }
    if (!$z->addFile($abs, $local)) err('写入压缩包失败：' . $local);
}

function compress_zip_archive(string $dest, string $archiveName, array $names, string $jailBase): void {
    $zipBin = find_bin(['/usr/bin/zip', '/bin/zip']);
    $archive = $dest . '/' . $archiveName;
    if ($zipBin !== null) {
        $argv = array_merge([$zipBin, '-r', '-q', $archiveName, '--'], $names);
        $r = cmd_capture($argv, EXTRACT_TIMEOUT, $dest);
        if (cmd_timed_out($r['code'])) err('压缩超时（最多 ' . EXTRACT_TIMEOUT . ' 秒）');
        if ($r['code'] !== 0) {
            @unlink($archive);
            $hint = trim($r['stderr'] ?: $r['stdout']);
            err('压缩失败' . ($hint !== '' ? '：' . $hint : ''));
        }
        return;
    }
    if (!class_exists('ZipArchive')) err('系统未安装 zip，无法压缩');
    $z = new ZipArchive();
    $opened = $z->open($archive, ZipArchive::CREATE | ZipArchive::EXCL);
    if ($opened !== true) err('创建压缩包失败');
    foreach ($names as $n) {
        zip_add_tree($z, $dest . '/' . $n, $n, $jailBase);
    }
    if (!$z->close()) err('写入压缩包失败');
}

$action = $argv[1] ?? '';
$user   = $argv[2] ?? '';
if (!valid_user($user)) err('invalid site user');

/* ------------------------------ list ------------------------------------ */
if ($action === 'list') {
    $rel = $argv[3] ?? '/';
    if ($GLOBALS['DRY']) {
        $now = date('Y-m-d H:i:s');
        $norm = '/' . trim(str_replace('\\', '/', (string) $rel), '/');
        $entries = match ($norm) {
            '/', '' => [
                ['name' => 'public', 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => '0755'],
                ['name' => 'app', 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => '0755'],
                ['name' => 'logs', 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => '0755'],
            ],
            '/public' => [
                ['name' => 'wp-content', 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => '0755'],
                ['name' => 'index.php', 'type' => 'file', 'size' => 4521, 'mtime' => $now, 'perms' => '0644'],
                ['name' => 'wp-config.php', 'type' => 'file', 'size' => 3012, 'mtime' => $now, 'perms' => '0640'],
                ['name' => 'theme.zip', 'type' => 'file', 'size' => 204800, 'mtime' => $now, 'perms' => '0644'],
            ],
            '/app' => [
                ['name' => 'node_modules', 'type' => 'dir', 'size' => 0, 'mtime' => $now, 'perms' => '0755'],
                ['name' => 'index.js', 'type' => 'file', 'size' => 1204, 'mtime' => $now, 'perms' => '0644'],
                ['name' => 'package.json', 'type' => 'file', 'size' => 812, 'mtime' => $now, 'perms' => '0644'],
            ],
            default => [],
        };
        out(['ok' => true, 'path' => $norm === '' ? '/' : $norm, 'entries' => $entries]);
    }
    $dir = jail($user, $rel);
    if (!is_dir($dir)) err('not a directory');
    $entries = [];
    foreach (scandir($dir) as $n) {
        if ($n === '.' || $n === '..') continue;
        $p = $dir . '/' . $n;
        lstat($p);
        $entries[] = [
            'name'  => $n,
            'type'  => is_dir($p) ? 'dir' : (is_link($p) ? 'link' : 'file'),
            'size'  => is_dir($p) ? 0 : (int) filesize($p),
            'mtime' => date('Y-m-d H:i:s', filemtime($p)),
            'perms' => perm_octal($p),
        ];
    }
    usort($entries, fn($a, $b) => $a['type'] !== $b['type']
        ? ($a['type'] === 'dir' ? -1 : 1)
        : strcasecmp($a['name'], $b['name']));
    out(['ok' => true, 'path' => '/' . trim(str_replace($GLOBALS['WEB_ROOT'] . '/' . $user, '', $dir), '/'), 'entries' => $entries]);
}

/* --------------------------- read (edit) -------------------------------- */
$EDIT_EXT = ['php','txt','html','htm','css','js','json','xml','yml','yaml','ini','conf','log','md','htaccess','po','mo','sql','svg'];

if ($action === 'read' || $action === 'cat') {
    $rel = $argv[3] ?? '';
    if ($GLOBALS['DRY']) {
        if ($action === 'cat') { fwrite(STDOUT, "<?php // dry-run\n"); exit(0); }
        out(['ok' => true, 'path' => $rel, 'content' => "<?php\n// dry-run placeholder\n"]);
    }
    $p = jail($user, $rel);
    if (!is_file($p)) err('file not found');
    if (filesize($p) > 5 * 1024 * 1024) err('file too large for online editor (max 5MB)');
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if ($action === 'read' && !in_array($ext, $EDIT_EXT, true) && basename($p) !== '.htaccess') {
        err('this file type is not editable online');
    }
    $data = file_get_contents($p);
    if (strpos($data, "\0") !== false) err('binary file cannot be edited');
    if ($action === 'cat') { fwrite(STDOUT, $data); exit(0); }
    out(['ok' => true, 'path' => $rel, 'content' => $data]);
}

/* ------------------------------ write ----------------------------------- */
if ($action === 'write') {
    $rel = $argv[3] ?? '';
    if ($GLOBALS['DRY']) out(['ok' => true]);
    $p = jail($user, $rel);
    if (is_dir($p)) err('target is a directory');
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, $EDIT_EXT, true) && basename($p) !== '.htaccess') err('file type not writable online');
    $content = stream_get_contents(STDIN);
    if (strlen($content) > 5 * 1024 * 1024) err('content too large (max 5MB)');
    if (file_put_contents($p . '.tmp-webpanel', $content) === false) err('write failed');
    rename($p . '.tmp-webpanel', $p);
    chown_to($p, $user);
    chmod($p, file_exists($p) ? fileperms($p) : 0644);
    out(['ok' => true]);
}

/* ------------------------------ mkdir ----------------------------------- */
if ($action === 'mkdir') {
    $rel = $argv[3] ?? '';
    if ($GLOBALS['DRY']) out(['ok' => true]);
    $p = jail($user, $rel);
    if (file_exists($p)) err('already exists');
    mkdir($p, 0755, true) || err('mkdir failed');
    chown_to($p, $user);
    out(['ok' => true]);
}

/* ------------------------------ rename ---------------------------------- */
if ($action === 'rename') {
    $from = $argv[3] ?? '';
    $to   = $argv[4] ?? '';
    if (!preg_match('/^[A-Za-z0-9._\/ -]+$/u', $to)) err('invalid destination name');
    if ($GLOBALS['DRY']) out(['ok' => true]);
    $src = jail($user, $from);
    $dst = jail($user, $to);
    if (!file_exists($src)) err('source not found');
    if (file_exists($dst)) err('destination already exists');
    rename($src, $dst) || err('rename failed');
    chown_to($dst, $user);
    out(['ok' => true]);
}

/* ------------------------------ chmod ----------------------------------- */
if ($action === 'chmod') {
    $rel = $argv[3] ?? '';
    $mode = $argv[4] ?? '';
    if (!preg_match('/^[0-7]{3,4}$/', $mode)) err('invalid permission mode');
    $m = octdec($mode);
    if ($m & 06000) err('setuid/setgid bits are not allowed');
    if ($GLOBALS['DRY']) out(['ok' => true]);
    $p = jail($user, $rel);
    chmod($p, $m) || err('chmod failed');
    out(['ok' => true, 'perms' => perm_octal($p)]);
}

/* ------------------------------ delete ---------------------------------- */
if ($action === 'delete') {
    $rel = $argv[3] ?? '';
    if ($GLOBALS['DRY']) out(['ok' => true]);
    $p = jail($user, $rel);
    if (!file_exists($p)) err('path not found');
    if (is_dir($p)) {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($rii as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($p);
    } else {
        @unlink($p);
    }
    file_exists($p) && err('delete failed');
    out(['ok' => true]);
}

/* ------------------------------ upload ---------------------------------- */
if ($action === 'upload') {
    $dirRel  = $argv[3] ?? '/';
    $tmp     = $argv[4] ?? '';
    $name    = $argv[5] ?? '';
    if (!preg_match('/^[A-Za-z0-9._ -]+$/u', $name) || in_array($name, ['.', '..'], true)) err('invalid file name');
    if ($GLOBALS['DRY']) out(['ok' => true, 'name' => $name]);
    if (!is_file($tmp)) err('upload temp file missing');
    $dir = jail($user, $dirRel);
    if (!is_dir($dir)) err('target directory missing');
    $dst = $dir . '/' . $name;
    if (file_exists($dst)) err('file already exists');
    if (!@rename($tmp, $dst) && !@copy($tmp, $dst)) err('move uploaded file failed');
    chown_to($dst, $user);
    chmod($dst, 0644);
    @unlink($tmp);
    out(['ok' => true, 'name' => $name, 'size' => filesize($dst)]);
}

/* ------------------------------ extract --------------------------------- */
if ($action === 'extract' || $action === 'unzip') {
    $rel = $argv[3] ?? '';
    if ($GLOBALS['DRY']) {
        $destRel = dirname('/' . ltrim(str_replace('\\', '/', $rel), '/'));
        if ($destRel === '/' . '.' || $destRel === '.') $destRel = '/';
        out(['ok' => true, 'extracted' => 0, 'dest' => $destRel === '\\' ? '/' : $destRel]);
    }
    @set_time_limit(EXTRACT_TIMEOUT + 30);

    $archive = jail($user, $rel);
    if (!is_file($archive)) err('压缩包不存在');
    $kind = archive_kind($archive);
    if ($kind === '') err('仅支持 .zip / .tar.gz / .tgz 压缩包');
    if (!archive_magic_ok($archive, $kind)) err('文件内容与扩展名不符（不是有效的压缩包）');
    if (filesize($archive) > EXTRACT_MAX_ARCHIVE) err('压缩包不能超过 1GB');

    $jailBase = jail($user, '/');
    $dest = dirname($archive);
    if ($dest !== $jailBase && strpos($dest . '/', $jailBase . '/') !== 0) err('目标目录超出站点目录');

    $rawNames = $kind === 'zip' ? zip_list_members($archive) : tar_list_members($archive);
    $rels = [];
    foreach ($rawNames as $n) {
        $safe = safe_member_rel($n);
        member_target($dest, $safe, $jailBase);
        $rels[] = $safe;
    }
    $rels = array_values(array_unique($rels));
    if (count($rels) > EXTRACT_MAX_FILES) err('压缩包内文件过多（最多 ' . EXTRACT_MAX_FILES . ' 个）');

    if ($kind === 'zip') extract_zip_archive($archive, $dest);
    else extract_tar_archive($archive, $dest);

    harden_extracted($dest, $rels, $user, $jailBase);

    $destRel = ($dest === $jailBase) ? '/' : '/' . ltrim(substr($dest, strlen($jailBase)), '/');
    out(['ok' => true, 'extracted' => count($rels), 'dest' => $destRel]);
}

/* ------------------------------ compress -------------------------------- */
if ($action === 'compress' || $action === 'zip') {
    $dirRel = $argv[3] ?? '/';
    $name   = $argv[4] ?? '';
    if (!preg_match('/^[A-Za-z0-9._ -]+\.zip$/i', $name) || in_array($name, ['.', '..'], true)) {
        err('压缩包名须为 .zip，且只含字母、数字、点、下划线、空格和连字符');
    }
    $raw = (string) stream_get_contents(STDIN);
    $names = json_decode($raw, true);
    if (!is_array($names) || $names === []) err('请选择要压缩的文件或文件夹');
    if (count($names) > EXTRACT_MAX_FILES) err('选中的文件过多（最多 ' . EXTRACT_MAX_FILES . ' 个）');
    foreach ($names as $n) {
        if (!is_string($n) || !preg_match('/^[A-Za-z0-9._ -]+$/u', $n) || in_array($n, ['.', '..', $name], true)) {
            err('选中的名称不合法');
        }
    }
    $names = array_values(array_unique($names));
    if ($GLOBALS['DRY']) out(['ok' => true, 'name' => $name, 'size' => 0]);
    @set_time_limit(EXTRACT_TIMEOUT + 30);

    $jailBase = jail($user, '/');
    $dest = jail($user, $dirRel);
    if (!is_dir($dest)) err('目标目录不存在');
    $archive = $dest . '/' . $name;
    if (file_exists($archive)) err('压缩包已存在：' . $name);

    $files = 0;
    $total = 0;
    foreach ($names as $n) {
        $p = $dest . '/' . $n;
        if (is_link($p)) err('不允许压缩符号链接');
        if (!file_exists($p)) err('文件不存在：' . $n);
        $real = realpath($p);
        if ($real === false || ($real !== $jailBase && strpos($real . '/', $jailBase . '/') !== 0)) {
            err('路径超出站点目录');
        }
        // selected entries must live directly in the current directory
        if (dirname($real) !== $dest) err('只能压缩当前目录中的项目');
        $total += tree_size_safe($p, $jailBase, $files);
        if ($total > EXTRACT_MAX_UNCOMP) err('待压缩内容超过 8GB，已拒绝');
    }

    compress_zip_archive($dest, $name, $names, $jailBase);
    if (!is_file($archive)) err('压缩包未生成');
    if (filesize($archive) > EXTRACT_MAX_ARCHIVE) {
        @unlink($archive);
        err('生成的压缩包超过 1GB，已删除');
    }
    chown_to($archive, $user);
    chmod($archive, 0644);
    out(['ok' => true, 'name' => $name, 'size' => filesize($archive)]);
}

err('unknown action: ' . $action, 64);
