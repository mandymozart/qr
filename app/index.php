<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$uid = $user['id'];
$campaignFilter = int_param('campaign');

$params = [$uid];
$where = 'l.user_id = ?';
if ($campaignFilter) {
    $where .= ' AND l.campaign_id = ?';
    $params[] = $campaignFilter;
}

$links = q("SELECT l.*, c.name AS campaign_name,
              (SELECT COUNT(*) FROM hits h WHERE h.link_id = l.id AND h.device <> 'bot') AS scans
            FROM links l LEFT JOIN campaigns c ON c.id = l.campaign_id
            WHERE $where ORDER BY l.created_at DESC, l.id DESC", $params)->fetchAll();

// 14-day sparkline data for all listed links in one query
$spark = [];
if ($links) {
    $rows = q("SELECT h.link_id, DATE(h.created_at) d, COUNT(*) n FROM hits h JOIN links l ON l.id = h.link_id
               WHERE $where AND h.device <> 'bot' AND h.created_at >= CURDATE() - INTERVAL 13 DAY
               GROUP BY h.link_id, d", $params)->fetchAll();
    foreach ($rows as $r) $spark[$r['link_id']][$r['d']] = (int) $r['n'];
}
$days = [];
for ($i = 13; $i >= 0; $i--) $days[] = date('Y-m-d', strtotime("-$i day"));

$today = (int) q("SELECT COUNT(*) FROM hits h JOIN links l ON l.id = h.link_id WHERE l.user_id = ? AND h.device <> 'bot' AND h.created_at >= CURDATE()", [$uid])->fetchColumn();
$month = (int) q("SELECT COUNT(*) FROM hits h JOIN links l ON l.id = h.link_id WHERE l.user_id = ? AND h.device <> 'bot' AND h.created_at >= CURDATE() - INTERVAL 29 DAY", [$uid])->fetchColumn();
$campaigns = user_campaigns($user);

page_head(t('Codes'), 'codes');
?>
<div class="page-head">
  <div>
    <span class="entity-type"><?= te('Codes') ?></span>
    <h2 class="entity-title"><?= te('Your QR links') ?></h2>
    <p class="entity-description"><?= t('Every code redirects through {url}', ['url' => '<span class="url">' . e(base_url()) . '/?c=…</span>']) ?></p>
  </div>
  <div><a class="button ghost" href="/app/campaigns.php#bulk"><?= te('Bulk') ?></a> <a class="button" href="/app/edit.php"><?= te('+ New code') ?></a></div>
</div>

<div class="tiles">
  <div class="tile"><div class="label"><?= te('Scans today') ?></div><div class="value"><?= $today ?></div></div>
  <div class="tile"><div class="label"><?= te('Last 30 days') ?></div><div class="value"><?= number_format($month) ?></div></div>
  <div class="tile"><div class="label"><?= te('Codes') ?></div><div class="value"><?= count($links) ?></div><div class="sub"><?= te('{n} campaigns', ['n' => count($campaigns)]) ?></div></div>
</div>

<div class="list-header">
  <span><input type="search" placeholder="<?= te('Search…') ?>" data-filter="#links" aria-label="<?= te('Search codes') ?>"></span>
  <span>
    <form method="get" class="inline-form">
      <select name="campaign" onchange="this.form.submit()" aria-label="<?= te('Filter by campaign') ?>">
        <option value="0"><?= te('All campaigns') ?></option>
        <?php foreach ($campaigns as $c): ?><option value="<?= $c['id'] ?>"<?= $campaignFilter === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </form>
    · <?= te('{n} codes', ['n' => count($links)]) ?>
  </span>
</div>

<?php if (!$links): ?>
  <div class="empty"><?= t('No codes yet. {first} or {bulk}.', ['first' => '<a href="/app/edit.php">' . te('Create your first one') . '</a>', 'bulk' => '<a href="/app/campaigns.php#bulk">' . te('make a bunch at once') . '</a>']) ?></div>
<?php else: ?>
<div class="list" id="links">
<?php foreach ($links as $l):
    $counts = array_map(fn($d) => $spark[$l['id']][$d] ?? 0, $days); ?>
  <div class="row hoverable" data-search="<?= e($l['title'] . ' ' . $l['placement'] . ' ' . $l['code'] . ' ' . $l['target_url'] . ' ' . $l['campaign_name']) ?>">
    <a href="/app/code.php?id=<?= $l['id'] ?>"><img class="qr-thumb" src="/app/qr.php?id=<?= $l['id'] ?>&amp;v=<?= e($l['code']) ?>" alt="" loading="lazy"></a>
    <div class="main">
      <h4><a href="/app/code.php?id=<?= $l['id'] ?>" style="text-decoration:none"><?= e($l['title']) ?></a>
        <?php if ($l['campaign_name']): ?><span class="badge campaign"><?= e($l['campaign_name']) ?></span><?php endif; ?>
        <?php if (!$l['active']): ?><span class="badge off"><?= te('paused') ?></span><?php endif; ?></h4>
      <div class="meta"><span class="url">?c=<?= e($l['code']) ?></span><?php if ($l['placement'] !== ''): ?><span>📍 <?= e($l['placement']) ?></span><?php endif; ?><span class="target">→ <?= e($l['target_url']) ?></span></div>
    </div>
    <?= sparkline($counts) ?>
    <div class="stat"><strong><?= (int) $l['scans'] ?></strong><span><?= te('scans') ?></span></div>
    <div class="tools">
      <button class="button small ghost" data-copy="<?= e(short_url($l['code'])) ?>"><?= te('Copy link') ?></button>
      <a class="button small ghost" href="/app/qr.php?id=<?= $l['id'] ?>&amp;fmt=png&amp;dl=1" download title="<?= te('Download PNG') ?>">PNG</a>
      <a class="button small ghost" href="/app/qr.php?id=<?= $l['id'] ?>&amp;fmt=svg&amp;dl=1" download title="<?= te('Download SVG') ?>">SVG</a>
      <a class="button small ghost" href="/app/code.php?id=<?= $l['id'] ?>"><?= te('Stats') ?></a>
      <a class="button small ghost" href="/app/edit.php?id=<?= $l['id'] ?>"><?= te('Edit') ?></a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_foot();
