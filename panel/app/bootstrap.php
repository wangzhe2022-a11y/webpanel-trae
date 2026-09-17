<?php
/**
 * Bootstrap: constants, sessions, sqlite, routing, auth plumbing.
 */
declare(strict_types=1);

error_reporting(E_ALL);

define('PANEL_BASE', dirname(__DIR__));
define('PANEL_APP', PANEL_BASE . '/app');
define('PANEL_DATA', getenv('PANEL_DATA_DIR') ?: (PANEL_BASE . '/data'));
define('PANEL_BIN', getenv('PANEL_BIN_DIR') ?: '/usr/local/webpanel/bin');
define('PANEL_DRY', getenv('PANEL_DRYRUN') === '1'
    || @file_exists('/usr/local/webpanel/.dryrun')
    || @file_exists(PANEL_BIN . '/../.dryrun'));
define('PANEL_DEBUG', getenv('PANEL_DEBUG') === '1');

ini_set('display_errors', PANEL_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

require PANEL_APP . '/functions.php';

spl_autoload_register(function (string $class): void {
    // controllers live in the global namespace; everything else under WebPanel\
    if (str_starts_with($class, 'WebPanel\\')) {
        $file = PANEL_APP . '/' . str_replace('\\', '/', substr($class, strlen('WebPanel\\'))) . '.php';
    } else {
        // page controllers live in controllers/, shared base classes in app/
        $file = is_file(PANEL_APP . '/controllers/' . $class . '.php')
            ? PANEL_APP . '/controllers/' . $class . '.php'
            : PANEL_APP . '/' . $class . '.php';
    }
    if (is_file($file)) {
        require $file;
    }
});

foreach ([PANEL_DATA, PANEL_DATA . '/sessions', PANEL_DATA . '/tmp', PANEL_DATA . '/cache'] as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
    }
}

set_exception_handler(function (Throwable $e): void {
    error_log('[webpanel] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/sys/')
        || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    if ($isJson || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => PANEL_DEBUG ? (string) $e : '服务器内部错误，请查看日志']);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>错误</title>'
           . '<div style="font-family:sans-serif;padding:40px;color:#c00">服务器内部错误 (500)</div>';
    }
});

if (PHP_SAPI !== 'cli') {
    $secure = (getenv('PANEL_COOKIE_SECURE') !== '0')
        && (($_SERVER['HTTPS'] ?? '') === 'on' || (($_SERVER['SERVER_PORT'] ?? '') === '443'));
    session_name('WEBPANELSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    if (!is_dir(PANEL_DATA . '/sessions')) {
        @mkdir(PANEL_DATA . '/sessions', 0700, true);
    }
    session_save_path(PANEL_DATA . '/sessions');
    session_start();
}

\WebPanel\Db::boot();
