<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/panel/app/Download.php';

$desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open('exec sleep 60', $desc, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "proc_open failed\n");
    exit(2);
}
$st = proc_get_status($proc);
$pid = (int) ($st['pid'] ?? 0);
if ($pid <= 1) {
    fwrite(STDERR, "no child pid\n");
    exit(2);
}

WebPanel\Download::reap($proc, $pipes);

$still = [];
@exec('kill -0 ' . $pid . ' 2>/dev/null', $still, $code);
if ($code === 0) {
    fwrite(STDERR, "child $pid still alive after reap\n");
    @exec('kill -9 ' . $pid . ' 2>/dev/null');
    exit(1);
}
