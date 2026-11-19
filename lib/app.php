<?php
declare(strict_types=1);

// Everything an /app/ page needs.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/qr.php';

header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
