<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * Admin support tool: log in as a user to see their account.
 *   POST action=start&id=USER  (admins only)  → become that user for up to 2 hours
 *   POST action=stop                          → back to the admin's own account
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/app/');
check_csrf();
$me = require_user();

if (post('action') === 'stop') {
    $adminId = (int) ($me['impersonator_id'] ?? 0);
    end_session();
    $admin = $adminId ? q("SELECT * FROM users WHERE id = ? AND role = 'admin' AND status = 'active'", [$adminId])->fetch() : null;
    if (!$admin) redirect('/app/login.php');
    start_session((int) $admin['id']);
    flash('Back in your own account.');
    redirect('/app/users.php');
}

// start
if ($me['role'] !== 'admin' || !empty($me['impersonator_id'])) { http_response_code(403); exit('Admins only.'); }
$u = q('SELECT * FROM users WHERE id = ?', [int_param('id')])->fetch();
if (!$u || (int) $u['id'] === (int) $me['id'] || $u['status'] !== 'active' || strcasecmp($u['email'], (string) config('admin_user')) === 0) {
    flash("You can't log in as this user.", 'err');
    redirect('/app/users.php');
}
end_session();
start_session((int) $u['id'], (int) $me['id']);
flash('You are now logged in as ' . $u['email'] . '.', 'info');
redirect('/app/');
