<?php
/**
 * Front controller for WebPanel.
 * All non-file requests are routed here (nginx try_files).
 */
declare(strict_types=1);

// PHP built-in dev server: serve real static files directly with correct MIME
// types instead of letting the front controller wrap them as text/html.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/') {
        $file = __DIR__ . $path;
        if (is_file($file)) {
            return false;
        }
    }
}

require __DIR__ . '/../app/bootstrap.php';

use WebPanel\{Auth, Csrf, Router};

$router = new Router();

/* ---- auth ---------------------------------------------------------------- */
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

/* ---- dashboard ----------------------------------------------------------- */
$router->get('/', [DashboardController::class, 'index']);

/* ---- websites ------------------------------------------------------------ */
$router->get('/sites', [SiteController::class, 'index']);
$router->post('/sites/create', [SiteController::class, 'create']);
$router->post('/sites/delete', [SiteController::class, 'delete']);
$router->post('/sites/php', [SiteController::class, 'setPhp']);
$router->post('/sites/node-svc', [SiteController::class, 'nodeSvc']);
$router->post('/sites/node-npmi', [SiteController::class, 'nodeNpmInstall']);
$router->post('/sites/wp', [SiteController::class, 'installWordPress']);

/* ---- SSL ----------------------------------------------------------------- */
$router->get('/ssl', [SslController::class, 'index']);
$router->post('/ssl/issue', [SslController::class, 'issue']);
$router->post('/ssl/upload', [SslController::class, 'upload']);
$router->post('/ssl/remove', [SslController::class, 'remove']);
$router->post('/ssl/hsts', [SslController::class, 'toggleHsts']);

/* ---- databases ----------------------------------------------------------- */
$router->get('/databases', [DatabaseController::class, 'index']);
$router->post('/databases/create', [DatabaseController::class, 'create']);
$router->post('/databases/passwd', [DatabaseController::class, 'resetPassword']);
$router->post('/databases/delete', [DatabaseController::class, 'delete']);

/* ---- file manager -------------------------------------------------------- */
$router->get('/files', [FileController::class, 'index']);
$router->post('/files/list', [FileController::class, 'ls']);
$router->post('/files/read', [FileController::class, 'read']);
$router->post('/files/write', [FileController::class, 'write']);
$router->post('/files/mkdir', [FileController::class, 'mkdir']);
$router->post('/files/rename', [FileController::class, 'rename']);
$router->post('/files/chmod', [FileController::class, 'chmod']);
$router->post('/files/delete', [FileController::class, 'delete']);
$router->post('/files/upload', [FileController::class, 'upload']);
$router->get('/files/download', [FileController::class, 'download']);

/* ---- host / services ----------------------------------------------------- */
$router->get('/sys/info', [ServiceController::class, 'info']);
$router->post('/sys/svc', [ServiceController::class, 'svc']);

/* ---- backup / restore ------------------------------------------------------ */
$router->get('/backup', [BackupController::class, 'index']);
$router->post('/backup/create', [BackupController::class, 'create']);
$router->get('/backup/status', [BackupController::class, 'status']);
$router->post('/backup/delete', [BackupController::class, 'delete']);
$router->post('/backup/restore', [BackupController::class, 'restore']);
$router->get('/backup/download', [BackupController::class, 'download']);

/* ---- installatron (one-click web app installer) ---------------------------- */
$router->get('/installatron', [InstallatronController::class, 'index']);
$router->get('/installatron/status', [InstallatronController::class, 'status']);
$router->get('/installatron/demo', [InstallatronController::class, 'demo']);
$router->post('/installatron/install', [InstallatronController::class, 'install']);
$router->post('/installatron/login', [InstallatronController::class, 'login']);
$router->post('/installatron/upgrade', [InstallatronController::class, 'upgrade']);
$router->post('/installatron/uninstall', [InstallatronController::class, 'uninstall']);

$router->dispatch();
