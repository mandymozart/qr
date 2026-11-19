<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/* Admin: codes made without an account (landing page). Pause or delete anything abusive. */
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = int_param('id');
    $l = q('SELECT * FROM links WHERE id = ? AND user_id IS NULL', [$id])->fetch();
    if ($l) {
        if (post('action') === 'toggle') {
            q('UPDATE links SET active = 1 - active WHERE id = ?', [$id]);
            flash($l['active'] ? t('Paused {code}.', ['code' => $l['code']]) : t('Activated {code}.', ['code' => $l['code']]));
        } elseif (post('action') === 'delete') {
            q('DELETE FROM links WHERE id = ?', [$id]);
            flash(t('Deleted {code}.', ['code' => $l['code']]));
        }
    }
    redirect('/app/guests.php');
}

$links = q("SELECT l.*, (SELECT COUNT(*) FROM hits h WHERE h.link_id = l.id AND h.device <> 'bot') scans
            FROM links l WHERE l.user_id IS NULL ORDER BY l.id DESC LIMIT 500")->fetchAll();
$week = (int) q('SELECT COUNT(*) FROM links WHERE user_id IS NULL AND created_at > NOW() - INTERVAL 7 DAY')->fetchColumn();

page_head(t('Guest codes'), 'guests');
?>
<div class="page-head">
  <div>
    <span class="entity-type"><?= te('Admin') ?></span>
    <h2 class="entity-title"><?= te('Guest codes') ?></h2>
    <p class="entity-description"><?= te('Made on the landing page without an account. {total} in total, {week} in the last 7 days. Check the targets now and then and pause or delete anything shady.', ['total' => count($links), 'week' => $week]) ?></p>
  </div>
</div>

<div class="list-header"><span><input type="search" placeholder="<?= te('Search target or code…') ?>" data-filter="#guests" aria-label="<?= te('Search') ?>"></span><span><?= te('newest first') ?></span></div>
<?php if (!$links): ?>
  <div class="empty"><?= te('No guest codes yet.') ?></div>
<?php else: ?>
<div class="list" id="guests">
<?php foreach ($links as $l): ?>
  <div class="row" data-search="<?= e($l['title'] . ' ' . $l['code'] . ' ' . $l['target_url']) ?>">
    <div class="main">
      <h4><?= e($l['title']) ?><?= $l['active'] ? '' : ' <span class="badge off">' . te('paused') . '</span>' ?></h4>
      <div class="meta"><span class="url">?c=<?= e($l['code']) ?></span>
        <a class="target" href="<?= e($l['target_url']) ?>" target="_blank" rel="noopener noreferrer nofollow">→ <?= e($l['target_url']) ?></a>
        <span><?= te('created {when}', ['when' => ago($l['created_at'])]) ?></span></div>
    </div>
    <div class="stat"><strong><?= (int) $l['scans'] ?></strong><span><?= te('scans') ?></span></div>
    <div class="tools">
      <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="action" value="toggle">
        <button class="button small ghost"><?= te($l['active'] ? 'Pause' : 'Activate') ?></button></form>
      <form class="inline-form" method="post" data-confirm="<?= te('Delete this guest code?') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="action" value="delete">
        <button class="button small danger"><?= te('Delete') ?></button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_foot();
