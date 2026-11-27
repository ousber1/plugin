<?php $this->layout('layouts/admin', ['title' => __('admin.nav_seo')]); ?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php
$tabs = ['general' => ['bi-sliders', __('seo_admin.tab_general')], 'pages' => ['bi-file-earmark-text', __('seo_admin.tab_pages')],
    'redirects' => ['bi-signpost-split', __('seo_admin.tab_redirects') . ' (' . $counts['redirects'] . ')'],
    'not_found' => ['bi-exclamation-octagon', __('seo_admin.tab_not_found') . ' (' . $counts['not_found'] . ')'], 'audit' => ['bi-clipboard2-check', __('seo_admin.tab_audit')]];
$in = fn ($n, $v, $extra = '') => '<input class="form-control form-control-sm' . invalid($n) . '" name="' . e($n) . '" value="' . e((string) old($n, $v)) . '" ' . $extra . '>';
$serp = function (string $titleName, string $descName, string $title, string $desc, string $url, string $dir = 'ltr') {
    return '<div class="serp-preview" data-serp dir="' . $dir . '"><div class="serp-url">' . e($url) . '</div><div class="serp-title" data-serp-title-out>' . e($title) . '</div><div class="serp-desc" data-serp-desc-out>' . e($desc) . '</div></div>';
};
?>
<ul class="nav settings-tabs mb-3 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $k => [$icon, $label]): ?>
        <li class="nav-item"><a class="nav-link text-nowrap<?= $tab === $k ? ' active' : '' ?>" href="<?= e(route('admin.seo.index', ['tab' => $k])) ?>"><i class="bi <?= $icon ?> me-1"></i><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'general'): ?>
    <form method="post" action="<?= e(route('admin.seo.general')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="admin-card mb-3"><div class="card-body">
            <h2 class="h6"><?= e(__('seo_admin.defaults')) ?></h2>
            <div class="row g-2" data-serp-form>
                <div class="col-12"><label class="form-label small"><?= e(__('seo_admin.default_title')) ?> <span class="serp-count" data-count-for="seo_meta_title" data-max="60"></span></label><?= $in('seo_meta_title', $values['seo_meta_title'], 'maxlength="191" data-serp-title') ?></div>
                <div class="col-12"><label class="form-label small"><?= e(__('seo_admin.default_description')) ?> <span class="serp-count" data-count-for="seo_meta_description" data-max="160"></span></label><textarea class="form-control form-control-sm<?= invalid('seo_meta_description') ?>" name="seo_meta_description" rows="2" maxlength="320" data-serp-desc><?= e((string) old('seo_meta_description', $values['seo_meta_description'])) ?></textarea></div>
                <div class="col-12"><?= $serp('seo_meta_title', 'seo_meta_description', $values['seo_meta_title'], $values['seo_meta_description'], absolute_url('/')) ?></div>
            </div>
            <p class="small text-muted-bwa mt-2 mb-0"><?= e(__('seo_admin.og_hint')) ?> <a href="<?= e(route('admin.settings', ['tab' => 'seo'])) ?>"><?= e(__('admin.manage')) ?></a></p>
        </div></div>

        <div class="admin-card mb-3"><div class="card-body">
            <h2 class="h6"><?= e(__('seo_admin.verification')) ?></h2>
            <p class="small text-muted-bwa"><?= e(__('seo_admin.verification_help')) ?></p>
            <div class="row g-2">
                <?php foreach (['seo_google_verification' => 'Google Search Console', 'seo_bing_verification' => 'Bing Webmaster Tools', 'seo_yandex_verification' => 'Yandex', 'seo_pinterest_verification' => 'Pinterest', 'seo_facebook_verification' => 'Facebook (Meta) — domaine'] as $k => $label): ?>
                    <div class="col-md-6"><label class="form-label small"><?= e($label) ?></label><?= $in($k, $values[$k], 'dir="ltr" placeholder="' . e(__('seo_admin.code_only')) . '"') ?><?php if (error($k)): ?><div class="invalid-feedback"><?= e(error($k)) ?></div><?php endif; ?></div>
                <?php endforeach; ?>
                <div class="col-md-6"><label class="form-label small">X / Twitter (@compte)</label><?= $in('seo_twitter_site', $values['seo_twitter_site'] !== '' ? '@' . $values['seo_twitter_site'] : '', 'dir="ltr" placeholder="@berradiwallart"') ?></div>
            </div>
            <p class="small mt-2 mb-0"><i class="bi bi-diagram-3 me-1 text-gold"></i><?= e(__('seo_admin.sitemap_hint')) ?> <code class="user-select-all" dir="ltr"><?= e(absolute_url('sitemap.xml')) ?></code></p>
        </div></div>

        <div class="admin-card mb-3"><div class="card-body">
            <h2 class="h6"><?= e(__('seo_admin.local')) ?></h2>
            <p class="small text-muted-bwa"><?= e(__('seo_admin.local_help')) ?></p>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small"><?= e(__('seo_admin.business_type')) ?></label>
                    <select class="form-select form-select-sm" name="seo_business_type"><?php foreach (\App\Services\SeoService::BUSINESS_TYPES as $bt): ?><option value="<?= e($bt) ?>" <?= old('seo_business_type', $values['seo_business_type']) === $bt ? 'selected' : '' ?>><?= e(__('seo_admin.type_' . $bt)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-8"><label class="form-label small"><?= e(__('seo_admin.opening_hours')) ?></label><?= $in('seo_opening_hours', $values['seo_opening_hours'], 'dir="ltr" placeholder="Mo-Fr 09:00-19:00, Sa 10:00-18:00"') ?><?php if (error('seo_opening_hours')): ?><div class="invalid-feedback"><?= e(error('seo_opening_hours')) ?></div><?php endif; ?></div>
                <div class="col-4 col-md-3"><label class="form-label small"><?= e(__('seo_admin.lat')) ?></label><?= $in('seo_geo_lat', $values['seo_geo_lat'], 'dir="ltr" inputmode="decimal" placeholder="33.5731"') ?></div>
                <div class="col-4 col-md-3"><label class="form-label small"><?= e(__('seo_admin.lng')) ?></label><?= $in('seo_geo_lng', $values['seo_geo_lng'], 'dir="ltr" inputmode="decimal" placeholder="-7.5898"') ?></div>
                <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('seo_admin.price_range')) ?></label><?= $in('seo_price_range', $values['seo_price_range'], 'placeholder="$$"') ?></div>
            </div>
            <details class="mt-2"><summary class="small"><?= e(__('seo_admin.schema_preview')) ?></summary><pre class="code-block small mt-2" dir="ltr"><?= e(json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details>
        </div></div>

        <div class="admin-card mb-3"><div class="card-body">
            <h2 class="h6"><?= e(__('seo_admin.robots')) ?></h2>
            <p class="small text-muted-bwa"><?= e(__('seo_admin.robots_help')) ?></p>
            <textarea class="form-control form-control-sm font-monospace<?= invalid('seo_robots_extra') ?>" name="seo_robots_extra" rows="3" dir="ltr" placeholder="Disallow: /shop?sort="><?= e((string) old('seo_robots_extra', $values['seo_robots_extra'])) ?></textarea>
            <?php if (error('seo_robots_extra')): ?><div class="invalid-feedback d-block"><?= e(error('seo_robots_extra')) ?></div><?php endif; ?>
            <details class="mt-2"><summary class="small"><?= e(__('seo_admin.robots_current')) ?></summary><pre class="code-block small mt-2" dir="ltr"><?= e($robots) ?></pre></details>
            <label class="form-check form-switch mt-3 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="seo_log_404" value="1" <?= $values['seo_log_404'] ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('seo_admin.log_404')) ?></span></label>
        </div></div>
        <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
    </form>

<?php elseif ($tab === 'pages'): ?>
    <p class="small text-muted-bwa"><?= e(__('seo_admin.pages_help')) ?></p>
    <?php foreach ($pages as $path => $labelKey): ?>
        <details class="admin-card option-row mb-2" id="page-<?= md5($path) ?>">
            <summary class="card-body d-flex flex-wrap align-items-center gap-3">
                <span class="fw-semibold"><?= e(__($labelKey)) ?></span><code class="small" dir="ltr"><?= e($path) ?></code>
                <?php foreach ($locales as $l): $r = $rows[$path][$l] ?? null; ?>
                    <span class="badge-status <?= $r === null ? 'is-muted' : ((int) $r['noindex'] ? 'is-danger' : 'is-success') ?>"><?= strtoupper($l) ?> · <?= e($r === null ? __('seo_admin.automatic') : ((int) $r['noindex'] ? 'noindex' : __('seo_admin.custom'))) ?></span>
                <?php endforeach; ?>
            </summary>
            <form method="post" action="<?= e(route('admin.seo.pages')) ?>" class="card-body border-top" novalidate>
                <?= csrf_field() ?><input type="hidden" name="page" value="<?= e($path) ?>">
                <div class="row g-3">
                    <?php foreach ($locales as $l): $r = $rows[$path][$l] ?? []; $dir = $l === 'ar' ? 'rtl' : 'ltr';
                        // what Google sees when nothing is set here
                        $autoTitle = $path === '/' ? (string) setting('seo_meta_title', $siteName) : \App\Core\Translator::get($labelKey, [], $l) . ' — ' . $siteName; ?>
                        <div class="col-lg-6" data-serp-form>
                            <div class="small fw-semibold mb-1"><?= e(__('seo_admin.lang_' . $l)) ?></div>
                            <label class="form-label small"><?= e(__('seo_admin.title')) ?> <span class="serp-count" data-max="60"></span></label>
                            <input class="form-control form-control-sm" name="title_<?= $l ?>" value="<?= e((string) ($r['meta_title'] ?? '')) ?>" maxlength="191" dir="<?= $dir ?>" data-serp-title placeholder="<?= e($autoTitle) ?>">
                            <label class="form-label small mt-2"><?= e(__('seo_admin.description')) ?> <span class="serp-count" data-max="160"></span></label>
                            <textarea class="form-control form-control-sm" name="description_<?= $l ?>" rows="2" maxlength="320" dir="<?= $dir ?>" data-serp-desc placeholder="<?= e(__('seo_admin.auto_description')) ?>"><?= e((string) ($r['meta_description'] ?? '')) ?></textarea>
                            <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="noindex_<?= $l ?>" value="1" <?= !empty($r['noindex']) ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('seo_admin.noindex')) ?></span></label>
                            <?= $serp('', '', (string) (($r['meta_title'] ?? '') ?: $autoTitle), (string) (($r['meta_description'] ?? '') ?: __('seo_admin.auto_description')), absolute_url(ltrim($path, '/')) . ($l !== default_locale() ? '?lang=' . $l : ''), $dir) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-bwa mt-3" type="submit"><?= e(__('common.save')) ?></button>
            </form>
        </details>
    <?php endforeach; ?>

