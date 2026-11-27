<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Translator;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Seo\RedirectService;
use App\Services\Seo\SeoAudit;
use App\Services\Seo\SeoPages;
use App\Services\Seo\SitemapService;
use App\Services\SeoService;
use App\Services\SettingsService;

/**
 * SEO tools: general settings (search-engine verification, local business, robots.txt), per-page titles and
 * descriptions (FR/AR) with a Google preview, 301 redirects, the 404 monitor, and the catalogue audit.
 */
final class SeoController extends Controller
{
    private const TABS = ['general', 'pages', 'redirects', 'not_found', 'audit'];

    private const GENERAL = [
        'seo_meta_title' => 'nullable|max:191',
        'seo_meta_description' => 'nullable|max:320',
        'seo_google_verification' => ['nullable', 'max:100', 'regex:/^[A-Za-z0-9_\-=.]{4,100}$/'],
        'seo_bing_verification' => ['nullable', 'max:100', 'regex:/^[A-Za-z0-9_\-=.]{4,100}$/'],
        'seo_yandex_verification' => ['nullable', 'max:100', 'regex:/^[A-Za-z0-9_\-=.]{4,100}$/'],
        'seo_pinterest_verification' => ['nullable', 'max:100', 'regex:/^[A-Za-z0-9_\-=.]{4,100}$/'],
        'seo_facebook_verification' => ['nullable', 'max:100', 'regex:/^[A-Za-z0-9_\-=.]{4,100}$/'],
        'seo_twitter_site' => ['nullable', 'max:16', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'],
        'seo_business_type' => 'required|in:Organization,Store,HomeGoodsStore,ArtGallery,LocalBusiness',
        'seo_opening_hours' => ['nullable', 'max:191', 'regex:/^(\s*(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))?(,(Mo|Tu|We|Th|Fr|Sa|Su))*\s+\d{2}:\d{2}-\d{2}:\d{2}\s*)(,\s*(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))?(,(Mo|Tu|We|Th|Fr|Sa|Su))*\s+\d{2}:\d{2}-\d{2}:\d{2}\s*)*$/'],
        'seo_geo_lat' => 'nullable|numeric|between:-90,90',
        'seo_geo_lng' => 'nullable|numeric|between:-180,180',
        'seo_price_range' => 'nullable|max:20',
        'seo_robots_extra' => 'nullable|max:5000',
    ];

    public function index(): Response
    {
        $tab = in_array($this->request->str('tab'), self::TABS, true) ? $this->request->str('tab') : 'general';
        $db = Database::instance();
        $data = ['tab' => $tab];
        if ($tab === 'general') {
            $values = [];
            foreach (array_keys(self::GENERAL) as $k) {
                $values[$k] = (string) SettingsService::get($k, '');
            }
            $values['seo_log_404'] = (bool) SettingsService::get('seo_log_404', true);
            $data += ['values' => $values, 'robots' => SitemapService::robots(), 'schema' => SeoService::organization()];
        } elseif ($tab === 'pages') {
            $rows = [];
            foreach ($db->fetchAll('SELECT * FROM seo_pages') as $r) {
                $rows[$r['page_key']][$r['locale']] = $r;
            }
            $data += ['pages' => SeoPages::PAGES, 'rows' => $rows, 'locales' => Translator::SUPPORTED, 'siteName' => (string) setting('company_name', 'BERRADI WALL ART')];
        } elseif ($tab === 'redirects') {
            $data += ['redirects' => $db->fetchAll('SELECT * FROM seo_redirects ORDER BY id DESC LIMIT 500'), 'prefill' => (string) $this->request->query('from', '')];
        } elseif ($tab === 'not_found') {
            $data += ['missing' => $db->fetchAll(
                'SELECT n.*, (SELECT r.id FROM seo_redirects r WHERE r.source_hash = n.path_hash) AS redirect_id FROM seo_not_found n ORDER BY n.hits DESC, n.last_seen_at DESC LIMIT 300'
            )];
        } else {
            $data += ['audit' => SeoAudit::run()];
        }
        $data['counts'] = [
            'redirects' => (int) $db->value('SELECT COUNT(*) FROM seo_redirects'),
            'not_found' => (int) $db->value('SELECT COUNT(*) FROM seo_not_found n WHERE NOT EXISTS (SELECT 1 FROM seo_redirects r WHERE r.source_hash = n.path_hash)'),
        ];
        return $this->view('admin/seo/index', $data);
    }

    public function general(): Response
    {
        $d = $this->validate(self::GENERAL, ['seo_opening_hours' => __('seo_admin.opening_hours'), 'seo_geo_lat' => __('seo_admin.lat'), 'seo_geo_lng' => __('seo_admin.lng')]);
        foreach (array_keys(self::GENERAL) as $k) {
            $v = trim((string) ($d[$k] ?? ''));
            if ($k === 'seo_twitter_site') {
                $v = ltrim($v, '@');
            }
            if ($k === 'seo_robots_extra') {
                $kept = SitemapService::extraRobotsLines($v);
                $lines = array_filter(array_map('trim', preg_split('/\R/', $v) ?: []));
                if (count($kept) !== count($lines)) {
                    throw new ValidationException(['seo_robots_extra' => __('seo_admin.err_robots')], $this->request->all());
                }
                $v = implode("\n", $kept);
            }
            SettingsService::set($k, $v, 'seo');
        }
        SettingsService::set('seo_log_404', $this->request->bool('seo_log_404') ? '1' : '0', 'seo', 'bool');
        SitemapService::forget();
        ActivityLogger::log('seo.settings_updated');
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.seo.index', ['tab' => 'general']));
    }

    public function savePage(): Response
    {
        $path = $this->request->str('page');
        if (!isset(SeoPages::PAGES[$path])) {
            throw new HttpException(404);
        }
        $rules = [];
        foreach (Translator::SUPPORTED as $l) {
            $rules["title_{$l}"] = 'nullable|max:191';
            $rules["description_{$l}"] = 'nullable|max:320';
        }
        $d = $this->validate($rules);
        $values = [];
        foreach (Translator::SUPPORTED as $l) {
            $values[$l] = ['meta_title' => $d["title_{$l}"] ?? null, 'meta_description' => $d["description_{$l}"] ?? null, 'noindex' => $this->request->bool("noindex_{$l}")];
        }
        SeoPages::save($path, $values);
        ActivityLogger::log('seo.page_updated', null, null, $path);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.seo.index', ['tab' => 'pages']) . '#page-' . md5($path));
    }

    public function saveRedirect(?string $id = null): Response
    {
        $db = Database::instance();
        $row = $id !== null ? $db->fetch('SELECT * FROM seo_redirects WHERE id = ?', [(int) $id]) : null;
        if ($id !== null && $row === null) {
            throw new HttpException(404);
        }
        $d = $this->validate([
            'source_path' => 'required|max:500',
            'target_url' => 'required|max:500',
            'status_code' => 'required|in:301,302',
            'note' => 'nullable|max:191',
        ], ['source_path' => __('seo_admin.source'), 'target_url' => __('seo_admin.target')]);
        $source = RedirectService::normalize((string) $d['source_path']);
        $target = trim((string) $d['target_url']);
        $admin = '/' . trim((string) config('app.admin_path', 'admin'), '/');
        if ($source === '/' || str_starts_with($source, $admin)) {
            throw new ValidationException(['source_path' => __('seo_admin.err_source')], $this->request->all());
        }
        if (!RedirectService::validTarget($source, $target)) {
            throw new ValidationException(['target_url' => __('seo_admin.err_target')], $this->request->all());
        }
        // no chains: the target must not itself be redirected
        if (str_starts_with($target, '/') && $db->value('SELECT id FROM seo_redirects WHERE source_hash = ? AND id <> ?', [RedirectService::hash($target), (int) ($row['id'] ?? 0)]) !== null) {
            throw new ValidationException(['target_url' => __('seo_admin.err_chain')], $this->request->all());
        }
        $dup = $db->value('SELECT id FROM seo_redirects WHERE source_hash = ? AND id <> ?', [sha1($source), (int) ($row['id'] ?? 0)]);
        if ($dup !== null) {
            throw new ValidationException(['source_path' => __('seo_admin.err_duplicate')], $this->request->all());
        }
        $values = ['source_path' => $source, 'source_hash' => sha1($source), 'target_url' => $target, 'status_code' => (int) $d['status_code'],
            'note' => $d['note'] ?? null, 'is_active' => $this->request->bool('is_active') || $row === null ? 1 : 0];
        if ($row === null) {
            $values['created_by'] = $this->user()['id'] ?? null;
            $id = (string) $db->insert('seo_redirects', $values);
        } else {
            $db->update('seo_redirects', $values, ['id' => (int) $id]);
        }
        ActivityLogger::log('seo.redirect_saved', null, null, $source . ' → ' . $target);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.seo.index', ['tab' => 'redirects']));
    }

    public function deleteRedirect(string $id): Response
    {
        $db = Database::instance();
        $r = $db->fetch('SELECT * FROM seo_redirects WHERE id = ?', [(int) $id]);
        if ($r === null) {
            throw new HttpException(404);
        }
        $db->delete('seo_redirects', ['id' => (int) $id]);
        ActivityLogger::log('seo.redirect_deleted', null, null, $r['source_path'] . ' → ' . $r['target_url']);
        $this->flash('success', __('common.deleted'));
        return $this->redirect(route('admin.seo.index', ['tab' => 'redirects']));
    }

    public function clearNotFound(?string $id = null): Response
    {
        $db = Database::instance();
        if ($id !== null) {
            $db->delete('seo_not_found', ['id' => (int) $id]);
        } else {
            $db->query('DELETE FROM seo_not_found');
            ActivityLogger::log('seo.not_found_cleared');
        }
        $this->flash('success', __('common.deleted'));
        return $this->redirect(route('admin.seo.index', ['tab' => 'not_found']));
    }

    /** @return array<string,mixed>|null */
    private function user(): ?array
    {
        return \App\Services\Auth::user();
    }
}
