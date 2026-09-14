<?php
declare(strict_types=1);

namespace WebPanel;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function boot(): void
    {
        if (self::$pdo instanceof PDO) {
            return;
        }
        $dbFile = PANEL_DATA . '/panel.db';
        $fresh = !file_exists($dbFile);
        self::$pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        if ($fresh) {
            chmod($dbFile, 0600);
        }
        self::migrate();
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::$pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::$pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::$pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::$pdo->lastInsertId();
    }

    private static function migrate(): void
    {
        self::$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY,
    username      TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS sites (
    id          INTEGER PRIMARY KEY,
    sysuser     TEXT UNIQUE NOT NULL,
    domain      TEXT NOT NULL,
    aliases     TEXT NOT NULL DEFAULT '',
    php_version TEXT NOT NULL DEFAULT '82',
    ssl         INTEGER NOT NULL DEFAULT 0,
    hsts        INTEGER NOT NULL DEFAULT 0,
    status      TEXT NOT NULL DEFAULT 'active',
    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS databases (
    id         INTEGER PRIMARY KEY,
    site_id    INTEGER REFERENCES sites(id) ON DELETE SET NULL,
    name       TEXT NOT NULL,
    username   TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY,
    ip TEXT NOT NULL,
    ts TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS action_log (
    id     INTEGER PRIMARY KEY,
    actor  TEXT,
    action TEXT,
    detail TEXT,
    ip     TEXT,
    ts     TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);
    }
}
