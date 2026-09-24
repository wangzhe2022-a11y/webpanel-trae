<?php
/**
 * Drive WebPanel\Download::streamPipes against fs-worker.php cat (no sudo).
 */
declare(strict_types=1);

$worker = $argv[1] ?? '';
$rel = $argv[2] ?? '/big.bin';
if ($worker === '' || !is_file($worker)) {
    fwrite(STDERR, "usage: new-stream.php <fs-worker.php> [rel]\n");
    exit(2);
}

require dirname(__DIR__, 2) . '/panel/app/Download.php';

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker)
    . ' cat __vdb ' . escapeshellarg($rel);
$desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($cmd, $desc, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "proc_open failed\n");
    exit(2);
}
fclose($pipes[0]);
$pipes[0] = null;

WebPanel\Download::prepareOutput();
$vdb = (string) getenv('VDB_ROOT');
$abs = $vdb !== '' ? rtrim($vdb, '/') . '/' . ltrim($rel, '/') : '';
$size = ($abs !== '' && is_file($abs)) ? (int) filesize($abs) : null;
WebPanel\Download::streamPipes($proc, $pipes, basename($rel), $size);

$code = http_response_code();
if (is_int($code) && $code >= 400) {
    exit(1);
}
