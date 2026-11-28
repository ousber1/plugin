<?php $this->layout('layouts/storefront', ['title' => __('account.addresses'), 'noindex' => true]) ?>
<section class="section-sm">
    <div class="container">
        <h1 class="mb-4"><?= e(__('account.addresses')) ?></h1>
        <div class="row g-4">
            <div class="col-lg-3"><?= $this->insert('customer/account/_nav', ['current' => 'customer.addresses']) ?></div>
            <div class="col-lg-9">
                <div class="row g-3 mb-4">
                    <?php foreach ($addresses as $a): ?>
                        <div class="col-md-6">
                            <div class="card-bwa h-100 p-3 address-card<?= (int) $a['is_default'] === 1 ? ' is-default' : '' ?>">
                                <div class="d-flex justify-content-between">
                                    <strong><?= e($a['label'] ?: $a['city']) ?></strong>
                                    <?php if ((int) $a['is_default'] === 1): ?><span class="badge-status is-dark"><?= e(__('account.default')) ?></span><?php endif; ?>
                                </div>
                                <div class="small mt-2"><?= e($a['full_name']) ?> · <span dir="ltr"><?= e($a['phone']) ?></span><br><?= e($a['address_line1']) ?><?= $a['address_line2'] ? '<br>' . e($a['address_line2']) : '' ?><br><?= e($a['city']) ?> <?= e((string) $a['postal_code']) ?></div>
                                <div class="d-flex gap-3 mt-3 small">
                                    <a href="<?= e(route('customer.addresses', ['edit' => $a['id']])) ?>#address-form"><?= e(__('account.edit')) ?></a>
                                    <form method="post" action="<?= e(route('customer.addresses.delete', ['id' => $a['id']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                        <?= csrf_field() ?><button class="btn btn-link p-0 small text-danger" type="submit"><?= e(__('account.delete_now')) ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($addresses === []): ?><div class="col-12"><div class="empty-state card-bwa"><i class="bi bi-geo-alt"></i><?= e(__('account.no_addresses')) ?></div></div><?php endif; ?>
                </div>
                <div class="card-bwa" id="address-form">
                    <div class="card-header"><?= e($edit ? __('account.edit_address') : __('account.add_address')) ?></div>
                    <div class="card-body">
                        <form method="post" action="<?= e($edit ? route('customer.addresses.update', ['id' => $edit['id']]) : route('customer.addresses.store')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'label', 'label' => __('account.address_label'), 'value' => $edit['label'] ?? '', 'placeholder' => __('account.address_label_ph')]) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'full_name', 'label' => __('checkout.full_name'), 'value' => $edit['full_name'] ?? '', 'required' => true]) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'value' => $edit['phone'] ?? '', 'required' => true]) ?></div>
                                <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'address_line1', 'label' => __('checkout.address'), 'value' => $edit['address_line1'] ?? '', 'required' => true]) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'address_line2', 'label' => __('account.address_line2'), 'value' => $edit['address_line2'] ?? '']) ?></div>
                                <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'city', 'label' => __('checkout.city'), 'value' => $edit['city'] ?? '', 'required' => true, 'attrs' => 'list="ma-cities"']) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'postal_code', 'label' => __('checkout.postal_code'), 'value' => $edit['postal_code'] ?? '']) ?></div>
                            </div>
                            <datalist id="ma-cities"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" <?= (int) ($edit['is_default'] ?? 0) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_default"><?= e(__('account.make_default')) ?></label></div>
                            <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
                            <?php if ($edit): ?><a class="btn btn-link" href="<?= e(route('customer.addresses')) ?>"><?= e(__('common.cancel')) ?></a><?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
