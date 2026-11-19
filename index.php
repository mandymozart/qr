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
        // A mini-app (or a code without a target) shows our hosted step page first.
        $hasStep = false;
        try {
            $hasStep = (bool) q('SELECT 1 FROM steps WHERE link_id = ? AND active = 1', [$link['id']])->fetch();
        } catch (Throwable) {
        }
        if ($hasStep || trim((string) $link['target_url']) === '') {
            record_hit((int) $link['id']);
            header('Cache-Control: no-store');
            header('Location: /step.php?c=' . rawurlencode($link['code']), true, 302);
            exit;
        }

        // Send the visitor on their way first, record afterwards.
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('Location: ' . ($link['utm'] ? with_utm($link['target_url'], $link['code'], $link['campaign']) : $link['target_url']), true, 302);
        header('Content-Length: 0');
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        record_hit((int) $link['id']);
        if (function_exists('fastcgi_finish_request')) {
            try { maintenance(); } catch (Throwable $ex) { error_log('qr maintenance error: ' . $ex->getMessage()); }
        }
        exit;
    }

    // Unknown or paused code
    require_once __DIR__ . '/lib/app.php';
    http_response_code(404);
    public_head(t('Code not found · qr'));
    ?>
    <section class="hero">
      <svg viewBox="0 0 260 260" width="180" height="180" aria-hidden="true" style="overflow:visible;display:block;margin-bottom:8px">
        <g transform="translate(20 10)" style="color:var(--blue)"><use href="#pose-hold"/></g>
        <text x="200" y="70" font-family="Permanent Marker, cursive" font-size="80" fill="var(--red)" stroke="#111" stroke-width="3">?</text>
      </svg>
      <span class="entity-type"><?= te('Hmm.') ?></span>
      <h1><?= te('This QR code doesn\'t lead anywhere (anymore).') ?></h1>
      <p class="entity-description"><?= t('The code {code} doesn\'t exist or was paused by its owner.', ['code' => '<span class="url">' . e($code) . '</span>']) ?>
        <?= te('If you scanned it from a poster or flyer, the organisers may have ended that campaign.') ?></p>
      <p><a class="button" href="/"><?= te('Make your own QR code →') ?></a></p>
      <p class="field-hint"><?= te('Free, takes 10 seconds, and you can change where it points to any time.') ?></p>
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
        $error = t('Something went wrong. Please try again.'); // honeypot filled → bot
    } elseif (!valid_url($url)) {
        $error = t('Please paste a full web address, starting with https://');
    } elseif ($host === strtolower((string) parse_url(base_url(), PHP_URL_HOST))) {
        $error = t('That\'s already a qr link. Paste the address it should lead to.');
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
public_head(t('qr · QR codes you can change later'), 'wide');
?>
<div class="st-hero">
  <div>
    <h1><?= t('QR codes you can {change} {later}', ['change' => '<span class="w r">' . te('change') . '</span>', 'later' => '<span class="w y">' . te('later!') . '</span>']) ?></h1>
    <p class="lead"><?= te('Print it once. Point it anywhere, any time. See who scans. Free, no sign-up.') ?></p>

    <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="create">
      <input type="url" name="target_url" value="<?= e($url) ?>" required aria-label="<?= te('Link the QR code should open') ?>" placeholder="<?= te('https://your-show.at') ?>">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
      <button class="btn r"><?= te('Make my QR!') ?></button>
    </form>
    <?php if (current_user()): ?>
      <p class="hint"><?= t('You\'re signed in. {link} for campaigns and full stats.', ['link' => '<a href="/app/edit.php">' . te('Make it in your account') . '</a>']) ?></p>
    <?php else: ?>
      <p class="hint"><?= te('For posters, flyers, stickers, gallery walls & merch tables.') ?></p>
    <?php endif; ?>
  </div>

  <div class="stage">
    <svg viewBox="0 0 600 600" role="img" aria-label="<?= te('Dancing figures around a QR code') ?>">
      <rect x="170" y="140" width="260" height="260" rx="22" fill="#fff" stroke="#111" stroke-width="10"/>
      <image href="/app/assets/qr-landing.svg" x="190" y="160" width="220" height="220"/>
      <use href="#burst" x="300" y="100" class="pulse"/>
      <use href="#burst" x="530" y="270" class="pulse" style="animation-delay:-.6s"/>
      <use href="#burst" x="80" y="480" class="pulse" style="animation-delay:-.3s"/>
      <g transform="translate(20 150) scale(.95)"><g class="wiggle" style="color:var(--red)"><use href="#pose-jump"/></g></g>
      <g transform="translate(400 320) scale(.95)"><g class="wiggle d2" style="color:var(--blue)"><use href="#pose-run"/></g></g>
      <g transform="translate(205 385) scale(.8)"><g class="wiggle d3" style="color:var(--yellow)"><use href="#pose-hold"/></g></g>
      <path d="M470 120 l14 -14 a10 10 0 0 1 14 14 l-14 14 -14 -14 a10 10 0 0 1 14 -14z" fill="var(--pink)" stroke="#111" stroke-width="6"/>
      <circle cx="120" cy="110" r="16" fill="var(--green)" stroke="#111" stroke-width="6"/>
    </svg>
  </div>
</div>

<?php if ($mine): ?>
<section class="st-sec">
  <span class="tag"><?= te('Yours') ?></span>
  <h2><?= te('Your codes in this browser') ?></h2>
  <p class="hint" style="margin-top:-12px"><?= t('Use each code\'s edit link on other devices, or {link} to keep them for good.', ['link' => '<a href="/app/register.php">' . te('create an account') . '</a>']) ?></p>
  <div class="list">
  <?php foreach ($mine as $l): ?>
    <a class="row" href="/manage.php?id=<?= $l['id'] ?>">
      <img class="qr-thumb" src="/app/qr.php?id=<?= $l['id'] ?>&amp;v=<?= e(qr_version($l)) ?>" alt="">
      <div class="main"><h4><?= e($l['title']) ?><?= $l['active'] ? '' : ' <span class="badge off">' . te('paused') . '</span>' ?></h4>
        <div class="meta"><span class="url">?c=<?= e($l['code']) ?></span><span class="target">→ <?= e($l['target_url']) ?></span></div></div>
      <div class="stat"><strong><?= (int) $l['scans'] ?></strong><span><?= te('scans') ?></span></div>
    </a>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="st-sec">
  <span class="tag"><?= te('How it works') ?></span>
  <h2><?= te('A QR code in 10 seconds.') ?></h2>
  <div class="st-steps">
    <div class="panel">
      <div class="num">1</div>
      <svg viewBox="0 0 220 250" aria-hidden="true"><g transform="translate(10 0)" style="color:#fff"><use href="#pose-run"/></g></svg>
      <h3><?= te('Paste a link') ?></h3>
      <p><?= te('Your show, your shop, your Insta, your ticket page.') ?></p>
    </div>
    <div class="panel">
      <div class="num">2</div>
      <svg viewBox="0 0 220 250" aria-hidden="true"><g transform="translate(10 0)" style="color:var(--pink)"><use href="#pose-hold"/></g>
        <rect x="150" y="70" width="58" height="58" rx="8" fill="#fff" stroke="#111" stroke-width="7"/>
        <path d="M162 82h12v12h-12zM186 82h12v12h-12zM162 106h12v12h-12zM186 106h6v6h-6z" fill="#111"/></svg>
      <h3><?= te('Get your QR') ?></h3>
      <p><?= te('PNG or SVG. Sharp on a sticker, sharp on a billboard.') ?></p>
    </div>
    <div class="panel">
      <div class="num">3</div>
      <svg viewBox="0 0 220 250" aria-hidden="true"><g transform="translate(10 0)" style="color:var(--yellow)"><use href="#pose-jump"/></g><use href="#burst" x="190" y="60"/></svg>
      <h3><?= te('Change it any time') ?></h3>
      <p><?= te('Show\'s over? Point the same printed code to the next one.') ?></p>
    </div>
  </div>
</section>

<div class="chalk">
  <div>
    <span class="tag"><?= te('Stats included') ?></span>
    <h2><?= t('See who scans.{br}Not who they are.', ['br' => '<br>']) ?></h2>
    <ul>
      <li class="yes"><?= te('Scans per day, unique visitors') ?></li>
      <li class="yes"><?= te('City, device, language, time of day') ?></li>
      <li><?= te('No tracking cookies') ?></li>
      <li><?= te('No stored IP addresses') ?></li>
    </ul>
  </div>
  <svg viewBox="0 0 420 320" aria-hidden="true">
    <g stroke="#f5f5f0" stroke-width="6" stroke-linecap="round" fill="none">
      <path d="M20 290 H400"/>
      <path d="M50 290 V230 M90 290 V200 M130 290 V215 M170 290 V170 M210 290 V185 M250 290 V140 M290 290 V120"/>
    </g>
    <g transform="translate(250 30) scale(.95)"><use href="#chalk-fig"/></g>
    <g stroke="var(--pink)" stroke-width="6" stroke-linecap="round"><path d="M230 70 l-18 -10 M236 50 l-20 -2 M240 92 l-18 6"/></g>
  </svg>
</div>

<section class="st-sec">
  <span class="tag"><?= te('Good for') ?></span>
  <div class="goodfor">
    <span><?= te('Exhibition posters') ?></span><span><?= te('Gallery walls') ?></span><span><?= te('Flyers') ?></span><span><?= te('Stickers') ?></span><span><?= te('Merch') ?></span>
    <span><?= te('Zines') ?></span><span><?= te('Tickets') ?></span><span><?= te('Price tags') ?></span><span><?= te('Studio doors') ?></span><span><?= te('Tote bags') ?></span>
  </div>
</section>

<section class="st-sec">
  <span class="tag"><?= te('Free') ?></span>
  <h2><?= te('Start now. Keep more later.') ?></h2>
  <div class="st-tiers">
    <div class="panel">
      <h3><?= te('Without an account') ?></h3>
      <ul>
        <li><?= te('QR code as PNG and SVG') ?></li>
        <li><?= te('Change the target any time with your edit link') ?></li>
        <li><?= te('Scan count for the last 30 days') ?></li>
        <li><?= te('Up to {n} codes per browser', ['n' => GUEST_MAX_CODES]) ?></li>
      </ul>
    </div>
    <div class="panel">
      <h3><?= te('With a free account') ?></h3>
      <ul>
        <li><?= te('Unlimited codes, kept safe in your account') ?></li>
        <li><?= te('Campaigns & bulk codes for print runs') ?></li>
        <li><?= te('Mini-apps: flyer pages, sign-ups, polls before the target') ?></li>
        <li><?= te('Stats by place, city, time, device & language') ?></li>
        <li><?= te('Codes from this browser move in automatically') ?></li>
      </ul>
      <a class="btn y" href="/app/register.php"><?= te('Create a free account') ?></a>
    </div>
  </div>
</section>

<section class="st-sec vienna">
  <div class="vienna-box">
    <div>
      <span class="tag"><?= te('Made in Vienna') ?></span>
      <h2><?= te('Made in Vienna. For the scene.') ?></h2>
      <p class="lead"><?= te('qr is built in Vienna for artists, bands, galleries, off-spaces, collectives and everyone who puts posters on walls.') ?></p>
      <div class="goodfor">
        <span><?= te('Built in Vienna') ?></span><span><?= te('Free for the scene') ?></span><span><?= te('Hosted in the EU') ?></span><span><?= te('No ads') ?></span>
      </div>
    </div>
    <svg class="wheel" viewBox="0 0 320 340" aria-hidden="true">
      <!-- an original Ferris-wheel doodle, Vienna-style: only the wheel turns, the stand stays -->
      <g fill="none" stroke="#170a2c" stroke-width="9" stroke-linecap="round"><path d="M160 150l-70 170M160 150l70 170M70 320h180"/></g>
      <g class="spin">
        <g fill="none" stroke="#170a2c" stroke-width="9" stroke-linecap="round">
          <circle cx="160" cy="150" r="118"/>
          <circle cx="160" cy="150" r="96" stroke-width="5"/>
          <path d="M160 32v236M42 150h236M77 67l166 166M243 67L77 233"/>
        </g>
        <g stroke="#170a2c" stroke-width="6">
          <rect x="146" y="14" width="28" height="22" rx="6" fill="var(--red)"/>
          <rect x="263" y="139" width="28" height="22" rx="6" fill="var(--green)"/>
          <rect x="146" y="264" width="28" height="22" rx="6" fill="var(--blue)"/>
          <rect x="29" y="139" width="28" height="22" rx="6" fill="var(--yellow)"/>
          <rect x="229" y="55" width="28" height="22" rx="6" fill="var(--pink)"/>
          <rect x="63" y="55" width="28" height="22" rx="6" fill="var(--orange)"/>
          <rect x="229" y="222" width="28" height="22" rx="6" fill="var(--yellow)"/>
          <rect x="63" y="222" width="28" height="22" rx="6" fill="var(--green)"/>
        </g>
      </g>
      <circle cx="160" cy="150" r="16" fill="var(--yellow)" stroke="#170a2c" stroke-width="7"/>
    </svg>
  </div>
</section>

<section class="st-cta">
  <h2><?= te('Make your own QR code.') ?></h2>
  <a class="btn y" href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});document.querySelector('.create input').focus({preventScroll:true});return false"><?= te('Let\'s go!') ?></a>
  <p class="hint"><?= te('Free · No sign-up · With stats') ?></p>
</section>
<?php public_foot();
