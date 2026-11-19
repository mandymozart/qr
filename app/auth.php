<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * Secret-link landing. GET shows a confirm button (so mail scanners that
 * prefetch links don't burn the one-time token); POST consumes it.
 */
$tok = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$user = $tok !== '' ? q("SELECT * FROM users WHERE token_hash = ? AND token_expires_at > NOW()", [hash('sha256', $tok)])->fetch() : null;

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['status'] !== 'active') {
        $user = null;
    } else {
        q('UPDATE users SET token_hash = NULL, token_expires_at = NULL WHERE id = ?', [$user['id']]);
        start_session((int) $user['id']);
        redirect(($_POST['next'] ?? '') === 'account' ? '/app/account.php?next=reset#password' : '/app/');
    }
}

page_head(t('Sign in'));
?>
<a class="st-logo" href="/">q<span>r</span></a>
<?php if ($user): ?>
  <p class="entity-description"><?= $user['name']
    ? t('Welcome, {name}. You\'re signing in as {email}.', ['name' => e($user['name']), 'email' => '<strong>' . e($user['email']) . '</strong>'])
    : t('Welcome. You\'re signing in as {email}.', ['email' => '<strong>' . e($user['email']) . '</strong>']) ?></p>
  <form method="post">
    <input type="hidden" name="t" value="<?= e($tok) ?>">
    <input type="hidden" name="next" value="<?= e($_GET['next'] ?? '') ?>">
    <button class="button"><?= te('Continue to qr →') ?></button>
  </form>
<?php else: ?>
  <div class="notice err"><?= te('This link is invalid, already used or expired.') ?></div>
  <a class="button" href="/app/login.php"><?= te('Request a new link') ?></a>
<?php endif; ?>
<?php page_foot();
