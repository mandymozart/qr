<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$newKey = null;

// API keys: a new key is shown exactly once, so this POST renders the page instead of redirecting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(post('action'), ['api_create', 'api_revoke'], true)) {
    check_csrf();
    if (!empty($user['impersonator_id'])) {
        flash(t("You can't manage API keys of someone else's account."), 'err');
        redirect('/app/account.php#api');
    }
    if (post('action') === 'api_create') {
        if (count(api_keys_of($user)) >= 10) {
            flash(t('You can have up to 10 API keys. Revoke one first.'), 'err');
            redirect('/app/account.php#api');
        }
        $newKey = api_key_create($user, post('key_name') ?: t('My key'));
    } else {
        q('DELETE FROM api_keys WHERE id = ? AND user_id = ?', [int_param('key_id'), $user['id']]);
        flash(t('API key revoked. Apps using it stop working.'));
        redirect('/app/account.php#api');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$newKey) {
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

<section class="block" id="api">
  <h3><?= te('API & AI agents') ?></h3>
  <?php $plan = api_plan($user); $lim = api_limits($plan);
        $used = q("SELECT COUNT(*) calls, COALESCE(SUM(units), 0) codes FROM api_calls WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY", [$user['id']])->fetch(); ?>
  <p class="hint"><?= t('Let AI assistants and your own scripts create and change codes. Connect any MCP-capable AI app to {mcp} or use the REST API ({docs}).', [
      'mcp' => '<code>' . e(base_url()) . '/mcp</code>',
      'docs' => '<a href="/' . lang() . '/' . (lang() === 'de' ? 'entwickler' : 'developers') . '">' . te('docs') . '</a>']) ?></p>
  <div class="tiles">
    <div class="tile"><div class="label"><?= te('Plan') ?></div><div class="value" style="font-size:1.6rem"><?= $plan === 'pro' ? '⭐ Pro' : te('Free') ?></div></div>
    <div class="tile"><div class="label"><?= te('New codes via API (24 h)') ?></div><div class="value" style="font-size:1.6rem"><?= (int) $used['codes'] ?> / <?= $lim['codes_per_day'] ?></div></div>
    <div class="tile"><div class="label"><?= te('Requests per hour') ?></div><div class="value" style="font-size:1.6rem"><?= $lim['requests_per_hour'] ?></div><div class="sub"><?= te('bulk: up to {n} per call', ['n' => $lim['bulk_per_call']]) ?></div></div>
  </div>
  <?php if ($plan !== 'pro'): ?><p class="hint"><?= te('Need more? Pro has much higher limits.') ?> <?= pro_button() ?></p><?php endif; ?>

  <?php if ($newKey): ?>
    <div class="notice info"><strong><?= te('Your new API key. Copy it now, it is shown only once:') ?></strong>
      <div class="copy-field" style="margin:.6rem 0 0"><input value="<?= e($newKey) ?>" readonly onclick="this.select()"><button class="button" data-copy="<?= e($newKey) ?>"><?= te('Copy') ?></button></div>
      <p class="field-hint"><?= te('In your AI app, add the MCP server with this header:') ?> <code>Authorization: Bearer <?= e(substr($newKey, 0, 12)) ?>…</code></p>
    </div>
  <?php endif; ?>

  <?php $keys = api_keys_of($user); if ($keys): ?>
  <table class="data" style="margin:1rem 0">
    <thead><tr><th><?= te('Name') ?></th><th><?= te('Key') ?></th><th><?= te('Created') ?></th><th><?= te('Last used') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($keys as $k): ?>
      <tr><td><?= e($k['name']) ?></td><td><code><?= e($k['prefix']) ?>…</code></td><td><?= e(fmt_date($k['created_at'])) ?></td><td><?= e(ago($k['last_used_at'])) ?></td>
        <td><form class="inline-form" method="post" data-confirm="<?= te('Revoke this key? Apps using it stop working.') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="api_revoke"><input type="hidden" name="key_id" value="<?= $k['id'] ?>"><button class="button small danger"><?= te('Revoke') ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <form method="post" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
    <?= csrf_field() ?><input type="hidden" name="action" value="api_create">
    <div class="field" style="flex:1;min-width:12rem;margin:0"><label for="key_name"><?= te('Name for the new key') ?></label><input id="key_name" name="key_name" placeholder="<?= te('e.g. Claude, Cursor, my script') ?>"></div>
    <button class="button"><?= te('Create API key') ?></button>
  </form>
</section>
</div>
<?php page_foot();
