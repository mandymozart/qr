<?php
declare(strict_types=1);

/*
 * https://qr.viennastruggle.at/
 *   ?c=CODE  → log the scan (no cookies, no IP stored) and 302 to the target
 *   no ?c=   → landing page: create a QR code without an account
 *   unknown/paused code → friendly "not found" with a call to action
 */
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/qr.php';
require_once __DIR__ . '/lib/track.php';

$code = (string) ($_GET['c'] ?? '');

if ($code !== '') {
    try {
        $link = q('SELECT l.id, l.code, l.target_url, l.utm, c.name AS campaign FROM links l
                   LEFT JOIN campaigns c ON c.id = l.campaign_id WHERE l.code = ? AND l.active = 1', [$code])->fetch();
    } catch (Throwable $ex) {
        error_log('qr redirect error: ' . $ex->getMessage());
        $link = null;
    }

    if ($link) {
        // Send the visitor on their way first, record afterwards.
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('Location: ' . ($link['utm'] ? with_utm($link['target_url'], $link['code'], $link['campaign']) : $link['target_url']), true, 302);
        header('Content-Length: 0');
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

        try {
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            [$device, $os] = device_info($ua);
            $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
            [$country, $city] = $device === 'bot' ? ['', ''] : geo_lookup($ip);
            q('INSERT INTO hits (link_id, device, os, referer, visitor, country, city, lang, browser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $link['id'], $device, $os, substr($ref, 0, 190),
                $device === 'bot' ? '' : visitor_id($ip, $ua),
                $country, $city, accept_language(), $device === 'bot' ? '' : browser_name($ua),
            ]);
            unset($ip);
            if (function_exists('fastcgi_finish_request')) maintenance();
        } catch (Throwable $ex) {
            error_log('qr tracking error: ' . $ex->getMessage());
        }
        exit;
    }

    // Unknown or paused code
    require_once __DIR__ . '/lib/app.php';
    http_response_code(404);
    public_head('Code not found · qr');
    ?>
    <section class="hero">
      <span class="entity-type">Hmm.</span>
      <h1>This QR code doesn't lead anywhere (anymore).</h1>
      <p class="entity-description">The code <span class="url"><?= e($code) ?></span> doesn't exist or was paused by its owner.
        If you scanned it from a poster or flyer, the organisers may have ended that campaign.</p>
      <p><a class="button" href="/">Make your own QR code →</a></p>
      <p class="field-hint">Free, takes 10 seconds, and you can change where it points to any time.</p>
    </section>
    <?php
    public_foot();
    exit;
}

/* ---------------------------------------------------------------- landing */

require_once __DIR__ . '/lib/app.php';
$error = null;
$url = 'https://';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = post('target_url');
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (post('website') !== '') {
        $error = 'Something went wrong. Please try again.'; // honeypot filled → bot
    } elseif (!valid_url($url)) {
        $error = 'Please paste a full web address, starting with https://';
    } elseif ($host === strtolower((string) parse_url(base_url(), PHP_URL_HOST))) {
        $error = "That's already a qr link. Paste the address it should lead to.";
    } else {
        $creator = visitor_id((string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $error = guest_limit_error($creator);
        if (!$error) {
            $token = guest_token(true);
            $title = post('title') !== '' ? mb_substr(post('title'), 0, 160) : preg_replace('~^www\.~', '', $host);
            q('INSERT INTO links (user_id, code, title, target_url, guest_hash, creator) VALUES (NULL, ?, ?, ?, ?, ?)',
                [unique_code(), $title, $url, hash('sha256', $token), $creator]);
            $edit = new_edit_link((int) db()->lastInsertId());
            redirect($edit . '&new=1');
        }
    }
}

$mine = current_user() ? [] : guest_links();
public_head('qr · QR codes you can change later');
?>
<section class="hero">
  <h1>QR codes you can <span class="hl">change later.</span></h1>
  <p class="entity-description">Paste a link, get a QR code. Change where it leads any time, and see how often it's scanned. Free, no sign-up.</p>

  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="quick-create">
    <input type="url" name="target_url" value="<?= e($url) ?>" required aria-label="Web address the QR code should open" placeholder="https://…" autofocus>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
    <button class="button">Create QR code</button>
  </form>
  <?php if (current_user()): ?>
    <p class="field-hint">You're signed in. <a href="/app/edit.php">Create it in your account</a> to use campaigns and full stats.</p>
  <?php endif; ?>
</section>

<?php if ($mine): ?>
<section class="block">
  <h3>Your codes in this browser</h3>
  <p class="hint">Kept in this browser. Use each code's edit link from another device, or <a href="/app/register.php">create an account</a> to keep them for good.</p>
  <div class="list">
  <?php foreach ($mine as $l): ?>
    <a class="row" href="/manage.php?id=<?= $l['id'] ?>">
      <img class="qr-thumb" src="/app/qr.php?id=<?= $l['id'] ?>&amp;v=<?= e($l['code']) ?>" alt="" loading="lazy">
      <div class="main"><h4><?= e($l['title']) ?><?= $l['active'] ? '' : ' <span class="badge off">paused</span>' ?></h4>
        <div class="meta"><span class="url">?c=<?= e($l['code']) ?></span><span class="target">→ <?= e($l['target_url']) ?></span></div></div>
      <div class="stat"><strong><?= (int) $l['scans'] ?></strong><span>scans</span></div>
    </a>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="block">
  <span class="entity-type">How it works</span>
  <div class="story-strip" aria-label="How it works, in 4 slides">
    <?php foreach ([
        1 => 'QR codes you can change later. Printed means final? Not anymore.',
        2 => 'A QR code in 10 seconds: 1. paste a link, 2. get your QR code as PNG or SVG, 3. change the target any time, the printed code stays the same. No sign-up, free.',
        3 => 'See who scans, no tracking cookies: scans, visitors, last 30 days, top city, mobile share. No IP addresses stored.',
        4 => 'Make your own QR code at qr.viennastruggle.at. Free, no sign-up, with stats.',
    ] as $n => $alt): ?>
      <img src="/app/assets/story/story-<?= $n ?>.webp" width="540" height="960" alt="<?= e($alt) ?>" decoding="async">
    <?php endforeach; ?>
  </div>
</section>

<section class="block tiers">
  <div>
    <h3>Without an account</h3>
    <ul>
      <li>QR code as PNG and SVG</li>
      <li>Change the target any time with your edit link</li>
      <li>Scan count for the last 30 days</li>
      <li>Up to <?= GUEST_MAX_CODES ?> codes per browser</li>
    </ul>
  </div>
  <div>
    <h3>With a free account</h3>
    <ul>
      <li>Unlimited codes, kept safely in your account</li>
      <li>Campaigns and bulk codes for print runs</li>
      <li>Stats by place, city, time, device and language</li>
      <li>Codes from this browser move in automatically</li>
    </ul>
    <p><a class="button" href="/app/register.php">Create a free account</a></p>
  </div>
</section>
<?php public_foot();
