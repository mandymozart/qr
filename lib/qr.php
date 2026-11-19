<?php
declare(strict_types=1);

/*
 * QR rendering with styles.
 *
 * A style is ['shape' => square|dots, 'color' => preset key, 'logo' => none|badge|upload].
 * Every style keeps the three corner "eyes" square and stays dark-on-white, and a logo
 * switches to the highest error correction (H, 30%). qr_scans() renders a PNG and decodes it
 * with the library's own reader; styles are only saved when that succeeds.
 */

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Output\QROutputInterface;

/** Color presets. 'data' may be a [from, to] gradient. Only dark colors: they must contrast with white. */
const QR_COLORS = [
    'classic'  => ['label' => 'Classic',  'data' => '#111111', 'eye' => '#111111', 'dot' => '#111111'],
    'pop'      => ['label' => 'Pop',      'data' => '#1f4fd8', 'eye' => '#e01010', 'dot' => '#111111'],
    'gradient' => ['label' => 'Gradient', 'data' => ['#3a0ca3', '#c9184a'], 'eye' => '#111111', 'dot' => '#3a0ca3'],
    'berry'    => ['label' => 'Berry',    'data' => '#b0103a', 'eye' => '#111111', 'dot' => '#b0103a'],
    'ocean'    => ['label' => 'Ocean',    'data' => '#123c9c', 'eye' => '#123c9c', 'dot' => '#111111'],
    'forest'   => ['label' => 'Forest',   'data' => '#0b6e3a', 'eye' => '#111111', 'dot' => '#0b6e3a'],
];
const QR_SHAPES = ['square', 'dots'];
const QR_LOGOS = ['none', 'badge', 'upload'];
const QR_EYES = ['square', 'rounded', 'round']; // the three big corner pieces
const QR_LOGO_DIR = __DIR__ . '/logos';
const QR_BADGE = __DIR__ . '/../app/assets/qr-badge.png';
const QR_LOGO_PLACEHOLDER = __DIR__ . '/../app/assets/qr-logo-placeholder.png'; // preview only, before an upload

/** Normalizes a stored style (JSON string or array) to a full, valid style array. */
function qr_style(string|array|null $s): array
{
    if (is_string($s)) $s = json_decode($s, true);
    $s = is_array($s) ? $s : [];
    return [
        'shape' => in_array($s['shape'] ?? '', QR_SHAPES, true) ? $s['shape'] : 'square',
        'color' => isset(QR_COLORS[$s['color'] ?? '']) || ($s['color'] ?? '') === 'custom' ? $s['color'] : 'classic',
        'logo'  => in_array($s['logo'] ?? '', QR_LOGOS, true) ? $s['logo'] : 'none',
        'eye'   => in_array($s['eye'] ?? '', QR_EYES, true) ? $s['eye'] : 'square',
    ] + (($s['color'] ?? '') === 'custom' ? [
        'c1' => qr_valid_hex($s['c1'] ?? '') ?? '#111111', // dots
        'c2' => qr_valid_hex($s['c2'] ?? '') ?? '#111111', // corners
    ] : []);
}

function qr_valid_hex(string $v): ?string
{
    return preg_match('~^#[0-9a-fA-F]{6}$~', $v) ? strtolower($v) : null;
}

/** The colors to paint with: a preset, or the user's two custom colors. */
function qr_palette(array $style): array
{
    if ($style['color'] === 'custom') {
        return ['data' => $style['c1'], 'eye' => $style['c2'], 'dot' => $style['c2']];
    }
    return QR_COLORS[$style['color']];
}

