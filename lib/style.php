<?php
declare(strict_types=1);

/*
 * QR style editor shared by the account code page (app/code.php) and the guest edit page (manage.php).
 *   style_save($link)  → handles the POST (incl. logo upload), runs the scan check, returns [flashType, message]
 *   style_form(...)    → the form with live preview
 */

function style_save(array $link): array
{
    $id = (int) $link['id'];
    $style = qr_style(['shape' => post('shape'), 'color' => post('color'), 'logo' => post('logo')]);
    $newLogo = false;

    if ($style['logo'] === 'upload' && !empty($_FILES['logo_file']['name'])) {
        // keep the previous logo until the new style is confirmed to scan
        $old = QR_LOGO_DIR . "/$id.png";
        $backup = is_file($old) ? (string) file_get_contents($old) : null;
        $err = qr_store_logo($_FILES['logo_file'], $id);
        if ($err) {
            return ['err', match ($err) {
                'size' => t('That image is too big (max. 3 MB).'),
                'type' => t('Please upload a PNG, JPG, GIF or WebP image.'),
                default => t('The upload did not work. Please try again.'),
            }];
        }
        $newLogo = $backup;
    }

    $logo = qr_logo_file($style, $id);
    if ($style['logo'] === 'upload' && !$logo) {
        return ['err', t('Choose an image to upload as your logo.')];
    }

    if (!qr_scans(short_url($link['code']), $style, $logo)) {
        if ($newLogo !== false) {
            $newLogo === null ? qr_delete_logo($id) : file_put_contents(QR_LOGO_DIR . "/$id.png", $newLogo);
        }
        return ['err', t("This style didn't scan reliably in our test, so we kept the old one. Try a smaller or simpler logo, or another color.")];
    }

    q('UPDATE links SET style = ? WHERE id = ?', [qr_is_default($style) ? null : json_encode($style), $id]);
    if ($style['logo'] !== 'upload') qr_delete_logo($id);
    return ['ok', t('Style saved. We test-scanned it and it works.')];
}

/**
 * @param string $img     current image URL (preview base; style params are appended by JS)
 * @param string $hidden  extra hidden inputs (csrf, edit key)
 */
function style_form(array $link, string $img, string $hidden): void
{
    $style = qr_style($link['style'] ?? null);
    $hasUpload = is_file(QR_LOGO_DIR . '/' . (int) $link['id'] . '.png');
    ?>
<section class="block style-editor">
  <h3><?= te('Style') ?></h3>
  <p class="hint"><?= te('Make it yours. Every style is test-scanned before it is saved.') ?></p>
  <form method="post" enctype="multipart/form-data" class="style-form" data-preview="#qr-preview" data-base="<?= e($img) ?>">
    <?= $hidden ?>
    <input type="hidden" name="action" value="style">
    <fieldset>
      <legend><?= te('Shape') ?></legend>
      <div class="choices">
        <?php foreach (['square' => t('Squares'), 'dots' => t('Dots')] as $k => $label): ?>
          <label class="choice"><input type="radio" name="shape" value="<?= $k ?>"<?= $style['shape'] === $k ? ' checked' : '' ?>><span><i class="shape-<?= $k ?>"></i><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= te('Colors') ?></legend>
      <div class="choices">
        <?php foreach (QR_COLORS as $k => $c):
            $d = is_array($c['data']) ? 'linear-gradient(135deg,' . $c['data'][0] . ',' . $c['data'][1] . ')' : $c['data']; ?>
          <label class="choice"><input type="radio" name="color" value="<?= $k ?>"<?= $style['color'] === $k ? ' checked' : '' ?>>
            <span><i class="swatch" style="background:<?= e($d) ?>;box-shadow:inset 0 0 0 4px <?= e($c['eye']) ?>"></i><?= te($c['label']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= te('Logo in the middle') ?></legend>
      <div class="choices">
        <?php foreach (['none' => t('None'), 'badge' => t('qr sticker'), 'upload' => t('My own')] as $k => $label): ?>
          <label class="choice"><input type="radio" name="logo" value="<?= $k ?>"<?= $style['logo'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="logo-upload"<?= $style['logo'] === 'upload' ? '' : ' hidden' ?>>
        <input type="file" name="logo_file" accept="image/png,image/jpeg,image/gif,image/webp">
        <p class="field-hint"><?= $hasUpload ? te('A logo is uploaded. Choose a new file to replace it.') : te('Square images work best. PNG, JPG, GIF or WebP, max. 3 MB.') ?></p>
      </div>
    </fieldset>
    <p class="field-hint"><?= te('Tip: keep the colors dark and print the code at least 2 cm wide. Logos make the code a bit denser.') ?></p>
    <button class="button"><?= te('Save style') ?></button>
  </form>
</section>
<?php
}
