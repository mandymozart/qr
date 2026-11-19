<?php
declare(strict_types=1);

/*
 * Pop-art illustrations for the mini-app presets: thick ink outlines, flat palette colors,
 * Haring-style motion lines. Inline SVG so they follow the theme colors (CSS variables).
 */

function step_preset_art(string $key): string
{
    $ink = 'var(--ink)';
    $lines = fn(string $d) => '<path d="' . $d . '" stroke-width="4"/>';
    $art = [
        'newsletter' => ['var(--green)',
            $lines('M30 40 L18 35 M28 57 L14 57 M30 74 L18 79') .
            '<rect x="40" y="32" width="80" height="54" rx="7" fill="#fff"/>' .
            '<path d="M40 37 L80 64 L120 37"/>' .
            '<path d="M117 41 C101 30 102 15 111 15 C115 15 117 18 117 20 C117 18 119 15 123 15 C132 15 133 30 117 41 Z" fill="var(--red)" stroke-width="4"/>' .
            $lines('M136 18 L144 12 M138 30 L148 31')],
        'registration' => ['var(--orange)',
            $lines('M30 38 L16 38 M26 52 L10 52 M30 66 L16 66 M134 38 L146 34 M134 56 L148 56') .
            '<rect x="42" y="22" width="76" height="66" rx="8" fill="#fff"/>' .
            '<path d="M42 38 V30 A8 8 0 0 1 50 22 H110 A8 8 0 0 1 118 30 V38 Z" fill="var(--blue)"/>' .
            '<circle cx="51" cy="31" r="3" fill="#fff" stroke-width="3"/><circle cx="109" cy="31" r="3" fill="#fff" stroke-width="3"/>' .
            '<text x="80" y="78" text-anchor="middle" font-family="\'Permanent Marker\', Impact, sans-serif" font-size="36" fill="' . $ink . '" stroke="none">27</text>'],
        'survey' => ['var(--blue)',
            '<path d="M30 18 H90 A10 10 0 0 1 100 28 V60 A10 10 0 0 1 90 70 H52 L38 84 V70 H30 A10 10 0 0 1 20 60 V28 A10 10 0 0 1 30 18 Z" fill="#fff"/>' .
            '<text x="60" y="61" text-anchor="middle" font-family="\'Permanent Marker\', Impact, sans-serif" font-size="38" fill="' . $ink . '" stroke="none">?</text>' .
            '<path d="M116 42 H134 A8 8 0 0 1 142 50 V66 A8 8 0 0 1 134 74 H132 L134 86 L120 74 H116 A8 8 0 0 1 108 66 V50 A8 8 0 0 1 116 42 Z" fill="var(--yellow)"/>' .
            '<text x="125" y="70" text-anchor="middle" font-family="\'Permanent Marker\', Impact, sans-serif" font-size="24" fill="' . $ink . '" stroke="none">!</text>' .
            $lines('M112 22 L118 14 M124 28 L134 22')],
        'photo' => ['var(--pink)',
            '<path d="M60 36 L68 24 H92 L100 36" fill="var(--yellow)"/>' .
            '<rect x="34" y="36" width="92" height="56" rx="10" fill="#fff"/>' .
            '<circle cx="80" cy="64" r="19" fill="var(--green)"/>' .
            '<circle cx="80" cy="64" r="8" fill="' . $ink . '" stroke="none"/><circle cx="76" cy="60" r="2.5" fill="#fff" stroke="none"/>' .
            '<rect x="106" y="43" width="12" height="7" rx="2" fill="var(--yellow)" stroke-width="3"/>' .
            $lines('M126 26 L134 17 M132 36 L144 34 M118 20 L120 9 M30 48 L20 44 M30 64 L18 64 M30 80 L20 84')],
        'poll' => ['var(--yellow)',
            '<rect x="36" y="58" width="22" height="32" rx="3" fill="var(--blue)"/>' .
            '<rect x="69" y="36" width="22" height="54" rx="3" fill="var(--red)"/>' .
            '<rect x="102" y="48" width="22" height="42" rx="3" fill="var(--green)"/>' .
            '<path d="M24 92 H136"/>' .
            '<path d="M80 6 L82.6 12.4 L89.5 12.9 L84.3 17.4 L85.9 24.1 L80 20.5 L74.1 24.1 L75.7 17.4 L70.5 12.9 L77.4 12.4 Z" fill="#fff" stroke-width="3.5"/>' .
            $lines('M62 14 L54 8 M98 14 L106 8 M60 28 L50 28 M100 28 L110 28')],
        'flyer' => ['var(--blue)',
            $lines('M32 34 L20 30 M30 54 L16 54 M32 74 L20 78 M128 34 L140 30 M130 54 L144 54 M128 74 L140 78') .
            '<g transform="rotate(-6 80 56)">' .
            '<rect x="48" y="14" width="64" height="84" rx="4" fill="#fff"/>' .
            '<path d="M58 58 A22 22 0 0 1 102 58 Z" fill="var(--orange)"/>' .
            '<path d="M58 66 H102 M64 76 H96 M70 86 H90" stroke-width="4"/>' .
            '<rect x="68" y="8" width="24" height="11" fill="var(--pink)" stroke-width="3" transform="rotate(4 80 13)"/>' .
            '</g>'],
        'tickets' => ['var(--red)',
            '<g transform="rotate(-8 80 56)">' .
            '<path d="M30 36 H130 V48 A8 8 0 0 0 130 64 V76 H30 V64 A8 8 0 0 0 30 48 Z" fill="var(--yellow)"/>' .
            '<path d="M104 40 V72" stroke-width="4" stroke-dasharray="1 8"/>' .
            '<path d="M66 44 L69.3 51.8 L77.4 52.3 L71.1 57.6 L73.1 65.5 L66 61.1 L58.9 65.5 L60.9 57.6 L54.6 52.3 L62.7 51.8 Z" fill="#fff" stroke-width="3.5"/>' .
            '</g>' .
            $lines('M40 22 L34 12 M58 18 L56 8 M120 92 L126 102 M102 96 L104 106')],
        'style' => ['var(--pink)',
            '<rect x="38" y="14" width="66" height="66" rx="8" fill="#fff"/>' .
            '<rect x="46" y="22" width="16" height="16" rx="4" fill="var(--blue)" stroke-width="4"/>' .
            '<rect x="80" y="22" width="16" height="16" rx="4" fill="var(--blue)" stroke-width="4"/>' .
            '<rect x="46" y="56" width="16" height="16" rx="4" fill="var(--blue)" stroke-width="4"/>' .
            '<g fill="' . $ink . '" stroke="none"><circle cx="72" cy="48" r="3.5"/><circle cx="86" cy="48" r="3.5"/><circle cx="72" cy="62" r="3.5"/><circle cx="80" cy="70" r="3.5"/><circle cx="94" cy="60" r="3.5"/></g>' .
            '<path d="M142 26 L116 70" stroke-width="9"/>' .
            '<path d="M116 70 C106 74 102 86 98 98 C112 96 122 88 122 76 Z" fill="var(--yellow)" stroke-width="4"/>' .
            '<circle cx="28" cy="94" r="7" fill="var(--yellow)" stroke-width="4"/>' .
            $lines('M128 98 L136 104 M140 88 L150 90 M24 30 L14 26 M26 46 L12 46')],
        'miniapp' => ['var(--green)',
            '<rect x="56" y="8" width="48" height="94" rx="10" fill="#fff"/>' .
            '<path d="M72 16 H88" stroke-width="4"/>' .
            '<path d="M65 32 H95 M65 42 H88" stroke-width="4"/>' .
            '<rect x="65" y="52" width="30" height="10" rx="3" fill="var(--yellow)" stroke-width="3.5"/>' .
            '<rect x="64" y="72" width="32" height="13" rx="6.5" fill="var(--red)" stroke-width="4"/>' .
            $lines('M44 30 L32 24 M42 52 L28 52 M44 74 L32 80 M116 30 L128 24 M118 52 L132 52 M116 74 L128 80')],
    ];
    if (!isset($art[$key])) return '';
    [$bg, $body] = $art[$key];
    return '<svg class="preset-art" viewBox="0 0 160 110" aria-hidden="true" focusable="false">'
        . '<rect width="160" height="110" fill="' . $bg . '"/>'
        . '<g fill="none" stroke="' . $ink . '" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">' . $body . '</g></svg>';
}

