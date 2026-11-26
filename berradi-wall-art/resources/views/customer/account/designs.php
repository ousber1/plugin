<?php $this->layout('layouts/storefront', ['title' => __('account.designs'), 'noindex' => true]) ?>
<section class="section-sm">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <h1 class="mb-0"><?= e(__('account.designs')) ?></h1>
            <a class="btn btn-bwa" href="<?= e(route('builder.index')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('builder.new_design')) ?></a>
        </div>
        <div class="row g-4">
            <div class="col-lg-3"><?= $this->insert('customer/account/_nav', ['current' => 'customer.designs']) ?></div>
            <div class="col-lg-9">
                <?php if ($designs === []): ?>
                    <div class="empty-state card-bwa"><i class="bi bi-images"></i><?= e(__('account.no_designs')) ?></div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($designs as $d): ?>
                            <div class="col-sm-6 col-xl-4">
                                <a class="design-card" href="<?= e(route('builder.design.show', ['uuid' => $d['uuid']])) ?>">
                                    <span class="design-thumb">
                                        <?php if ($d['preview_path']): ?><img src="<?= e(route('builder.design.preview', ['uuid' => $d['uuid']])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                                    </span>
                                    <span class="d-block p-3">
                                        <strong class="d-block"><?= e(cm($d['config']['overall_width_cm'] ?? $d['width_cm']) . ' × ' . cm($d['config']['overall_height_cm'] ?? $d['height_cm']) . ' cm') ?> · <?= e($d['config']['material']['name'] ?? '') ?></strong>
                                        <span class="small text-muted-bwa"><?= e(format_date($d['created_at'])) ?> · <?= e(money((string) $d['unit_price'])) ?></span>
                                        <span class="badge-status mt-2 d-inline-block"><?= e(__('builder.status_' . $d['status'])) ?></span>
                                    </span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card-bwa mt-4">
                    <div class="card-header"><i class="bi bi-shield-lock me-2 text-gold"></i><?= e(__('account.my_photos')) ?></div>
                    <div class="card-body">
                        <p class="small text-muted-bwa"><?= e(__('account.photos_privacy', ['days' => (int) setting('retention_unordered_days', 30)])) ?></p>
                        <?php if ($photos === []): ?>
                            <p class="mb-0 text-muted-bwa small"><?= e(__('account.no_photos')) ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($photos as $ph): ?>
                                        <tr>
                                            <td style="width:64px"><img src="<?= e(route('builder.image', ['uuid' => $ph['uuid'], 'variant' => 'thumb'])) ?>" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover" loading="lazy"></td>
                                            <td class="small"><?= e($ph['original_name']) ?><br><span class="text-muted-bwa"><?= (int) $ph['width_px'] ?>×<?= (int) $ph['height_px'] ?> · <?= e(number_format($ph['size_bytes'] / 1048576, 1, ',', ' ')) ?> Mo</span></td>
                                            <td class="small text-muted-bwa"><?= e($ph['status'] === 'ordered' ? __('account.photo_ordered') : __('account.photo_until', ['date' => format_date($ph['retention_until'])])) ?></td>
                                            <td class="text-end">
                                                <?php if ($ph['status'] !== 'ordered'): ?>
                                                    <form method="post" action="<?= e(route('builder.image.delete', ['uuid' => $ph['uuid']])) ?>" data-confirm="<?= e(__('builder.confirm_delete_photo')) ?>">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> <?= e(__('account.delete_now')) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
