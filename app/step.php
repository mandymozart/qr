<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

/*
 * Owner editor for a code's mini-app + its responses.
 *   /app/step.php?id=LINK                 editor + responses
 *   /app/step.php?id=LINK&export=csv      all responses as CSV
 *   /app/step.php?id=LINK&file=NAME       download an uploaded photo/file
 */
$user = require_user();
$link = find_link(int_param('id'), $user);
if (!$link) { http_response_code(404); exit(t('Not found')); }
$id = (int) $link['id'];
$step = step_for_link($id);
$self = '/app/step.php?id=' . $id;

// Downloads of uploaded files
if ($step && isset($_GET['file'])) {
    $name = basename((string) $_GET['file']);
    $path = STEP_UPLOAD_DIR . '/' . (int) $step['id'] . '/' . $name;
    if (!preg_match('~^[a-f0-9]{24}\.(jpg|pdf)$~', $name) || !is_file($path)) { http_response_code(404); exit(t('Not found')); }
    header('Content-Type: ' . (str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/jpeg'));
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . slug($link['title']) . '-' . $name . '"');
    readfile($path);
    exit;
}

// CSV export
if ($step && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . slug($link['title']) . '-responses-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
    if ($step['type'] === 'poll') {
        fputcsv($out, [t('Time'), t('Answer')]);
    } else {
        fputcsv($out, array_merge([t('Time')], array_map(fn($f) => $f['label'], $step['config']['fields'] ?? [])));
    }
    foreach (q('SELECT created_at, data FROM responses WHERE step_id = ? ORDER BY id', [$step['id']])->fetchAll() as $r) {
        $d = json_decode((string) $r['data'], true) ?: [];
        if ($step['type'] === 'poll') {
            fputcsv($out, [$r['created_at'], $step['config']['options'][(int) ($d['choice'] ?? -1)] ?? '']);
            continue;
        }
        $row = [$r['created_at']];
        foreach ($step['config']['fields'] ?? [] as $i => $f) {
            $v = $d[step_field_key($i)] ?? '';
            $row[] = is_array($v) ? base_url() . $self . '&file=' . $v['file'] : ($f['type'] === 'checkbox' ? ($v ? t('yes') : '') : $v);
        }
        fputcsv($out, $row);
    }
    exit;
}

$errors = [];
$form = $step;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = post('action');
    if ($action === 'remove' && $step) {
        step_delete($step);
        flash(t('Mini-app removed. The code goes straight to its target again.'));
        redirect('/app/code.php?id=' . $id);
    }
    if ($action === 'clear' && $step) {
        step_delete_files((int) $step['id']);
        q('DELETE FROM responses WHERE step_id = ?', [$step['id']]);
        flash(t('All responses deleted.'));
        redirect($self . '#responses');
    }
    if ($action === 'save') {
        [$new, $errors] = step_from_post();
        if ($new['type'] !== 'page' && trim((string) $link['target_url']) === '' && $new['type'] !== 'info') {
            // a form/poll without a target simply ends on a thank-you note; that's fine
        }
        if (!$errors) {
            if ($new['type'] === 'page') {
                $keep = ($step['type'] ?? '') === 'page' ? ($step['config']['image'] ?? '') : '';
                if (isset($_POST['remove_image'])) $keep = '';
                $new['config']['image'] = $keep;
            }
            if ($step && $step['type'] !== $new['type']) {
                // answers of another type don't fit (form fields vs. poll votes): start fresh
                step_delete_files((int) $step['id']);
                q('DELETE FROM responses WHERE step_id = ?', [$step['id']]);
            }
            step_save($id, $new);
            $saved = step_for_link($id);
            // flyer image upload (after save, it needs the step id)
            if ($new['type'] === 'page' && !empty($_FILES['page_image']['name'])) {
                $res = step_store_upload($_FILES['page_image'], 'photo', STEP_UPLOAD_DIR . '/' . (int) $saved['id']);
                if (str_starts_with($res, 'error:')) {
                    flash(substr($res, 6), 'err');
                    redirect($self);
                }
                $saved['config']['image'] = $res;
                q('UPDATE steps SET config = ? WHERE id = ?', [json_encode($saved['config'], JSON_UNESCAPED_UNICODE), $saved['id']]);
            }
            flash($step ? t('Mini-app saved.') : t('Mini-app created. Scan your code to try it.'));
            redirect($self);
        }
        $form = $new + ['id' => $step['id'] ?? 0];
    }
}

