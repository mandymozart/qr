<?php
declare(strict_types=1);

// Everything an /app/ page needs.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/qr.php';

header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/track.php';
require_once __DIR__ . '/guest.php';
require_once __DIR__ . '/style.php';
require_once __DIR__ . '/steps.php';
require_once __DIR__ . '/plan.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/api.php';
