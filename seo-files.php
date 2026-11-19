<?php
declare(strict_types=1);

/*
 * /robots.txt, /sitemap.xml and /llms.txt (rewritten here by .htaccess so they always use the current domain).
 */
require_once __DIR__ . '/lib/app.php';

$b = base_url();
$file = (string) ($_GET['file'] ?? '');

if ($file === 'robots') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    echo "Disallow: /app/\n";       // the tool itself
    echo "Disallow: /manage.php\n"; // guest edit links
    echo "Disallow: /step.php\n";   // mini-app pages of individual codes
    echo "Allow: /\n\n";
    echo "Sitemap: $b/sitemap.xml\n";
    exit;
}

if ($file === 'sitemap') {
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [['de' => "$b/?lang=de", 'en' => "$b/?lang=en"]];
    foreach (content_pages() as $page) {
        $urls[] = ['de' => seo_page_url('de', $page['de']['slug']), 'en' => seo_page_url('en', $page['en']['slug'])];
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach ($urls as $pair) {
        foreach ($pair as $lang => $loc) {
            echo "  <url>\n    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            foreach ($pair as $l => $u) echo '    <xhtml:link rel="alternate" hreflang="' . $l . '" href="' . htmlspecialchars($u) . '"/>' . "\n";
            echo "  </url>\n";
        }
    }
    echo "</urlset>\n";
    exit;
}

if ($file === 'llms') {
    // short, factual summary for AI assistants and agents (https://llmstxt.org)
    header('Content-Type: text/plain; charset=utf-8');
    $pages = content_pages();
    echo "# qr\n\n";
    echo "> Free dynamic QR codes that never expire. Change where a printed code leads at any time, see anonymous scan statistics, and style codes with dots, colors, emoji or a logo. Made in Vienna for artists, bands, galleries and collectives. Available in English and German.\n\n";
    echo "## Key facts\n\n";
    echo "- Free: up to 10 codes per browser without an account; up to " . FREE_MAX_CODES . " codes with a free account; Pro on request for more.\n";
    echo "- Codes do not expire: no trial period and no subscription trap; codes keep working on the free plan.\n";
    echo "- Dynamic: the QR code points to a short link whose target can be changed after printing.\n";
    echo "- Privacy: no tracking cookies for people who scan, IP addresses are never stored; stats show scans, daily unique visitors, city/country, device, language and time.\n";
    echo "- Styles: squares or dots, square/rounded/round corners, color presets or custom colors, emoji or own logo; every style is test-scanned before saving.\n";
    echo "- Downloads: SVG (print) and PNG; campaigns create numbered codes in bulk with a zip download.\n";
    echo "- Mini-apps: optional page shown before the target: hosted flyer page (works without a target), form (sign-up, registration, survey, photo or file upload), poll, or info with a button (e.g. tickets).\n";
    echo "- Hosted in the EU.\n\n";
    echo "## Pages\n\n";
    echo "- [Create a QR code]($b/?lang=en): paste a link and get a QR code, no sign-up\n";
    foreach ($pages as $page) {
        echo '- [' . $page['en']['title'] . '](' . seo_page_url('en', $page['en']['slug']) . '): ' . $page['en']['description'] . "\n";
    }
    echo "\n## Deutsch\n\n";
    echo "- [QR-Code erstellen]($b/?lang=de)\n";
    foreach ($pages as $page) {
        echo '- [' . $page['de']['title'] . '](' . seo_page_url('de', $page['de']['slug']) . ")\n";
    }
    exit;
}

http_response_code(404);
