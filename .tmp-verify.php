<?php
$db = new PDO('sqlite:/workspace/.tmp-panel-data/panel.db');
$h = $db->query("SELECT password_hash FROM users WHERE username='admin'")->fetchColumn();
var_dump(password_verify('DemoPass12345', $h));
var_dump(strlen($h));
