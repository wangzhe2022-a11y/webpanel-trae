<?php
declare(strict_types=1);

namespace WebPanel;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $h): void { $this->routes['GET' . $path] = $h; }
    public function post(string $path, array $h): void { $this->routes['POST' . $path] = $h; }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($path === '') {
            $path = '/';
        }

        // public assets are served directly (php built-in server safety net)
        if ($method === 'GET' && $path !== '/') {
            $candidate = PANEL_BASE . '/public' . $path;
            if (is_file($candidate)) {
                readfile($candidate);
                return;
            }
        }

        $handler = $this->routes[$method . $path] ?? null;
        if (!$handler) {
            http_response_code(404);
            if (self::wantsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Not Found']);
            } else {
                echo '<!doctype html><meta charset="utf-8"><title>404</title>'
                   . '<div style="font-family:sans-serif;padding:40px">404 - 页面不存在，<a href="/">返回首页</a></div>';
            }
            return;
        }

        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action();
    }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
