<?php $this->layout('layouts/storefront', ['title' => __('artwork.proof_title'), 'noindex' => true]); ?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow d-block"><?= e(__('artwork.proof_eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e(__('artwork.proof_title')) ?></h1>
        <?php if ($found !== null): ?><p class="page-lead"><?= e(__('artwork.proof_lead', ['number' => $found['order']['order_number']])) ?></p><?php endif; ?>
    </div>
</section>
<section class="section-sm">
    <div class="container" style="max-width:960px">
        <?php if ($found === null): ?>
            <div class="card-bwa"><div class="card-body p-4 text-center">
                <i class="bi bi-link-45deg fs-1 text-gold"></i>
                <p class="mt-2 mb-3"><?= e(__('artwork.proof_invalid')) ?></p>
                <a class="btn btn-outline-bwa" href="<?= e(whatsapp_link(null, '')) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i><?= e(__('artwork.contact_us')) ?></a>
            </div></div>
        <?php else: $it = $found['item']; ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card-bwa"><div class="card-body p-3 text-center" style="background:#EFEBE4">
                        <img class="img-fluid" style="max-height:560px" src="<?= e(url('artwork/' . $token . '/preview')) ?>" alt="<?= e($it['title']) ?>">
                    </div></div>
                </div>
                <div class="col-lg-5">
                    <div class="card-bwa mb-3"><div class="card-body p-4">
                        <h2 class="h5"><?= e($it['title']) ?></h2>
                        <p class="small text-muted-bwa mb-0">
                            <?= $it['width_cm'] ? e(cm($it['width_cm']) . ' × ' . cm($it['height_cm']) . ' cm') . '<br>' : '' ?>
                            <?= e((string) $it['material_name']) ?><?= $it['frame_name'] ? ' · ' . e($it['frame_name']) : '' ?><br>
                            <?= e(__('orders.qty')) ?> : <?= (int) $it['quantity'] ?>
                        </p>
                        <?= $this->insert('partials/personalization', ['options' => $it['options'] ?? null]) ?>
                    </div></div>
                    <form method="post" action="<?= e(url('artwork/' . $token)) ?>" class="card-bwa" novalidate>
                        <div class="card-body p-4">
                            <?= csrf_field() ?>
                            <p class="small"><?= e(__('artwork.proof_check')) ?></p>
                            <button class="btn btn-bwa w-100 mb-3" type="submit" name="decision" value="approve"><i class="bi bi-check2-circle me-1"></i><?= e(__('artwork.proof_approve')) ?></button>
                            <label class="form-label small" for="feedback"><?= e(__('artwork.proof_feedback')) ?></label>
                            <textarea class="form-control mb-2" id="feedback" name="feedback" rows="3" maxlength="2000"></textarea>
                            <button class="btn btn-outline-bwa w-100" type="submit" name="decision" value="changes"><?= e(__('artwork.proof_changes')) ?></button>
                            <p class="small text-muted-bwa mt-3 mb-0"><?= e(__('artwork.proof_once')) ?></p>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
