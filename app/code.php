<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$link = find_link(int_param('id'), $user);
if (!$link) { http_response_code(404); exit('Not found'); }
$id = (int) $link['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    switch (post('action')) {
        case 'toggle':
            q('UPDATE links SET active = 1 - active WHERE id = ? AND user_id = ?', [$id, $user['id']]);
            flash($link['active'] ? 'Paused. The code now shows a "not found" page.' : 'Activated.');
            redirect('/app/code.php?id=' . $id);
        case 'delete':
            q('DELETE FROM links WHERE id = ? AND user_id = ?', [$id, $user['id']]);
            flash('Deleted "' . $link['title'] . '".');
            redirect($link['campaign_id'] ? '/app/campaign.php?id=' . $link['campaign_id'] : '/app/');
    }
}

$short = short_url($link['code']);
$daily = daily_counts('l.id = ?', [$id], 30);
$total = (int) q("SELECT COUNT(*) FROM hits WHERE link_id = ? AND device <> 'bot'", [$id])->fetchColumn();
$last = q("SELECT MAX(created_at) FROM hits WHERE link_id = ? AND device <> 'bot'", [$id])->fetchColumn();
$devices = q("SELECT device, COUNT(*) n FROM hits WHERE link_id = ? AND device <> 'bot' GROUP BY device ORDER BY n DESC", [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = q("SELECT created_at, device, os, referer FROM hits WHERE link_id = ? AND device <> 'bot' ORDER BY id DESC LIMIT 25", [$id])->fetchAll();
$mobilePct = $total ? round((($devices['mobile'] ?? 0) + ($devices['tablet'] ?? 0)) / $total * 100) : 0;

page_head($link['title'], 'codes');
?>
<div style="max-width:44rem">
<span class="entity-type">Code</span>
<h2 class="entity-title"><?= e($link['title']) ?>
  <?php if ($link['campaign_name']): ?><a class="badge campaign" href="/app/campaign.php?id=<?= $link['campaign_id'] ?>" style="text-decoration:none"><?= e($link['campaign_name']) ?></a><?php endif; ?>
  <span class="badge <?= $link['active'] ? 'on' : 'off' ?>"><?= $link['active'] ? 'active' : 'paused' ?></span></h2>

<div class="qr-panel" style="margin-top:1rem">
  <div>
    <div class="qr-big"><img src="/app/qr.php?id=<?= $id ?>&amp;v=<?= e($link['code']) ?>" alt="QR code for <?= e($short) ?>"></div>
    <div class="dl">
      <a class="button small" href="/app/qr.php?id=<?= $id ?>&amp;fmt=png&amp;dl=1" download>↓ PNG</a>
      <a class="button small" href="/app/qr.php?id=<?= $id ?>&amp;fmt=svg&amp;dl=1" download>↓ SVG</a>
    </div>
  </div>
  <div>
    <label class="copy-label" for="short-link">Link</label>
    <div class="copy-field">
      <input id="short-link" value="<?= e($short) ?>" readonly onclick="this.select()">
      <button class="button" data-copy="<?= e($short) ?>">Copy</button>
    </div>
    <dl class="kv">
      <dt>Target</dt><dd class="mono"><a href="<?= e($link['target_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['target_url']) ?></a></dd>
      <dt>Campaign</dt><dd><?= $link['campaign_name'] ? e($link['campaign_name']) : '—' ?></dd>
      <dt>Created</dt><dd><?= e(fmt_date($link['created_at'])) ?></dd>
    </dl>
    <div class="form-actions" style="justify-content:flex-start">
      <a class="button" href="/app/edit.php?id=<?= $id ?>">Edit</a>
      <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle">
        <button class="button ghost"><?= $link['active'] ? 'Pause' : 'Activate' ?></button></form>
      <form class="inline-form" method="post" data-confirm="Delete this code and all its stats? Printed codes will stop working."><?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="button danger">Delete</button></form>
    </div>
  </div>
</div>

<section class="block" style="margin-top:2rem">
  <h3>Scans</h3><p class="hint">Bots and link previews are not counted.</p>
  <div class="tiles">
    <div class="tile"><div class="label">Total</div><div class="value"><?= number_format($total) ?></div></div>
    <div class="tile"><div class="label">30 days</div><div class="value"><?= number_format(array_sum($daily)) ?></div></div>
    <div class="tile"><div class="label">Mobile</div><div class="value"><?= $mobilePct ?>%</div></div>
    <div class="tile"><div class="label">Last scan</div><div class="value" style="font-size:1.1rem;padding-top:.6rem"><?= e(ago($last ?: null)) ?></div></div>
  </div>
  <?= bar_chart($daily) ?>
</section>

<section class="block">
  <h3>Recent scans</h3><p class="hint">Newest first, last 25.</p>
  <?php if (!$recent): ?><div class="empty">No scans yet. Scan the code with your phone to test it.</div><?php else: ?>
  <table class="data"><thead><tr><th>When</th><th>Device</th><th>Referrer</th></tr></thead><tbody>
  <?php foreach ($recent as $h): ?>
    <tr><td><?= e(fmt_date($h['created_at'], 'M j, H:i')) ?></td><td><?= e($h['device'] . ($h['os'] ? ' · ' . $h['os'] : '')) ?></td><td><?= e($h['referer'] ?: '—') ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>
</div>
<?php page_foot();