/** Contrast ratio of a color against white (WCAG formula). QR codes want roughly 3:1 or more. */
function qr_contrast_with_white(string $hex): float
{
    $l = array_map(function (int $c) {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, qr_hex($hex));
    $lum = 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    return 1.05 / ($lum + 0.05);
}

function qr_is_default(array $style): bool
{
    return $style === qr_style(null);
}

/** Path of the logo image for a style + link, or null. */
function qr_logo_file(array $style, ?int $linkId): ?string
{
    if ($style['logo'] === 'badge') return is_file(QR_BADGE) ? QR_BADGE : null;
    if ($style['logo'] === 'upload' && $linkId) {
        $f = QR_LOGO_DIR . '/' . $linkId . '.png';
        return is_file($f) ? $f : null;
    }
    return null;
}

/** Cache-busting token for a link's current look. */
function qr_version(array $link): string
{
    $style = qr_style($link['style'] ?? null);
    $logo = qr_logo_file($style, (int) $link['id']);
    return substr(md5($link['code'] . json_encode($style) . ($logo ? filemtime($logo) : '')), 0, 10);
}

function qr_hex(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** PNG renderer that can paint data modules with a diagonal gradient. */
final class QrGdStyled extends QRGdImagePNG
{
    public static ?array $gradient = null; // [[r,g,b], [r,g,b]]
    public static bool $skipFinder = false; // corner pieces are drawn separately (rounded / round)
    private array $gradientCache = [];

    protected function module(int $x, int $y, int $M_TYPE): void
    {
        if (self::$skipFinder && in_array($M_TYPE, [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT], true)) {
            return;
        }
        $g = self::$gradient;
        $types = [QRMatrix::M_DATA_DARK, QRMatrix::M_ALIGNMENT_DARK, QRMatrix::M_TIMING_DARK, QRMatrix::M_FORMAT_DARK, QRMatrix::M_VERSION_DARK];
        if ($g === null || !in_array($M_TYPE, $types, true)) {
            parent::module($x, $y, $M_TYPE);
            return;
        }
        $n = max(1, $this->moduleCount - 1);
        $t = round(($x + $y) / (2 * $n), 2);
        $key = (string) $t;
        if (!isset($this->gradientCache[$key])) {
            $c = [];
            for ($i = 0; $i < 3; $i++) $c[] = (int) round($g[0][$i] + ($g[1][$i] - $g[0][$i]) * $t);
            $this->gradientCache[$key] = imagecolorallocate($this->image, ...$c);
        }
        $color = $this->gradientCache[$key];
        if ($this->drawCircularModules && !$this->matrix->checkTypeIn($x, $y, $this->keepAsSquare)) {
            imagefilledellipse($this->image, ($x * $this->scale) + intdiv($this->scale, 2), ($y * $this->scale) + intdiv($this->scale, 2),
                (int) ($this->circleDiameter * $this->scale), (int) ($this->circleDiameter * $this->scale), $color);
            return;
        }
        imagefilledrectangle($this->image, $x * $this->scale, $y * $this->scale, ($x + 1) * $this->scale, ($y + 1) * $this->scale, $color);
    }
}

/** Builds options + matrix for a style. Returns [QROptions, QRMatrix, logoModules]. */
function qr_prepare(string $data, array $style, bool $png, int $scale, bool $hasLogo): array
{
    $c = qr_palette($style);
    $dots = $style['shape'] === 'dots';
    $dataColor = is_array($c['data']) ? ($png ? $c['data'][0] : 'url(#qrg)') : $c['data'];
    $classic = $style['color'] === 'classic';
    $val = fn(string $v) => $png ? (str_starts_with($v, '#') ? qr_hex($v) : qr_hex('#111111')) : $v;

    $opt = [
        'outputBase64' => false,
        'eccLevel' => $hasLogo ? EccLevel::H : ($dots || !$classic ? EccLevel::Q : EccLevel::M),
        'drawLightModules' => false,
        'drawCircularModules' => $dots,
        'circleRadius' => 0.45,
        'keepAsSquare' => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT],
        'moduleValues' => [
            QRMatrix::M_FINDER_DARK => $val($c['eye']),
            QRMatrix::M_FINDER_DOT => $val($c['dot']),
            QRMatrix::M_DATA_DARK => $val($dataColor),
            QRMatrix::M_ALIGNMENT_DARK => $val(is_array($c['data']) ? $dataColor : $c['eye']),
            QRMatrix::M_TIMING_DARK => $val($dataColor),
            QRMatrix::M_FORMAT_DARK => $val($dataColor),
            QRMatrix::M_VERSION_DARK => $val($dataColor),
            QRMatrix::M_DARKMODULE => $val($dataColor),
        ],
    ];
    if ($png) {
        $opt += ['outputType' => QROutputInterface::CUSTOM, 'outputInterface' => QrGdStyled::class,
                 'scale' => $scale, 'bgColor' => [255, 255, 255], 'returnResource' => true];
    } else {
        $opt += ['outputType' => QROutputInterface::MARKUP_SVG, 'svgAddXmlHeader' => true, 'connectPaths' => !$dots, 'cssClass' => 'qr',
                 'svgDefs' => is_array($c['data'])
                     ? '<linearGradient id="qrg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c['data'][0] . '"/><stop offset="1" stop-color="' . $c['data'][1] . '"/></linearGradient>'
                     : ''];
    }
    $options = new QROptions($opt);
    $qr = new QRCode($options);
    $qr->addByteSegment($data);
    $matrix = $qr->getQRMatrix();
    $logoModules = 0;
    if ($hasLogo) {
        $inner = $matrix->getSize() - 2 * 4; // without quiet zone
        $logoModules = (int) round($inner * 0.24);
        if ($logoModules % 2 !== $inner % 2) $logoModules++;
        $matrix->setLogoSpace($logoModules, $logoModules);
    }
    QrGdStyled::$gradient = ($png && is_array($c['data'])) ? [qr_hex($c['data'][0]), qr_hex($c['data'][1])] : null;
    QrGdStyled::$skipFinder = $png && $style['eye'] !== 'square';
    return [$options, $matrix, $logoModules];
}

function qr_svg(string $data, array $style = [], ?string $logoFile = null): string
{
    $style = qr_style($style);
    [$options, $matrix, $lm] = qr_prepare($data, $style, false, 1, $logoFile !== null);
    $svg = (new QRMarkupSVG($options, $matrix))->dump();
    // white background so downloads print on any paper/background
    $svg = preg_replace('~(<svg[^>]*>)~', '$1<rect width="100%" height="100%" fill="#fff"/>', $svg, 1);
    if ($style['eye'] !== 'square') {
        $svg = preg_replace('~<path class="qr-finder-(?:dark|dot)[^"]*"[^>]*/>\s*~', '', $svg);
        $c = qr_palette($style);
        $eyes = '';
        foreach (qr_eye_positions($matrix->getSize()) as [$x, $y]) {
            if ($style['eye'] === 'round') {
                $ring = qr_circle_path($x + 3.5, $y + 3.5, 3.5) . qr_circle_path($x + 3.5, $y + 3.5, 2.5);
                $dot = qr_circle_path($x + 3.5, $y + 3.5, 1.5);
            } else {
                $ring = qr_rrect_path($x, $y, 7, 7, 2.2) . qr_rrect_path($x + 1, $y + 1, 5, 5, 1.4);
                $dot = qr_rrect_path($x + 2, $y + 2, 3, 3, 0.9);
            }
            $eyes .= '<path class="qr-eye" fill-rule="evenodd" fill="' . $c['eye'] . '" d="' . $ring . '"/><path class="qr-eye-dot" fill="' . $c['dot'] . '" d="' . $dot . '"/>';
        }
        $svg = str_replace('</svg>', $eyes . '</svg>', $svg);
    }
    if ($logoFile && $lm) {
        $n = $matrix->getSize();
        $size = $lm - 1; // leave a little white around the logo
        $xy = ($n - $size) / 2;
        $img = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoFile));
        $svg = str_replace('</svg>', '<image x="' . $xy . '" y="' . $xy . '" width="' . $size . '" height="' . $size . '" href="' . $img . '"/></svg>', $svg);
    }
    return $svg;
}

