<?php
declare(strict_types=1);

namespace WebPanel;

/**
 * Privileged file downloads (file manager + backups).
 *
 * Production 2026-09-25: /files/download blocked on stderr of
 * `sudo wp-fs.sh cat` while stdout filled the pipe. Nginx never saw
 * response headers (504), the PHP session lock stayed exclusive, and
 * leftover cat/fs-worker processes sat until they were killed.
 *
 * Rules:
 *  - close the session immediately after auth/CSRF
 *  - never drain stderr before reading stdout
 *  - flush headers before the body so nginx is not waiting
 *  - kill the sudo/fs-worker tree if the client goes away
 */
final class Download
{
    private const PEEK_SECONDS = 8.0;
    private const CHUNK = 262144; // 256 KiB
    private const SIG_TERM = 15;
    private const SIG_KILL = 9;

    public static function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function prepareOutput(): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('output_buffering', '0');
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ignore_user_abort(true);
    }

    public static function sendHeaders(string $filename, ?int $size = null): void
    {
        $safe = self::safeFilename($filename);
        header('Content-Type: application/octet-stream');
        header(
            'Content-Disposition: attachment; filename="' . rawurlencode($safe)
            . '"; filename*=UTF-8\'\'' . rawurlencode($safe)
        );
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
        if ($size !== null && $size >= 0) {
            header('Content-Length: ' . $size);
        }
    }

    /**
     * Stream a privileged helper (wp-fs.sh cat / wp-backup.sh download).
     *
     * @param list<string> $args
     */
    public static function streamPrivileged(string $script, array $args, string $filename, ?int $size = null): void
    {
        self::prepareOutput();
        [$proc, $pipes] = Shell::sudoStream($script, $args);
        if (!is_resource($proc)) {
            http_response_code(500);
            echo '下载失败：无法启动传输';
            return;
        }
        self::streamPipes($proc, $pipes, $filename, $size);
    }

    /**
     * Multiplex stdout/stderr from an already-opened helper and copy the body.
     *
     * @param resource $proc
     * @param array<int, resource|null> $pipes
     */
    public static function streamPipes($proc, array $pipes, string $filename, ?int $size = null): void
    {
        $stdout = $pipes[1] ?? null;
        $stderr = $pipes[2] ?? null;
        if (!is_resource($stdout)) {
            self::reap($proc, $pipes);
            http_response_code(500);
            echo '下载失败：无法读取传输流';
            return;
        }

        if (is_resource($stdout)) {
            stream_set_blocking($stdout, false);
        }
        if (is_resource($stderr)) {
            stream_set_blocking($stderr, false);
        }

        $head = '';
        $err = '';
        $deadline = microtime(true) + self::PEEK_SECONDS;

        try {
            while ($head === '' && microtime(true) < $deadline) {
                $st = @proc_get_status($proc);
                $running = is_array($st) && !empty($st['running']);
                self::drainReady($stdout, $stderr, $head, $err);
                if ($head !== '') {
                    break;
                }
                if (!$running) {
                    self::drainRest($stdout, $stderr, $head, $err);
                    break;
                }
            }

            $st = @proc_get_status($proc);
            $running = is_array($st) && !empty($st['running']);
            if ($head === '' && !$running) {
                $msg = self::workerError($err);
                if ($msg !== '') {
                    http_response_code(500);
                    echo '下载失败：' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                    return;
                }
            }

            if (!headers_sent()) {
                self::sendHeaders($filename, $size);
            }
            // Push the status line + headers to nginx before any large body.
            if (function_exists('flush')) {
                flush();
            }

            if ($head !== '') {
                echo $head;
                flush();
            }

            if (is_resource($stdout)) {
                stream_set_blocking($stdout, true);
            }

            while (is_resource($stdout) && !feof($stdout)) {
                if (connection_aborted()) {
                    break;
                }
                $chunk = fread($stdout, self::CHUNK);
                if ($chunk === false) {
                    break;
                }
                if ($chunk === '') {
                    $st = @proc_get_status($proc);
                    if (!is_array($st) || empty($st['running'])) {
                        break;
                    }
                    usleep(10000);
                    continue;
                }
                echo $chunk;
                flush();
            }
        } finally {
            self::reap($proc, $pipes);
        }
    }

    /**
     * Close pipes and terminate sudo + descendants (wp-fs.sh / fs-worker.php).
     *
     * @param resource|false $proc
     * @param array<int, resource|null> $pipes
     */
    public static function reap($proc, array $pipes): void
    {
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                @fclose($p);
            }
        }
        if (!is_resource($proc)) {
            return;
        }
        $st = @proc_get_status($proc);
        $pid = (int) ($st['pid'] ?? 0);
        $running = is_array($st) && !empty($st['running']);
        if ($running && $pid > 1) {
            foreach (array_merge(self::childPids($pid), [$pid]) as $p) {
                self::signalPid($p, self::SIG_TERM);
            }
            @proc_terminate($proc, self::SIG_TERM);
            for ($i = 0; $i < 20; $i++) {
                $st = @proc_get_status($proc);
                if (!is_array($st) || empty($st['running'])) {
                    break;
                }
                usleep(100000);
            }
            $st = @proc_get_status($proc);
            if (is_array($st) && !empty($st['running'])) {
                foreach (array_merge(self::childPids($pid), [$pid]) as $p) {
                    self::signalPid($p, self::SIG_KILL);
                }
                @proc_terminate($proc, self::SIG_KILL);
            }
        }
        @proc_close($proc);
    }

    private static function drainReady($stdout, $stderr, string &$head, string &$err): void
    {
        $read = [];
        if (is_resource($stdout) && !feof($stdout)) {
            $read[] = $stdout;
        }
        if (is_resource($stderr) && !feof($stderr)) {
            $read[] = $stderr;
        }
        if ($read === []) {
            return;
        }
        $write = null;
        $except = null;
        $n = @stream_select($read, $write, $except, 0, 200000);
        if ($n === false || $n < 1) {
            return;
        }
        foreach ($read as $s) {
            $buf = fread($s, 65536);
            if ($buf === false || $buf === '') {
                continue;
            }
            if ($s === $stdout) {
                $head .= $buf;
            } else {
                $err .= $buf;
            }
        }
    }

    private static function drainRest($stdout, $stderr, string &$head, string &$err): void
    {
        if (is_resource($stdout)) {
            $rest = @stream_get_contents($stdout);
            if (is_string($rest) && $rest !== '') {
                $head .= $rest;
            }
        }
        if (is_resource($stderr)) {
            $rest = @stream_get_contents($stderr);
            if (is_string($rest) && $rest !== '') {
                $err .= $rest;
            }
        }
    }

    private static function workerError(string $err): string
    {
        $err = trim($err);
        if ($err === '') {
            return '';
        }
        foreach (preg_split("/\r\n|\n|\r/", $err) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $j = json_decode($line, true);
            if (is_array($j) && isset($j['error']) && is_string($j['error']) && $j['error'] !== '') {
                return $j['error'];
            }
            return $line;
        }
        return $err;
    }

    /** @return list<int> */
    private static function childPids(int $pid): array
    {
        if ($pid <= 1) {
            return [];
        }
        $out = [];
        @exec('pgrep -P ' . $pid . ' 2>/dev/null', $out);
        $kids = [];
        foreach ($out as $line) {
            $c = (int) trim((string) $line);
            if ($c > 1) {
                $kids[] = $c;
                foreach (self::childPids($c) as $g) {
                    $kids[] = $g;
                }
            }
        }
        return $kids;
    }

    private static function signalPid(int $pid, int $sig): void
    {
        if ($pid <= 1) {
            return;
        }
        if (function_exists('posix_kill')) {
            @posix_kill($pid, $sig);
            return;
        }
        $name = $sig === self::SIG_KILL ? 'KILL' : 'TERM';
        @exec('kill -' . $name . ' ' . $pid . ' 2>/dev/null');
    }

    private static function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            return 'download';
        }
        return $name;
    }
}
