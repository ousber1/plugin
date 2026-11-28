<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\ImageUploadService;
use App\Services\TranslationService;
use App\Services\UploadException;

/**
 * Storefront content: banners (hero / promo), customer gallery (with consent) and FAQ, in French and Arabic.
 */
final class ContentController extends Controller
{
    private const TYPES = [
        'banners' => ['table' => 'banners', 'entity' => 'banner', 'ar' => ['title', 'subtitle', 'button_text']],
        'gallery' => ['table' => 'gallery_items', 'entity' => 'gallery', 'ar' => ['title']],
        'faqs' => ['table' => 'faqs', 'entity' => 'faq', 'ar' => ['question', 'answer']],
    ];

    public function index(): Response
    {
        $tab = array_key_exists($this->request->str('tab'), self::TYPES) ? $this->request->str('tab') : 'banners';
        $db = Database::instance();
        $data = [];
        foreach (self::TYPES as $k => $t) {
            $rows = $db->fetchAll("SELECT * FROM {$t['table']} ORDER BY sort_order, id");
            $ar = [];
            foreach ($db->fetchAll("SELECT entity_id, field, value FROM translations WHERE entity_type = ? AND locale = 'ar'", [$t['entity']]) as $tr) {
                $ar[(int) $tr['entity_id']][$tr['field']] = $tr['value'];
            }
            $data[$k] = ['rows' => $rows, 'ar' => $ar];
        }
        return $this->view('admin/content/index', ['tab' => $tab, 'data' => $data, 'products' => $db->fetchAll('SELECT id, title FROM products WHERE deleted_at IS NULL ORDER BY title')]);
    }

    public function save(string $type, ?string $id = null): Response
    {
        if (!isset(self::TYPES[$type])) {
            throw new HttpException(404);
        }
        $t = self::TYPES[$type];
        $db = Database::instance();
        $row = $id !== null ? $db->fetch("SELECT * FROM {$t['table']} WHERE id = ?", [(int) $id]) : null;
        if ($id !== null && $row === null) {
            throw new HttpException(404);
        }
        $rules = match ($type) {
            'banners' => ['position' => 'required|in:hero,promo', 'title' => 'required|max:191', 'subtitle' => 'nullable|max:500', 'button_text' => 'nullable|max:80',
                'link_url' => ['nullable', 'max:255', 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/'], 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date'],
            'gallery' => ['title' => 'required|max:191', 'city' => 'nullable|max:100', 'product_id' => 'nullable|integer|exists:products,id'],
            default => ['question' => 'required|max:255', 'answer' => 'required|max:5000', 'category' => 'nullable|max:60'],
        };
        $rules += ['sort_order' => 'nullable|integer'];
        foreach ($t['ar'] as $f) {
            $rules['ar_' . $f] = 'nullable|max:' . ($f === 'answer' ? 5000 : 500);
        }
        $d = $this->validate($rules, ['link_url' => __('content.link')]);
        $values = array_intersect_key($d, array_flip(array_filter(array_keys($rules), fn ($k) => !str_starts_with($k, 'ar_'))));
        $values['sort_order'] = (int) ($d['sort_order'] ?? 0);
        $values['is_active'] = $this->request->bool('is_active') ? 1 : 0;
        if ($type === 'banners') {
            $dt = fn ($v) => $v !== null && $v !== '' ? date('Y-m-d H:i:s', (int) strtotime((string) $v)) : null;
            $values['starts_at'] = $dt($d['starts_at'] ?? null);
            $values['ends_at'] = $dt($d['ends_at'] ?? null);
        }
        if ($type === 'gallery') {
            $values['product_id'] = !empty($d['product_id']) ? (int) $d['product_id'] : null;
            // a customer's interior is published only with their documented consent
            $values['customer_consent'] = $this->request->bool('customer_consent') ? 1 : 0;
        }
        if ($type !== 'faqs') {
            $file = $this->request->file('image');
            if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $values['image'] = ImageUploadService::storePublic($file, $type, $type === 'banners' ? 2400 : 1600);
                } catch (UploadException $e) {
                    throw new ValidationException(['image' => $e->getMessage()], $this->request->all());
                }
            } elseif ($row === null) {
                throw new ValidationException(['image' => __('content.err_image')], $this->request->all());
            }
        }
        if ($row === null) {
            $id = (string) $db->insert($t['table'], $values);
        } else {
            $db->update($t['table'], $values, ['id' => (int) $id]);
        }
        $ar = [];
        foreach ($t['ar'] as $f) {
            $ar[$f] = $d['ar_' . $f] ?? null;
        }
        TranslationService::save($t['entity'], (int) $id, 'ar', $ar);
        ActivityLogger::log('content.' . $t['entity'] . '_saved', $t['entity'], (int) $id);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.content.index', ['tab' => $type]) . '#' . $type . '-' . (int) $id);
    }

    public function destroy(string $type, string $id): Response
    {
        if (!isset(self::TYPES[$type])) {
            throw new HttpException(404);
        }
        $t = self::TYPES[$type];
        $db = Database::instance();
        if ($db->value("SELECT id FROM {$t['table']} WHERE id = ?", [(int) $id]) === null) {
            throw new HttpException(404);
        }
        $db->delete($t['table'], ['id' => (int) $id]);
        $db->query('DELETE FROM translations WHERE entity_type = ? AND entity_id = ?', [$t['entity'], (int) $id]);
        ActivityLogger::log('content.' . $t['entity'] . '_deleted', $t['entity'], (int) $id);
        $this->flash('success', __('common.deleted'));
        return $this->redirect(route('admin.content.index', ['tab' => $type]));
    }

    /** Announcement strip under the header (two messages, FR / AR, optional link). */
    public function announcements(): Response
    {
        $rules = [];
        foreach ([1, 2] as $n) {
            $rules["promo_bar_{$n}_text"] = 'nullable|max:120';
            $rules["promo_bar_{$n}_text_ar"] = 'nullable|max:120';
            $rules["promo_bar_{$n}_link"] = ['nullable', 'max:255', 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/'];
        }
        $d = $this->validate($rules, ['promo_bar_1_link' => __('content.link'), 'promo_bar_2_link' => __('content.link')]);
        foreach (array_keys($rules) as $k) {
            \App\Services\SettingsService::set($k, trim((string) ($d[$k] ?? '')), 'content');
        }
        ActivityLogger::log('content.announcements_saved');
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.content.index') . '#announcements');
    }
}