/**
 * Selectable preset cards (radio inputs named "preset").
 *   $checked  preset key to show as selected
 *   $form     id of the form the radios belong to, when the cards sit outside it
 *   $compact  small cards without the one-line description (side boxes)
 */
function step_preset_cards(?string $checked, string $form = '', bool $compact = false, string $href = ''): string
{
    $out = '<div class="preset-cards' . ($compact ? ' compact' : '') . '"' . ($href === '' ? ' role="radiogroup"' : '') . '>';
    foreach (step_presets() as $k => $p) {
        $out .= ($href !== ''
            ? '<a class="preset-card" href="' . e($href . rawurlencode($k)) . '">'
            : '<label class="preset-card"><input type="radio" name="preset" value="' . e($k) . '"'
              . ($form !== '' ? ' form="' . e($form) . '"' : '') . ($checked === $k ? ' checked' : '') . '>')
            . '<span class="pc-body">' . step_preset_art($k)
            . '<b class="pc-label">' . e($p['label']) . '</b>'
            . (!$compact && !empty($p['hint']) ? '<small class="pc-hint">' . e($p['hint']) . '</small>' : '')
            . '</span>' . ($href !== '' ? '</a>' : '</label>');
    }
    return $out . '</div>';
}

/**
 * A feature that stays out of the way until someone wants it: a raised card with a picture,
 * a title and one line. Clicking it opens the settings inside (a native <details>, works without JS).
 * Inputs inside a closed card are disabled by app.js, so a closed card means "feature off".
 */
function feature_card(string $id, string $art, string $title, string $hint, string $body, bool $open = false, string $badge = ''): string
{
    return '<details class="feature-card" id="' . e($id) . '"' . ($open ? ' open' : '') . '>'
        . '<summary>' . ($badge !== '' ? '<span class="new-badge">' . e($badge) . '</span>' : '')
        . '<span class="fc-art">' . step_preset_art($art) . '</span>'
        . '<span class="fc-text"><b>' . e($title) . '</b><small>' . e($hint) . '</small></span>'
        . '<span class="fc-go" aria-hidden="true"></span></summary>'
        . '<div class="fc-body">' . $body . '</div></details>';
}

/** "Dots · Berry · Emoji" — what a code's style currently is, for the collapsed style card. */
function style_summary(string|array|null $raw): string
{
    $s = qr_style($raw);
    $parts = [$s['shape'] === 'dots' ? t('Dots') : t('Squares')];
    $parts[] = $s['color'] === 'custom' ? t('Custom') : t(QR_COLORS[$s['color']]['label']);
    if ($s['logo'] === 'emoji') $parts[] = t('Emoji');
    if ($s['logo'] === 'upload') $parts[] = t('Logo');
    return implode(' · ', $parts);
}

function style_is_default(string|array|null $raw): bool
{
    $s = qr_style($raw);
    return $s['shape'] === 'square' && $s['color'] === 'classic' && $s['logo'] === 'none' && $s['eye'] === 'square';
}