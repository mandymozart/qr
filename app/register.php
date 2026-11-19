<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * Self sign-up: email (+ name) → secret link by mail. Clicking it signs you in,
 * and any codes made in this browser without an account move into the account.
 */
if (current_user()) redirect('/app/');

$sent = false;
$error = null;
$email = strtolower(post('email'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('website') !== '') {
        $sent = true; // honeypot: pretend it worked
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('Please enter a valid email address.');
    } else {
        $u = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        if (!$u) {
            q("INSERT INTO users (email, name, role) VALUES (?, ?, 'user')", [$email, mb_substr(post('name'), 0, 120)]);
            $u = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        }
        // existing accounts just get a sign-in link; max one mail per minute; blocked accounts get nothing
        if ($u['status'] === 'active' && (!$u['token_created_at'] || strtotime($u['token_created_at']) < time() - 60)) {
            send_login_mail($u, issue_login_link((int) $u['id'], 60 * 24), !$u['last_login_at'], '24 hours');
        }
        $sent = true;
    }
}

$guestCount = count(guest_links());
page_head(t('Create account'));
?>
<a class="st-logo" href="/">q<span>r</span></a>
<?php if ($sent): ?>
  <div class="notice info"><?= t('Check your inbox: we sent a sign-in link to {email}. It\'s valid for 24 hours.', ['email' => '<strong>' . e($email) . '</strong>']) ?></div>
  <p class="entity-description"><?= t('No mail? Look in your spam folder, or {link} in a minute.', ['link' => '<a href="/app/register.php">' . te('try again') . '</a>']) ?></p>
<?php else: ?>
  <p class="entity-description"><?= te('Free. We\'ll mail you a secret link to sign in, no password needed (you can set one later).') ?></p>
  <?php if ($guestCount): ?><div class="notice ok"><?= $guestCount === 1 ? te('The code you made in this browser will move into your account.') : te('The {n} codes you made in this browser will move into your account.', ['n' => $guestCount]) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <div class="field"><label for="email"><?= te('Email') ?></label>
      <input type="email" id="email" name="email" required autocomplete="email" autofocus value="<?= e($email) ?>"></div>
    <div class="field"><label for="name"><?= te('Name') ?> <span style="font-weight:400;color:var(--neutral-20)"><?= te('(optional)') ?></span></label>
      <input id="name" name="name" autocomplete="name" value="<?= e(post('name')) ?>"></div>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
    <button class="button"><?= te('Create account') ?></button>
  </form>
  <p class="field-hint" style="margin-top:1.5rem"><?= t('Already have an account? {link}', ['link' => '<a href="/app/login.php">' . te('Sign in') . '</a>']) ?></p>
<?php endif; ?>
<?php page_foot();
