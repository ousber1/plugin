<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Translator;
use App\Services\ActivityLogger;
use App\Services\CatalogService;
use App\Services\Mailer;
use App\Services\SeoService;

/**
 * About, Contact and FAQ pages.
 */
final class PageController extends Controller
{
    /** Editable page text: setting "{key}_{locale}" with the translation file as default. */
    private function content(string $key): string
    {
        $value = (string) setting($key . '_' . Translator::locale(), '');
        return $value !== '' ? $value : __('pages.' . $key);
    }

    public function about(): Response
    {
        return $this->view('storefront/pages/about', [
            'title' => $this->content('about_title'),
            'body' => $this->content('about_body'),
            'collections' => CatalogService::collections(true, 3),
            'seo' => SeoService::page(__('nav.about'), $this->content('about_body'), [
                'canonical' => '/about',
                'jsonLd' => [SeoService::breadcrumbs([[__('nav.home'), '/'], [__('nav.about'), null]])],
            ]),
        ]);
    }

    public function contact(): Response
    {
        $order = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string) $this->request->query('order', '')));
        return $this->view('storefront/pages/contact', [
            'hours' => $this->content('contact_hours'),
            'orderRef' => $order,
            'seo' => SeoService::page(__('nav.contact'), __('pages.contact_intro'), [
                'canonical' => '/contact',
                'jsonLd' => [SeoService::organization()],
            ]),
        ]);
    }

    public function sendContact(): Response
    {
        // honeypot: bots fill hidden fields; pretend success
        if (trim((string) $this->request->input('website', '')) !== '') {
            $this->flash('success', __('pages.contact_sent'));
            return $this->redirectRoute('page.contact');
        }
        $data = $this->validate([
            'name' => 'required|max:120',
            'email' => 'required|email',
            'phone' => 'nullable|phone',
            'subject' => 'required|in:order,custom,quote,b2b,other',
            'message' => 'required|min:10|max:3000',
        ], [
            'name' => __('pages.your_name'),
            'subject' => __('pages.subject'),
            'message' => __('pages.message'),
        ]);
        $subjectLabel = __('pages.subject_' . $data['subject']);
        $body = sprintf(
            "%s: %s\n%s: %s\n%s: %s\n%s: %s\n\n%s",
            __('pages.your_name'), $data['name'],
            __('auth.email'), $data['email'],
            __('auth.phone'), $data['phone'] ?? '-',
            __('pages.subject'), $subjectLabel,
            $data['message']
        );
        $to = (string) (setting('admin_notification_email') ?: setting('company_email', ''));
        $db = Database::instance();
        $id = $db->insert('notifications', [
            'channel' => 'email',
            'event' => 'contact_message',
            'recipient' => $to !== '' ? $to : 'admin',
            'subject' => mb_substr('[' . $subjectLabel . '] ' . $data['name'], 0, 255),
            'body' => $body,
            'status' => 'queued',
        ]);
        $sent = $to !== '' && Mailer::send(
            $to,
            '[Contact] ' . $subjectLabel . ' — ' . $data['name'],
            nl2br(e($body)),
            (string) $data['email']
        );
        $db->update('notifications', ['status' => $sent ? 'sent' : 'queued', 'sent_at' => $sent ? date('Y-m-d H:i:s') : null], ['id' => $id]);
        ActivityLogger::log('contact.message', 'notification', $id, (string) $data['email']);
        \App\Services\Notifications\StaffNotifier::notify('contact_received', 'customers.view',
            __('notifications.contact', ['name' => $data['name'], 'subject' => $subjectLabel]), 'notifications/emails/' . $id, 'notification', $id);
        \App\Services\Tracking\TrackingService::queue('generate_lead', ['items' => []]);
        $this->flash('success', __('pages.contact_sent'));
        return $this->redirectRoute('page.contact');
    }

    public function terms(): Response
    {
        return $this->view('storefront/pages/terms', [
            'body' => $this->content('terms_body'),
            'seo' => SeoService::page(__('pages.terms'), null, ['canonical' => '/terms']),
        ]);
    }

    public function faq(): Response
    {
        $faqs = CatalogService::faqs();
        $jsonLd = [SeoService::breadcrumbs([[__('nav.home'), '/'], [__('pages.faq'), null]])];
        if ($faqs !== []) {
            $jsonLd[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $f['answer'])],
                ], $faqs),
            ];
        }
        return $this->view('storefront/pages/faq', [
            'faqs' => $faqs,
            'seo' => SeoService::page(__('pages.faq'), __('pages.faq_intro'), ['canonical' => '/faq', 'jsonLd' => $jsonLd]),
        ]);
    }
}
