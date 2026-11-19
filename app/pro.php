<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/* "Request Pro": mails the master admin, then goes back to where the button was. */
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/app/');
check_csrf();
[$type, $msg] = request_pro($user);
flash($msg, $type);
$back = (string) ($_POST['back'] ?? '/app/');
redirect(str_starts_with($back, '/app/') ? $back : '/app/');
