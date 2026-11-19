<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    switch (post('action')) {
        case 'profile':
            q('UPDATE users SET name = ? WHERE id = ?', [post('name'), $user['id']]);
            flash(t('Saved.'));
            break;
        case 'password':
        case 'nopassword':
            if (!empty($user['impersonator_id']) && in_array(post('action'), ['password', 'nopassword'], true)) {
                flash(t('You can\'t change someone else\'s password. Send them a sign-in link instead.'), 'err');
                break;
            }
            if (post('action') === 'nopassword') {
                q('UPDATE users SET password_hash = NULL WHERE id = ?', [$user['id']]);
                flash(t('Password removed. Sign in with a link from now on.'));
                break;
            }
            $pw = (string) ($_POST['password'] ?? '');
            if (strlen($pw) < 10) { flash(t('Use at least 10 characters.'), 'err'); break; }
            if ($pw !== ($_POST['password2'] ?? '')) { flash(t('The passwords don\'t match.'), 'err'); break; }
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
            // sign out other devices
            q('DELETE FROM sessions WHERE user_id = ? AND token_hash <> ?', [$user['id'], hash('sha256', $_COOKIE['qr_s'] ?? '')]);
            flash(t('Password set. You can now sign in with it (other devices were signed out).'));
            break;
    }
    redirect('/app/account.php');
}

$isMaster = strcasecmp($user['email'], (string) config('admin_user')) === 0;
page_head(t('Account'));
?>
<div style="max-width:36rem">
<span class="entity-type"><?= te('Account') ?></span>
<h2 class="entity-title"><?= e($user['name'] ?: $user['email']) ?></h2>
<p class="entity-description"><?= e($user['email']) ?> · <?= $user['role'] === 'admin' ? ($isMaster ? te('master admin') : te('admin')) : te('user') ?></p>

<?php if (isset($_GET['next'])): ?><div class="notice info"><?= te('You\'re signed in. Set a new password below.') ?></div><?php endif; ?>

<section class="block">
  <h3><?= te('Profile') ?></h3>
  <form method="post" style="display:flex;gap:.5rem;align-items:flex-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <div class="field" style="flex:1;margin:0"><label for="name"><?= te('Name') ?></label><input id="name" name="name" value="<?= e($user['name']) ?>"></div>
    <button class="button ghost"><?= te('Save') ?></button>
  </form>
  <p class="field-hint"><?= te('To change your email, ask an admin.') ?></p>
</section>

<section class="block" id="password">
  <h3><?= te('Password') ?></h3>
  <p class="hint"><?= $user['password_hash'] ? te('You have a password set.') : te('Optional. Without one, you sign in with a link by mail.') ?>
    <?= $isMaster ? ' ' . te('The ADMIN_PASSWORD from the deploy secrets also always works for you.') : '' ?></p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <input type="text" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
    <div class="form-grid">
      <div class="field"><label for="pw1"><?= te('New password') ?></label><input id="pw1" type="password" name="password" minlength="10" required autocomplete="new-password"></div>
      <div class="field"><label for="pw2"><?= te('Repeat') ?></label><input id="pw2" type="password" name="password2" minlength="10" required autocomplete="new-password"></div>
    </div>
    <button class="button"><?= $user['password_hash'] ? te('Change password') : te('Set password') ?></button>
  </form>
  <?php if ($user['password_hash']): ?>
  <form method="post" style="margin-top:1rem" data-confirm="<?= te('Remove your password? You\'ll sign in with mail links only.') ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="nopassword">
    <button class="button small ghost"><?= te('Remove password') ?></button>
  </form>
  <?php endif; ?>
</section>
</div>
<?php page_foot();
