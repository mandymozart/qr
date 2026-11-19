<?php
// Local dev only (php -S localhost:8088 router.php): mimics the .htaccess rewrites. Not deployed.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$map = [
    '~^/api/v1/(.*)$~' => fn($m) => ['api.php', ['path' => $m[1]]],
    '~^/mcp/?$~' => fn($m) => ['mcp.php', []],
    '~^/qr/([A-Za-z0-9_-]{2,40})\.(png|svg)$~' => fn($m) => ['qr-image.php', ['c' => $m[1], 'fmt' => $m[2]]],
    '~^/(de|en)/([a-z0-9-]+)/?$~' => fn($m) => ['page.php', ['lang' => $m[1], 'slug' => $m[2]]],
    '~^/(sitemap\.xml|robots\.txt|llms\.txt)$~' => fn($m) => ['seo-files.php', ['file' => strtok($m[1], '.')]],
];
foreach ($map as $re => $to) {
    if (preg_match($re, $path, $m)) {
        [$file, $get] = $to($m);
        $_GET = $get + $_GET;
        chdir(__DIR__);
        require __DIR__ . '/' . $file;
        return true;
    }
}
return false;
