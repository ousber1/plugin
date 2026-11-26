<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\ImageUploadService;
use App\Services\Mailer;
use App\Services\SettingsService;
use App\Services\UploadException;

/**
 * Company & application settings, organised in tabs.
 */
final class SettingsController extends Controller
{
    /** @return array<string,array<string,string>> tab => [field => rules] */
    private function tabs(): array
    {
        return [
            'company' => [
                'company_name' => 'required|max:150',
                'company_legal_name' => 'nullable|max:191',
                'company_email' => 'required|email',
                'company_phone' => 'required|phone',
                'company_whatsapp' => 'nullable|phone',
                'company_address' => 'nullable|max:500',
                'company_city' => 'nullable|max:100',
                'company_ice' => 'nullable|max:30|regex:/^[0-9A-Za-z ]+$/',
                'company_rc' => 'nullable|max:30',
                'company_if' => 'nullable|max:30',
                'company_patente' => 'nullable|max:30',
                'company_cnss' => 'nullable|max:30',
                'company_bank_name' => 'nullable|max:100',
                'company_rib' => 'nullable|max:40|regex:/^[0-9 ]+$/',
                'company_website' => 'nullable|url|max:191',
                'social_instagram' => 'nullable|url|max:191',
                'social_facebook' => 'nullable|url|max:191',
                'social_tiktok' => 'nullable|url|max:191',
            ],
            'localization' => [
                'default_language' => 'required|locale',
                'currency_code' => 'required|in:MAD',
                'currency_symbol' => 'required|in:DH,MAD,د.م.',
                'currency_position' => 'required|in:before,after',
            ],
            'taxes' => [
                'tax_enabled' => 'nullable|boolean',
                'tax_default_rate' => 'required|decimal:2|between:0,100',
                'tax_prices_include_tax' => 'nullable|boolean',
                'tax_label' => 'required|max:20',
            ],
            'documents' => [
                'order_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
                'invoice_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
                'credit_note_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
                'quotation_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
                'payment_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
                'purchase_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
                'invoice_due_days' => 'required|integer|between:0,365',
                'quotation_validity_days' => 'required|integer|between:1,365',
                'invoice_payment_terms' => 'nullable|max:500',
                'quotation_terms' => 'nullable|max:2000',
                'invoice_footer_note' => 'nullable|max:500',
            ],
            'shipping' => [
                'shipping_flat_rate' => 'required|decimal:2|min:0',
                'shipping_free_threshold' => 'nullable|decimal:2|min:0',
                'shipping_min_days' => 'required|integer|between:0,60',
                'shipping_max_days' => 'required|integer|between:0,60',
                'production_days' => 'required|integer|between:0,60',
            ],
            'orders' => [
                'guest_checkout_enabled' => 'nullable|boolean',
                'low_dpi_warning' => 'required|integer|between:30,300',
                'min_dpi_block' => 'required|integer|between:10,300',
                'upload_max_mb' => 'required|integer|between:1,100',
                'upload_max_files' => 'required|integer|between:1,20',
                'retention_unordered_days' => 'required|integer|between:1,365',
                'retention_ordered_days' => 'required|integer|between:30,3650',
            ],
            'mail' => [
                'mail_driver' => 'required|in:mail,smtp,log',
                'mail_host' => 'nullable|max:191|regex:/^[A-Za-z0-9.\-]+$/',
                'mail_port' => 'required|integer|between:1,65535',
                'mail_encryption' => 'required|in:ssl,tls,none',
                'mail_username' => 'nullable|max:191',
                'mail_from_address' => 'nullable|email',
                'mail_from_name' => 'nullable|max:150',
                'admin_notification_email' => 'nullable|email',
            ],
            'seo' => [
                'seo_meta_title' => 'nullable|max:191',
                'seo_meta_description' => 'nullable|max:320',
                'maintenance_mode' => 'nullable|boolean',
            ],
        ];
    }

    /** checkbox fields: absent in POST means false */
    private const BOOLEANS = ['tax_enabled', 'tax_prices_include_tax', 'guest_checkout_enabled', 'maintenance_mode'];

    public function edit(?string $tab = null): Response
    {
        $tab ??= 'company';
        if (!array_key_exists($tab, $this->tabs())) {
            throw new HttpException(404);
        }
        $values = [];
        foreach (array_keys($this->tabs()[$tab]) as $key) {
            $values[$key] = SettingsService::get($key, '');
        }
        $values['company_logo'] = SettingsService::get('company_logo', '');
        $values['seo_og_image'] = SettingsService::get('seo_og_image', '');
        $values['mail_password_set'] = (string) SettingsService::get('mail_password', '') !== '';
        return $this->view('admin/settings/index', [
            'tab' => $tab,
            'tabs' => array_keys($this->tabs()),
            'values' => $values,
        ]);
    }

    public function update(string $tab): Response
    {
        $tabs = $this->tabs();
        if (!isset($tabs[$tab])) {
            throw new HttpException(404);
        }
        $data = $this->validate($tabs[$tab]);
        foreach (self::BOOLEANS as $b) {
            if (array_key_exists($b, $tabs[$tab])) {
                $data[$b] = $this->request->bool($b);
            }
        }
        foreach ($data as $k => $v) {
            if ($v === null) {
                $data[$k] = '';
            }
        }
        if ($tab === 'shipping' && (int) $data['shipping_max_days'] < (int) $data['shipping_min_days']) {
            throw new ValidationException(['shipping_max_days' => __('admin.settings_max_days')], $this->request->all());
        }

        if ($tab === 'company') {
            $data += $this->handleImage('company_logo', 'branding', 800, $this->request->bool('remove_logo'));
        }
        if ($tab === 'seo') {
            $data += $this->handleImage('seo_og_image', 'branding', 1200, $this->request->bool('remove_og_image'));
        }
        if ($tab === 'mail') {
            $password = $this->request->input('mail_password', '');
            if (is_string($password) && $password !== '') {
                SettingsService::set('mail_password', $password, 'mail', 'encrypted');
            } elseif ($this->request->bool('clear_mail_password')) {
                SettingsService::set('mail_password', '', 'mail', 'encrypted');
            }
        }

        SettingsService::setMany($data);
        ActivityLogger::log('settings.updated', 'settings', null, 'Tab: ' . $tab, ['keys' => array_keys($data)]);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.settings', ['tab' => $tab]);
    }

    /** @return array<string,string> */
    private function handleImage(string $key, string $folder, int $maxPx, bool $remove): array
    {
        $file = $this->request->file($key);
        $current = (string) SettingsService::get($key, '');
        if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $path = ImageUploadService::storePublic($file, $folder, $maxPx);
            } catch (UploadException $e) {
                throw new ValidationException([$key => $e->getMessage()], $this->request->all());
            }
            ImageUploadService::deletePublic($current);
            return [$key => $path];
        }
        if ($remove && $current !== '') {
            ImageUploadService::deletePublic($current);
            return [$key => ''];
        }
        return [];
    }

    public function testMail(): Response
    {
        $user = Auth::user();
        $to = (string) ($user['email'] ?? '');
        $ok = Mailer::sendTemplate($to, __('admin.test_mail_subject'), 'test', ['name' => $user['name'] ?? '']);
        $this->flash($ok ? 'success' : 'danger', $ok ? __('admin.test_mail_ok', ['email' => $to]) : __('admin.test_mail_failed'));
        return $this->redirectRoute('admin.settings', ['tab' => 'mail']);
    }
}
