<?php
/**
 * "Sur mon mur" studio (product page). Everything runs in the browser: photos and camera frames never leave the device.
 * @var array<string,mixed> $p product, string $effect, string $artUrl
 */
$walls = ['#F4F1EA' => 'wall_ivory', '#E7DCCB' => 'wall_beige', '#CFC8BD' => 'wall_greige', '#B9C4B1' => 'wall_sage', '#C98B6B' => 'wall_terracotta',
    '#E9CFC9' => 'wall_rose', '#2F3A4A' => 'wall_navy', '#3B3B3B' => 'wall_charcoal'];
?>
<dialog class="wall-studio" data-wall-studio data-art="<?= e($artUrl) ?>" data-effect="<?= e($effect) ?>" data-slug="<?= e((string) $p['slug']) ?>"
        data-brand="<?= e((string) setting('company_name', 'BERRADI WALL ART')) ?>" aria-labelledby="wsTitle">
    <div class="ws-shell">
        <header class="ws-head">
            <h2 class="ws-title" id="wsTitle"><i class="bi bi-house-heart me-1"></i><?= e(__('wall.title')) ?></h2>
            <div class="ws-modes" role="tablist" aria-label="<?= e(__('wall.title')) ?>">
                <button type="button" role="tab" class="ws-mode is-active" data-ws-mode="creative" aria-selected="true"><i class="bi bi-palette"></i><span><?= e(__('wall.mode_creative')) ?></span></button>
                <button type="button" role="tab" class="ws-mode" data-ws-mode="photo" aria-selected="false"><i class="bi bi-image"></i><span><?= e(__('wall.mode_photo')) ?></span></button>
                <button type="button" role="tab" class="ws-mode" data-ws-mode="camera" aria-selected="false"><i class="bi bi-camera"></i><span><?= e(__('wall.mode_camera')) ?></span><em class="ws-pro">PRO</em></button>
            </div>
            <button type="button" class="ws-close" data-ws-close aria-label="<?= e(__('common.close')) ?>"><i class="bi bi-x-lg"></i></button>
        </header>

        <div class="ws-body">
            <div class="ws-stage-wrap">
                <div class="ws-stage" data-ws-stage>
                    <div class="ws-scene" data-ws-scene></div>
                    <img class="ws-photo d-none" data-ws-photo alt="">
                    <video class="ws-video d-none" data-ws-video playsinline muted autoplay></video>
                    <div class="ws-art" data-ws-art tabindex="0" role="img" aria-label="<?= e(__('wall.art_label')) ?>"></div>
                    <div class="ws-light" data-ws-light></div>
                    <svg class="ws-measure d-none" data-ws-measure aria-hidden="true">
                        <line data-ws-mline></line>
                        <circle r="14" data-ws-handle="a"></circle>
                        <circle r="14" data-ws-handle="b"></circle>
                    </svg>
                    <div class="ws-level d-none" data-ws-level><span data-ws-level-text></span></div>
                    <div class="ws-empty d-none" data-ws-empty>
                        <i class="bi bi-image"></i>
                        <p data-ws-empty-text></p>
                    </div>
                    <span class="ws-dim" data-ws-dim></span>
                </div>
                <p class="ws-hint" data-ws-hint><?= e(__('wall.hint_drag')) ?></p>
            </div>

            <aside class="ws-panel">
                <!-- sizes (all modes) -->
                <div class="ws-group">
                    <span class="ws-label"><?= e(__('wall.size')) ?></span>
                    <div class="ws-chips" data-ws-sizes></div>
                </div>

                <!-- creative -->
                <div data-ws-panel="creative">
                    <div class="ws-group">
                        <span class="ws-label"><?= e(__('wall.room')) ?></span>
                        <div class="ws-chips">
                            <?php foreach (['living', 'bedroom', 'office', 'dining', 'entry'] as $i => $scene): ?>
                                <button type="button" class="ws-chip<?= $i === 0 ? ' is-active' : '' ?>" data-ws-scene-btn="<?= $scene ?>"><?= e(__('wall.room_' . $scene)) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ws-group">
                        <span class="ws-label"><?= e(__('wall.wall_colour')) ?></span>
                        <div class="ws-swatches">
                            <?php $first = true; foreach ($walls as $hex => $key): ?>
                                <button type="button" class="ws-swatch<?= $first ? ' is-active' : '' ?>" data-ws-wall="<?= e($hex) ?>" style="--sw: <?= e($hex) ?>" title="<?= e(__('wall.' . $key)) ?>" aria-label="<?= e(__('wall.' . $key)) ?>"></button>
                            <?php $first = false; endforeach; ?>
                            <label class="ws-swatch ws-swatch-custom" title="<?= e(__('wall.wall_custom')) ?>"><input type="color" value="#F4F1EA" data-ws-wall-custom aria-label="<?= e(__('wall.wall_custom')) ?>"><i class="bi bi-eyedropper"></i></label>
                        </div>
                    </div>
                    <div class="ws-group">
                        <span class="ws-label"><?= e(__('wall.floor')) ?></span>
                        <div class="ws-chips">
                            <?php foreach (['oak', 'walnut', 'concrete', 'tiles'] as $i => $f): ?>
                                <button type="button" class="ws-chip<?= $i === 0 ? ' is-active' : '' ?>" data-ws-floor="<?= $f ?>"><?= e(__('wall.floor_' . $f)) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ws-group">
                        <span class="ws-label"><?= e(__('wall.light')) ?></span>
                        <div class="ws-chips">
                            <button type="button" class="ws-chip is-active" data-ws-light-btn="day"><i class="bi bi-sun me-1"></i><?= e(__('wall.light_day')) ?></button>
                            <button type="button" class="ws-chip" data-ws-light-btn="evening"><i class="bi bi-lamp me-1"></i><?= e(__('wall.light_evening')) ?></button>
                        </div>
                    </div>
                </div>

                <!-- own photo -->
                <div data-ws-panel="photo" hidden>
                    <div class="ws-group">
                        <label class="btn btn-outline-bwa btn-sm w-100 mb-0">
                            <i class="bi bi-upload me-1"></i><?= e(__('wall.choose_photo')) ?>
                            <input type="file" accept="image/*" class="visually-hidden" data-ws-file>
                        </label>
                        <p class="ws-small mt-2 mb-0"><?= e(__('wall.photo_tip')) ?></p>
                    </div>
                </div>

                <!-- camera -->
                <div data-ws-panel="camera" hidden>
                    <div class="ws-group d-grid gap-2">
                        <button type="button" class="btn btn-bwa btn-sm" data-ws-cam-start><i class="bi bi-camera-video me-1"></i><?= e(__('wall.camera_start')) ?></button>
                        <button type="button" class="btn btn-bwa btn-sm d-none" data-ws-cam-capture><i class="bi bi-record-circle me-1"></i><?= e(__('wall.camera_capture')) ?></button>
                        <button type="button" class="btn btn-outline-bwa btn-sm d-none" data-ws-cam-retake><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('wall.camera_retake')) ?></button>
                        <p class="ws-small mb-0"><?= e(__('wall.camera_tip')) ?></p>
                    </div>
                </div>

                <!-- real scale (photo & camera) -->
                <div class="ws-group" data-ws-scale-group hidden>
                    <span class="ws-label"><?= e(__('wall.scale')) ?></span>
                    <label class="ws-small d-block mb-1" for="wsWidth"><?= e(__('wall.visible_width')) ?> <strong data-ws-width-out></strong></label>
                    <input type="range" class="form-range" id="wsWidth" min="80" max="800" step="5" value="300" data-ws-width>
                    <button type="button" class="btn btn-outline-bwa btn-sm w-100 mt-1" data-ws-measure-btn><i class="bi bi-rulers me-1"></i><?= e(__('wall.calibrate')) ?></button>
                    <div class="ws-calib d-none" data-ws-calib>
                        <p class="ws-small mb-2"><?= e(__('wall.calibrate_help')) ?></p>
                        <div class="ws-chips mb-2">
                            <button type="button" class="ws-chip is-active" data-ws-ref="29.7"><?= e(__('wall.ref_a4_long')) ?></button>
                            <button type="button" class="ws-chip" data-ws-ref="21"><?= e(__('wall.ref_a4_short')) ?></button>
                            <button type="button" class="ws-chip" data-ws-ref="204"><?= e(__('wall.ref_door')) ?></button>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control" min="5" max="1000" step="0.1" value="29.7" data-ws-ref-cm aria-label="<?= e(__('wall.ref_length')) ?>">
                            <span class="input-group-text">cm</span>
                            <button type="button" class="btn btn-bwa" data-ws-calib-apply><?= e(__('wall.apply')) ?></button>
                        </div>
                    </div>
                    <p class="ws-small mt-2 mb-0" data-ws-calibrated hidden><i class="bi bi-check-circle text-success me-1"></i><?= e(__('wall.calibrated')) ?></p>
                </div>

                <div class="ws-group ws-actions">
                    <button type="button" class="btn btn-outline-bwa btn-sm" data-ws-center><i class="bi bi-bullseye me-1"></i><?= e(__('wall.center')) ?></button>
                    <button type="button" class="btn btn-bwa btn-sm" data-ws-download><i class="bi bi-download me-1"></i><?= e(__('wall.download')) ?></button>
                    <button type="button" class="btn btn-outline-bwa btn-sm d-none" data-ws-share><i class="bi bi-share me-1"></i><?= e(__('wall.share')) ?></button>
                </div>
                <p class="ws-small ws-privacy"><i class="bi bi-shield-lock me-1"></i><?= e(__('wall.privacy')) ?></p>
            </aside>
        </div>
    </div>
    <script type="application/json" data-ws-i18n><?= json_encode([
        'hint_drag' => __('wall.hint_drag'),
        'hint_measure' => __('wall.hint_measure'),
        'photo_empty' => __('wall.photo_empty'),
        'camera_empty' => __('wall.camera_empty'),
        'camera_denied' => __('wall.camera_denied'),
        'camera_insecure' => __('wall.camera_insecure'),
        'camera_none' => __('wall.camera_none'),
        'level_ok' => __('wall.level_ok'),
        'level_tilt' => __('wall.level_tilt'),
        'level_vertical' => __('wall.level_vertical'),
        'cm' => 'cm',
        'shared_text' => __('wall.shared_text', ['title' => (string) $p['title']]),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</dialog>
