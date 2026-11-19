<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    end_session();
}
redirect('/app/login.php');
