<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * Admin: user management. There is no self sign-up — admins create every account.
 * The master admin (ADMIN_USER from the deploy secrets) can't be blocked, demoted or deleted.
 */
$me = require_admin();

function is_master(array $u): bool
{
    return strcasecmp($u['email'], (string) config('admin_user')) === 0;
}

/**
 * Sends a 7-day login link; on mail failure returns the link so the admin can pass it on.
 * Returns [message, flash type].
 */
function send_access(array $u, bool $invite): array
{
    $link = issue_login_link((int) $u['id'], 60 * 24 * 7);
    $err = filter_var($u['email'], FILTER_VALIDATE_EMAIL) ? send_login_mail($u, $link, true) : t('not an email address');
    return $err === null
        ? [$invite ? t('User created and invite sent to {email}.', ['email' => $u['email']]) : t('Sign-in link sent to {email}.', ['email' => $u['email']]), 'ok']
        : [t('Mail could not be sent ({error}). Share this link manually (valid 7 days, works once): {link}', ['error' => $err, 'link' => $link]), 'info'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = post('action');

    if ($action === 'create') {
        $email = strtolower(post('email'));
        $role = post('role') === 'admin' ? 'admin' : 'user';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash(t('Please enter a valid email address.'), 'err');
        } elseif (q('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) {
            flash(t('A user with that email already exists.'), 'err');
        } else {
            q('INSERT INTO users (email, name, role) VALUES (?, ?, ?)', [$email, post('name'), $role]);
            $u = q('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()])->fetch();
            [$msg, $type] = isset($_POST['send']) ? send_access($u, true) : [t('User created. Send them a link when ready.'), 'ok'];
            flash($msg, $type);
        }
        redirect('/app/users.php');
    }

    $u = q('SELECT * FROM users WHERE id = ?', [int_param('id')])->fetch();
    if (!$u) redirect('/app/users.php');
    $protected = is_master($u) || (int) $u['id'] === (int) $me['id'];

    switch ($action) {
        case 'resend':
            if ($u['status'] !== 'active') { flash(t('Unblock the user first.'), 'err'); break; }
            [$msg, $type] = send_access($u, false);
            flash($msg, $type);
            break;
        case 'block':
            if ($protected) { flash(t('You can\'t block this account.'), 'err'); break; }
            $new = $u['status'] === 'active' ? 'blocked' : 'active';
            q('UPDATE users SET status = ? WHERE id = ?', [$new, $u['id']]);
            if ($new === 'blocked') q('DELETE FROM sessions WHERE user_id = ?', [$u['id']]);
            flash($new === 'blocked'
                ? t('{email} is blocked and signed out. Their codes keep working.', ['email' => $u['email']])
                : t('{email} is active again.', ['email' => $u['email']]));
            break;
        case 'role':
            if ($protected) { flash(t('You can\'t change the role of this account.'), 'err'); break; }
            $role = $u['role'] === 'admin' ? 'user' : 'admin';
            q('UPDATE users SET role = ? WHERE id = ?', [$role, $u['id']]);
            flash($role === 'admin'
                ? t('{email} is now an admin.', ['email' => $u['email']])
                : t('{email} is now a regular user.', ['email' => $u['email']]));
            break;
        case 'edit':
            $email = strtolower(post('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !is_master($u)) { flash(t('Invalid email.'), 'err'); break; }
            if (is_master($u) && $email !== strtolower($u['email'])) { flash(t('The master admin email is set via ADMIN_USER.'), 'err'); break; }
            if (q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])->fetch()) { flash(t('Email already in use.'), 'err'); break; }
            q('UPDATE users SET name = ?, email = ? WHERE id = ?', [post('name'), $email, $u['id']]);
            flash(t('Saved.'));
            break;
        case 'delete':
            if ($protected) { flash(t('You can\'t delete this account.'), 'err'); break; }
            q('DELETE FROM users WHERE id = ?', [$u['id']]);
            flash(t('{email} and all their codes, campaigns and stats were deleted.', ['email' => $u['email']]));
            break;
    }
    redirect('/app/users.php');
}

$users = q("SELECT u.*, (SELECT COUNT(*) FROM links l WHERE l.user_id = u.id) codes,
              (SELECT COUNT(*) FROM hits h JOIN links l ON l.id = h.link_id WHERE l.user_id = u.id AND h.device <> 'bot') scans
            FROM users u ORDER BY u.role = 'admin' DESC, u.created_at")->fetchAll();
$editId = int_param('edit');

page_head(t('Users'), 'users');
?>
<div class="page-head">
  <div>
    <span class="entity-type"><?= te('Admin') ?></span>
    <h2 class="entity-title"><?= te('Users') ?></h2>
    <p class="entity-description"><?= te('People can sign up themselves, and admins can add accounts here. Everyone signs in with a password or a secret link by mail.') ?></p>
  </div>
</div>

<div class="list user-list">
<?php foreach ($users as $u): $protected = is_master($u) || (int) $u['id'] === (int) $me['id']; ?>
  <div class="row">
    <div class="main">
      <?php if ($editId === (int) $u['id']): ?>
        <form method="post" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $u['id'] ?>">
          <div class="field" style="margin:0"><label><?= te('Name') ?></label><input name="name" value="<?= e($u['name']) ?>"></div>
          <div class="field" style="margin:0;flex:1"><label><?= te('Email') ?></label><input name="email" value="<?= e($u['email']) ?>"<?= is_master($u) ? ' readonly' : '' ?>></div>
          <button class="button small"><?= te('Save') ?></button><a class="button small ghost" href="/app/users.php"><?= te('Cancel') ?></a>
        </form>
      <?php else: ?>
        <h4><?= e($u['name'] ?: $u['email']) ?>
          <?php if ($u['role'] === 'admin'): ?><span class="badge admin"><?= te(is_master($u) ? 'master admin' : 'admin') ?></span><?php endif; ?>
          <span class="badge <?= $u['status'] ?>"><?= te($u['status'] === 'active' ? 'active' : 'blocked') ?></span>
          <?php if ((int) $u['id'] === (int) $me['id']): ?><span class="badge"><?= te('you') ?></span><?php endif; ?></h4>
        <div class="meta"><span><?= e($u['email']) ?></span><span><?= te('last sign-in {when}', ['when' => ago($u['last_login_at'])]) ?></span><span><?= te('since {date}', ['date' => fmt_date($u['created_at'])]) ?></span></div>
      <?php endif; ?>
    </div>
    <div class="stat"><strong><?= (int) $u['codes'] ?></strong><span><?= te('codes') ?></span></div>
    <div class="stat"><strong><?= (int) $u['scans'] ?></strong><span><?= te('scans') ?></span></div>
    <div class="tools">
      <?php if (!is_master($u) && (int) $u['id'] !== (int) $me['id'] && $u['status'] === 'active'): ?>
      <form class="inline-form" method="post" action="/app/impersonate.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="start">
        <button class="button small" title="<?= te('See and use their account to help them (2 hours max)') ?>"><?= te('Log in as user') ?></button></form>
      <?php endif; ?>
      <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="resend">
        <button class="button small ghost" title="<?= te('Mail a fresh sign-in link (valid 7 days)') ?>"><?= te('Send link') ?></button></form>
      <a class="button small ghost" href="/app/users.php?edit=<?= $u['id'] ?>"><?= te('Edit') ?></a>
      <?php if (!$protected): ?>
        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="role">
          <button class="button small ghost"><?= te($u['role'] === 'admin' ? 'Make user' : 'Make admin') ?></button></form>
        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="block">
          <button class="button small ghost"><?= te($u['status'] === 'active' ? 'Block' : 'Unblock') ?></button></form>
        <form class="inline-form" method="post" data-confirm="<?= te('Delete {email} with all their codes, campaigns and stats? Their printed codes stop working.', ['email' => $u['email']]) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="delete">
          <button class="button small danger"><?= te('Delete') ?></button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<section class="block" style="margin-top:2rem;max-width:40rem">
  <h3><?= te('Add user') ?></h3>
  <p class="hint"><?= te('They get a mail with a secret sign-in link (valid 7 days). Admins can also manage users.') ?></p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="form-grid">
      <div class="field"><label for="email"><?= te('Email') ?></label><input id="email" name="email" type="email" required></div>
      <div class="field"><label for="name"><?= te('Name') ?></label><input id="name" name="name"></div>
      <div class="field"><label for="role"><?= te('Role') ?></label><select id="role" name="role"><option value="user"><?= te('User') ?></option><option value="admin"><?= te('Admin') ?></option></select></div>
    </div>
    <div class="field check"><label><input type="checkbox" name="send" checked> <?= te('Send the invite mail now') ?></label></div>
    <button class="button"><?= te('Add user') ?></button>
  </form>
</section>
<?php page_foot();
