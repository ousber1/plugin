<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use App\Services\Tracking\TrackingService;

/**
 * Analytics & advertising tags: IDs (validated against each provider's format), consent banner, extra CSP hosts
 * for tags added in Google Tag Manager, Meta Conversions API token (encrypted, never shown again) and its log.
 */
final class TrackingController extends Controller
{
    public function index(): Response
    {
        $values = [];
        foreach (TrackingService::PROVIDERS as [$key]) {
            $values[$key] = (string) SettingsService::get($key, '');
        }
        foreach (['track_ads_purchase_label', 'track_meta_test_code', 'track_privacy_url', 'track_extra_hosts'] as $k) {
            $values[$k] = (string) SettingsService::get($k, '');
        }
        $values['track_consent_enabled'] = TrackingService::consentRequired();
        $db = Database::instance();
        return $this->view('admin/tracking/index', [
            'values' => $values,
            'enabled' => TrackingService::enabled(),
            'tokenSet' => TrackingService::capiToken() !== '',
            'capiStats' => array_column($db->fetchAll("SELECT status, COUNT(*) AS n FROM tracking_events WHERE created_at > NOW() - INTERVAL 30 DAY GROUP BY status"), 'n', 'status'),
            'capiRecent' => $db->fetchAll('SELECT e.id, e.event_name, e.event_id, e.status, e.attempts, e.last_error, e.created_at, e.sent_at, o.order_number
                FROM tracking_events e LEFT JOIN orders o ON o.id = e.order_id ORDER BY e.id DESC LIMIT 15'),
        ]);
    }

    public function save(): Response
    {
        $rules = [];
        foreach (TrackingService::PROVIDERS as [$key, $pattern]) {
            $rules[$key] = ['nullable', 'max:64', 'regex:' . $pattern];
        }
        $rules += [
            'track_ads_purchase_label' => ['nullable', 'max:40', 'regex:/^[A-Za-z0-9_-]{4,40}$/'],
            'track_meta_test_code' => ['nullable', 'max:20', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'track_meta_capi_token' => ['nullable', 'max:400', 'regex:/^[A-Za-z0-9_-]{20,400}$/'],
            'track_privacy_url' => ['nullable', 'max:255', 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/'],
            'track_extra_hosts' => 'nullable|max:2000',
        ];
        $d = $this->validate($rules, [
            'track_ga4_id' => 'Google Analytics 4', 'track_gtm_id' => 'Google Tag Manager', 'track_ads_id' => 'Google Ads', 'track_meta_pixel_id' => 'Meta Pixel',
            'track_tiktok_id' => 'TikTok Pixel', 'track_snapchat_id' => 'Snap Pixel', 'track_pinterest_id' => 'Pinterest Tag', 'track_clarity_id' => 'Microsoft Clarity',
            'track_meta_capi_token' => __('tracking_admin.capi_token'), 'track_extra_hosts' => __('tracking_admin.extra_hosts'),
        ]);
        $hosts = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($d['track_extra_hosts'] ?? '')) ?: []));
        foreach ($hosts as $h) {
            if (!preg_match('#^https://(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+/?$#', $h)) {
                throw new ValidationException(['track_extra_hosts' => __('tracking_admin.err_host', ['host' => $h])], $this->request->all());
            }
        }
        foreach (TrackingService::PROVIDERS as [$key]) {
            SettingsService::set($key, trim((string) ($d[$key] ?? '')), 'tracking');
        }
        foreach (['track_ads_purchase_label', 'track_meta_test_code', 'track_privacy_url'] as $k) {
            SettingsService::set($k, trim((string) ($d[$k] ?? '')), 'tracking');
        }
        SettingsService::set('track_extra_hosts', implode("\n", array_map(fn ($h) => rtrim($h, '/'), $hosts)), 'tracking', 'text');
        SettingsService::set('track_consent_enabled', $this->request->bool('track_consent_enabled') ? '1' : '0', 'tracking', 'bool');
        $tokenChanged = false;
        if ($this->request->bool('clear_capi_token')) {
            SettingsService::set('track_meta_capi_token', '', 'tracking', 'encrypted');
            $tokenChanged = true;
        } elseif (trim((string) ($d['track_meta_capi_token'] ?? '')) !== '') {
            SettingsService::set('track_meta_capi_token', trim((string) $d['track_meta_capi_token']), 'tracking', 'encrypted');
            $tokenChanged = true;
        }
        ActivityLogger::log('tracking.updated', null, null, implode(', ', array_keys(TrackingService::enabled())) ?: '—', ['token_changed' => $tokenChanged]);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.tracking.index');
    }

    /** Sends a test event to Meta (visible in Events Manager → Test events when a test code is set). */
    public function test(): Response
    {
        $pixel = TrackingService::enabled()['meta'] ?? null;
        $token = TrackingService::capiToken();
        $code = trim((string) setting('track_meta_test_code', ''));
        if ($pixel === null || $token === '' || $code === '') {
            $this->flash('warning', __('tracking_admin.test_needs'));
            return $this->redirectRoute('admin.tracking.index');
        }
        try {
            $res = TrackingService::httpPost('https://graph.facebook.com/v19.0/' . rawurlencode($pixel) . '/events?access_token=' . rawurlencode($token), [
                'data' => [['event_name' => 'PageView', 'event_time' => time(), 'event_id' => 'test-' . TrackingService::newId(), 'action_source' => 'website',
                    'event_source_url' => absolute_url('/'), 'user_data' => ['client_ip_address' => $this->request->ip(), 'client_user_agent' => $this->request->userAgent()]]],
                'test_event_code' => $code,
            ]);
            $ok = $res['status'] >= 200 && $res['status'] < 300;
            $this->flash($ok ? 'success' : 'danger', $ok ? __('tracking_admin.test_ok') : __('tracking_admin.test_failed', ['error' => str_replace($token, '***', 'HTTP ' . $res['status'] . ' ' . $res['body'])]));
        } catch (\Throwable $e) {
            $this->flash('danger', __('tracking_admin.test_failed', ['error' => str_replace($token, '***', $e->getMessage())]));
        }
        return $this->redirectRoute('admin.tracking.index');
    }
}
