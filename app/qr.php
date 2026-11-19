<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * QR images.
 *   /app/qr.php?id=12                → SVG (inline)
 *   /app/qr.php?id=12&fmt=png&dl=1   → PNG download
 *   /app/qr.php?campaign=3&fmt=zip   → all codes of a campaign as SVG + PNG in a zip
 *   /app/qr.php?k=EDITKEY            → guest code (also ?id= from the browser that created it)
 */
$user = current_user();
$fmt = $_GET['fmt'] ?? 'svg';

if ($cid = int_param('campaign')) {
    if (!$user) redirect('/app/login.php');
    $c = find_campaign($cid, $user);
    if (!$c || !class_exists('ZipArchive')) { http_response_code(404); exit(t('Not available')); }
    $links = q('SELECT * FROM links WHERE campaign_id = ? AND user_id = ? ORDER BY id', [$cid, $user['id']])->fetchAll();
    $tmp = tempnam(sys_get_temp_dir(), 'qrzip');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $csv = "code,title,short_url,target_url\n";
    foreach ($links as $l) {
        $name = slug($l['title']) . '-' . $l['code'];
        $st = qr_style($l['style'] ?? null);
        $lg = qr_logo_file($st, (int) $l['id']);
        $zip->addFromString("svg/$name.svg", qr_svg(short_url($l['code']), $st, $lg));
        $zip->addFromString("png/$name.png", qr_png(short_url($l['code']), $st, $lg));
        $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', [$l['code'], $l['title'], short_url($l['code']), $l['target_url']])) . "\n";
    }
    $zip->addFromString('codes.csv', $csv);
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . slug($c['name']) . '-qr.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

$link = ($user && !isset($_GET['k'])) ? find_link(int_param('id'), $user) : null;
if (!$link && ($g = guest_link_access()) && !$g['user_id']) $link = $g;
if (!$link) { http_response_code(404); exit(t('Not found')); }
$data = short_url($link['code']);
$name = slug($link['title']) . '-' . $link['code'];

$style = qr_style($link['style'] ?? null);
if (isset($_GET['preview'])) {
    // live preview from the style editor (not saved)
    $style = qr_style(['shape' => $_GET['shape'] ?? '', 'color' => $_GET['color'] ?? '', 'logo' => $_GET['logo'] ?? '', 'eye' => $_GET['eye'] ?? '',
                        'c1' => $_GET['c1'] ?? '', 'c2' => $_GET['c2'] ?? '']);
    header('Cache-Control: no-store');
} else {
    header('Cache-Control: private, max-age=3600');
}
$logo = qr_logo_file($style, (int) $link['id']);
if ($style['logo'] === 'upload' && !$logo) $logo = QR_LOGO_PLACEHOLDER; // preview before the first upload

if ($fmt === 'png') {
    header('Content-Type: image/png');
    if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="' . $name . '.png"');
    echo qr_png($data, $style, $logo);
} else {
    header('Content-Type: image/svg+xml');
    if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="' . $name . '.svg"');
    echo qr_svg($data, $style, $logo);
}
