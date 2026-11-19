<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$c = find_campaign(int_param('id'), $user);
if (!$c) { http_response_code(404); exit('Not found'); }
$cid = (int) $c['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    switch (post('action')) {
        case 'rename':
            if (post('name') !== '') q('UPDATE campaigns SET name = ? WHERE id = ?', [post('name'), $cid]);
            flash('Renamed.');
            redirect('/app/campaign.php?id=' . $cid);
        case 'retarget':
            if (!valid_url(post('target_url'))) { flash('Target URL needs to be a full http(s):// URL.', 'err'); redirect('/app/campaign.php?id=' . $cid); }
            $n = q('UPDATE links SET target_url = ? WHERE campaign_id = ? AND user_id = ?', [post('target_url'), $cid, $user['id']])->rowCount();
            flash("Updated the target of $n codes.");
            redirect('/app/campaign.php?id=' . $cid);
        case 'delete':
            $withCodes = isset($_POST['with_codes']);
            if ($withCodes) q('DELETE FROM links WHERE campaign_id = ? AND user_id = ?', [$cid, $user['id']]);
            q('DELETE FROM campaigns WHERE id = ? AND user_id = ?', [$cid, $user['id']]);
            flash($withCodes ? 'Campaign and its codes deleted.' : 'Campaign deleted, its codes are kept.');
            redirect('/app/campaigns.php');
    }
}

$links = q("SELECT l.*, (SELECT COUNT(*) FROM hits h WHERE h.link_id = l.id AND h.device <> 'bot') scans
            FROM links l WHERE l.campaign_id = ? AND l.user_id = ? ORDER BY l.id", [$cid, $user['id']])->fetchAll();
$daily = daily_counts('l.campaign_id = ?', [$cid], 30);
$total = array_sum(array_column($links, 'scans'));

page_head($c['name'], 'campaigns');
?>
<div class="page-head">
  <div>
    <span class="entity-type">Campaign</span>
    <h2 class="entity-title"><?= e($c['name']) ?></h2>
    <p class="entity-description"><?= count($links) ?> codes · <?= number_format($total) ?> scans · created <?= e(fmt_date($c['created_at'])) ?></p>
  </div>
  <div>
    <?php if ($links && class_exists('ZipArchive')): ?><a class="button ghost" href="/app/qr.php?campaign=<?= $cid ?>&amp;fmt=zip">Download all (.zip)</a><?php endif; ?>
    <a class="button ghost" href="/app/?campaign=<?= $cid ?>">As list</a>
    <a class="button" href="/app/edit.php?campaign=<?= $cid ?>">+ Add code</a>
  </div>
</div>

<?= bar_chart($daily) ?>

<section class="block" style="margin-top:1.5rem;border:0">
<?php if (!$links): ?>
  <div class="empty">No codes in this campaign. Add some below or <a href="/app/edit.php?campaign=<?= $cid ?>">one by one</a>.</div>
<?php else: ?>
  <div class="list-header"><span><input type="search" placeholder="Search…" data-filter="#grid" aria-label="Search codes"></span><span>sorted by creation</span></div>
  <div class="qr-grid" id="grid">
  <?php foreach ($links as $l): ?>
    <a class="qr-card" href="/app/code.php?id=<?= $l['id'] ?>" data-search="<?= e($l['title'] . ' ' . $l['placement'] . ' ' . $l['code']) ?>">
      <img src="/app/qr.php?id=<?= $l['id'] ?>&amp;v=<?= e($l['code']) ?>" alt="" loading="lazy">
      <h4><?= e($l['title']) ?><?= $l['active'] ? '' : ' <span class="badge off">paused</span>' ?></h4>
      <?php if ($l['placement'] !== ''): ?><div class="meta"><span><?= e($l['placement']) ?></span></div><?php endif; ?>
      <div class="meta"><span class="mono">?c=<?= e($l['code']) ?></span><span><?= (int) $l['scans'] ?> scans</span></div>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>

<section class="block" style="max-width:40rem">
  <h3>Add codes</h3>
  <p class="hint">Bulk-add codes to this campaign. <code>{n}</code> = running number, <code>{code}</code> = short code.</p>
  <form method="post" action="/app/campaigns.php">
    <?= csrf_field() ?><input type="hidden" name="campaign_id" value="<?= $cid ?>">
    <div class="form-grid">
      <div class="field"><label for="count">Number of codes</label><input id="count" name="count" type="number" min="1" max="500" value="1"></div>
      <div class="field"><label for="title">Code names</label><input id="title" name="title" value="<?= e($c['name']) ?> #{n}"></div>
    </div>
    <div class="field"><label for="target_url">Target URL</label><input id="target_url" name="target_url" value="<?= e($links[0]['target_url'] ?? 'https://') ?>"></div>
    <button class="button">Add codes</button>
  </form>
</section>

<section class="block" style="max-width:40rem">
  <h3>Settings</h3>
  <form method="post" style="display:flex;gap:.5rem;align-items:flex-end;margin-bottom:1rem">
    <?= csrf_field() ?><input type="hidden" name="action" value="rename">
    <div class="field" style="flex:1;margin:0"><label for="rename">Name</label><input id="rename" name="name" value="<?= e($c['name']) ?>"></div>
    <button class="button ghost">Rename</button>
  </form>
  <?php if ($links): ?>
  <form method="post" style="display:flex;gap:.5rem;align-items:flex-end;margin-bottom:1rem" data-confirm="Point ALL codes of this campaign to this URL?">
    <?= csrf_field() ?><input type="hidden" name="action" value="retarget">
    <div class="field" style="flex:1;margin:0"><label for="retarget">Point all codes to</label><input id="retarget" name="target_url" value="<?= e($links[0]['target_url']) ?>"></div>
    <button class="button ghost">Update all</button>
  </form>
  <?php endif; ?>
  <form method="post" data-confirm="Delete this campaign?">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <div class="field check"><label><input type="checkbox" name="with_codes"> Also delete its <?= count($links) ?> codes and their stats</label></div>
    <button class="button danger">Delete campaign</button>
  </form>
</section>
<?php page_foot();
