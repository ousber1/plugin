<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Translator;
use App\Core\ValidationException;
use App\Core\Validator;

/**
 * Newsletter opt-in (explicit consent) and one-click unsubscribe.
 */
final class NewsletterController extends Controller
{
    public function subscribe(): Response
    {
        $v = Validator::make($this->request->all(), ['email' => 'required|email', 'consent' => 'required|in:1,on']);
        if ($v->fails()) {
            if ($this->request->isAjax()) {
                return $this->json(['ok' => false, 'message' => __('newsletter.invalid')], 422);
            }
            throw new ValidationException(['newsletter_email' => __('newsletter.invalid')], ['newsletter_email' => $this->request->str('email')]);
        }
        $email = mb_strtolower((string) $v->validated()['email']);
        $db = Database::instance();
        $existing = $db->fetch('SELECT id, status FROM newsletter_subscribers WHERE email = ?', [$email]);
        if ($existing === null) {
            $db->insert('newsletter_subscribers', [
                'email' => $email,
                'locale' => Translator::locale(),
                'status' => 'subscribed',
                'unsubscribe_token' => bin2hex(random_bytes(32)),
                'source' => mb_substr($this->request->str('source', 'footer'), 0, 50),
                'ip' => $this->request->ip(),
            ]);
        } elseif ($existing['status'] !== 'subscribed') {
            $db->update('newsletter_subscribers', ['status' => 'subscribed', 'unsubscribed_at' => null, 'subscribed_at' => date('Y-m-d H:i:s')], ['id' => (int) $existing['id']]);
        }
        // same answer whether or not the address was already known (no e-mail enumeration)
        if ($this->request->isAjax()) {
            return $this->json(['ok' => true, 'message' => __('newsletter.thanks')]);
        }
        $this->flash('success', __('newsletter.thanks'));
        return $this->back();
    }

    public function unsubscribe(string $token): Response
    {
        $ok = false;
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $ok = Database::instance()->query(
                "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE unsubscribe_token = ?",
                [$token]
            )->rowCount() > 0 || Database::instance()->value('SELECT id FROM newsletter_subscribers WHERE unsubscribe_token = ?', [$token]) !== null;
        }
        return $this->view('storefront/pages/message', [
            'heading' => $ok ? __('newsletter.unsubscribed_title') : __('errors.404_title'),
            'text' => $ok ? __('newsletter.unsubscribed_text') : __('newsletter.invalid_link'),
            'seo' => \App\Services\SeoService::page(__('newsletter.unsubscribed_title'), null, ['noindex' => true]),
        ], $ok ? 200 : 404);
    }
}
