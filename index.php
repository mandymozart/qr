<?php
declare(strict_types=1);

/*
 * Public redirect endpoint:  https://qr.viennastruggle.at/?c=CODE
 * Logs the scan and 302-redirects to the target. Without ?c= → /app/.
 */
require __DIR__ . '/lib/bootstrap.php';

$code = (string) ($_GET['c'] ?? '');
if ($code === '') redirect('/app/');

function device_info(string $ua): array
{
    if ($ua === '' || preg_match('~bot|crawl|spider|slurp|facebookexternalhit|preview|curl|wget|python|headless~i', $ua)) return ['bot', ''];
    $os = match (true) {
        (bool) preg_match('~iphone|ipad|ipod~i', $ua) => 'iOS',
        (bool) preg_match('~android~i', $ua) => 'Android',
        (bool) preg_match('~windows~i', $ua) => 'Windows',
        (bool) preg_match('~mac os~i', $ua) => 'macOS',
        (bool) preg_match('~linux~i', $ua) => 'Linux',
        default => '',
    };
    $device = preg_match('~mobile|iphone|ipod|android.*mobile|windows phone~i', $ua) ? 'mobile'
        : (preg_match('~ipad|tablet|android~i', $ua) ? 'tablet' : 'desktop');
    return [$device, $os];
}

try {
    $link = q('SELECT id, target_url FROM links WHERE code = ? AND active = 1', [$code])->fetch();
    if ($link) {
        [$device, $os] = device_info((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
        q('INSERT INTO hits (link_id, device, os, referer) VALUES (?, ?, ?, ?)', [$link['id'], $device, $os, substr($ref, 0, 190)]);
    }
} catch (Throwable $ex) {
    error_log('qr redirect error: ' . $ex->getMessage());
    $link = null;
    http_response_code(503);
}

if ($link) {
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('Location: ' . $link['target_url'], true, 302);
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
