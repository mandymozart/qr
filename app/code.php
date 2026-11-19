<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$link = find_link(int_param('id'), $user);
if (!$link) { http_response_code(404); exit(t('Not found')); }
$id = (int) $link['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    switch (post('action')) {
        case 'style':
            [$type, $msg] = style_save($link);
            flash($msg, $type);
            redirect('/app/code.php?id=' . $id . '#style');
        case 'toggle':
            q('UPDATE links SET active = 1 - active WHERE id = ? AND user_id = ?', [$id, $user['id']]);
            flash($link['active'] ? t('Paused. The code now shows a "not found" page.') : t('Activated.'));
            redirect('/app/code.php?id=' . $id);
        case 'delete':
            q('DELETE FROM links WHERE id = ? AND user_id = ?', [$id, $user['id']]);
            qr_delete_logo($id);
            flash(t('Deleted "{title}".', ['title' => $link['title']]));
            redirect($link['campaign_id'] ? '/app/campaign.php?id=' . $link['campaign_id'] : '/app/');
    }
}

$short = short_url($link['code']);
$daily = daily_counts('l.id = ?', [$id], 30);
$total = (int) q("SELECT COUNT(*) FROM hits WHERE link_id = ? AND device <> 'bot'", [$id])->fetchColumn();
$last = q("SELECT MAX(created_at) FROM hits WHERE link_id = ? AND device <> 'bot'", [$id])->fetchColumn();
$devices = q("SELECT device, COUNT(*) n FROM hits WHERE link_id = ? AND device <> 'bot' GROUP BY device ORDER BY n DESC", [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = q("SELECT created_at, device, os, referer, city, country, lang, browser FROM hits WHERE link_id = ? AND device <> 'bot' ORDER BY id DESC LIMIT 25", [$id])->fetchAll();
$uniques = (int) q('SELECT ' . UNIQUES_SQL . " FROM hits h WHERE h.link_id = ? AND h.device <> 'bot'", [$id])->fetchColumn();
$places = q('SELECT ' . PLACE_SQL . " d, COUNT(*) n FROM hits h WHERE h.link_id = ? AND h.device <> 'bot' GROUP BY d ORDER BY n DESC LIMIT 10", [$id])->fetchAll();
$langs = q("SELECT h.lang d, COUNT(*) n FROM hits h WHERE h.link_id = ? AND h.device <> 'bot' GROUP BY h.lang ORDER BY n DESC LIMIT 8", [$id])->fetchAll();
$browsers = q("SELECT CASE WHEN h.browser <> '' THEN h.browser ELSE '— unknown —' END d, COUNT(*) n FROM hits h WHERE h.link_id = ? AND h.device <> 'bot' GROUP BY d ORDER BY n DESC LIMIT 8", [$id])->fetchAll();
// Fixed SQL labels (e.g. '— unknown —') are English; translate them for display.
$unknown = fn(array $rows) => array_map(fn($r) => ($r['d'] ?? null) === '— unknown —' ? ['d' => t('— unknown —')] + $r : $r, $rows);
$mobilePct = $total ? round((($devices['mobile'] ?? 0) + ($devices['tablet'] ?? 0)) / $total * 100) : 0;

page_head($link['title'], 'codes');
?>
<div class="code-page">
<span class="entity-type"><?= te('Code') ?></span>
<h2 class="entity-title"><?= e($link['title']) ?>
  <?php if ($link['campaign_name']): ?><a class="badge campaign" href="/app/campaign.php?id=<?= $link['campaign_id'] ?>" style="text-decoration:none"><?= e($link['campaign_name']) ?></a><?php endif; ?>
  <span class="badge <?= $link['active'] ? 'on' : 'off' ?>"><?= $link['active'] ? te('active') : te('paused') ?></span></h2>

<div class="code-top">
  <div class="code-qr">
    <div class="qr-big"><img id="qr-preview" src="/app/qr.php?id=<?= $id ?>&amp;v=<?= e(qr_version($link)) ?>" alt="<?= te('QR code for {url}', ['url' => $short]) ?>"></div>
    <div class="dl">
      <a class="button small" href="/app/qr.php?id=<?= $id ?>&amp;fmt=png&amp;dl=1" download>↓ PNG</a>
      <a class="button small" href="/app/qr.php?id=<?= $id ?>&amp;fmt=svg&amp;dl=1" download>↓ SVG</a>
      <a class="button small ghost jump-style" href="#style">🎨 <?= te('Style') ?></a>
    </div>
  </div>
  <div class="code-info">
    <label class="copy-label" for="short-link"><?= te('Link') ?></label>
    <div class="copy-field">
      <input id="short-link" value="<?= e($short) ?>" readonly onclick="this.select()">
      <button class="button" data-copy="<?= e($short) ?>"><?= te('Copy') ?></button>
    </div>
    <dl class="kv">
      <dt><?= te('Target') ?></dt><dd class="mono"><a href="<?= e($link['target_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['target_url']) ?></a></dd>
      <dt><?= te('Placement') ?></dt><dd><?= $link['placement'] !== '' ? e($link['placement']) : '—' ?></dd>
      <dt><?= te('UTM tags') ?></dt><dd><?= $link['utm'] ? te('on') : te('off') ?></dd>
      <dt><?= te('Campaign') ?></dt><dd><?= $link['campaign_name'] ? e($link['campaign_name']) : '—' ?></dd>
      <dt><?= te('Created') ?></dt><dd><?= e(fmt_date($link['created_at'])) ?></dd>
    </dl>
    <div class="form-actions" style="justify-content:flex-start">
      <a class="button" href="/app/edit.php?id=<?= $id ?>"><?= te('Edit') ?></a>
      <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle">
        <button class="button ghost"><?= $link['active'] ? te('Pause') : te('Activate') ?></button></form>
      <form class="inline-form" method="post" data-confirm="<?= te('Delete this code and all its stats? Printed codes will stop working.') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="button danger"><?= te('Delete') ?></button></form>
    </div>
  </div>
  <div class="code-style" id="style"><?php style_form($link, '/app/qr.php?id=' . $id, csrf_field()); ?></div>
</div>

<section class="block" style="margin-top:2rem">
  <h3><?= te('Scans') ?></h3><p class="hint"><?= te('Bots and link previews are not counted.') ?></p>
  <div class="tiles">
    <div class="tile"><div class="label"><?= te('Total') ?></div><div class="value"><?= number_format($total) ?></div></div>
    <div class="tile"><div class="label"><?= te('Unique visitors') ?></div><div class="value"><?= number_format($uniques) ?></div><div class="sub"><?= te('per day') ?></div></div>
    <div class="tile"><div class="label"><?= te('30 days') ?></div><div class="value"><?= number_format(array_sum($daily)) ?></div></div>
    <div class="tile"><div class="label"><?= te('Mobile') ?></div><div class="value"><?= $mobilePct ?>%</div></div>
    <div class="tile"><div class="label"><?= te('Last scan') ?></div><div class="value" style="font-size:1.1rem;padding-top:.6rem"><?= e(ago($last ?: null)) ?></div></div>
  </div>
  <?= bar_chart($daily) ?>
</section>

<section class="block">
  <h3><?= te('Where') ?></h3><p class="hint"><?= te('Approximate location from the IP address. The IP itself is never stored.') ?></p>
  <?= share_table($unknown($places), t('Place')) ?>
</section>

<section class="block">
  <h3><?= te('Language & browser') ?></h3><p class="hint"><?= te('Phone language setting and the app or browser that opened the link.') ?></p>
  <?= share_table($unknown(array_map(fn($r) => ['d' => lang_name($r['d']), 'n' => $r['n']], $langs)), t('Language')) ?>
  <div style="height:1rem"></div>
  <?= share_table($unknown($browsers), t('Browser')) ?>
</section>

<section class="block">
  <h3><?= te('Recent scans') ?></h3><p class="hint"><?= te('Newest first, last 25.') ?></p>
  <?php if (!$recent): ?><div class="empty"><?= te('No scans yet. Scan the code with your phone to test it.') ?></div><?php else: ?>
  <table class="data"><thead><tr><th><?= te('When') ?></th><th><?= te('Where') ?></th><th><?= te('Device') ?></th><th><?= te('Browser') ?></th><th><?= te('Lang') ?></th></tr></thead><tbody>
  <?php foreach ($recent as $h): ?>
    <tr><td><?= e(fmt_date($h['created_at'], 'M j, H:i')) ?></td><td><?= e($h['city'] ? $h['city'] . ', ' . $h['country'] : ($h['country'] ?: '—')) ?></td><td><?= e($h['device'] . ($h['os'] ? ' · ' . $h['os'] : '')) ?></td><td><?= e($h['browser'] ?: '—') ?></td><td><?= e($h['lang'] ?: '—') ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>
</div>
<?php page_foot();
