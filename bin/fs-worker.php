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

$action = $argv[1] ?? '';
$user   = $argv[2] ?? '';
if (!valid_user($user)) err('invalid site user');

/* ------------------------------ list ------------------------------------ */
if ($action === 'list') {
    $rel = $argv[3] ?? '/';
    if ($GLOBALS['DRY']) {
        out(['ok' => true, 'path' => '/', 'entries' => [
            ['name' => 'index.php', 'type' => 'file', 'size' => 4096, 'mtime' => date('Y-m-d H:i:s'), 'perms' => '0644'],
            ['name' => 'wp-content', 'type' => 'dir', 'size' => 0, 'mtime' => date('Y-m-d H:i:s'), 'perms' => '0755'],
        ]]);
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

err('unknown action: ' . $action, 64);
