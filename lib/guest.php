<?php
declare(strict_types=1);

/*
 * Guest tier: codes without an account.
 * - A browser cookie (qr_g, set only when a guest creates a code) lists "your codes in this browser".
 * - Each code also has a secret edit key (shown as an edit link) that works from any browser.
 * - Signing in / registering moves the browser's guest codes into the account (claim_guest_codes).
 */

const GUEST_MAX_CODES = 10;      // per browser
const GUEST_MAX_PER_HOUR = 5;    // per visitor (daily-salted IP hash), across browsers

function guest_token(bool $create = false): ?string
{
    $t = $_COOKIE['qr_g'] ?? '';
    if (preg_match('~^[a-f0-9]{64}$~', $t)) return $t;
    if (!$create) return null;
    $t = bin2hex(random_bytes(32));
    setcookie('qr_g', $t, ['expires' => time() + 365 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    $_COOKIE['qr_g'] = $t;
    return $t;
}

function guest_hash(): ?string
{
    $t = guest_token();
    return $t ? hash('sha256', $t) : null;
}

/** Codes created in this browser (not yet moved into an account). */
function guest_links(): array
{
    $h = guest_hash();
    if (!$h) return [];
    return q("SELECT l.*, (SELECT COUNT(*) FROM hits h WHERE h.link_id = l.id AND h.device <> 'bot') scans
              FROM links l WHERE l.guest_hash = ? AND l.user_id IS NULL ORDER BY l.id DESC", [$h])->fetchAll();
}

/** A guest link the current visitor may edit: via edit key (?k=) or via this browser's token (?id=). */
function guest_link_access(): ?array
{
    $k = (string) ($_GET['k'] ?? $_POST['k'] ?? '');
    if ($k !== '') {
        $l = q('SELECT * FROM links WHERE edit_key_hash = ?', [hash('sha256', $k)])->fetch();
        return $l ?: null;
    }
    $h = guest_hash();
    $id = int_param('id');
    if (!$h || !$id) return null;
    $l = q('SELECT * FROM links WHERE id = ? AND guest_hash = ? AND user_id IS NULL', [$id, $h])->fetch();
    return $l ?: null;
}

/** Creates a fresh edit key for a link and returns the edit URL (the key is stored hashed). */
function new_edit_link(int $linkId): string
{
    $k = bin2hex(random_bytes(16));
    q('UPDATE links SET edit_key_hash = ? WHERE id = ?', [hash('sha256', $k), $linkId]);
    return base_url() . '/manage.php?k=' . $k;
}

/** Move this browser's guest codes into the user's account. Returns how many. */
function claim_guest_codes(int $userId): int
{
    $h = guest_hash();
    if (!$h) return 0;
    $n = q('UPDATE links SET user_id = ?, guest_hash = NULL, edit_key_hash = NULL WHERE guest_hash = ? AND user_id IS NULL', [$userId, $h])->rowCount();
    setcookie('qr_g', '', ['expires' => 1, 'path' => '/']);
    return $n;
}

/** Returns an error message, or null if this guest may create another code. */
function guest_limit_error(string $creator): ?string
{
    $h = guest_hash();
    if ($h && (int) q('SELECT COUNT(*) FROM links WHERE guest_hash = ? AND user_id IS NULL', [$h])->fetchColumn() >= GUEST_MAX_CODES) {
        return 'You have ' . GUEST_MAX_CODES . ' codes in this browser. Create a free account to make more.';
    }
    if ($creator !== '' && (int) q('SELECT COUNT(*) FROM links WHERE creator = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$creator])->fetchColumn() >= GUEST_MAX_PER_HOUR) {
        return 'That was a lot of codes in a short time. Please try again in an hour, or create a free account.';
    }
    return null;
}

/** Simple public page shell (landing, manage, not found). */
function public_head(string $title): void
{
    $user = current_user();
    $f = flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="Free QR codes you can change later, with scan stats. No tracking cookies.">
<link rel="stylesheet" href="/app/assets/app.css?v=7">
</head>
<body class="public">
<header class="topbar">
  <a class="logo" href="/">qr</a>
  <div class="actions">
    <?php if ($user): ?>
      <a class="button small" href="/app/">My codes</a>
    <?php else: ?>
      <a class="nav-button" href="/app/login.php">Sign in</a>
      <a class="button small" href="/app/register.php">Create account</a>
    <?php endif; ?>
  </div>
</header>
<main class="public-main">
<?php if ($f): ?><div class="notice <?= $f[0] === 'err' ? 'err' : ($f[0] === 'info' ? 'info' : 'ok') ?>"><?= e($f[1]) ?></div><?php endif;
}

function public_foot(): void
{
    ?>
</main>
<footer class="public-foot">
  <span>No tracking cookies. Scans are counted anonymously.</span>
</footer>
<script src="/app/assets/app.js?v=3"></script>
</body>
</html>
<?php
}