function qr_png(string $data, array $style = [], ?string $logoFile = null, int $scale = 12): string
{
    $style = qr_style($style);
    [$options, $matrix, $lm] = qr_prepare($data, $style, true, $scale, $logoFile !== null);
    $im = (new QrGdStyled($options, $matrix))->dump();
    if ($style['eye'] !== 'square') {
        $c = qr_palette($style);
        $m = imagesx($im) / $matrix->getSize();
        $eye = imagecolorallocate($im, ...qr_hex($c['eye']));
        $dot = imagecolorallocate($im, ...qr_hex($c['dot']));
        $white = imagecolorallocate($im, 255, 255, 255);
        foreach (qr_eye_positions($matrix->getSize()) as [$x, $y]) {
            $cx = ($x + 3.5) * $m; $cy = ($y + 3.5) * $m;
            if ($style['eye'] === 'round') {
                imagefilledellipse($im, (int) $cx, (int) $cy, (int) (7 * $m), (int) (7 * $m), $eye);
                imagefilledellipse($im, (int) $cx, (int) $cy, (int) (5 * $m), (int) (5 * $m), $white);
                imagefilledellipse($im, (int) $cx, (int) $cy, (int) (3 * $m), (int) (3 * $m), $dot);
            } else {
                qr_gd_rrect($im, $x * $m, $y * $m, ($x + 7) * $m, ($y + 7) * $m, 2.2 * $m, $eye);
                qr_gd_rrect($im, ($x + 1) * $m, ($y + 1) * $m, ($x + 6) * $m, ($y + 6) * $m, 1.4 * $m, $white);
                qr_gd_rrect($im, ($x + 2) * $m, ($y + 2) * $m, ($x + 5) * $m, ($y + 5) * $m, 0.9 * $m, $dot);
            }
        }
    }
    if ($logoFile && $lm && ($logo = @imagecreatefrompng($logoFile))) {
        $px = imagesx($im);
        $size = (int) round($px * ($lm - 1) / $matrix->getSize());
        $xy = (int) round(($px - $size) / 2);
        imagecopyresampled($im, $logo, $xy, $xy, 0, 0, $size, $size, imagesx($logo), imagesy($logo));
    }
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

/** Top-left module coordinates of the three corner pieces (matrix incl. 4-module quiet zone). */
function qr_eye_positions(int $n): array
{
    $q = 4;
    return [[$q, $q], [$n - $q - 7, $q], [$q, $n - $q - 7]];
}

function qr_rrect_path(float $x, float $y, float $w, float $h, float $r): string
{
    $f = fn(float $v) => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    return 'M' . $f($x + $r) . ' ' . $f($y) . 'H' . $f($x + $w - $r) . 'A' . $f($r) . ' ' . $f($r) . ' 0 0 1 ' . $f($x + $w) . ' ' . $f($y + $r)
        . 'V' . $f($y + $h - $r) . 'A' . $f($r) . ' ' . $f($r) . ' 0 0 1 ' . $f($x + $w - $r) . ' ' . $f($y + $h)
        . 'H' . $f($x + $r) . 'A' . $f($r) . ' ' . $f($r) . ' 0 0 1 ' . $f($x) . ' ' . $f($y + $h - $r)
        . 'V' . $f($y + $r) . 'A' . $f($r) . ' ' . $f($r) . ' 0 0 1 ' . $f($x + $r) . ' ' . $f($y) . 'Z';
}

function qr_circle_path(float $cx, float $cy, float $r): string
{
    return 'M' . ($cx - $r) . ' ' . $cy . 'a' . $r . ' ' . $r . ' 0 1 0 ' . (2 * $r) . ' 0a' . $r . ' ' . $r . ' 0 1 0 ' . (-2 * $r) . ' 0Z';
}

/** Filled rounded rectangle for GD. */
function qr_gd_rrect(GdImage $im, float $x1, float $y1, float $x2, float $y2, float $r, int $color): void
{
    $x1 = (int) round($x1); $y1 = (int) round($y1); $x2 = (int) round($x2) - 1; $y2 = (int) round($y2) - 1; $r = (int) round($r); $d = 2 * $r;
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $d, $d, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $d, $d, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $d, $d, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $d, $d, $color);
}

