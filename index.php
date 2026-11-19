<?php
declare(strict_types=1);

/*
 * Public redirect endpoint:  https://qr.viennastruggle.at/?c=CODE
 * Logs the scan (no cookies, no IP stored) and 302-redirects to the target.
 * Without ?c= → /app/.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/qr.php';
require __DIR__ . '/lib/track.php';

$code = (string) ($_GET['c'] ?? '');
if ($code === '') redirect('/app/');

try {
    $link = q('SELECT l.id, l.code, l.target_url, l.utm, c.name AS campaign FROM links l
               LEFT JOIN campaigns c ON c.id = l.campaign_id WHERE l.code = ? AND l.active = 1', [$code])->fetch();
} catch (Throwable $ex) {
    error_log('qr redirect error: ' . $ex->getMessage());
    $link = null;
    http_response_code(503);
}

if ($link) {
    // Send the visitor on their way first, record afterwards.
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('Location: ' . ($link['utm'] ? with_utm($link['target_url'], $link['code'], $link['campaign']) : $link['target_url']), true, 302);
    header('Content-Length: 0');
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

    try {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        [$device, $os] = device_info($ua);
        $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
        [$country, $city] = $device === 'bot' ? ['', ''] : geo_lookup($ip);
        q('INSERT INTO hits (link_id, device, os, referer, visitor, country, city, lang, browser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $link['id'], $device, $os, substr($ref, 0, 190),
            $device === 'bot' ? '' : visitor_id($ip, $ua),
            $country, $city, accept_language(), $device === 'bot' ? '' : browser_name($ua),
        ]);
        unset($ip);
        if (function_exists('fastcgi_finish_request')) maintenance();
    } catch (Throwable $ex) {
        error_log('qr tracking error: ' . $ex->getMessage());
    }
    exit;
}

if (http_response_code() !== 503) http_response_code(404);
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Not found · qr</title><link rel="stylesheet" href="/app/assets/app.css"></head>
<body><main class="center-card">
<span class="entity-type">qr</span>
<h2 class="entity-title">This code doesn't lead anywhere (anymore).</h2>
<p class="entity-description">The QR code <span class="url"><?= e($code) ?></span> is unknown or has been paused.</p>
</main></body></html>
