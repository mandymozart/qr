<?php
/*
 * Research prototype: artful QR styles with chillerlan/php-qrcode, each one decode-tested.
 *   php drafts/artqr/make.php            → writes drafts/artqr/*.svg + index.html
 * Rendering to PNG + decoding is done by test.sh (headless Chrome + the library's own reader).
 */
declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;

$data = 'https://qr.viennastruggle.at/?c=ART123';
$out = __DIR__;

function finder(array $c): array
{
    // color for the three big corner squares ("eyes")
    return [
        QRMatrix::M_FINDER_DARK => $c[0], QRMatrix::M_FINDER_DOT => $c[1] ?? $c[0],
    ];
}

$styles = [
    'classic' => ['Classic', 'Square modules, black. Baseline.', [
        'eccLevel' => EccLevel::M,
    ]],
    'dots' => ['Dots', 'Round data dots, square eyes kept for reliable detection.', [
        'eccLevel' => EccLevel::Q, 'drawCircularModules' => true, 'circleRadius' => 0.42,
        'keepAsSquare' => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT, QRMatrix::M_ALIGNMENT_DARK],
    ]],
    'pop' => ['Pop colors', 'Red eyes, blue data, black timing. Dark colors on white keep contrast.', [
        'eccLevel' => EccLevel::Q, 'drawCircularModules' => true, 'circleRadius' => 0.45,
        'keepAsSquare' => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT],
        'moduleValues' => finder(['#e01010', '#111']) + [
            QRMatrix::M_DATA_DARK => '#1f4fd8', QRMatrix::M_ALIGNMENT_DARK => '#e01010', QRMatrix::M_TIMING_DARK => '#111',
        ],
    ]],
    'gradient' => ['Gradient', 'Data modules filled with a diagonal gradient (purple → pink). Keep both ends dark enough.', [
        'eccLevel' => EccLevel::Q, 'drawCircularModules' => true, 'circleRadius' => 0.45,
        'keepAsSquare' => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT],
        'svgDefs' => '<linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3a0ca3"/><stop offset="1" stop-color="#c9184a"/></linearGradient>',
        'moduleValues' => finder(['#111']) + [QRMatrix::M_DATA_DARK => 'url(#g)', QRMatrix::M_ALIGNMENT_DARK => 'url(#g)', QRMatrix::M_TIMING_DARK => 'url(#g)', QRMatrix::M_FORMAT_DARK => 'url(#g)', QRMatrix::M_VERSION_DARK => 'url(#g)'],
    ]],
    'logo' => ['Logo in the middle', 'Highest error correction (H, 30%) and a cleared space for a logo of ~20% width.', [
        'eccLevel' => EccLevel::H, 'addLogoSpace' => true, 'logoSpaceWidth' => 9, 'logoSpaceHeight' => 9,
        'drawCircularModules' => true, 'circleRadius' => 0.45,
        'keepAsSquare' => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT],
    ]],
    'inverted' => ['Inverted (light on dark)', 'Yellow modules on black. Looks great, but some older scanner apps cannot read inverted codes.', [
        'eccLevel' => EccLevel::Q, 'invert' => true,
    ]],
];

$cards = '';
foreach ($styles as $id => [$name, $desc, $opt]) {
    $o = new QROptions($opt + [
        'outputType' => QROutputInterface::MARKUP_SVG, 'outputBase64' => false,
        'svgAddXmlHeader' => false, 'connectPaths' => !($opt['drawCircularModules'] ?? false),
        'drawLightModules' => false, 'svgUseFillAttributes' => true,
    ]);
    $invert = !empty($opt['invert']);
    $svg = (new QRCode($o))->render($data);
    // background
    $bg = $invert ? '#111' : '#fff';
    $svg = preg_replace('~(<svg[^>]*>)~', '$1<rect width="100%" height="100%" fill="' . $bg . '"/>', $svg, 1);
    if ($invert) $svg = str_replace(['fill="#000"', 'fill="#000000"'], 'fill="#ffd60a"', $svg);
    if ($id === 'logo') {
        // drop an original pop figure + "qr" into the cleared centre
        preg_match('~viewBox="0 0 (\d+) (\d+)"~', $svg, $m);
        $n = (int) $m[1]; $c = $n / 2; $r = 4.2;
        $logo = '<g><rect x="' . ($c - $r) . '" y="' . ($c - $r) . '" width="' . (2 * $r) . '" height="' . (2 * $r) . '" rx="1.6" fill="#ffd60a" stroke="#111" stroke-width=".5"/>'
              . '<text x="' . $c . '" y="' . ($c + 1.6) . '" text-anchor="middle" font-family="Permanent Marker, Arial Black, sans-serif" font-size="4.6" fill="#111">qr</text></g>';
        $svg = str_replace('</svg>', $logo . '</svg>', $svg);
    }
    file_put_contents("$out/$id.svg", $svg);
    $cards .= "<figure data-id=\"$id\"><img src=\"$id.svg\" alt=\"\"><figcaption><b>" . htmlspecialchars($name) . '</b> <span class="res"></span><br>' . htmlspecialchars($desc) . '</figcaption></figure>';
}

file_put_contents("$out/index.html", '<!doctype html><meta charset="utf-8"><title>Artful QR research</title>
<link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&family=Source+Sans+3:wght@400;700&display=swap" rel="stylesheet">
<style>body{font-family:"Source Sans 3",sans-serif;background:#fffdf5;margin:24px}h1{font-family:"Permanent Marker"}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:20px}
figure{margin:0;border:4px solid #111;border-radius:18px;padding:14px;background:#fff;box-shadow:5px 5px 0 #111}
figure img{width:100%;display:block}figcaption{font-size:14px;margin-top:10px}.ok{color:#00a050}.bad{color:#e01010}</style>
<h1>Artful QR styles (research)</h1><p>Every code points to the same link. Decode results come from test.sh.</p><div class="grid">' . $cards . '</div>
<script>fetch("results.json").then(r=>r.json()).then(j=>{for(const[k,v]of Object.entries(j)){const s=document.querySelector(`[data-id="${k}"] .res`);if(s){s.textContent=v?"✓ scans":"✕ failed";s.className="res "+(v?"ok":"bad")}}}).catch(()=>{})</script>');
echo "written " . count($styles) . " styles\n";
