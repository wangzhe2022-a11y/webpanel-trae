<?php
declare(strict_types=1);

use WebPanel\Auth;
use WebPanel\Csrf;
use WebPanel\Db;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            header('Location: /');
            return;
        }
        $this->renderLogin('login', ['error' => '']);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $ip = Auth::ip();

        if (Auth::recentFailures($ip) >= 8) {
            $this->fail('Too many attempts. Try again in 10 minutes.', 429);
        }

        $username = (string) $this->input('username', '');
        $password = (string) ($_POST['password'] ?? '');

        $user = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Auth::recordFailure($ip);
            sleep(1);
            $this->fail('Invalid account or password', 401);
        }

        Auth::clearFailures($ip);
        Auth::login((int) $user['id']);
        Auth::log('login', '用户登录');
        $this->ok(['redirect' => '/']);
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::log('logout', '用户退出');
        Auth::logout();
        header('Location: /login');
    }
}
