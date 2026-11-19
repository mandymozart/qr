<?php
declare(strict_types=1);

/*
 * Tiny i18n.
 *
 *   t('Saved.')                          → "Gespeichert." in German, "Saved." otherwise
 *   t('Created {n} codes.', ['n' => 5])  → placeholders in {braces}
 *
 * English text is the key. German lives in lib/lang/de/*.php (each returns [english => german]);
 * a missing entry just falls back to English. Dictionary entries are trusted, HTML-safe text.
 *
 * Language: ?lang=de|en (remembered in the qr_lang preference cookie, set only on that click),
 * else the qr_lang cookie, else the browser's Accept-Language (German → de), else English.
 */

const LANGS = ['en', 'de'];

function lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $q = (string) ($_GET['lang'] ?? '');
    if (in_array($q, LANGS, true)) {
        if (!headers_sent()) {
            setcookie('qr_lang', $q, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'secure' => is_https()]);
        }
        return $lang = $q;
    }
    $c = (string) ($_COOKIE['qr_lang'] ?? '');
    if (in_array($c, LANGS, true)) return $lang = $c;
    $al = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    return $lang = preg_match('~^\s*de\b~', $al) ? 'de' : 'en';
}

function dict(): array
{
    static $d = null;
    if ($d !== null) return $d;
    $d = [];
    if (lang() === 'en') return $d;
    foreach (glob(__DIR__ . '/lang/' . lang() . '/*.php') ?: [] as $file) {
        $part = require $file;
        if (is_array($part)) $d = array_merge($d, $part);
    }
    return $d;
}

function t(string $text, array $vars = []): string
{
    $out = dict()[$text] ?? $text;
    foreach ($vars as $k => $v) $out = str_replace('{' . $k . '}', (string) $v, $out);
    return $out;
}

/** Escaped translation for HTML output. */
function te(string $text, array $vars = []): string
{
    return e(t($text, $vars));
}

/** Current URL with ?lang= set. */
function lang_url(string $l): string
{
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
    $q = $_GET;
    $q['lang'] = $l;
    return $path . '?' . http_build_query($q);
}

function lang_switch(string $class = 'lang-switch'): string
{
    $out = '<span class="' . e($class) . '">';
    foreach (LANGS as $i => $l) {
        if ($i) $out .= '<span aria-hidden="true">/</span>';
        $out .= lang() === $l
            ? '<strong aria-current="true">' . strtoupper($l) . '</strong>'
            : '<a href="' . e(lang_url($l)) . '" hreflang="' . $l . '" lang="' . $l . '">' . strtoupper($l) . '</a>';
    }
    return $out . '</span>';
}

/** German month/day names for date(). */
function localize_date(string $s): string
{
    if (lang() !== 'de') return $s;
    static $map = [
        'January' => 'Jänner', 'February' => 'Februar', 'March' => 'März', 'May' => 'Mai', 'June' => 'Juni', 'July' => 'Juli',
        'October' => 'Oktober', 'December' => 'Dezember',
        'Jan' => 'Jän', 'Mar' => 'Mär', 'May' => 'Mai', 'Oct' => 'Okt', 'Dec' => 'Dez',
        'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch', 'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag', 'Saturday' => 'Samstag', 'Sunday' => 'Sonntag',
        'Mon' => 'Mo', 'Tue' => 'Di', 'Wed' => 'Mi', 'Thu' => 'Do', 'Fri' => 'Fr', 'Sat' => 'Sa', 'Sun' => 'So',
    ];
    return preg_replace_callback('~\b[A-Z][a-z]+\b~', fn($m) => $map[$m[0]] ?? $m[0], $s);
}
