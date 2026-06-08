<?php
declare(strict_types=1);

$defaults = [
    'app_url' => 'http://localhost/portal',
    'db_driver' => 'sqlite',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => __DIR__ . '/storage/saeta.sqlite',
    'db_user' => '',
    'db_password' => '',
    'mail_from' => 'halamadrid@saetavinotinto.com',
    'mail_from_name' => 'Saeta Vinotinto',
    'admin_notification_email' => 'urrutiajm@gmail.com',
    // Allowlist of emails that may reach superadmin features when paired with
    // is_superadmin=1 on the user row. Hardcoded fuse — even a flipped
    // is_superadmin flag in the DB cannot elevate someone outside this list.
    // Override (or extend) via config.local.php for local/test environments.
    'superadmin_emails' => ['urrutiajm@gmail.com'],
];

$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];

return array_merge($defaults, is_array($local) ? $local : []);
