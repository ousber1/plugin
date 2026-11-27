<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_content')]);
$in = fn ($n, $v, $extra = '') => '<input class="form-control form-control-sm" name="' . $n . '" value="' . e((string) $v) . '" ' . $extra . '>';
$ta = fn ($n, $v, $extra = '') => '<textarea class="form-control form-control-sm" name="' . $n . '" rows="3" ' . $extra . '>' . e((string) $v) . '</textarea>';
$lbl = fn ($t) => '<label class="form-label small mb-1">' . e($t) . '</label>';
$dtl = fn ($d) => $d ? date('Y-m-d\TH:i', strtotime((string) $d)) : '';
$sw = fn ($n, $v, $l) => '<label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="' . $n . '" value="1" ' . ((int) $v ? 'checked' : '') . '><span class="form-check-label small">' . e($l) . '</span></label>';
?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<ul class="nav settings-tabs border-bottom mb-4" role="tablist" data-hash-tabs>
    <?php foreach (['banners', 'gallery', 'faqs'] as $k): ?><li class="nav-item"><button class="nav-link<?= $tab === $k ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $k ?>" type="button"><?= e(__('content.tab_' . $k)) ?> (<?= count($data[$k]['rows']) ?>)</button></li><?php endforeach; ?>
</ul>
<div class="tab-content">
<?php foreach (['banners', 'gallery', 'faqs'] as $type): ?>
    <div class="tab-pane fade<?= $tab === $type ? ' show active' : '' ?>" id="tab-<?= $type ?>" role="tabpanel">
        <p class="small text-muted-bwa"><?= e(__('content.help_' . $type)) ?></p>
        <?php foreach (array_merge($data[$type]['rows'], [null]) as $r): $isNew = $r === null; $r ??= ['id' => 0, 'is_active' => 1, 'sort_order' => 0]; $ar = $data[$type]['ar'][(int) $r['id']] ?? []; ?>
            <details class="admin-card option-row mb-2" id="<?= $type ?>-<?= (int) $r['id'] ?>">
                <summary class="card-body d-flex flex-wrap align-items-center gap-3">
                    <?php if ($isNew): ?><span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('content.add_' . $type)) ?></span>
                    <?php else: ?>
                        <?php if (!empty($r['image'])): ?><img class="thumb-sm" src="<?= e(upload_url($r['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
                        <span class="fw-semibold" dir="auto"><?= e((string) ($r['title'] ?? $r['question'] ?? '')) ?></span>
                        <?php if ($type === 'banners'): ?><span class="small text-muted-bwa"><?= e(__('content.pos_' . $r['position'])) ?></span><?php endif; ?>
                        <?php if ($type === 'gallery' && !(int) $r['customer_consent']): ?><span class="badge-status is-danger"><?= e(__('content.no_consent')) ?></span><?php endif; ?>
                        <?php if (!(int) $r['is_active']): ?><span class="badge-status is-muted"><?= e(__('admin.inactive')) ?></span><?php endif; ?>
                    <?php endif; ?>
                </summary>
                <form method="post" action="<?= e($isNew ? route('admin.content.store', ['type' => $type]) : route('admin.content.update', ['type' => $type, 'id' => $r['id']])) ?>" enctype="multipart/form-data" class="card-body border-top" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <?php if ($type === 'banners'): ?>
                            <div class="col-md-3"><?= $lbl(__('content.position')) ?><select class="form-select form-select-sm" name="position"><?php foreach (['hero', 'promo'] as $pos): ?><option value="<?= $pos ?>" <?= ($r['position'] ?? 'hero') === $pos ? 'selected' : '' ?>><?= e(__('content.pos_' . $pos)) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-5"><?= $lbl(__('content.title')) ?><?= $in('title', $r['title'] ?? '', 'maxlength="191"') ?></div>
                            <div class="col-md-4"><?= $lbl(__('content.title') . ' (AR)') ?><?= $in('ar_title', $ar['title'] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.subtitle')) ?><?= $in('subtitle', $r['subtitle'] ?? '', 'maxlength="500"') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.subtitle') . ' (AR)') ?><?= $in('ar_subtitle', $ar['subtitle'] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-md-3"><?= $lbl(__('content.button')) ?><?= $in('button_text', $r['button_text'] ?? '', 'maxlength="80"') ?></div>
                            <div class="col-md-3"><?= $lbl(__('content.button') . ' (AR)') ?><?= $in('ar_button_text', $ar['button_text'] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.link')) ?><?= $in('link_url', $r['link_url'] ?? '', 'placeholder="/shop" dir="ltr"') ?></div>
                            <div class="col-6 col-md-3"><?= $lbl(__('admin.starts_at')) ?><input class="form-control form-control-sm" type="datetime-local" name="starts_at" value="<?= e($dtl($r['starts_at'] ?? null)) ?>"></div>
                            <div class="col-6 col-md-3"><?= $lbl(__('admin.ends_at')) ?><input class="form-control form-control-sm" type="datetime-local" name="ends_at" value="<?= e($dtl($r['ends_at'] ?? null)) ?>"></div>
                        <?php elseif ($type === 'gallery'): ?>
                            <div class="col-md-4"><?= $lbl(__('content.title')) ?><?= $in('title', $r['title'] ?? '', 'maxlength="191"') ?></div>
                            <div class="col-md-4"><?= $lbl(__('content.title') . ' (AR)') ?><?= $in('ar_title', $ar['title'] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-md-4"><?= $lbl(__('checkout.city')) ?><?= $in('city', $r['city'] ?? '', 'maxlength="100"') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.product')) ?><select class="form-select form-select-sm" name="product_id"><option value="">—</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($r['product_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6 pt-md-4"><?= $sw('customer_consent', $r['customer_consent'] ?? 0, __('content.consent')) ?></div>
                        <?php else: ?>
                            <div class="col-md-6"><?= $lbl(__('content.question')) ?><?= $in('question', $r['question'] ?? '', 'maxlength="255"') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.question') . ' (AR)') ?><?= $in('ar_question', $ar['question'] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.answer')) ?><?= $ta('answer', $r['answer'] ?? '') ?></div>
                            <div class="col-md-6"><?= $lbl(__('content.answer') . ' (AR)') ?><?= $ta('ar_answer', $ar['answer'] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-md-4"><?= $lbl(__('admin.category')) ?><?= $in('category', $r['category'] ?? '', 'maxlength="60"') ?></div>
                        <?php endif; ?>
                        <?php if ($type !== 'faqs'): ?>
                            <div class="col-md-6"><?= $lbl(__('admin.image') . ($isNew ? ' *' : '')) ?><input class="form-control form-control-sm" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
                        <?php endif; ?>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.sort_order')) ?><?= $in('sort_order', $r['sort_order'], 'type="number"') ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <?= $sw('is_active', $r['is_active'], __('admin.active')) ?>
                        <button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                    </div>
                </form>
                <?php if (!$isNew): ?><form method="post" action="<?= e(route('admin.content.destroy', ['type' => $type, 'id' => $r['id']])) ?>" class="px-3 pb-3" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" type="submit"><?= e(__('account.delete_now')) ?></button></form><?php endif; ?>
            </details>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>
