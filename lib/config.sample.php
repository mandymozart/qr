<?php
// Copy to lib/config.php for local dev. On deploy, the GitHub Action writes it from the
// "production" environment secrets/variables.
return [
    'base_url'       => 'http://localhost:8088',   // public origin, no trailing slash
    'db_host'        => '127.0.0.1',
    'db_port'        => 3306,
    'db_name'        => 'qr',
    'db_user'        => 'qr',
    'db_pass'        => 'qr',
    'admin_user'     => 'you@example.com',          // master admin email
    'admin_password' => 'change-me',                // master admin password
    'mail_from'      => '',                         // default noreply@<host>
    'smtp_host'      => '',                         // empty = PHP mail()
    'smtp_port'      => 587,
    'smtp_user'      => '',
    'smtp_pass'      => '',
];
