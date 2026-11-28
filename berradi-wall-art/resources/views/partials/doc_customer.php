<?php
/** Customer block of a document form. Vars: doc (row or null), customers, prefill (customer row or null). */
$d = $doc ?? [];
$p = $prefill ?? null;
$val = fn (string $k, ?string $fromCustomer = null) => (string) ($d[$k] ?? ($p !== null && $fromCustomer !== null ? ($p[$fromCustomer] ?? '') : ''));
$pName = $p !== null ? trim($p['first_name'] . ' ' . $p['last_name']) : '';
?>
<div class="admin-card mb-4">
    <div class="card-header"><h2><?= e(__('finance.customer')) ?></h2></div>
    <div class="card-body">
        <label class="form-label small" for="customer_id"><?= e(__('orders.existing_customer')) ?></label>
        <select class="form-select mb-3" id="customer_id" name="customer_id" data-customer-picker>
            <option value=""><?= e(__('finance.no_account')) ?></option>
            <?php foreach ($customers as $c): $n = trim($c['first_name'] . ' ' . $c['last_name']); $sel = (string) ($d['customer_id'] ?? ($p['id'] ?? '')) === (string) $c['id']; ?>
                <option value="<?= (int) $c['id'] ?>" <?= $sel ? 'selected' : '' ?> data-name="<?= e($n) ?>" data-company="<?= e((string) $c['company_name']) ?>" data-ice="<?= e((string) $c['ice']) ?>" data-email="<?= e((string) $c['email']) ?>" data-phone="<?= e((string) $c['phone']) ?>"><?= e($n . ($c['company_name'] ? ' — ' . $c['company_name'] : '') . ' · ' . ($c['phone'] ?: $c['email'])) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="row g-2">
            <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'customer_name', 'label' => __('finance.contact_name'), 'value' => $val('customer_name') ?: $pName, 'required' => true]) ?></div>
            <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'customer_company', 'label' => __('admin.company'), 'value' => $val('customer_company', 'company_name')]) ?></div>
            <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'customer_ice', 'label' => 'ICE', 'value' => $val('customer_ice', 'ice'), 'help' => __('finance.ice_help')]) ?></div>
            <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'customer_email', 'label' => __('auth.email'), 'type' => 'email', 'value' => $val('customer_email', 'email'), 'attrs' => 'dir="ltr"']) ?></div>
            <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'customer_phone', 'label' => __('auth.phone'), 'value' => $val('customer_phone', 'phone'), 'attrs' => 'dir="ltr"']) ?></div>
        </div>
        <?= $this->insert('partials/field', ['name' => 'customer_address', 'label' => __('checkout.address'), 'type' => 'textarea', 'rows' => 2, 'value' => $val('customer_address')]) ?>
    </div>
</div>
