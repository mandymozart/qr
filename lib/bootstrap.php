<?php
declare(strict_types=1);

/*
 * qr — shared bootstrap. Loaded by /index.php (redirects) and every /app/*.php page.
 * Plain PHP, no framework. Config lives in lib/config.php (generated on deploy).
 */

date_default_timezone_set('Europe/Vienna');

define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

function config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        $cfg = is_file($file) ? (require $file) : [];
    }
    $v = $cfg[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function base_url(): string
{
    $b = config('base_url');
    if ($b) return rtrim($b, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function short_url(string $code): string
{
    return base_url() . '/?c=' . rawurlencode($code);
}

/* ---------------------------------------------------------------- DB */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (is_sqlite()) {
        // Local dev only: SQLite with the handful of MySQL functions we use.
        $pdo = new PDO('sqlite:' . config('db_path', ROOT . '/lib/dev.sqlite'), null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'), 0);
        $pdo->sqliteCreateFunction('HOUR', fn($d) => (int) date('G', strtotime((string) $d)), 1);
        $pdo->sqliteCreateFunction('IF', fn($c, $a, $b) => $c ? $a : $b, 3);
        $pdo->sqliteCreateFunction('CONCAT', fn(...$a) => implode('', $a), -1);
        require_once __DIR__ . '/schema.php';
        migrate_sqlite($pdo);
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        config('db_host', 'localhost'), (int) config('db_port', 3306), config('db_name', 'qr'));
    $pdo = new PDO($dsn, config('db_user'), config('db_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    require_once __DIR__ . '/schema.php';
    migrate($pdo);
    return $pdo;
}

function is_sqlite(): bool
{
    return config('db_driver') === 'sqlite';
}

function q(string $sql, array $params = []): PDOStatement
{
    if (is_sqlite()) {
        // NOW() + INTERVAL 30 DAY  →  datetime(NOW(), '+30 day')
        $sql = preg_replace_callback('~(NOW\(\)|CURDATE\(\))\s*([+-])\s*INTERVAL\s+(\d+)\s+(DAY|MINUTE)~i',
            fn($m) => "datetime({$m[1]}, '{$m[2]}{$m[3]} " . strtolower($m[4]) . "')", $sql);
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ---------------------------------------------------------------- helpers */

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        setcookie('qr_flash', json_encode([$type, $msg]), ['path' => '/app/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
        return null;
    }
    if (empty($_COOKIE['qr_flash'])) return null;
    $f = json_decode($_COOKIE['qr_flash'], true);
    setcookie('qr_flash', '', ['expires' => 1, 'path' => '/app/']);
    return is_array($f) ? $f : null;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function random_code(int $len = 6): string
{
    $abc = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return $s;
}

function unique_code(): string
{
    do {
        $c = random_code();
    } while (q('SELECT 1 FROM links WHERE code = ?', [$c])->fetch());
    return $c;
}

function valid_url(string $u): bool
{
    return (bool) filter_var($u, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $u);
}

function fmt_date(?string $d, string $f = 'M j, Y'): string
{
    return $d ? date($f, strtotime($d)) : '—';
}

function ago(?string $d): string
{
    if (!$d) return 'never';
    $s = time() - strtotime($d);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 86400 * 30) return floor($s / 86400) . ' d ago';
    return fmt_date($d);
}

function post(string $k, string $default = ''): string
{
    return trim((string) ($_POST[$k] ?? $default));
}

function int_param(string $k): int
{
    return (int) ($_GET[$k] ?? $_POST[$k] ?? 0);
}

/* ---------------------------------------------------------------- auth */

const SESSION_DAYS = 30;

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    $tok = $_COOKIE['qr_s'] ?? '';
    if (strlen($tok) !== 64) return null;
    $row = q('SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = ? AND s.expires_at > NOW()', [hash('sha256', $tok)])->fetch();
    if ($row && $row['status'] === 'active') $user = $row;
    return $user;
}

function require_user(): array
{
    $u = current_user();
    if (!$u) redirect('/app/login.php');
    return $u;
}

function require_admin(): array
{
    $u = require_user();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Admins only.');
    }
    return $u;
}

function start_session(int $userId): void
{
    $tok = bin2hex(random_bytes(32));
    q('INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, NOW() + INTERVAL ' . SESSION_DAYS . ' DAY)',
        [hash('sha256', $tok), $userId]);
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    q('DELETE FROM sessions WHERE expires_at < NOW()');
    setcookie('qr_s', $tok, [
        'expires' => time() + SESSION_DAYS * 86400, 'path' => '/',
        'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
}

function end_session(): void
{
    if (!empty($_COOKIE['qr_s'])) q('DELETE FROM sessions WHERE token_hash = ?', [hash('sha256', $_COOKIE['qr_s'])]);
    setcookie('qr_s', '', ['expires' => 1, 'path' => '/']);
}

function csrf_token(): string
{
    return hash_hmac('sha256', 'csrf', $_COOKIE['qr_s'] ?? 'anon');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Bad request (form expired, go back and reload).');
    }
}

/** Create a one-time login link for a user. Returns the URL. */
function issue_login_link(int $userId, int $minutes = 30): string
{
    $tok = bin2hex(random_bytes(24));
    q('UPDATE users SET token_hash = ?, token_expires_at = NOW() + INTERVAL ' . (int) $minutes . ' MINUTE, token_created_at = NOW() WHERE id = ?',
        [hash('sha256', $tok), $userId]);
    return base_url() . '/app/auth.php?t=' . $tok;
}

/* ---------------------------------------------------------------- data access */

function find_link(int $id, array $user): ?array
{
    $r = q('SELECT l.*, c.name AS campaign_name FROM links l LEFT JOIN campaigns c ON c.id = l.campaign_id
            WHERE l.id = ? AND l.user_id = ?', [$id, $user['id']])->fetch();
    return $r ?: null;
}

function find_campaign(int $id, array $user): ?array
{
    $r = q('SELECT * FROM campaigns WHERE id = ? AND user_id = ?', [$id, $user['id']])->fetch();
    return $r ?: null;
}

function user_campaigns(array $user): array
{
    return q('SELECT id, name FROM campaigns WHERE user_id = ? ORDER BY name', [$user['id']])->fetchAll();
}

/** Daily scan counts for the last $days days, zero-filled. $where restricts hits h / links l. */
function daily_counts(string $where, array $params, int $days = 30): array
{
    $rows = q("SELECT DATE(h.created_at) d, COUNT(*) n FROM hits h JOIN links l ON l.id = h.link_id
               WHERE $where AND h.device <> 'bot' AND h.created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
               GROUP BY d", $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $out[$d] = (int) ($rows[$d] ?? 0);
    }
    return $out;
}