$presets = step_presets();
$total = $step ? (int) q('SELECT COUNT(*) FROM responses WHERE step_id = ?', [$step['id']])->fetchColumn() : 0;
$form ??= null;
$type = $form['type'] ?? '';
$cfg = $form['config'] ?? [];
$fields = $cfg['fields'] ?? [];
$links = $cfg['links'] ?? [];
$recent = $step ? q('SELECT * FROM responses WHERE step_id = ? ORDER BY id DESC LIMIT 100', [$step['id']])->fetchAll() : [];
$short = short_url($link['code']);

page_head(t('Mini-app') . ' · ' . $link['title'], 'codes');
?>
<div class="page-head">
  <div>
    <span class="entity-type"><?= te('Mini-app') ?></span>
    <h2 class="entity-title"><?= e($link['title']) ?></h2>
    <p class="entity-description"><?= te('Show something between the scan and the target: a flyer page, a sign-up form, a poll or a ticket button.') ?></p>
  </div>
  <div><a class="button ghost" href="/app/code.php?id=<?= $id ?>">← <?= te('Back to the code') ?></a>
    <?php if ($step): ?><a class="button" href="<?= e($short) ?>" target="_blank" rel="noopener"><?= te('Try it') ?> ↗</a><?php endif; ?></div>
</div>

<?php if ($errors): ?><div class="notice err"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<?php $await = !$form && !isset($presets[$_GET['preset'] ?? '']); // new mini-app: the form opens once a preset (or a blank type) is picked ?>
<div class="step-editor-grid">
<aside class="step-presets">
  <h3 class="side-label"><?= te('Start from a preset') ?></h3>
  <?= step_preset_cards($cfg['preset'] ?? null, 'step-editor') ?>
  <p class="blank-start"<?= $await ? '' : ' hidden' ?>><span><?= te('or start blank:') ?></span>
    <?php foreach (STEP_TYPES as $t): ?><button type="button" class="button small ghost" data-blank="<?= $t ?>"><?= e(step_type_label($t)) ?></button><?php endforeach; ?></p>
</aside>

<div class="step-main">
<div class="step-wait"<?= $await ? '' : ' hidden' ?>>
  <?= step_preset_art('miniapp') ?>
  <p><b><?= te('Pick a preset to start.') ?></b><br><?= te('You can change every text and field afterwards.') ?></p>
