<?php
declare(strict_types=1);

/*
 * Guest code page: https://qr.viennastruggle.at/manage.php?k=EDIT_KEY  (from any browser)
 *                  https://qr.viennastruggle.at/manage.php?id=ID       (from the browser that created it)
 */
require_once __DIR__ . '/lib/app.php';

$link = guest_link_access();
$viaKey = isset($_GET['k']) || isset($_POST['k']);
$key = (string) ($_GET['k'] ?? $_POST['k'] ?? '');

if (!$link || $link['user_id']) {
    http_response_code(404);
    public_head('Not found · qr');
    ?>
    <section class="hero">
      <span class="entity-type">Edit link</span>
      <h1><?= $link ? 'This code now lives in an account.' : "This edit link doesn't work." ?></h1>
      <p class="entity-description"><?= $link
          ? 'It was moved into an account when its owner signed in. <a href="/app/login.php">Sign in</a> to manage it.'
          : 'It may have been replaced by a newer edit link, or the code was deleted.' ?></p>
      <p><a class="button" href="/">Make a new QR code</a></p>
    </section>
    <?php
    public_foot();
    exit;
}

$id = (int) $link['id'];
$self = '/manage.php?' . ($viaKey ? 'k=' . rawurlencode($key) : 'id=' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'save':
            if (!valid_url(post('target_url'))) { flash('Please use a full web address, starting with https://', 'err'); break; }
            q('UPDATE links SET title = ?, target_url = ? WHERE id = ?', [mb_substr(post('title') ?: $link['title'], 0, 160), post('target_url'), $id]);
            flash('Saved. The QR code stays the same and now leads to the new address.');
            break;
        case 'toggle':
            q('UPDATE links SET active = 1 - active WHERE id = ?', [$id]);
            flash($link['active'] ? 'Paused. People scanning it see a "not found" page.' : 'Active again.');
            break;
        case 'newkey':
            $edit = new_edit_link($id);
            flash('New edit link created. The old one no longer works.', 'info');
            redirect($edit . '&new=1');
        case 'delete':
            q('DELETE FROM links WHERE id = ?', [$id]);
            flash('Deleted.');
            redirect('/');
    }
    redirect($self);
}

$short = short_url($link['code']);
$editUrl = $viaKey ? base_url() . '/manage.php?k=' . $key : null;
$img = '/app/qr.php?' . ($viaKey ? 'k=' . rawurlencode($key) : 'id=' . $id) . '&v=' . rawurlencode($link['code']);
$daily = daily_counts('l.id = ?', [$id], 30);
$total = (int) q("SELECT COUNT(*) FROM hits WHERE link_id = ? AND device <> 'bot'", [$id])->fetchColumn();
$uniques = (int) q('SELECT ' . UNIQUES_SQL . " FROM hits h WHERE h.link_id = ? AND h.device <> 'bot'", [$id])->fetchColumn();

public_head($link['title'] . ' · qr');
?>
<?php if (isset($_GET['new'])): ?>
  <div class="notice info"><strong>Save your edit link!</strong> It's the only way to change this code from another browser or device.</div>
<?php endif; ?>

<span class="entity-type">Your QR code</span>
<h2 class="entity-title"><?= e($link['title']) ?> <span class="badge <?= $link['active'] ? 'on' : 'off' ?>"><?= $link['active'] ? 'active' : 'paused' ?></span></h2>

<div class="qr-panel" style="margin-top:1rem">
  <div>
    <div class="qr-big"><img src="<?= e($img) ?>" alt="QR code for <?= e($short) ?>"></div>
    <div class="dl">
      <a class="button small" href="<?= e($img) ?>&amp;fmt=png&amp;dl=1" download>↓ PNG</a>
      <a class="button small" href="<?= e($img) ?>&amp;fmt=svg&amp;dl=1" download>↓ SVG</a>
    </div>
  </div>
  <div>
    <label class="copy-label" for="short-link">Link in the QR code</label>
    <div class="copy-field">
      <input id="short-link" value="<?= e($short) ?>" readonly onclick="this.select()">
      <button class="button" data-copy="<?= e($short) ?>">Copy</button>
    </div>

    <label class="copy-label" for="edit-link">Your secret edit link</label>
    <?php if ($editUrl): ?>
      <div class="copy-field">
        <input id="edit-link" class="secret" value="<?= e($editUrl) ?>" readonly onclick="this.select()">
        <button class="button ghost" data-copy="<?= e($editUrl) ?>">Copy</button>
      </div>
      <p class="field-hint" style="margin-top:-.8rem">Anyone with this link can change the code. Bookmark it or keep it somewhere safe.</p>
    <?php else: ?>
      <form method="post" class="copy-field"><input type="hidden" name="action" value="newkey">
        <span class="field-hint" style="flex:1">For security we only show it once. Lost it?</span>
        <button class="button ghost small">Make a new edit link</button></form>
    <?php endif; ?>
  </div>
</div>

<section class="block" style="margin-top:1.5rem">
  <h3>Change</h3>
  <form method="post">
    <?php if ($viaKey): ?><input type="hidden" name="k" value="<?= e($key) ?>"><?php endif; ?>
    <input type="hidden" name="action" value="save">
    <div class="field"><label for="target_url">Leads to</label><input id="target_url" name="target_url" type="url" required value="<?= e($link['target_url']) ?>"></div>
    <div class="field"><label for="title">Name (just for you)</label><input id="title" name="title" value="<?= e($link['title']) ?>"></div>
    <div class="form-actions" style="justify-content:flex-start">
      <button class="button">Save</button>
    </div>
  </form>
  <div class="form-actions" style="justify-content:flex-start">
    <form class="inline-form" method="post"><?php if ($viaKey): ?><input type="hidden" name="k" value="<?= e($key) ?>"><?php endif; ?><input type="hidden" name="action" value="toggle">
      <button class="button ghost"><?= $link['active'] ? 'Pause' : 'Activate' ?></button></form>
    <form class="inline-form" method="post" data-confirm="Delete this QR code? Printed copies will stop working."><?php if ($viaKey): ?><input type="hidden" name="k" value="<?= e($key) ?>"><?php endif; ?><input type="hidden" name="action" value="delete">
      <button class="button danger">Delete</button></form>
  </div>
</section>

<section class="block">
  <h3>Scans</h3><p class="hint">Last 30 days. Bots and link previews are not counted.</p>
  <div class="tiles">
    <div class="tile"><div class="label">Total</div><div class="value"><?= number_format($total) ?></div></div>
    <div class="tile"><div class="label">Unique visitors</div><div class="value"><?= number_format($uniques) ?></div><div class="sub">per day</div></div>
    <div class="tile"><div class="label">30 days</div><div class="value"><?= number_format(array_sum($daily)) ?></div></div>
  </div>
  <?= bar_chart($daily) ?>
</section>

<section class="block cta">
  <h3>Want more?</h3>
  <p class="hint">With a free account you keep all your codes safely, make campaigns and bulk codes, and see where, when and on what devices people scan. Codes from this browser move in automatically.</p>
  <a class="button" href="/app/register.php">Create a free account</a>
</section>
<?php public_foot();
