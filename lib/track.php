<?php
declare(strict_types=1);

/*
 * Consent-free scan analytics. Nothing is stored on the visitor's device and
 * the IP address is never saved: it's only used in memory to look up a coarse
 * location and to build a daily visitor hash with a salt that is deleted
 * after the day ends (same approach as Plausible Analytics).
 */

const GEO_DIR = __DIR__ . '/geo';
const GEO_DB = GEO_DIR . '/dbip-city-lite.mmdb';
const RETENTION_MONTHS = 12;

function device_info(string $ua): array
{
    if ($ua === '' || preg_match('~bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegrambot|preview|curl|wget|python|headless|monitor~i', $ua)) return ['bot', ''];
    $os = match (true) {
        (bool) preg_match('~iphone|ipad|ipod~i', $ua) => 'iOS',
        (bool) preg_match('~android~i', $ua) => 'Android',
        (bool) preg_match('~windows~i', $ua) => 'Windows',
        (bool) preg_match('~mac os~i', $ua) => 'macOS',
        (bool) preg_match('~linux|cros~i', $ua) => 'Linux',
        default => '',
    };
    $device = preg_match('~mobile|iphone|ipod|android.*mobile|windows phone~i', $ua) ? 'mobile'
        : (preg_match('~ipad|tablet|android~i', $ua) ? 'tablet' : 'desktop');
    return [$device, $os];
}

function browser_name(string $ua): string
{
    return match (true) {
        (bool) preg_match('~Instagram~', $ua) => 'Instagram app',
        (bool) preg_match('~FBAN|FBAV|FB_IAB~', $ua) => 'Facebook app',
        (bool) preg_match('~TikTok|musical_ly|BytedanceWebview~i', $ua) => 'TikTok app',
        (bool) preg_match('~SamsungBrowser~', $ua) => 'Samsung Internet',
        (bool) preg_match('~Edg[A-Z]?/~', $ua) => 'Edge',
        (bool) preg_match('~OPR/|Opera~', $ua) => 'Opera',
        (bool) preg_match('~Firefox/|FxiOS~', $ua) => 'Firefox',
        (bool) preg_match('~CriOS|Chrome/~', $ua) => 'Chrome',
        (bool) preg_match('~Safari/~', $ua) => 'Safari',
        default => 'Other',
    };
}

function accept_language(): string
{
    $h = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    return preg_match('~^\s*([a-z]{2,3})~i', $h, $m) ? strtolower($m[1]) : '';
}

/** Anonymous daily visitor id: hash(daily random salt + IP + UA). Salt is deleted the next day. */
function visitor_id(string $ip, string $ua): string
{
    $day = date('Y-m-d');
    $salt = q('SELECT salt FROM salts WHERE day = ?', [$day])->fetchColumn();
    if (!$salt) {
        $salt = bin2hex(random_bytes(32));
        try {
            q('INSERT INTO salts (day, salt) VALUES (?, ?)', [$day, $salt]);
        } catch (PDOException) {
            $salt = q('SELECT salt FROM salts WHERE day = ?', [$day])->fetchColumn(); // parallel insert
        }
        q('DELETE FROM salts WHERE day < ?', [$day]);
    }
    return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 16);
}

