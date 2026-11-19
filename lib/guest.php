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
        return t('You have {n} codes in this browser. Create a free account to make more.', ['n' => GUEST_MAX_CODES]);
    }
    if ($creator !== '' && (int) q('SELECT COUNT(*) FROM links WHERE creator = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$creator])->fetchColumn() >= GUEST_MAX_PER_HOUR) {
        return t('That was a lot of codes in a short time. Please try again in an hour, or create a free account.');
    }
    return null;
}

/** Public page shell (landing, guest edit, not found) in the street/pop-art style. */
function public_head(string $title, string $mainClass = 'narrow'): void
{
    $user = current_user();
    $f = flash();
    ?><!doctype html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= te('Free QR codes you can change later, with scan stats. No tracking cookies.') ?>">
<link rel="stylesheet" href="/app/assets/app.css?v=16">
<link rel="stylesheet" href="/app/assets/street.css?v=17">
</head>
<body class="public street">
<?php street_defs(); ?>
<header class="st-header">
  <a class="st-logo" href="/">q<span>r</span></a>
  <nav class="st-nav">
    <?= lang_switch('lang-switch st') ?>
    <?php if ($user): ?>
      <a class="btn y" href="/app/"><?= te('My codes') ?></a>
    <?php else: ?>
      <a class="plain" href="/app/login.php"><?= te('Sign in') ?></a>
      <a class="btn y" href="/app/register.php"><?= te('Create account') ?></a>
    <?php endif; ?>
  </nav>
</header>
<main class="public-main <?= e($mainClass) ?>">
<?php if ($f): ?><div class="notice <?= $f[0] === 'err' ? 'err' : ($f[0] === 'info' ? 'info' : 'ok') ?>"><?= e($f[1]) ?></div><?php endif;
}

function public_foot(): void
{
    ?>
</main>
<footer class="st-foot"><?= te('No tracking cookies. Scans are counted anonymously.') ?><br><small><?= t('Emoji graphics: {link} (CC-BY 4.0)', ['link' => '<a href="https://github.com/jdecked/twemoji" rel="noopener">Twemoji</a>']) ?></small></footer>
<script src="/app/assets/app.js?v=10"></script>
</body>
</html>
<?php
}

/** Reusable SVG drawings: original bold-outline figures (black outline under a colored stroke, color = currentColor). */
function street_defs(): void
{
    $limbs = [
        'jump' => ['M100 82 L100 150 M100 96 L62 58 L44 26 M100 96 L140 62 L160 30 M100 150 L70 190 L52 230 M100 150 L132 188 L152 226', 100, 44],
        'hold' => ['M100 82 L104 152 M102 98 L140 112 L170 96 M102 98 L64 120 L40 100 M104 152 L80 196 L84 236 M104 152 L130 194 L124 234', 98, 44],
        'run'  => ['M108 84 L94 150 M104 100 L146 86 L168 104 M104 100 L66 118 L46 98 M94 150 L136 176 L150 220 M94 150 L66 186 L32 196', 112, 46],
    ];
    echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
    foreach ($limbs as $id => [$d, $hx, $hy]) {
        echo "<g id=\"pose-$id\"><path d=\"$d\" stroke=\"#111\" stroke-width=\"36\" stroke-linecap=\"round\" stroke-linejoin=\"round\" fill=\"none\"/>"
           . "<circle cx=\"$hx\" cy=\"$hy\" r=\"27\" fill=\"#111\"/>"
           . "<path d=\"$d\" stroke=\"currentColor\" stroke-width=\"22\" stroke-linecap=\"round\" stroke-linejoin=\"round\" fill=\"none\"/>"
           . "<circle cx=\"$hx\" cy=\"$hy\" r=\"20\" fill=\"currentColor\"/></g>";
    }
    echo '<g id="burst" stroke="#111" stroke-width="7" stroke-linecap="round" fill="none"><path d="M0 -40 L0 -58 M28 -28 L41 -41 M40 0 L58 0 M28 28 L41 41 M-28 -28 L-41 -41 M-40 0 L-58 0 M-28 28 L-41 41"/></g>';
    echo '<g id="chalk-fig" stroke="#f5f5f0" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none"><path d="' . $limbs['jump'][0] . '"/><circle cx="100" cy="44" r="22"/></g>';
    echo '</defs></svg>';
}