/** True if this style decodes back to $data (renders a PNG and reads it). */
function qr_scans(string $data, array $style, ?string $logoFile): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'qrscan');
    try {
        // scale 8 decodes all presets reliably; 7 as a second opinion against reader quirks
        foreach ([8, 7] as $scale) {
            file_put_contents($tmp, qr_png($data, $style, $logoFile, $scale));
            try {
                if ((string) (new QRCode(new QROptions([])))->readFromFile($tmp) === $data) return true;
            } catch (Throwable) {
            }
        }
        return false;
    } finally {
        @unlink($tmp);
    }
}

/**
 * Stores an uploaded logo (any common image) as a square 256px PNG, re-encoded so no
 * original file content survives. Returns null or an error message.
 */
function qr_store_logo(array $file, int $linkId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return 'upload';
    if ($file['size'] > 3 * 1024 * 1024) return 'size';
    $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$src) return 'type';
    $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
    $dst = imagecreatetruecolor(256, 256);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency onto white
    imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
    if (!is_dir(QR_LOGO_DIR)) @mkdir(QR_LOGO_DIR, 0755, true);
    return imagepng($dst, QR_LOGO_DIR . '/' . $linkId . '.png') ? null : 'save';
}

function qr_delete_logo(int $linkId): void
{
    @unlink(QR_LOGO_DIR . '/' . $linkId . '.png');
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
