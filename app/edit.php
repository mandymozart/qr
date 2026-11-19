<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$id = int_param('id');
$link = $id ? find_link($id, $user) : null;
if ($id && !$link) { http_response_code(404); exit(t('Not found')); }

$data = $link ?? ['title' => '', 'target_url' => 'https://', 'code' => '', 'campaign_id' => int_param('campaign') ?: null, 'active' => 1, 'placement' => '', 'utm' => 1];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $data = [
        'title' => post('title'),
        'target_url' => post('target_url'),
        'code' => post('code'),
        'campaign_id' => (int) post('campaign_id') ?: null,
        'active' => isset($_POST['active']) ? 1 : 0,
        'placement' => mb_substr(post('placement'), 0, 190),
        'utm' => isset($_POST['utm']) ? 1 : 0,
    ];
    if ($data['title'] === '') $errors['title'] = t('Give it a name.');
    if ($data['target_url'] === 'https://' || $data['target_url'] === 'http://') $data['target_url'] = '';
    if ($data['target_url'] !== '' && !valid_url($data['target_url'])) $errors['target_url'] = t('Needs to be a full http(s):// URL.');
    if ($data['campaign_id'] && !find_campaign($data['campaign_id'], $user)) $data['campaign_id'] = null;

    if ($data['code'] === '') {
        $data['code'] = $link['code'] ?? unique_code();
    } elseif (!preg_match('~^[A-Za-z0-9_-]{2,40}$~', $data['code'])) {
        $errors['code'] = t('Letters, numbers, - and _ only (2–40).');
    } elseif (q('SELECT 1 FROM links WHERE code = ? AND id <> ?', [$data['code'], $id])->fetch()) {
        $errors['code'] = t('That code is taken.');
    }

    if (!$errors) {
        if ($link) {
            q('UPDATE links SET title = ?, target_url = ?, code = ?, campaign_id = ?, active = ?, placement = ?, utm = ? WHERE id = ? AND user_id = ?',
                [$data['title'], $data['target_url'], $data['code'], $data['campaign_id'], $data['active'], $data['placement'], $data['utm'], $id, $user['id']]);
            flash(t('Saved.'));
        } else {
            q('INSERT INTO links (user_id, campaign_id, code, title, target_url, active, placement, utm) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$user['id'], $data['campaign_id'], $data['code'], $data['title'], $data['target_url'], $data['active'], $data['placement'], $data['utm']]);
            $id = (int) db()->lastInsertId();
            flash(t('Code created.'));
        }
        // new code with "show a mini-app first": continue in the mini-app editor with the chosen preset
        // side cards on a new code: a picked mini-app preset continues in the mini-app editor, "style it" opens the style card
        $presetKey = post('preset');
        if ($presetKey !== '' && isset(step_presets()[$presetKey]) && !step_for_link($id)) redirect('/app/step.php?id=' . $id . '&preset=' . rawurlencode($presetKey));
        // no target and no mini-app yet: go set up the hosted page right away
        if ($data['target_url'] === '' && !step_for_link($id)) redirect('/app/step.php?id=' . $id . '&preset=flyer');
        redirect('/app/code.php?id=' . $id . (post('style_next') ? '#style' : ''));
    }
}

function err(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="field-hint" style="color:var(--danger)">' . e($errors[$k]) . '</div>' : '';
}

page_head($link ? t('Edit code') : t('New code'), 'codes');
?>
<div class="edit-page">
<span class="entity-type"><?= $link ? te('Edit code') : te('New code') ?></span>
<h2 class="entity-title"><?= $link ? e($link['title']) : te('Create a QR link') ?></h2>
<p class="entity-description"><?= te('The QR code always points to the short link, so you can change the target later without reprinting.') ?></p>

<?php $moreOpen = isset($errors['code']) || (!$link && $data['campaign_id']); ?>
<form method="post" class="edit-form" id="edit-form">
  <?= csrf_field() ?>
  <div class="field"><label for="title"><?= te('Name') ?></label>
    <input id="title" name="title" required value="<?= e($data['title']) ?>" placeholder="<?= te('Flyer Westbahnhof') ?>"><?= err($errors, 'title') ?></div>
  <div class="field"><label for="target_url"><?= te('Target URL') ?></label>
    <input id="target_url" name="target_url" type="url" value="<?= e($data['target_url']) ?>"><?= err($errors, 'target_url') ?: '<div class="field-hint">' . te('Leave empty to host a small page or flyer instead (set it up as a mini-app after saving).') . '</div>' ?></div>

  <details class="more-options"<?= $moreOpen ? ' open' : '' ?>>
    <summary><?= te('More options') ?> <small><?= te('short code, campaign, placement, UTM') ?></small></summary>
    <div class="form-grid">
      <div class="field"><label for="code"><?= te('Short code') ?></label>
        <input id="code" name="code" value="<?= e($data['code']) ?>" placeholder="<?= te('auto') ?>" pattern="[A-Za-z0-9_\-]{2,40}">
        <?= err($errors, 'code') ?: '<div class="field-hint">' . te('Leave empty for a random one. Changing it breaks printed codes.') . '</div>' ?></div>
      <div class="field"><label for="campaign_id"><?= te('Campaign') ?></label><?= campaign_select($user, $data['campaign_id'] ? (int) $data['campaign_id'] : null) ?>
        <div class="field-hint"><a href="/app/campaigns.php"><?= te('Manage campaigns') ?></a></div></div>
    </div>
    <div class="field"><label for="placement"><?= te('Placement') ?> <span class="opt"><?= te('(optional)') ?></span></label>
      <input id="placement" name="placement" value="<?= e($data['placement']) ?>" placeholder="<?= te('e.g. Poster, Westbahnhof U6 exit') ?>">
      <div class="field-hint"><?= te('Where the code is printed or hung. Shows up in the stats.') ?></div></div>
    <div class="field check"><label><input type="checkbox" name="utm" value="1"<?= $data['utm'] ? ' checked' : '' ?>> <?= t('Add UTM tags to the target ({utm}), so your website analytics see where visitors came from', ['utm' => '<code>utm_source=qr&amp;utm_campaign=…&amp;utm_content=' . e($data['code'] ?: 'code') . '</code>']) ?></label></div>
    <div class="field check"><label><input type="checkbox" name="active" value="1"<?= $data['active'] ? ' checked' : '' ?>> <?= te('Active (paused codes show a "not found" page)') ?></label></div>
  </details>

  <div class="form-actions" style="justify-content:flex-start">
    <button class="button"><?= $link ? te('Save') : te('Create code') ?></button>
    <a class="button ghost" href="<?= $link ? '/app/code.php?id=' . $link['id'] : '/app/' ?>"><?= te('Cancel') ?></a>
  </div>

  <?php if (!$link): ?>
  <aside class="edit-side">
    <p class="side-label"><?= te('Extras, if you like') ?></p>
    <?= feature_card('style-next', 'style', t('Style it'), t('Dots, colors, an emoji or your logo in the middle.'),
        '<input type="hidden" name="style_next" value="1"><p>' . te('Right after saving you pick the look, with a live preview.') . '</p>', (bool) post('style_next')) ?>
    <?= feature_card('mini-next', 'miniapp', t('Show a mini-app first'), t('A flyer page, sign-up form, poll or ticket button between the scan and the target.'),
        step_preset_cards(post('preset') ?: null, '', true) . '<p class="fc-foot">' . te('You set it up right after saving.') . '</p>', post('preset') !== '', t('New')) ?>
  </aside>
  <?php endif; ?>
</form>
</div>
<?php page_foot();
