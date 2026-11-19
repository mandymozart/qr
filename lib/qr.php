<?php
declare(strict_types=1);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;

function qr_svg(string $data): string
{
    $o = new QROptions([
        'outputType' => QROutputInterface::MARKUP_SVG,
        'outputBase64' => false,
        'eccLevel' => EccLevel::M,
        'drawLightModules' => false,
        'svgAddXmlHeader' => true,
        'connectPaths' => true,
        'moduleValues' => [],
        'svgDefs' => '<style><![CDATA[ .dark { fill: #000; } ]]></style>',
        'cssClass' => 'qr',
    ]);
    $svg = (new QRCode($o))->render($data);
    // white background so downloads print on any paper/background
    return preg_replace('~(<svg[^>]*>)~', '$1<rect width="100%" height="100%" fill="#fff"/>', $svg, 1);
}

function qr_png(string $data, int $scale = 12): string
{
    $o = new QROptions([
        'outputType' => QROutputInterface::GDIMAGE_PNG,
        'outputBase64' => false,
        'eccLevel' => EccLevel::M,
        'scale' => $scale,
        'bgColor' => [255, 255, 255],
        'moduleValues' => [],
    ]);
    return (new QRCode($o))->render($data);
}

function qr_data_uri(string $data): string
{
    return 'data:image/svg+xml;base64,' . base64_encode(qr_svg($data));
}

function slug(string $s): string
{
    $s = strtolower(trim(preg_replace('~[^A-Za-z0-9]+~', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s), '-'));
    return $s ?: 'qr';
}
