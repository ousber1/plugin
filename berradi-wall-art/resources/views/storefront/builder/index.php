<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$this->start('head');
?>
<link rel="stylesheet" href="<?= e(asset('vendor/cropperjs/cropper.min.css')) ?>">
<?php $this->stop(); ?>

<section class="builder-hero">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('nav.custom'), null]]]) ?>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3">
            <div>
                <span class="eyebrow"><?= e(__('builder.eyebrow')) ?></span>
                <h1 class="page-title mt-1"><?= e($product ? $product['title'] : __('builder.title')) ?></h1>
            </div>
            <ol class="builder-steps" aria-label="<?= e(__('builder.steps')) ?>">
                <li data-step-indicator="photo"><span>1</span><?= e(__('builder.step_photo')) ?></li>
                <li data-step-indicator="format"><span>2</span><?= e(__('builder.step_format')) ?></li>
                <li data-step-indicator="finish"><span>3</span><?= e(__('builder.step_finish')) ?></li>
                <li data-step-indicator="save"><span>4</span><?= e(__('builder.step_save')) ?></li>
            </ol>
        </div>
    </div>
</section>

<section class="builder" data-builder>
    <script type="application/json" id="builder-config"><?= \App\Services\SeoService::encode($config) ?></script>
    <noscript><div class="container"><div class="alert alert-warning mt-4"><?= e(__('builder.no_js')) ?></div></div></noscript>
    <div class="container">
        <div class="row g-4">
            <!-- ======================== stage ======================== -->
            <div class="col-lg-7">
                <div class="builder-stage-card">
                    <div class="stage-tabs" role="tablist">
                        <button type="button" class="stage-tab active" data-stage-tab="edit" role="tab"><i class="bi bi-crop me-1"></i><?= e(__('builder.tab_edit')) ?></button>
                        <button type="button" class="stage-tab" data-stage-tab="wall" role="tab"><i class="bi bi-house me-1"></i><?= e(__('builder.tab_wall')) ?></button>
                        <span class="quality-badge ms-auto d-none" data-quality></span>
                    </div>
                    <div class="panel-tabs d-none" data-panel-tabs></div>

                    <div class="stage-edit" data-stage="edit">
                        <div class="dropzone" data-dropzone>
                            <i class="bi bi-cloud-arrow-up"></i>
                            <strong><?= e(__('builder.drop_title')) ?></strong>
                            <span><?= e(__('builder.drop_text', ['max' => (int) $config['limits']['maxMb']])) ?></span>
                            <label class="btn btn-bwa mt-3">
                                <?= e(__('builder.choose_photos')) ?>
                                <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-file-input>
                            </label>
                            <small class="mt-3 text-muted-bwa"><i class="bi bi-shield-lock me-1"></i><?= e(__('builder.privacy_note')) ?></small>
                        </div>
                        <div class="editor d-none" data-editor>
                            <div class="editor-canvas"><img alt="" data-editor-image></div>
                        </div>
                    </div>

                    <div class="stage-wall d-none" data-stage="wall">
                        <div class="room-wall builder-room">
                            <div class="builder-composition" data-composition-wrap><canvas data-composition></canvas></div>
                            <svg class="room-sofa" viewBox="0 0 400 120" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
                                <rect x="20" y="30" width="360" height="60" rx="14" fill="#cfc6b8"/>
                                <rect x="0" y="45" width="50" height="60" rx="12" fill="#c3b9a9"/>
                                <rect x="350" y="45" width="50" height="60" rx="12" fill="#c3b9a9"/>
                                <rect x="40" y="60" width="320" height="40" rx="8" fill="#d8d0c3"/>
                                <rect x="40" y="100" width="8" height="20" fill="#6f6658"/>
                                <rect x="352" y="100" width="8" height="20" fill="#6f6658"/>
                            </svg>
                        </div>
                        <div class="room-floor"></div>
                        <p class="room-note" data-wall-note></p>
                    </div>

                    <div class="editor-toolbar" data-toolbar hidden>
                        <button type="button" class="tool" data-action="zoom-out" title="<?= e(__('builder.zoom_out')) ?>"><i class="bi bi-zoom-out"></i></button>
                        <input type="range" min="0" max="100" value="0" class="zoom-range" data-zoom aria-label="<?= e(__('builder.zoom')) ?>">
                        <button type="button" class="tool" data-action="zoom-in" title="<?= e(__('builder.zoom_in')) ?>"><i class="bi bi-zoom-in"></i></button>
                        <span class="tool-sep"></span>
                        <button type="button" class="tool" data-action="rotate-left" title="<?= e(__('builder.rotate_left')) ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button type="button" class="tool" data-action="rotate-right" title="<?= e(__('builder.rotate_right')) ?>"><i class="bi bi-arrow-clockwise"></i></button>
                        <button type="button" class="tool" data-action="center" title="<?= e(__('builder.center')) ?>"><i class="bi bi-bullseye"></i></button>
                        <button type="button" class="tool" data-action="reset" title="<?= e(__('builder.reset')) ?>"><i class="bi bi-arrow-repeat"></i></button>
                    </div>
                    <p class="editor-hint" data-hint hidden><i class="bi bi-hand-index me-1"></i><?= e(__('builder.hint')) ?></p>
                </div>

                <div class="photo-strip mt-3" data-photo-strip hidden>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="filter-title mb-0"><?= e(__('builder.your_photos')) ?></span>
                        <label class="btn btn-sm btn-outline-bwa mb-0"><i class="bi bi-plus-lg me-1"></i><?= e(__('builder.add_photo')) ?><input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-file-input></label>
                    </div>
                    <div class="photo-list" data-photo-list></div>
                </div>
            </div>

            <!-- ======================== options ======================== -->
            <div class="col-lg-5">
                <div class="builder-options">
                    <div class="opt-section">
                        <h2 class="opt-title"><span>2</span><?= e(__('builder.format')) ?></h2>
                        <div class="seg" role="radiogroup" aria-label="<?= e(__('builder.orientation')) ?>" data-orientation>
                            <button type="button" data-value="portrait"><i class="bi bi-phone"></i> <?= e(__('builder.portrait')) ?></button>
                            <button type="button" data-value="landscape"><i class="bi bi-phone-landscape"></i> <?= e(__('builder.landscape')) ?></button>
                            <button type="button" data-value="square"><i class="bi bi-square"></i> <?= e(__('builder.square')) ?></button>
                        </div>
                        <div class="option-chips mt-3" data-sizes></div>
                        <details class="custom-size mt-3" data-custom-size>
                            <summary><?= e(__('builder.custom_size')) ?></summary>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <input type="number" class="form-control" min="10" step="1" data-custom-w aria-label="<?= e(__('builder.width')) ?>">
                                <span>×</span>
                                <input type="number" class="form-control" min="10" step="1" data-custom-h aria-label="<?= e(__('builder.height')) ?>">
                                <span>cm</span>
                                <button type="button" class="btn btn-outline-bwa btn-sm" data-apply-custom><?= e(__('shop.apply')) ?></button>
                            </div>
                            <small class="text-muted-bwa" data-size-range></small>
                        </details>
                        <div class="mt-4" data-layout-block>
                            <span class="filter-title"><?= e(__('builder.layout')) ?></span>
                            <div class="option-chips" data-layouts></div>
                            <div class="seg seg-sm mt-2 d-none" data-mode>
                                <button type="button" data-value="span"><?= e(__('builder.mode_span')) ?></button>
                                <button type="button" data-value="per_panel"><?= e(__('builder.mode_per_panel')) ?></button>
                            </div>
                        </div>
                        <p class="dims-line mt-3" data-dims></p>
                    </div>

                    <div class="opt-section">
                        <h2 class="opt-title"><span>3</span><?= e(__('builder.support')) ?></h2>
                        <div class="material-list" data-materials></div>
                        <div class="mt-3" data-border-block>
                            <span class="filter-title"><?= e(__('builder.border')) ?></span>
                            <div class="option-chips" data-borders></div>
                        </div>
                        <div class="mt-3" data-finish-block>
                            <span class="filter-title"><?= e(__('builder.finish')) ?></span>
                            <div class="option-chips" data-finishes></div>
                        </div>
                        <div class="mt-3" data-frame-block>
                            <span class="filter-title"><?= e(__('builder.frame')) ?></span>
                            <div class="frame-swatches" data-frames></div>
                        </div>
                    </div>

                    <div class="opt-section price-section">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <div class="qty-input" data-qty>
                                <button type="button" data-step="-1" aria-label="<?= e(__('product.decrease')) ?>">−</button>
                                <input type="number" value="1" min="1" max="<?= (int) $config['limits']['maxQty'] ?>" data-quantity aria-label="<?= e(__('product.quantity')) ?>">
                                <button type="button" data-step="1" aria-label="<?= e(__('product.increase')) ?>">+</button>
                            </div>
                            <div class="text-end">
                                <div class="price-total" data-price-total>—</div>
                                <div class="small text-muted-bwa" data-price-unit></div>
                            </div>
                        </div>
                        <details class="price-breakdown mt-2"><summary><?= e(__('builder.price_details')) ?></summary><ul class="list-unstyled small mt-2 mb-0" data-breakdown></ul></details>
                        <div class="alert alert-danger small mt-3 d-none" data-error role="alert"></div>
                        <div class="alert alert-warning small mt-3 d-none" data-dpi-warning role="status"></div>
                        <button type="button" class="btn btn-bwa btn-lg w-100 mt-3" data-save disabled><i class="bi bi-check2-circle me-2"></i><?= e(__('builder.save')) ?></button>
                        <p class="small text-muted-bwa mt-2 mb-0" data-save-help><?= e(__('builder.save_help')) ?></p>
                        <div class="save-result d-none" data-save-result></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php $this->start('scripts'); ?>
<script src="<?= e(asset('vendor/cropperjs/cropper.min.js')) ?>"></script>
<script src="<?= e(asset('js/builder.js')) ?>"></script>
<?php $this->stop(); ?>