</div>
<form method="post" enctype="multipart/form-data" class="step-editor" id="step-editor"<?= $await ? ' hidden' : '' ?>>
  <?= csrf_field() ?><input type="hidden" name="action" value="save">

  <fieldset>
    <legend><?= te('Type') ?></legend>
    <div class="choices">
      <?php foreach (STEP_TYPES as $t): ?>
        <label class="choice"><input type="radio" name="type" value="<?= $t ?>"<?= $type === $t ? ' checked' : '' ?>><span><?= e(step_type_label($t)) ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <?php if ($step && $total): ?><p class="field-hint"><?= te('Changing the type deletes the existing responses.') ?></p><?php endif; ?>
  <div class="field"><label for="st-title"><?= te('Title') ?></label><input id="st-title" name="title" value="<?= e($form['title'] ?? '') ?>" maxlength="190"></div>
  <div class="field"><label for="st-intro"><?= te('Text') ?> <span class="opt"><?= te('(optional)') ?></span></label><textarea id="st-intro" name="intro" rows="4"><?= e($form['intro'] ?? '') ?></textarea></div>

  <div class="type-panel" data-type="page">
    <div class="field"><label><?= te('Flyer or image') ?> <span class="opt"><?= te('(optional)') ?></span></label>
      <label class="dropzone step-drop<?= !empty($cfg['image']) ? ' has-file' : '' ?>"><input type="file" name="page_image" accept="image/*">
        <span class="dz-thumb"><?php if (!empty($cfg['image']) && $step): ?><img src="/step.php?c=<?= e(rawurlencode($link['code'])) ?>&amp;img=1" alt=""><?php endif; ?></span>
        <span class="dz-text"><b data-default="<?= te('Drop your flyer here') ?>"><?= !empty($cfg['image']) ? te('Your flyer') : te('Drop your flyer here') ?></b><small><?= te('JPG, PNG, WebP · max. 10 MB · shown on top of the page') ?></small></span></label>
      <?php if (!empty($cfg['image'])): ?><label class="field check"><span><input type="checkbox" name="remove_image" value="1"> <?= te('Remove image') ?></span></label><?php endif; ?>
    </div>
    <div class="field"><label><?= te('Buttons') ?> <span class="opt"><?= te('(up to {n})', ['n' => STEP_MAX_LINKS]) ?></span></label>
      <div class="link-rows">
        <?php for ($i = 0; $i < STEP_MAX_LINKS; $i++): $l = $links[$i] ?? ['label' => '', 'url' => '']; ?>
          <div class="link-row"><input name="links[<?= $i ?>][label]" value="<?= e($l['label']) ?>" placeholder="<?= te('Label, e.g. Tickets') ?>"><input name="links[<?= $i ?>][url]" value="<?= e($l['url']) ?>" placeholder="https://"></div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="type-panel" data-type="form">
    <div class="field"><label><?= te('Fields') ?></label>
      <div class="field-rows" id="field-rows">
        <?php foreach ($fields ?: [['label' => '', 'type' => 'text', 'required' => false]] as $i => $f): ?>
          <div class="field-row">
            <input name="fields[<?= $i ?>][label]" value="<?= e($f['label']) ?>" placeholder="<?= te('Question or label') ?>">
            <select name="fields[<?= $i ?>][type]">
              <?php foreach (['text' => t('Short text'), 'email' => t('Email'), 'tel' => t('Phone'), 'number' => t('Number'), 'textarea' => t('Long text'), 'select' => t('Choice'), 'checkbox' => t('Checkbox'), 'photo' => t('Photo (camera)'), 'file' => t('File upload')] as $k => $lbl): ?>
                <option value="<?= $k ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="req"><input type="checkbox" name="fields[<?= $i ?>][required]" value="1"<?= !empty($f['required']) ? ' checked' : '' ?>> <?= te('required') ?></label>
            <button type="button" class="button small danger rm-field" title="<?= te('Remove') ?>">✕</button>
            <textarea class="opts" name="fields[<?= $i ?>][options]" rows="3" placeholder="<?= te('Options, one per line') ?>"<?= $f['type'] === 'select' ? '' : ' hidden' ?>><?= e(implode("\n", $f['options'] ?? [])) ?></textarea>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button small ghost" id="add-field">+ <?= te('Add field') ?></button>
    </div>
    <div class="form-grid">
      <div class="field"><label for="st-button"><?= te('Button text') ?></label><input id="st-button" name="button" value="<?= e($cfg['button'] ?? '') ?>" placeholder="<?= te('Continue') ?>"></div>
      <div class="field"><label for="st-privacy"><?= te('Your privacy policy') ?> <span class="opt"><?= te('(optional)') ?></span></label><input id="st-privacy" name="privacy_url" value="<?= e($cfg['privacy_url'] ?? '') ?>" placeholder="https://"></div>
    </div>
    <p class="field-hint"><?= te('People always have to tick a consent box saying their answers go to you. Only ask for what you need.') ?></p>
  </div>

  <div class="type-panel" data-type="poll">
    <div class="field"><label for="st-opts"><?= te('Options, one per line') ?></label>
      <textarea id="st-opts" name="poll_options" rows="5"><?= e(implode("\n", $cfg['options'] ?? [])) ?></textarea></div>
    <div class="field check"><label><input type="checkbox" name="show_results" value="1"<?= !empty($cfg['show_results']) || !$form ? ' checked' : '' ?>> <?= te('Show the results after voting') ?></label></div>
  </div>

  <div class="type-panel" data-type="info">
    <div class="form-grid">
      <div class="field"><label for="st-bl"><?= te('Button text') ?></label><input id="st-bl" name="button_label" value="<?= e($cfg['button_label'] ?? '') ?>" placeholder="<?= te('Buy ticket') ?>"></div>
      <div class="field"><label for="st-bu"><?= te('Button link') ?></label><input id="st-bu" name="button_url" value="<?= e($cfg['button_url'] ?? 'https://') ?>"></div>
    </div>
    <p class="field-hint"><?= te('Tip: paste your ticket shop or a Stripe payment link here.') ?></p>
  </div>

  <div class="field check"><label><input type="checkbox" name="required" value="1"<?= !empty($form['required']) ? ' checked' : '' ?>> <?= te('Required: people cannot skip to the target') ?></label></div>
  <div class="field check"><label><input type="checkbox" name="active" value="1"<?= !$form || !empty($form['active']) ? ' checked' : '' ?>> <?= te('Active') ?></label></div>
  <?php if (trim((string) $link['target_url']) === ''): ?><p class="field-hint"><?= te('This code has no target, so the mini-app is the final page.') ?></p><?php endif; ?>

  <div class="form-actions" style="justify-content:flex-start">
    <button class="button"><?= $step ? te('Save mini-app') : te('Create mini-app') ?></button>
  </div>
