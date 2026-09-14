<?php
declare(strict_types=1);

namespace WebPanel;

final class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['uid']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return Db::one('SELECT id, username FROM users WHERE id = ?', [$_SESSION['uid']]);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function login(int $uid): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function recentFailures(string $ip): int
    {
        $row = Db::one(
            "SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND ts >= datetime('now','localtime','-10 minutes')",
            [$ip]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function recordFailure(string $ip): void
    {
        Db::run('INSERT INTO login_attempts (ip) VALUES (?)', [$ip]);
    }

    public static function clearFailures(string $ip): void
    {
        Db::run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    }

    public static function log(string $action, string $detail = ''): void
    {
        Db::run(
            'INSERT INTO action_log (actor, action, detail, ip) VALUES (?,?,?,?)',
            [self::user()['username'] ?? 'system', $action, $detail, self::ip()]
        );
    }

    public static function ip(): string
    {
        return substr($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 64);
    }
}
