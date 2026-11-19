<?php
declare(strict_types=1);

/*
 * Public QR image of a code: /qr/CODE.png or /qr/CODE.svg (rewritten by .htaccess).
 * The image only contains the public short URL, so it is safe to serve without auth
 * (used by the API/MCP so agents can show and embed the code).
 */
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/qr.php';

$code = (string) ($_GET['c'] ?? '');
$fmt = ($_GET['fmt'] ?? 'png') === 'svg' ? 'svg' : 'png';
$link = preg_match('~^[A-Za-z0-9_-]{2,40}$~', $code) ? q('SELECT * FROM links WHERE code = ?', [$code])->fetch() : null;
if (!$link) { http_response_code(404); exit('Not found'); }

$style = qr_style($link['style'] ?? null);
$logo = qr_logo_file($style, (int) $link['id']);
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');
header('Content-Disposition: inline; filename="qr-' . $link['code'] . '.' . $fmt . '"');
if ($fmt === 'svg') {
    header('Content-Type: image/svg+xml');
    echo qr_svg(short_url($link['code']), $style, $logo);
} else {
    header('Content-Type: image/png');
    echo qr_png(short_url($link['code']), $style, $logo);
}