</form>
</div>

<aside class="step-preview" aria-label="<?= te('Live preview') ?>">
  <h3 class="side-label"><?= te('Live preview') ?></h3>
  <div class="phone"><div class="phone-screen" id="step-pv"
    data-img="<?= !empty($cfg['image']) && $step ? e('/step.php?c=' . rawurlencode($link['code']) . '&img=1') : '' ?>"
    data-t-skip="<?= te('Skip') ?> →" data-t-photo="📷 <?= te('Tap to take a photo') ?>" data-t-file="📄 <?= te('File upload') ?>"
    data-t-consent="<?= te('I agree that my answers are sent to the owner of this code.') ?>" data-t-continue="<?= te('Continue') ?>"
    data-t-open="<?= te('Open') ?>" data-t-empty="<?= te('Pick a preset or a type to see the preview.') ?>"></div></div>
</aside>
</div>

<?php if ($step): ?>
<div class="step-side" id="responses">
  <h3><?= te('Responses') ?> <span class="badge"><?= $total ?></span></h3>
  <?php if ($step['type'] === 'page' || $step['type'] === 'info'): ?>
    <p class="hint"><?= te('This type has no answers. Its visits are counted in the code stats.') ?></p>
  <?php elseif ($step['type'] === 'poll'): [$counts, $sum] = step_poll_results($step); ?>
    <div class="poll-results">
      <?php foreach ($step['config']['options'] as $i => $o): $pct = $sum ? round($counts[$i] / $sum * 100) : 0; ?>
        <div class="poll-row"><span class="poll-label"><?= e($o) ?></span><span class="poll-bar"><i style="width:<?= $pct ?>%"></i></span><b><?= (int) $counts[$i] ?></b></div>
      <?php endforeach; ?>
    </div>
  <?php elseif (!$recent): ?>
    <div class="empty"><?= te('No responses yet. Scan the code and try it yourself.') ?></div>
  <?php else: ?>
    <div class="table-scroll"><table class="data">
      <thead><tr><th><?= te('When') ?></th><?php foreach ($step['config']['fields'] ?? [] as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): $d = json_decode((string) $r['data'], true) ?: []; ?>
        <tr><td><?= e(fmt_date($r['created_at'], 'M j, H:i')) ?></td>
          <?php foreach ($step['config']['fields'] ?? [] as $i => $f): $v = $d[step_field_key($i)] ?? ''; ?>
            <td><?php if (is_array($v)): ?>
              <a href="<?= e($self) ?>&amp;file=<?= e($v['file']) ?>" target="_blank"><?php if (str_ends_with($v['file'], '.jpg')): ?><img class="resp-thumb" src="<?= e($self) ?>&amp;file=<?= e($v['file']) ?>" alt="" loading="lazy"><?php else: ?>📄 PDF<?php endif; ?></a>
            <?php else: ?><?= $f['type'] === 'checkbox' ? ($v ? '✓' : '') : e($v) ?><?php endif; ?></td>
          <?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php endif; ?>
  <?php if ($total && $step['type'] !== 'page' && $step['type'] !== 'info'): ?>
    <div class="form-actions" style="justify-content:flex-start">
      <a class="button small" href="<?= e($self) ?>&amp;export=csv"><?= te('Export CSV') ?></a>
      <form class="inline-form" method="post" data-confirm="<?= te('Delete all responses (and uploaded files)? This cannot be undone.') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="button small danger"><?= te('Delete all responses') ?></button></form>
    </div>
  <?php endif; ?>
  <form method="post" class="remove-step" data-confirm="<?= te('Remove the mini-app and all its responses?') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="remove">
    <button class="button small ghost"><?= te('Remove mini-app') ?></button></form>
</div>
<?php endif; ?>

<script>
window.STEP_PRESETS = <?= json_encode(array_map(fn($p) => array_diff_key($p, ['label' => 1]), $presets), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
<?php if (!$step && isset($presets[$_GET['preset'] ?? ''])): ?>
window.STEP_INIT_PRESET = <?= json_encode($_GET['preset']) ?>;
<?php endif; ?>
</script>
<?php page_foot();