<?php elseif ($tab === 'redirects'): ?>
    <div class="admin-card mb-3"><div class="card-body">
        <h2 class="h6"><?= e(__('seo_admin.new_redirect')) ?></h2>
        <form method="post" action="<?= e(route('admin.seo.redirects.store')) ?>" class="row g-2 align-items-end" novalidate>
            <?= csrf_field() ?>
            <div class="col-md-4"><label class="form-label small"><?= e(__('seo_admin.source')) ?></label><?= $in('source_path', $prefill, 'dir="ltr" placeholder="/ancienne-page" required') ?><?php if (error('source_path')): ?><div class="invalid-feedback"><?= e(error('source_path')) ?></div><?php endif; ?></div>
            <div class="col-md-4"><label class="form-label small"><?= e(__('seo_admin.target')) ?></label><?= $in('target_url', '', 'dir="ltr" placeholder="/shop/category/toiles" required') ?><?php if (error('target_url')): ?><div class="invalid-feedback"><?= e(error('target_url')) ?></div><?php endif; ?></div>
            <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('admin.type')) ?></label><select class="form-select form-select-sm" name="status_code"><option value="301">301 — <?= e(__('seo_admin.permanent')) ?></option><option value="302">302 — <?= e(__('seo_admin.temporary')) ?></option></select></div>
            <div class="col-6 col-md-2"><button class="btn btn-sm btn-bwa w-100" type="submit"><?= e(__('common.create')) ?></button></div>
            <div class="col-12"><?= $in('note', '', 'placeholder="' . e(__('seo_admin.note')) . '" maxlength="191"') ?></div>
        </form>
        <p class="small text-muted-bwa mt-2 mb-0"><?= e(__('seo_admin.redirects_help')) ?></p>
    </div></div>
    <div class="admin-card"><div class="table-responsive"><table class="table table-bwa mb-0">
        <thead><tr><th><?= e(__('seo_admin.source')) ?></th><th><?= e(__('seo_admin.target')) ?></th><th>Code</th><th class="text-end"><?= e(__('seo_admin.hits')) ?></th><th><?= e(__('seo_admin.last_hit')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($redirects as $r): ?>
            <tr class="<?= (int) $r['is_active'] ? '' : 'opacity-50' ?>">
                <td dir="ltr"><code><?= e($r['source_path']) ?></code><?php if ($r['note']): ?><div class="small text-muted-bwa" dir="auto"><?= e($r['note']) ?></div><?php endif; ?></td>
                <td dir="ltr" class="small text-break"><?= e($r['target_url']) ?></td>
                <td><?= (int) $r['status_code'] ?></td>
                <td class="text-end"><?= (int) $r['hits'] ?></td>
                <td class="small text-nowrap"><?= $r['last_hit_at'] ? e(format_date((string) $r['last_hit_at'], true)) : '—' ?></td>
                <td class="text-end"><form method="post" action="<?= e(route('admin.seo.redirects.destroy', ['id' => $r['id']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" type="submit" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash"></i></button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($redirects === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-signpost-split"></i><?= e(__('seo_admin.no_redirects')) ?></div></td></tr><?php endif; ?>
        </tbody>
    </table></div></div>

<?php elseif ($tab === 'not_found'): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <p class="small text-muted-bwa mb-0"><?= e(__('seo_admin.not_found_help')) ?></p>
        <?php if ($missing !== []): ?><form method="post" action="<?= e(route('admin.seo.not_found.clear')) ?>" data-confirm="<?= e(__('seo_admin.clear_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><i class="bi bi-eraser me-1"></i><?= e(__('seo_admin.clear')) ?></button></form><?php endif; ?>
    </div>
    <div class="admin-card"><div class="table-responsive"><table class="table table-bwa mb-0">
        <thead><tr><th><?= e(__('seo_admin.path')) ?></th><th class="text-end"><?= e(__('seo_admin.hits')) ?></th><th><?= e(__('seo_admin.last_seen')) ?></th><th><?= e(__('seo_admin.referrer')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($missing as $n): ?>
            <tr>
                <td dir="ltr"><code class="text-break"><?= e($n['path']) ?></code></td>
                <td class="text-end"><?= (int) $n['hits'] ?></td>
                <td class="small text-nowrap"><?= e(format_date((string) $n['last_seen_at'], true)) ?></td>
                <td class="small text-break" dir="ltr"><?= e((string) $n['last_referrer']) ?: '—' ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($n['redirect_id']): ?><span class="badge-status is-success"><?= e(__('seo_admin.redirected')) ?></span>
                    <?php else: ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.seo.index', ['tab' => 'redirects', 'from' => $n['path']])) ?>"><?= e(__('seo_admin.create_redirect')) ?></a><?php endif; ?>
                    <form class="d-inline" method="post" action="<?= e(route('admin.seo.not_found.destroy', ['id' => $n['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-muted" type="submit" aria-label="<?= e(__('seo_admin.ignore')) ?>" title="<?= e(__('seo_admin.ignore')) ?>"><i class="bi bi-x-lg"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($missing === []): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-check2-circle"></i><?= e(__('seo_admin.no_404')) ?></div></td></tr><?php endif; ?>
        </tbody>
    </table></div></div>

<?php else: $s = $audit['summary']; ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="kpi-tile"><div class="kpi-label"><?= e(__('seo_admin.score')) ?></div><div class="kpi-value <?= $s['score'] >= 80 ? 'text-success' : ($s['score'] >= 50 ? 'text-warning' : 'text-danger') ?>"><?= (int) $s['score'] ?>/100</div></div></div>
        <div class="col-6 col-md-3"><div class="kpi-tile"><div class="kpi-label"><?= e(__('seo_admin.checked')) ?></div><div class="kpi-value"><?= (int) $s['items'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi-tile"><div class="kpi-label"><?= e(__('seo_admin.perfect')) ?></div><div class="kpi-value"><?= (int) $s['perfect'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi-tile"><div class="kpi-label"><?= e(__('seo_admin.to_fix')) ?></div><div class="kpi-value"><?= (int) $s['issues'] ?></div></div></div>
    </div>
    <p class="small text-muted-bwa"><?= e(__('seo_admin.audit_help', ['tmax' => \App\Services\Seo\SeoAudit::TITLE_MAX, 'dmax' => \App\Services\Seo\SeoAudit::DESC_MAX])) ?></p>
    <?php foreach ($audit['items'] as $it): if ($it['issues'] === []) continue; ?>
        <div class="admin-card mb-2"><div class="card-body d-flex flex-wrap gap-3 align-items-start">
            <div class="audit-score <?= $it['score'] >= 80 ? 'is-good' : ($it['score'] >= 50 ? 'is-mid' : 'is-bad') ?>"><?= (int) $it['score'] ?></div>
            <div class="flex-grow-1" style="min-width:240px">
                <div class="fw-semibold" dir="auto"><?= e($it['title']) ?> <span class="badge-status is-muted ms-1"><?= e(__('seo_admin.kind_' . $it['type'])) ?></span></div>
                <div class="serp-preview serp-sm mt-1"><div class="serp-url" dir="ltr"><?= e(absolute_url(ltrim($it['url'], '/'))) ?></div><div class="serp-title" dir="auto"><?= e(\App\Helpers\Str::limit($it['seo_title'], 70)) ?></div><div class="serp-desc" dir="auto"><?= e(\App\Helpers\Str::limit($it['seo_description'], 170)) ?></div></div>
                <ul class="audit-issues mt-2 mb-0"><?php foreach ($it['issues'] as $iss): ?><li><?= e(__('seo_admin.issue_' . $iss)) ?></li><?php endforeach; ?></ul>
            </div>
            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.' . ($it['type'] === 'product' ? 'products' : ($it['type'] === 'category' ? 'categories' : 'collections')) . '.edit', ['id' => $it['id']])) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a>
        </div></div>
    <?php endforeach; ?>
    <?php if ($s['items'] === $s['perfect']): ?><div class="empty-state"><i class="bi bi-trophy"></i><?= e(__('seo_admin.all_perfect')) ?></div><?php endif; ?>
<?php endif; ?>
