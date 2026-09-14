<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Csrf;
use WebPanel\Db;

abstract class Controller
{
    protected function requireLogin(): void
    {
        if (!Auth::check()) {
            if (WebPanel\Router::wantsJson()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => '未登录或会话已过期']);
                exit;
            }
            header('Location: /login');
            exit;
        }
    }

    protected function verifyCsrf(): void
    {
        if (!Csrf::verify()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => '会话令牌无效，请刷新页面后重试'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function ok(array $extra = []): never
    {
        $this->json(['ok' => true] + $extra);
        exit;
    }

    protected function fail(string $msg, int $code = 400): never
    {
        $this->json(['ok' => false, 'error' => $msg], $code);
        exit;
    }

    protected function render(string $view, array $data = []): void
    {
        $csrf = Csrf::token();
        $currentUser = Auth::user();
        extract($data, EXTR_SKIP);
        $viewFile = PANEL_APP . '/views/' . $view . '.php';
        require PANEL_APP . '/views/header.php';
        require $viewFile;
        require PANEL_APP . '/views/footer.php';
    }

    protected function renderLogin(string $view, array $data = []): void
    {
        $csrf = Csrf::token();
        extract($data, EXTR_SKIP);
        require PANEL_APP . '/views/' . $view . '.php';
    }

    protected function sites(): array
    {
        return Db::all('SELECT * FROM sites ORDER BY id DESC');
    }

    protected function findSite(int $id): ?array
    {
        return Db::one('SELECT * FROM sites WHERE id = ?', [$id]);
    }

    protected function domainsCsv(array $site): string
    {
        $all = [$site['domain']];
        foreach (preg_split('/[\s,]+/', (string) $site['aliases'], -1, PREG_SPLIT_NO_EMPTY) as $a) {
            $all[] = $a;
        }
        return implode(',', $all);
    }
}
