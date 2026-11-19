<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * Sign in: email + password (if the user set one), or a one-time secret link by mail.
 * "Forgot password" = a secret link that lands on the account page to set a new one.
 * Master admin: ADMIN_USER + ADMIN_PASSWORD from the deploy secrets always works.
 */
if (current_user()) redirect('/app/');

$notice = null;
$error = null;
$email = strtolower(post('email'));
$mode = post('mode'); // password | link | reset

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if ($email === '') {
        $error = 'Please enter your email.';
    } elseif ($mode === 'password') {
        $pw = (string) ($_POST['password'] ?? '');
        $isMasterLogin = config('admin_password') && strcasecmp($email, (string) config('admin_user')) === 0
            && hash_equals((string) config('admin_password'), $pw);
        if ($isMasterLogin && !q('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) {
            // master admin always exists (e.g. after ADMIN_USER was changed)
            q("INSERT INTO users (email, name, role) VALUES (?, 'Admin', 'admin')", [$email]);
        }
        $u = q("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email])->fetch();
        $master = $u && config('admin_password') && strcasecmp($email, (string) config('admin_user')) === 0
            && hash_equals((string) config('admin_password'), $pw);
        if ($u && ($master || ($u['password_hash'] && password_verify($pw, $u['password_hash'])))) {
            start_session((int) $u['id']);
            redirect('/app/');
        }
        sleep(1);
        $error = 'Wrong email or password. No password yet? Use the sign-in link.';
    } else {
        $u = q("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email])->fetch();
        // max one mail per minute per user; never reveal whether the account exists
        if ($u && filter_var($email, FILTER_VALIDATE_EMAIL) && (!$u['token_created_at'] || strtotime($u['token_created_at']) < time() - 60)) {
            $link = issue_login_link((int) $u['id'], 30) . ($mode === 'reset' ? '&next=account' : '');
            send_login_mail($u, $link);
        }
        $notice = $mode === 'reset'
            ? 'If that address has an account, a link to set a new password is on its way (valid 30 minutes).'
            : 'If that address has an account, a sign-in link is on its way (valid 30 minutes).';
    }
}

page_head('Sign in');
?>
<a class="logo" href="/">qr</a>
<p class="entity-description">Sign in with your password, or get a secret link by mail.</p>

<?php if ($notice): ?><div class="notice info"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="field"><label for="email">Email</label>
    <input type="text" inputmode="email" id="email" name="email" required autocomplete="username" autofocus value="<?= e($email) ?>"></div>
  <div class="field"><label for="password">Password <span style="font-weight:400;color:var(--neutral-20)">(if you set one)</span></label>
    <input type="password" id="password" name="password" autocomplete="current-password"></div>
  <div class="form-actions" style="justify-content:flex-start;flex-wrap:wrap">
    <button class="button" name="mode" value="password">Sign in</button>
    <button class="button ghost" name="mode" value="link" formnovalidate>Email me a sign-in link</button>
  </div>
  <p style="font-size:.8rem;margin-top:1.5rem"><button class="nav-button" name="mode" value="reset" style="padding:0;text-decoration:underline;color:var(--neutral-30)">Forgot password?</button></p>
</form>
<p class="field-hint">No account? <a href="/app/register.php">Create one for free</a>.</p>
<?php page_foot();
