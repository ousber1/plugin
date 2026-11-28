<?php
/**
 * Editable document lines priced HT (quotations, standalone invoices).
 * Vars: lines (rows from DB or old input; keys description, specifications|material_name, quantity, unit_price|unit_price_ht,
 * discount|discount_amount, tax_rate), taxRate (default %).
 */
$old = old('lines', null);
$src = is_array($old) ? array_values($old) : array_map(fn ($l) => [
    'description' => $l['description'], 'specifications' => $l['specifications'] ?? $l['material_name'] ?? '',
    'quantity' => rtrim(rtrim((string) $l['quantity'], '0'), '.'), 'unit_price' => $l['unit_price'] ?? $l['unit_price_ht'],
    'discount' => (float) ($l['discount'] ?? $l['discount_amount'] ?? 0) > 0 ? ($l['discount'] ?? $l['discount_amount']) : '',
    'tax_rate' => rtrim(rtrim(number_format((float) $l['tax_rate'], 2, '.', ''), '0'), '.'),
], $lines);
$rows = max(2, count($src) + 1);
$v = fn (int $i, string $k, string $d = '') => isset($src[$i][$k]) && $src[$i][$k] !== null ? (string) $src[$i][$k] : $d;
?>
<div class="admin-card mb-4">
    <div class="card-header"><h2><?= e(__('finance.lines')) ?></h2><button class="btn btn-sm btn-outline-bwa" type="button" data-repeat-add="#doc-lines"><i class="bi bi-plus-lg me-1"></i><?= e(__('orders.add_line')) ?></button></div>
    <div class="card-body" id="doc-lines" data-repeater data-doc-lines>
        <?php if (error('lines')): ?><div class="alert alert-danger py-2"><?= e(error('lines')) ?></div><?php endif; ?>
        <p class="small text-muted-bwa"><?= e(__('finance.lines_help')) ?></p>
        <?php for ($i = 0; $i < $rows; $i++): ?>
            <div class="line-row" data-repeat-row data-doc-line>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small"><?= e(__('pdf.description')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][description]" data-name="lines[__i__][description]" value="<?= e($v($i, 'description')) ?>" maxlength="500"></div>
                    <div class="col-md-6"><label class="form-label small"><?= e(__('finance.specifications')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][specifications]" data-name="lines[__i__][specifications]" value="<?= e($v($i, 'specifications')) ?>" maxlength="1000" placeholder="<?= e(__('finance.specifications_ph')) ?>"></div>
                    <div class="col-3 col-md-2"><label class="form-label small"><?= e(__('pdf.qty')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][quantity]" data-name="lines[__i__][quantity]" value="<?= e($v($i, 'quantity', '1')) ?>" inputmode="decimal" data-f="qty"></div>
                    <div class="col-5 col-md-3"><label class="form-label small"><?= e(__('pdf.unit_ht')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][unit_price]" data-name="lines[__i__][unit_price]" value="<?= e($v($i, 'unit_price')) ?>" inputmode="decimal" data-f="price"></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('pdf.line_discount')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][discount]" data-name="lines[__i__][discount]" value="<?= e($v($i, 'discount')) ?>" inputmode="decimal" data-f="discount"></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('inventory.vat')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][tax_rate]" data-name="lines[__i__][tax_rate]" value="<?= e($v($i, 'tax_rate', $taxRate)) ?>" inputmode="decimal" data-f="rate"></div>
                    <div class="col-8 col-md-3 d-flex align-items-end justify-content-end"><span class="small text-muted-bwa" data-line-total></span></div>
                </div>
            </div>
        <?php endfor; ?>
        <div class="d-flex justify-content-end gap-4 small mt-2" data-doc-totals>
            <span><?= e(__('pdf.total_ht')) ?> : <strong data-t="ht">—</strong></span>
            <span><?= e((string) setting('tax_label', 'TVA')) ?> : <strong data-t="tax">—</strong></span>
            <span><?= e(__('pdf.total_ttc')) ?> : <strong data-t="ttc">—</strong></span>
        </div>
        <p class="small text-muted-bwa text-end mb-0"><?= e(__('finance.preview_only')) ?></p>
    </div>
</div>
