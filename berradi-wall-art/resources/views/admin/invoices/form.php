<?php
$isEdit = $inv !== null;
$fromOrder = $isEdit && $inv['order_id'] !== null;
$this->layout('layouts/admin', ['title' => $isEdit ? __('finance.edit_draft') : __('finance.new_invoice')]);
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e($isEdit ? route('admin.invoices.show', ['id' => $inv['id']]) : route('admin.invoices.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_invoices')) ?></a>
<?php if ($fromOrder): ?><div class="alert alert-info"><?= e(__('finance.order_draft_help')) ?></div><?php endif; ?>
<form method="post" action="<?= e($isEdit ? route('admin.invoices.update', ['id' => $inv['id']]) : route('admin.invoices.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <?= $this->insert('partials/doc_customer', ['doc' => $inv, 'customers' => $customers, 'prefill' => null]) ?>
            <?php if (!$fromOrder): ?><?= $this->insert('partials/doc_lines', ['lines' => $lines, 'taxRate' => $taxRate]) ?><?php endif; ?>
        </div>
        <div class="col-xl-4"><div class="admin-card"><div class="card-body">
            <?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('finance.doc_language'), 'type' => 'select', 'options' => ['fr' => 'Français', 'ar' => 'العربية'], 'value' => $inv['locale'] ?? 'fr']) ?>
            <?= $this->insert('partials/field', ['name' => 'payment_terms', 'label' => __('pdf.payment_terms'), 'type' => 'textarea', 'rows' => 3, 'value' => $inv['payment_terms'] ?? (string) setting('invoice_payment_terms', '')]) ?>
            <?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('finance.notes_on_doc'), 'type' => 'textarea', 'rows' => 3, 'value' => (string) ($inv['notes'] ?? '')]) ?>
            <p class="small text-muted-bwa mb-0"><?= e(__('finance.draft_help')) ?></p>
        </div></div></div>
    </div>
    <div class="admin-sticky-save"><button class="btn btn-bwa btn-lg" type="submit" data-once><i class="bi bi-check2 me-1"></i><?= e(__('finance.save_draft')) ?></button></div>
</form>
