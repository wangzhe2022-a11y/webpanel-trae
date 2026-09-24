<?php
/**
 * Reproduces the pre-fix FileController::download() pipe deadlock:
 * drain stderr to EOF while the helper is still writing a large stdout.
 */
declare(strict_types=1);

$worker = $argv[1] ?? '';
$file = $argv[2] ?? '';
if ($worker === '' || $file === '' || !is_file($worker) || !is_file($file)) {
    fwrite(STDERR, "usage: old-deadlock.php <fs-worker.php> <file>\n");
    exit(2);
}

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker)
    . ' cat __vdb ' . escapeshellarg('/' . basename($file));
$desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($cmd, $desc, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "proc_open failed\n");
    exit(2);
}
fclose($pipes[0]);
// This is the production bug: blocks until the worker closes stderr (= exits),
// while stdout fills the OS pipe and the worker blocks on write.
$err = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$head = fread($pipes[1], 1);
echo $head;
fpassthru($pipes[1]);
fclose($pipes[1]);
proc_close($proc);
if ($err !== '') {
    fwrite(STDERR, $err);
}
