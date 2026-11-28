<?php
/**
 * Gifts & laser products: option chips (Option 1 × Option 2), personalisation fields and quantity price breaks.
 * Inside the product <form>. Vars: $p (product), $variants, $default.
 */
$o1 = [];
$o2 = [];
foreach ($variants as $vr) {
    if ($vr['option1'] !== null && $vr['option1'] !== '') {
        $o1[$vr['option1']] ??= ['label' => $vr['option1_label'], 'color' => $vr['color_hex']];
    }
    if ($vr['option2'] !== null && $vr['option2'] !== '') {
        $o2[$vr['option2']] ??= ['label' => $vr['option2_label']];
    }
}
$groups = [['opt1', $p['option1_name'] ?: __('personalize.choose'), $o1, $default['option1'] ?? null], ['opt2', $p['option2_name'] ?: __('personalize.choose'), $o2, $default['option2'] ?? null]];
?>
<?php foreach ($groups as [$name, $legend, $values, $selected]): if ($values === []) continue; ?>
    <fieldset class="option-group">
        <legend><?= e($legend) ?> <span class="option-value" data-label="<?= $name ?>"><?= e((string) ($values[$selected]['label'] ?? '')) ?></span></legend>
        <div class="option-chips">
            <?php foreach ($values as $val => $info): ?>
                <label class="chip<?= !empty($info['color']) ? ' chip-color' : '' ?>"><input type="radio" name="<?= $name ?>" value="<?= e((string) $val) ?>" data-name="<?= e((string) $info['label']) ?>" <?= (string) $selected === (string) $val ? 'checked' : '' ?>>
                    <span><?php if (!empty($info['color'])): ?><i class="swatch" style="--swatch: <?= e($info['color']) ?>"></i><?php endif; ?><?= e((string) $info['label']) ?></span></label>
            <?php endforeach; ?>
        </div>
    </fieldset>
<?php endforeach; ?>
<?php if ($variants !== []): ?><p class="small text-danger d-none" data-unavailable><?= e(__('product.combination_unavailable')) ?></p><?php endif; ?>

