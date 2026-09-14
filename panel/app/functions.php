<?php
declare(strict_types=1);

/* Global helpers ---------------------------------------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function valid_domain_list(string $csv): array
{
    $out = [];
    foreach (preg_split('/[\s,]+/', mb_strtolower($csv), -1, PREG_SPLIT_NO_EMPTY) as $d) {
        if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $d) || strlen($d) > 253) {
            return [];
        }
        $out[] = $d;
    }
    return $out;
}

function valid_mysql_name(string $n, int $max = 32): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]{2,' . $max . '}$/', $n);
}

function random_password(int $len = 20): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789._-';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * Build a unique Linux system username from the primary domain,
 * e.g. "shop.example.com" -> "shopexa1f3c9b"
 */
function make_sysuser(string $primaryDomain): string
{
    $base = preg_replace('/[^a-z0-9]/', '', explode('.', $primaryDomain)[0]);
    $base = substr((string) $base, 0, 10) ?: 'site';
    do {
        $user = $base . bin2hex(random_bytes(3));
        $exists = WebPanel\Db::one('SELECT 1 FROM sites WHERE sysuser = ?', [$user]);
    } while ($exists);
    return $user;
}

function panel_php_versions(): array
{
    return [
        '74' => 'PHP 7.4（旧版 WP/插件兼容）',
        '80' => 'PHP 8.0',
        '81' => 'PHP 8.1（WooCommerce 推荐）',
        '82' => 'PHP 8.2（默认推荐）',
        '83' => 'PHP 8.3（最新）',
    ];
}

function format_bytes(int|float $kb): string
{
    $kb = (float) $kb;
    if ($kb >= 1048576) {
        return round($kb / 1048576, 1) . ' GB';
    }
    if ($kb >= 1024) {
        return round($kb / 1024, 1) . ' MB';
    }
    return $kb . ' KB';
}

function human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $v = (float) $bytes;
    while ($v >= 1024 && $i < 3) {
        $v /= 1024;
        $i++;
    }
    return round($v, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}