/** [country code, city] from the local DB-IP Lite database, or ['', ''] if unavailable. */
function geo_lookup(string $ip): array
{
    if (!is_file(GEO_DB) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return ['', ''];
    try {
        $reader = new MaxMind\Db\Reader(GEO_DB);
        $r = $reader->get($ip);
        $reader->close();
        return [
            substr((string) ($r['country']['iso_code'] ?? ''), 0, 2),
            mb_substr((string) ($r['city']['names']['de'] ?? $r['city']['names']['en'] ?? ''), 0, 80),
        ];
    } catch (Throwable $ex) {
        error_log('qr geo: ' . $ex->getMessage());
        return ['', ''];
    }
}

/** Adds utm_* parameters to the target unless it already has them. */
function with_utm(string $url, string $code, ?string $campaign): string
{
    if (preg_match('~[?&]utm_~i', $url)) return $url;
    $frag = '';
    if (($p = strpos($url, '#')) !== false) {
        $frag = substr($url, $p);
        $url = substr($url, 0, $p);
    }
    $q = http_build_query([
        'utm_source' => 'qr',
        'utm_medium' => 'qr',
        'utm_campaign' => $campaign ? slug($campaign) : 'qr',
        'utm_content' => $code,
    ]);
    return $url . (str_contains($url, '?') ? '&' : '?') . $q . $frag;
}

/** Downloads this month's DB-IP City Lite database (CC BY 4.0). Returns null or an error. */
function geo_update(): ?string
{
    if (!is_dir(GEO_DIR) && !@mkdir(GEO_DIR, 0755, true)) return 'cannot create ' . GEO_DIR;
    @set_time_limit(600);
    foreach ([date('Y-m'), date('Y-m', strtotime('first day of last month'))] as $month) {
        $src = @gzopen("https://download.db-ip.com/free/dbip-city-lite-$month.mmdb.gz", 'rb');
        if (!$src) continue;
        $tmp = GEO_DB . '.tmp';
        $dst = fopen($tmp, 'wb');
        while (!gzeof($src)) fwrite($dst, gzread($src, 1 << 20));
        gzclose($src);
        fclose($dst);
        if (filesize($tmp) < 10_000_000) { @unlink($tmp); return 'download incomplete'; }
        rename($tmp, GEO_DB);
        return null;
    }
    return 'download failed (allow_url_fopen off or no network?)';
}

/**
 * Housekeeping, at most once per hour, run after the redirect was sent:
 * delete scans older than RETENTION_MONTHS, refresh the GeoIP database monthly.
 */
function maintenance(): void
{
    $stamp = GEO_DIR . '/.maintenance';
    if (is_file($stamp) && filemtime($stamp) > time() - 3600) return;
    if (!is_dir(GEO_DIR)) @mkdir(GEO_DIR, 0755, true);
    @touch($stamp);
    q('DELETE FROM hits WHERE created_at < NOW() - INTERVAL ' . (RETENTION_MONTHS * 31) . ' DAY');
    q('DELETE FROM salts WHERE day < ?', [date('Y-m-d')]);
    q('DELETE FROM api_calls WHERE created_at < NOW() - INTERVAL 3 DAY');
    // uploaded mini-app files whose mini-app (or code, or user) was deleted
    $updir = __DIR__ . '/uploads';
    if (is_dir($updir)) {
        $live = array_flip(array_map('strval', q('SELECT id FROM steps')->fetchAll(PDO::FETCH_COLUMN)));
        foreach (glob($updir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (!isset($live[basename($dir)])) {
                foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
                @rmdir($dir);
            }
        }
    }
    if (!is_file(GEO_DB) || filemtime(GEO_DB) < time() - 35 * 86400) geo_update();
}

/** SQL: "Wien, AT" style place label for hits aliased h. */
const PLACE_SQL = "CASE WHEN h.city <> '' THEN CONCAT(h.city, ', ', h.country) WHEN h.country <> '' THEN h.country ELSE '— unknown —' END";
/** SQL: unique visitors, counted per day (visitor ids rotate daily). */
const UNIQUES_SQL = "COUNT(DISTINCT CASE WHEN h.visitor <> '' THEN CONCAT(DATE(h.created_at), h.visitor) END)";

function lang_name(string $code): string
{
    static $names = ['de' => 'German', 'en' => 'English', 'tr' => 'Turkish', 'sr' => 'Serbian', 'hr' => 'Croatian', 'bs' => 'Bosnian',
        'hu' => 'Hungarian', 'cs' => 'Czech', 'sk' => 'Slovak', 'pl' => 'Polish', 'ro' => 'Romanian', 'it' => 'Italian', 'fr' => 'French',
        'es' => 'Spanish', 'pt' => 'Portuguese', 'ru' => 'Russian', 'uk' => 'Ukrainian', 'ar' => 'Arabic', 'fa' => 'Persian', 'nl' => 'Dutch',
        'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'sl' => 'Slovenian', 'bg' => 'Bulgarian', 'sq' => 'Albanian'];
    return $code === '' ? t('— unknown —') : (isset($names[$code]) ? t($names[$code]) : $code);
}

/** Logs one scan of a link (device, coarse place, language, daily visitor hash; never the IP). */
function record_hit(int $linkId): void
{
    try {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        [$device, $os] = device_info($ua);
        $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
        [$country, $city] = $device === 'bot' ? ['', ''] : geo_lookup($ip);
        q('INSERT INTO hits (link_id, device, os, referer, visitor, country, city, lang, browser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $linkId, $device, $os, substr($ref, 0, 190),
            $device === 'bot' ? '' : visitor_id($ip, $ua),
            $country, $city, accept_language(), $device === 'bot' ? '' : browser_name($ua),
        ]);
    } catch (Throwable $ex) {
        error_log('qr tracking error: ' . $ex->getMessage());
    }
}