<?php if ($p['personalization'] !== []): ?>
    <div class="perso-box" data-perso data-upload-url="<?= e(app()->router()->has('builder.upload') ? route('builder.upload') : '') ?>" data-uploading="<?= e(__('personalize.uploading')) ?>" data-change="<?= e(__('personalize.change_photo')) ?>">
        <div class="perso-title"><i class="bi bi-pencil-square me-1"></i><?= e(__('personalize.title')) ?></div>
        <?php foreach ($p['personalization'] as $f):
            $key = (string) $f['field_key'];
            $nameAttr = 'perso[' . e($key) . ']';
            $id = 'perso_' . e($key);
            $oldAll = old('perso', []);
            $old = is_array($oldAll) ? (string) ($oldAll[$key] ?? '') : '';
            $extra = (float) $f['price_add'] > 0 ? ' <span class="perso-extra">' . e(__('personalize.extra', ['price' => money((string) $f['price_add'])])) . '</span>' : '';
            $req = $f['is_required'] ? ' <span class="text-danger" aria-hidden="true">*</span>' : ''; ?>
            <div class="mb-3" data-perso-field data-price="<?= e((string) $f['price_add']) ?>">
                <label class="form-label" for="<?= $id ?>"><?= e((string) $f['label']) ?><?= $req . $extra ?></label>
                <?php switch ($f['field_type']):
                    case 'textarea': ?>
                        <textarea class="form-control<?= invalid('perso_' . $key) ?>" id="<?= $id ?>" name="<?= $nameAttr ?>" rows="3" maxlength="<?= (int) ($f['max_length'] ?: 500) ?>" placeholder="<?= e((string) $f['placeholder']) ?>" dir="auto" data-count <?= $f['is_required'] ? 'required' : '' ?>><?= e((string) $old) ?></textarea>
                        <?php break;
                    case 'font':
                    case 'select': ?>
                        <select class="form-select<?= invalid('perso_' . $key) ?><?= $f['field_type'] === 'font' ? ' font-select' : '' ?>" id="<?= $id ?>" name="<?= $nameAttr ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                            <option value=""><?= e(__('personalize.choose')) ?>…</option>
                            <?php foreach ($f['choices'] as $c): ?><option value="<?= e((string) $c) ?>" <?= (string) $old === (string) $c ? 'selected' : '' ?>><?= e((string) $c) ?></option><?php endforeach; ?>
                        </select>
                        <?php break;
                    case 'date': ?>
                        <input class="form-control<?= invalid('perso_' . $key) ?>" type="date" id="<?= $id ?>" name="<?= $nameAttr ?>" value="<?= e((string) $old) ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                        <?php break;
                    case 'photo': ?>
                        <input type="hidden" name="<?= $nameAttr ?>" value="<?= e((string) $old) ?>" data-photo-value>
                        <div class="perso-photo" data-photo-box data-print-w="<?= e((string) $f['print_width_cm']) ?>" data-print-h="<?= e((string) $f['print_height_cm']) ?>">
                            <img alt="" class="d-none" data-photo-thumb>
                            <label class="btn btn-outline-bwa btn-sm mb-0" for="<?= $id ?>"><i class="bi bi-upload me-1"></i><span data-photo-label><?= e(__('personalize.upload')) ?></span></label>
                            <input class="visually-hidden" type="file" id="<?= $id ?>" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" data-photo-input>
                            <span class="small text-muted-bwa" data-photo-status></span>
                        </div>
                        <div class="small text-warning d-none" data-photo-lowres><i class="bi bi-exclamation-triangle me-1"></i><?= e(__('personalize.low_res')) ?></div>
                        <div class="form-text"><i class="bi bi-shield-lock me-1"></i><?= e(__('personalize.photo_private')) ?></div>
                        <?php break;
                    default: ?>
                        <input class="form-control<?= invalid('perso_' . $key) ?>" id="<?= $id ?>" name="<?= $nameAttr ?>" value="<?= e((string) $old) ?>" maxlength="<?= (int) ($f['max_length'] ?: 60) ?>" placeholder="<?= e((string) $f['placeholder']) ?>" dir="auto" data-count <?= $f['is_required'] ? 'required' : '' ?>>
                <?php endswitch; ?>
                <?php if (error('perso_' . $key)): ?><div class="invalid-feedback d-block"><?= e(error('perso_' . $key)) ?></div><?php endif; ?>
                <?php if (!empty($f['help'])): ?><div class="form-text"><?= e((string) $f['help']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($p['tiers'] !== [] || (int) $p['min_qty'] > 1 || (int) $p['requires_proof'] === 1 || $p['production_days'] !== null): ?>
    <div class="gift-notes">
        <?php if ($p['tiers'] !== []): ?>
            <div class="tiers" data-tiers="<?= e(json_encode(array_map(fn ($t) => [(int) $t['min_qty'], (float) $t['discount_percent']], $p['tiers']))) ?>">
                <strong><i class="bi bi-percent me-1"></i><?= e(__('personalize.tiers_shop')) ?></strong>
                <ul><?php foreach (array_reverse($p['tiers']) as $t): ?><li><?= e(__('personalize.tier_line', ['qty' => $t['min_qty'], 'pct' => rtrim(rtrim($t['discount_percent'], '0'), '.')])) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if ((int) $p['min_qty'] > 1): ?><p class="mb-1"><i class="bi bi-box-seam me-1"></i><?= e(__('personalize.min_note', ['n' => (int) $p['min_qty']])) ?></p><?php endif; ?>
        <?php if ((int) $p['requires_proof'] === 1): ?><p class="mb-1"><i class="bi bi-check2-square me-1"></i><?= e(__('personalize.proof_note')) ?></p><?php endif; ?>
        <?php if ($p['production_days'] !== null): ?><p class="mb-0"><i class="bi bi-clock me-1"></i><?= e(__('personalize.lead_time', ['days' => (int) $p['production_days']])) ?></p><?php endif; ?>
    </div>
<?php endif; ?>
