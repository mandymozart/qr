<?php
declare(strict_types=1);

/*
 * SEO head tags for public pages: description, robots, canonical, hreflang, Open Graph / Twitter, JSON-LD.
 * Pages are noindex unless they opt in with ['index' => true] (only the landing page and content pages do).
 */

const SEO_SITE_NAME = 'qr';

function seo_tags(string $title, array $seo): string
{
    $desc = $seo['description'] ?? t('Free QR codes you can change later, with scan stats. No tracking cookies.');
    $index = !empty($seo['index']);
    $canonical = $seo['canonical'] ?? null;
    $image = base_url() . '/app/assets/og-' . lang() . '.png';
    $out = [];
    $out[] = '<meta name="description" content="' . e($desc) . '">';
    $out[] = '<meta name="robots" content="' . ($index ? 'index, follow' : 'noindex, follow') . '">';
    if ($canonical) $out[] = '<link rel="canonical" href="' . e($canonical) . '">';
    foreach ($seo['alternates'] ?? [] as $hl => $url) {
        $out[] = '<link rel="alternate" hreflang="' . e($hl) . '" href="' . e($url) . '">';
    }
    // share previews (WhatsApp, Signal, Instagram DMs, Mastodon, Slack, …)
    $out[] = '<meta property="og:type" content="website">';
    $out[] = '<meta property="og:site_name" content="' . SEO_SITE_NAME . '">';
    $out[] = '<meta property="og:title" content="' . e($seo['og_title'] ?? $title) . '">';
    $out[] = '<meta property="og:description" content="' . e($desc) . '">';
    $out[] = '<meta property="og:locale" content="' . (lang() === 'de' ? 'de_AT' : 'en_US') . '">';
    if ($canonical) $out[] = '<meta property="og:url" content="' . e($canonical) . '">';
    $out[] = '<meta property="og:image" content="' . e($image) . '">';
    $out[] = '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">';
    $out[] = '<meta name="twitter:card" content="summary_large_image">';
    $out[] = '<meta name="theme-color" content="#ffc23d">';
    foreach ($seo['jsonld'] ?? [] as $data) {
        $out[] = '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
    }
    return implode("\n", $out) . "\n";
}

/** schema.org description of the service (used on the landing page). */
function seo_app_jsonld(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => SEO_SITE_NAME,
        'url' => base_url() . '/',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'inLanguage' => ['de', 'en'],
        'description' => t('Free dynamic QR codes that never expire: change the target any time, see anonymous scan stats, style your code. No tracking cookies.'),
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR'],
        'featureList' => [
            t('Dynamic QR codes: change the target after printing'),
            t('Codes never expire, no subscription trap'),
            t('Anonymous scan stats without tracking cookies'),
            t('Styles: dots, colors, emoji or your logo'),
            t('Campaigns and bulk codes'),
            t('Mini-apps: flyer pages, sign-ups, polls'),
        ],
    ];
}

/** URLs of the public content pages, per language (for hreflang, sitemap and footer). */
function seo_page_url(string $lang, string $slug): string
{
    return base_url() . '/' . $lang . '/' . $slug;
}
