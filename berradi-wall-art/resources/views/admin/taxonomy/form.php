<?php
$isEdit = $row !== null;
$r = $row ?? [];
$this->layout('layouts/admin', ['title' => $isEdit ? $r['name'] : __('admin.new_' . $entity)]);
$v = fn (string $k, mixed $d = '') => $r[$k] ?? $d;
$dtl = fn (?string $d) => $d ? date('Y-m-d\TH:i', (int) strtotime($d)) : '';
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e(route('admin.' . $table . '.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_' . $table)) ?></a>
<form method="post" action="<?= e($isEdit ? route('admin.' . $table . '.update', ['id' => $r['id']]) : route('admin.' . $table . '.store')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card mb-4"><div class="card-body">
                <?= $this->insert('partials/field', ['name' => 'name', 'label' => __('admin.name'), 'value' => $v('name'), 'required' => true, 'attrs' => 'maxlength="150"']) ?>
                <?= $this->insert('partials/field', ['name' => 'slug', 'label' => __('admin.slug'), 'value' => $v('slug'), 'help' => __('admin.slug_auto')]) ?>
                <?= $this->insert('partials/field', ['name' => 'description', 'label' => __('product.description'), 'type' => 'textarea', 'rows' => 5, 'value' => $v('description')]) ?>
                <div class="form-section">
                    <h3>SEO (FR)</h3>
                    <?= $this->insert('partials/field', ['name' => 'seo_title', 'label' => __('settings.seo_meta_title'), 'value' => $v('seo_title'), 'attrs' => 'maxlength="191"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'seo_description', 'label' => __('settings.seo_meta_description'), 'type' => 'textarea', 'rows' => 2, 'value' => $v('seo_description'), 'attrs' => 'maxlength="320"']) ?>
                </div>
            </div></div>
            <div class="admin-card"><div class="card-header"><h2><?= e(__('admin.arabic_version')) ?></h2></div><div class="card-body">
                <?php foreach (['name' => 'text', 'description' => 'textarea', 'seo_title' => 'text', 'seo_description' => 'textarea'] as $f => $type): ?>
                    <?= $this->insert('partials/field', ['name' => 'ar_' . $f, 'label' => __('admin.ar_' . $f), 'type' => $type, 'rows' => 3, 'value' => $arv[$f] ?? '', 'attrs' => 'dir="rtl" lang="ar"']) ?>
                <?php endforeach; ?>
                <p class="small text-muted-bwa mb-0"><?= e(__('admin.arabic_fallback')) ?></p>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="admin-card mb-4"><div class="card-body">
                <?= $this->insert('partials/field', ['name' => 'is_active', 'label' => __('admin.active'), 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?>
                <?= $this->insert('partials/field', ['name' => 'is_featured', 'label' => __('admin.featured'), 'type' => 'checkbox', 'value' => $v('is_featured', 0)]) ?>
                <?php if ($entity === 'category'): ?>
                    <?= $this->insert('partials/field', ['name' => 'parent_id', 'label' => __('admin.parent'), 'type' => 'select', 'options' => ['' => '—'] + array_column($parents, 'name', 'id'), 'value' => $v('parent_id')]) ?>
                <?php else: ?>
                    <?= $this->insert('partials/field', ['name' => 'is_limited', 'label' => __('admin.limited_edition'), 'type' => 'checkbox', 'value' => $v('is_limited', 0)]) ?>
                    <?= $this->insert('partials/field', ['name' => 'starts_at', 'label' => __('admin.starts_at'), 'type' => 'datetime-local', 'value' => $dtl($r['starts_at'] ?? null)]) ?>
                    <?= $this->insert('partials/field', ['name' => 'ends_at', 'label' => __('admin.ends_at'), 'type' => 'datetime-local', 'value' => $dtl($r['ends_at'] ?? null)]) ?>
                <?php endif; ?>
                <?= $this->insert('partials/field', ['name' => 'sort_order', 'label' => __('admin.sort_order'), 'type' => 'number', 'value' => $v('sort_order', 0)]) ?>
            </div></div>
            <div class="admin-card"><div class="card-body">
                <label class="form-label" for="f_image"><?= e(__('admin.image')) ?></label>
                <?php if (!empty($r['image'])): ?>
                    <img src="<?= e(upload_url($r['image'])) ?>" alt="" class="img-fluid rounded mb-2">
                    <?= $this->insert('partials/field', ['name' => 'remove_image', 'label' => __('admin.remove_image'), 'type' => 'checkbox', 'value' => 0]) ?>
                <?php endif; ?>
                <input class="form-control<?= error('image') ? ' is-invalid' : '' ?>" type="file" id="f_image" name="image" accept="image/jpeg,image/png,image/webp">
                <?php if (error('image')): ?><div class="invalid-feedback d-block"><?= e(error('image')) ?></div><?php endif; ?>
                <div class="form-text"><?= e(__('admin.images_help')) ?></div>
            </div></div>
        </div>
    </div>
    <div class="admin-sticky-save"><button class="btn btn-bwa btn-lg" type="submit"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
</form>
<?php if ($isEdit): ?>
    <form method="post" action="<?= e(route('admin.' . $table . '.destroy', ['id' => $r['id']])) ?>" class="mt-3" data-confirm="<?= e(__('admin.delete_taxonomy_confirm')) ?>">
        <?= csrf_field() ?><input type="hidden" name="confirm_detach" value="1">
        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i><?= e(__('admin.delete_' . $entity)) ?></button>
    </form>
<?php endif; ?>
