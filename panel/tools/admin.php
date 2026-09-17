<?php
/**
 * admin.php - create / reset a panel administrator from the server shell
 *   php tools/admin.php password [username]
 * Run via: /usr/local/webpanel/bin/wp-panel.sh password admin
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

use WebPanel\Db;

$cmd = $argv[1] ?? '';
$username = $argv[2] ?? 'admin';

if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
    fwrite(STDERR, "invalid username (3-32 chars, letters/digits/_.-)\n");
    exit(1);
}

if ($cmd !== 'password') {
    fwrite(STDERR, "usage: admin.php password [username]\n");
    exit(64);
}

// generate password, allow override from stdin (non-empty first line)
$password = '';
$stream = STDIN;
$stat = fstat(STDIN);
if (($stat['mode'] & 0170000) === 0100000) { // regular file redirected
    $password = trim((string) fgets(STDIN));
}
if ($password === '') {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    for ($i = 0; $i < 18; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
if (strlen($password) < 10) {
    fwrite(STDERR, "password must be at least 10 chars\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = Db::one('SELECT id FROM users WHERE username = ?', [$username]);
if ($existing) {
    Db::run('UPDATE users SET password_hash = ? WHERE username = ?', [$hash, $username]);
} else {
    Db::insert('INSERT INTO users (username, password_hash) VALUES (?, ?)', [$username, $hash]);
}

echo "Panel URL : https://<server-ip>:8888/\n";
echo "Username  : {$username}\n";
echo "Password  : {$password}\n";
echo "\nChange it immediately after first login.\n";
