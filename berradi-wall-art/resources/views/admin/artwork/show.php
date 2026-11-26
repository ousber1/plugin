<?php
use App\Services\Sales\OrderStatus;

$this->layout('layouts/admin', ['title' => __('artwork.review_title', ['number' => $order['order_number']])]);
$canApprove = can('artwork.approve');
$allApproved = $images !== [] && count(array_filter($images, fn ($c) => $c['approval_status'] === 'approved')) === count($images);
$closed = in_array($order['status'], [OrderStatus::CANCELLED, OrderStatus::RETURNED], true);
$proofUrl = \App\Core\Session::getFlash('proof_url');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="small text-muted-bwa" href="<?= e(route('admin.artwork.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_artwork')) ?></a>
    <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.orders.show', ['id' => $order['id']])) ?>"><?= e($order['order_number']) ?> · <?= e(OrderStatus::label($order['status'])) ?></a>
</div>
<?php if ($proofUrl): ?>
    <div class="alert alert-info"><i class="bi bi-link-45deg me-1"></i><?= e(__('artwork.proof_link_once')) ?><input class="form-control form-control-sm mt-2" value="<?= e((string) $proofUrl) ?>" readonly dir="ltr" data-select-on-focus></div>
<?php endif; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e(__('artwork.final_mockup')) ?></h2></div>
            <div class="card-body text-center" style="background:#EFEBE4">
                <?php if (\App\Services\Operations\ArtworkService::previewPathFor($item) !== null): ?>
                    <img class="img-fluid" style="max-height:520px" src="<?= e(route('admin.orders.item_preview', ['id' => $order['id'], 'itemId' => $item['id']])) ?>" alt="<?= e(__('artwork.final_mockup')) ?>">
                <?php else: ?><div class="empty-state"><?= e(__('artwork.no_preview')) ?></div><?php endif; ?>
            </div>
        </div>
        <?php foreach ($images as $c):
            $dpi = $c['effective_dpi'] !== null ? (int) $c['effective_dpi'] : null;
            $crop = json_decode((string) $c['crop'], true) ?: []; ?>
            <div class="admin-card mb-3">
                <div class="card-header"><h2><?= e(__('artwork.panel_n', ['n' => (int) $c['panel_index'] + 1])) ?></h2><span class="badge-status <?= status_class($c['approval_status']) ?>"><?= e(__('artwork.status_' . $c['approval_status'])) ?></span></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <?php if ($c['image_uuid'] && $c['image_status'] !== 'purged'): ?>
                                <img class="img-fluid rounded border" src="<?= e(route('admin.artwork.original', ['itemId' => $item['id'], 'cid' => $c['id']])) ?>?v=preview" alt="" loading="lazy">
                            <?php else: ?><div class="thumb-sm w-100" style="height:120px"><i class="bi bi-trash"></i></div><div class="small text-muted-bwa mt-1"><?= e(__('artwork.photo_purged')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-sm-8 small">
                            <div class="stat-line"><span><?= e(__('artwork.file')) ?></span><span class="text-break"><?= e((string) $c['original_name']) ?></span></div>
                            <div class="stat-line"><span><?= e(__('artwork.source_px')) ?></span><span><?= (int) $c['width_px'] ?> × <?= (int) $c['height_px'] ?> px · <?= e(number_format((int) $c['size_bytes'] / 1048576, 1, ',', ' ')) ?> Mo</span></div>
                            <div class="stat-line"><span><?= e(__('artwork.crop')) ?></span><span><?= isset($crop['width']) ? (int) $crop['width'] . ' × ' . (int) $crop['height'] . ' px @ ' . (int) ($crop['x'] ?? 0) . ',' . (int) ($crop['y'] ?? 0) . (!empty($crop['rotate']) ? ' · ' . (int) $crop['rotate'] . '°' : '') : '—' ?></span></div>
                            <div class="stat-line"><span><?= e(__('artwork.effective_dpi')) ?></span><span><?php if ($dpi !== null): ?><span class="badge-status <?= $dpi < $minDpi ? 'is-danger' : ($dpi < $warnDpi ? '' : 'is-success') ?>"><?= $dpi ?> DPI</span><?php else: ?>—<?php endif; ?></span></div>
                            <?php if ($c['approved_at']): ?><div class="stat-line"><span><?= e(__('artwork.approved_on')) ?></span><span><?= e(format_date($c['approved_at'], true)) ?> · <?= e($c['approved_by'] ?? __('artwork.by_customer')) ?></span></div><?php endif; ?>
                            <?php if ($c['customer_feedback']): ?><div class="alert alert-warning small mt-2 mb-1"><strong><?= e(__('artwork.customer_feedback')) ?></strong><br><?= nl2br(e($c['customer_feedback'])) ?></div><?php endif; ?>
                            <?php if ($c['notes']): ?><div class="small text-muted-bwa mt-1" style="white-space:pre-line"><?= e($c['notes']) ?></div><?php endif; ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php if ($c['image_uuid'] && $c['image_status'] !== 'purged'): ?>
                                    <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.artwork.original', ['itemId' => $item['id'], 'cid' => $c['id']])) ?>"><i class="bi bi-download me-1"></i><?= e(__('artwork.download_original')) ?></a>
                                <?php endif; ?>
                                <?php if ($c['print_file_path']): ?>
                                    <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.artwork.print_file.download', ['itemId' => $item['id'], 'cid' => $c['id']])) ?>"><i class="bi bi-file-earmark-arrow-down me-1"></i><?= e(__('artwork.download_print')) ?></a>
                                <?php endif; ?>
                            </div>
                            <?php if ($canApprove && !$closed): ?>
                                <form method="post" action="<?= e(route('admin.artwork.print_file', ['itemId' => $item['id'], 'cid' => $c['id']])) ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                    <?= csrf_field() ?>
                                    <input class="form-control form-control-sm w-auto" type="file" name="print_file" accept=".pdf,.tif,.tiff,.jpg,.jpeg,.png" required aria-label="<?= e(__('artwork.print_file')) ?>">
                                    <button class="btn btn-sm btn-outline-bwa" type="submit"><?= e($c['print_file_path'] ? __('artwork.replace_print') : __('artwork.upload_print')) ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if (can('artwork.delete') && $c['image_uuid'] && $c['image_status'] !== 'purged'): ?>
                                <form method="post" action="<?= e(route('admin.artwork.purge', ['itemId' => $item['id'], 'cid' => $c['id']])) ?>" class="mt-2" data-confirm="<?= e(__('artwork.purge_confirm')) ?>">
                                    <?= csrf_field() ?><?php if (!in_array($order['status'], ['shipped', 'delivered', 'cancelled', 'returned'], true)): ?><input type="hidden" name="confirm_early" value="1"><?php endif; ?>
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash me-1"></i><?= e(__('artwork.purge')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="col-lg-5">
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('orders.specs')) ?></h2></div><div class="card-body small">
            <div class="stat-line"><span><?= e(__('orders.item')) ?></span><span><?= e($item['title']) ?></span></div>
            <div class="stat-line"><span><?= e(__('artwork.overall')) ?></span><span><?= $item['width_cm'] ? e(cm($item['width_cm']) . ' × ' . cm($item['height_cm']) . ' cm') : '—' ?></span></div>
            <?php if (!empty($options['panel_width_cm'])): ?><div class="stat-line"><span><?= e(__('artwork.panel_size')) ?></span><span><?= e(cm($options['panel_width_cm']) . ' × ' . cm($options['panel_height_cm']) . ' cm') ?> × <?= (int) ($options['panels'] ?? 1) ?><?= !empty($options['gap_cm']) ? ' · ' . e(__('artwork.gap', ['cm' => cm($options['gap_cm'])])) : '' ?></span></div><?php endif; ?>
            <div class="stat-line"><span><?= e(__('builder.support')) ?></span><span><?= e((string) $item['material_name']) ?></span></div>
            <div class="stat-line"><span><?= e(__('builder.frame')) ?></span><span><?= e((string) ($item['frame_name'] ?: __('builder.no_frame'))) ?></span></div>
            <?php if (!empty($options['border']['name'])): ?><div class="stat-line"><span><?= e(__('builder.border')) ?></span><span><?= e($options['border']['name']) ?></span></div><?php endif; ?>
            <?php if (!empty($options['finish']['name'])): ?><div class="stat-line"><span><?= e(__('builder.finish')) ?></span><span><?= e($options['finish']['name']) ?></span></div><?php endif; ?>
            <div class="stat-line"><span><?= e(__('orders.qty')) ?></span><span><?= (int) $item['quantity'] ?></span></div>
            <?php if ($order['customer_notes']): ?><div class="mt-2"><strong><?= e(__('orders.customer_note')) ?></strong><br><?= nl2br(e($order['customer_notes'])) ?></div><?php endif; ?>
        </div></div>

        <?php if ($canApprove && !$closed && !$allApproved): ?>
            <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('artwork.decision')) ?></h2></div><div class="card-body">
                <form method="post" action="<?= e(route('admin.artwork.approve', ['itemId' => $item['id']])) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="approve-notes"><?= e(__('artwork.notes_opt')) ?></label>
                    <input class="form-control mb-2" id="approve-notes" name="notes" maxlength="1000">
                    <button class="btn btn-bwa w-100" type="submit"><i class="bi bi-check2-circle me-1"></i><?= e(__('artwork.approve_studio')) ?></button>
                    <div class="form-text"><?= e(__('artwork.approve_help')) ?></div>
                </form>
                <hr>
                <form method="post" action="<?= e(route('admin.artwork.proof', ['itemId' => $item['id']])) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="proof-message"><?= e(__('artwork.proof_message')) ?></label>
                    <textarea class="form-control mb-2" id="proof-message" name="message" rows="2" maxlength="2000"></textarea>
                    <button class="btn btn-outline-bwa w-100" type="submit"><i class="bi bi-send me-1"></i><?= e(__('artwork.send_proof')) ?></button>
                    <div class="form-text"><?= e($order['customer_email'] ? __('artwork.proof_help') : __('artwork.proof_no_email')) ?></div>
                </form>
                <hr>
                <form method="post" action="<?= e(route('admin.artwork.changes', ['itemId' => $item['id']])) ?>" novalidate>
                    <?= csrf_field() ?>
                    <label class="form-label small" for="changes-notes"><?= e(__('artwork.changes_notes')) ?></label>
                    <textarea class="form-control mb-2<?= error('notes') ? ' is-invalid' : '' ?>" id="changes-notes" name="notes" rows="2" maxlength="2000" required></textarea>
                    <?php if (error('notes')): ?><div class="invalid-feedback d-block mb-2"><?= e(error('notes')) ?></div><?php endif; ?>
                    <button class="btn btn-outline-danger w-100" type="submit"><?= e(__('artwork.request_changes')) ?></button>
                </form>
            </div></div>
        <?php elseif ($allApproved): ?>
            <div class="alert alert-success"><i class="bi bi-check2-circle me-1"></i><?= e(__('artwork.all_approved')) ?></div>
        <?php endif; ?>
        <p class="small text-muted-bwa"><i class="bi bi-shield-lock me-1"></i><?= e(__('artwork.privacy_note')) ?></p>
    </div>
</div>
