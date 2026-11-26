<?php
$isEdit = $c !== null;
$r = $c ?? [];
$this->layout('layouts/admin', ['title' => $isEdit ? __('admin.edit_customer') : __('admin.new_customer')]);
$v = fn (string $k, mixed $d = '') => $r[$k] ?? $d;
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e($isEdit ? route('admin.customers.show', ['id' => $r['id']]) : route('admin.customers.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_customers')) ?></a>
<form method="post" action="<?= e($isEdit ? route('admin.customers.update', ['id' => $r['id']]) : route('admin.customers.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8"><div class="admin-card"><div class="card-body">
            <div class="row">
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'first_name', 'label' => __('auth.first_name'), 'value' => $v('first_name'), 'required' => true, 'attrs' => 'autocomplete="off"']) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'last_name', 'label' => __('auth.last_name'), 'value' => $v('last_name'), 'attrs' => 'autocomplete="off"']) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'value' => $v('phone'), 'required' => true, 'attrs' => 'dir="ltr"']) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'value' => $v('email'), 'attrs' => 'dir="ltr"']) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'company_name', 'label' => __('admin.company'), 'value' => $v('company_name')]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'ice', 'label' => 'ICE', 'value' => $v('ice'), 'help' => __('admin.ice_help')]) ?></div>
            </div>
            <?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('admin.internal_notes'), 'type' => 'textarea', 'rows' => 4, 'value' => $v('notes')]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="admin-card"><div class="card-body">
            <?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('admin.language'), 'type' => 'select', 'options' => ['fr' => 'Français', 'ar' => 'العربية'], 'value' => $v('locale', 'fr')]) ?>
            <?= $this->insert('partials/field', ['name' => 'is_active', 'label' => __('admin.active'), 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?>
            <?= $this->insert('partials/field', ['name' => 'marketing_opt_in', 'label' => __('admin.marketing_opt_in'), 'type' => 'checkbox', 'value' => $v('marketing_opt_in', 0), 'help' => __('admin.opt_in_help')]) ?>
            <?php if (!$isEdit): ?><p class="small text-muted-bwa mb-0"><?= e(__('admin.new_customer_help')) ?></p><?php endif; ?>
        </div></div></div>
    </div>
    <div class="admin-sticky-save"><button class="btn btn-bwa btn-lg" type="submit"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
</form>
