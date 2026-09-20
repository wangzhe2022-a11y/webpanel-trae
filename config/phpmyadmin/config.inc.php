<?php
/**
 * WebPanel phpMyAdmin configuration.
 *
 * Secrets (blowfish_secret + dedicated MySQL user) are generated at install
 * time into config.secret.php next to this file and are not in the repo.
 */
declare(strict_types=0);

$i = 1;

$cfg['blowfish_secret'] = '';

$cfg['Servers'][$i]['auth_type'] = 'config';
$cfg['Servers'][$i]['host'] = '127.0.0.1';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = false;
$cfg['Servers'][$i]['user'] = 'webpanel_pma';
$cfg['Servers'][$i]['password'] = '';
$cfg['Servers'][$i]['hide_db'] = '^(information_schema|performance_schema)$';
$cfg['Servers'][$i]['DisableIS'] = false;

$secret = __DIR__ . '/config.secret.php';
if (is_readable($secret)) {
    require $secret;
}

$cfg['DefaultLang'] = 'zh_CN';
$cfg['Lang'] = 'zh_CN';
$cfg['PmaAbsoluteUri'] = '/phpmyadmin/';
$cfg['ForceSSL'] = true;
$cfg['AllowThirdPartyFraming'] = 'sameorigin';
$cfg['TempDir'] = __DIR__ . '/tmp';
$cfg['CheckConfigurationPermissions'] = false;
$cfg['VersionCheck'] = false;
$cfg['ShowPhpInfo'] = false;
$cfg['AllowArbitraryServer'] = false;
$cfg['SendErrorReports'] = 'never';
$cfg['DefaultCharset'] = 'utf8mb4';
$cfg['Servers'][$i]['extension'] = 'mysqli';
