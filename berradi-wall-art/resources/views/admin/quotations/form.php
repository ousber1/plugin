<?php
$isEdit = $q !== null;
$this->layout('layouts/admin', ['title' => $isEdit ? $q['quotation_number'] : __('finance.new_quote')]);
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e($isEdit ? route('admin.quotations.show', ['id' => $q['id']]) : route('admin.quotations.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_quotations')) ?></a>
<?php if ($isEdit && $q['status'] === 'sent'): ?><div class="alert alert-warning"><?= e(__('finance.edit_sent_warning')) ?></div><?php endif; ?>
<form method="post" action="<?= e($isEdit ? route('admin.quotations.update', ['id' => $q['id']]) : route('admin.quotations.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <?= $this->insert('partials/doc_customer', ['doc' => $q, 'customers' => $customers, 'prefill' => $prefill]) ?>
            <?= $this->insert('partials/doc_lines', ['lines' => $lines, 'taxRate' => $taxRate]) ?>
        </div>
        <div class="col-xl-4">
            <div class="admin-card"><div class="card-body">
                <?= $this->insert('partials/field', ['name' => 'issue_date', 'label' => __('admin.date'), 'type' => 'date', 'value' => $q['issue_date'] ?? date('Y-m-d'), 'required' => true]) ?>
                <?= $this->insert('partials/field', ['name' => 'valid_until', 'label' => __('finance.valid_until'), 'type' => 'date', 'value' => $q['valid_until'] ?? date('Y-m-d', strtotime('+' . $validity . ' days')), 'required' => true]) ?>
                <?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('finance.doc_language'), 'type' => 'select', 'options' => ['fr' => 'Français', 'ar' => 'العربية'], 'value' => $q['locale'] ?? 'fr']) ?>
                <?= $this->insert('partials/field', ['name' => 'terms', 'label' => __('pdf.conditions'), 'type' => 'textarea', 'rows' => 4, 'value' => $q['terms'] ?? (string) setting('quotation_terms', '')]) ?>
                <?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('admin.internal_notes'), 'type' => 'textarea', 'rows' => 2, 'value' => (string) ($q['notes'] ?? '')]) ?>
            </div></div>
        </div>
    </div>
    <div class="admin-sticky-save"><button class="btn btn-bwa btn-lg" type="submit" data-once><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
</form>
