<?php $this->layout('layouts/storefront', ['seo' => $seo]); $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.'); ?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow d-block"><?= e(__('finance.your_quote')) ?></span>
        <h1 class="page-title mt-2"><?= $q ? e($q['quotation_number']) : e(__('finance.your_quote')) ?></h1>
        <?php if ($q): ?><p class="page-lead"><?= e(__('finance.quote_lead', ['date' => format_date((string) $q['valid_until'])])) ?></p><?php endif; ?>
    </div>
</section>
<section class="section-sm">
    <div class="container" style="max-width:960px">
        <?php if ($q === null): ?>
            <div class="card-bwa"><div class="card-body p-4 text-center"><p class="mb-3"><?= e(__('finance.quote_link_invalid')) ?></p><a class="btn btn-outline-bwa" href="<?= e(whatsapp_link(null, '')) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i><?= e(__('artwork.contact_us')) ?></a></div></div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card-bwa"><div class="card-body p-0">
                        <div class="table-responsive"><table class="table mb-0">
                            <thead><tr><th><?= e(__('pdf.description')) ?></th><th class="text-end"><?= e(__('pdf.qty')) ?></th><th class="text-end"><?= e(__('pdf.total_ttc')) ?></th></tr></thead>
                            <tbody><?php foreach ($lines as $l): ?><tr><td><?= e($l['description']) ?><?= $l['material_name'] ? '<div class="small text-muted-bwa">' . e($l['material_name']) . '</div>' : '' ?></td><td class="text-end"><?= e($qty($l['quantity'])) ?></td><td class="text-end text-nowrap"><?= e(money($l['line_total_ttc'])) ?></td></tr><?php endforeach; ?></tbody>
                        </table></div>
                        <div class="p-3 border-top">
                            <div class="d-flex justify-content-between small"><span><?= e(__('pdf.total_ht')) ?></span><span><?= e(money(\App\Helpers\Money::sub((string) $q['subtotal'], (string) $q['discount_total']))) ?></span></div>
                            <div class="d-flex justify-content-between small"><span><?= e((string) setting('tax_label', 'TVA')) ?></span><span><?= e(money($q['tax_total'])) ?></span></div>
                            <div class="d-flex justify-content-between fw-bold mt-1"><span><?= e(__('pdf.total_ttc')) ?></span><span><?= e(money($q['total'])) ?></span></div>
                        </div>
                    </div></div>
                    <?php if ($q['terms']): ?><p class="small text-muted-bwa mt-3" style="white-space:pre-line"><?= e($q['terms']) ?></p><?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <div class="card-bwa"><div class="card-body p-4">
                        <a class="btn btn-outline-bwa w-100 mb-3" href="<?= e(url('quote/' . $token . '/pdf')) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i><?= e(__('finance.download_pdf')) ?></a>
                        <?php if ($open): ?>
                            <form method="post" action="<?= e(url('quote/' . $token)) ?>">
                                <?= csrf_field() ?>
                                <label class="form-label small" for="comment"><?= e(__('finance.comment_opt')) ?></label>
                                <textarea class="form-control mb-3" id="comment" name="comment" rows="2" maxlength="1000"></textarea>
                                <button class="btn btn-bwa w-100 mb-2" type="submit" name="decision" value="accept"><i class="bi bi-check2-circle me-1"></i><?= e(__('finance.accept_quote')) ?></button>
                                <button class="btn btn-link w-100 text-muted-bwa" type="submit" name="decision" value="reject"><?= e(__('finance.decline_quote')) ?></button>
                            </form>
                        <?php else: ?>
                            <p class="small mb-0"><span class="badge-status"><?= e(__('finance.qs_' . $q['status'])) ?></span> <?= e(in_array($q['status'], ['sent'], true) ? __('finance.quote_expired_text') : '') ?></p>
                        <?php endif; ?>
                    </div></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
