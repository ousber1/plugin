<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Core\Translator;
use App\Services\Mailer;

/**
 * Order e-mails (customer in the order's language + internal alert), each recorded in `notifications`.
 */
final class OrderMailer
{
    /** @param array<string,mixed> $order */
    public static function placed(array $order, string $token): void
    {
        $trackUrl = absolute_url('track/' . $order['order_number'] . '/' . $token);
        if (!empty($order['customer_email'])) {
            self::send($order, (string) $order['customer_email'], 'order_placed', 'order_confirmation', ['trackUrl' => $trackUrl], (string) $order['locale']);
        }
        $admin = (string) (setting('admin_notification_email') ?: setting('company_email', ''));
        if ($admin !== '') {
            self::send($order, $admin, 'order_new_admin', 'order_admin', ['adminUrl' => absolute_url(trim((string) config('app.admin_path', 'admin'), '/'))], (string) setting('default_language', 'fr'));
        }
    }

    /** @param array<string,mixed> $order */
    public static function cancelled(array $order): void
    {
        if (!empty($order['customer_email'])) {
            self::send($order, (string) $order['customer_email'], 'order_cancelled', 'order_cancelled', [], (string) $order['locale']);
        }
        $admin = (string) (setting('admin_notification_email') ?: setting('company_email', ''));
        if ($admin !== '') {
            self::send($order, $admin, 'order_cancelled_admin', 'order_cancelled', ['internal' => true], (string) setting('default_language', 'fr'));
        }
    }

    /**
     * Status update chosen by staff (with optional message and shipment tracking).
     *
     * @param array<string,mixed> $order
     * @param array<string,mixed>|null $shipment
     */
    public static function statusChanged(array $order, string $comment = '', ?array $shipment = null): void
    {
        if (empty($order['customer_email'])) {
            return;
        }
        self::send($order, (string) $order['customer_email'], 'order_status_' . $order['status'], 'order_status', [
            'comment' => $comment, 'shipment' => $shipment, 'trackUrl' => absolute_url('track?number=' . rawurlencode((string) $order['order_number'])),
        ], (string) $order['locale']);
    }

    /**
     * Artwork proof to approve (link carries a single-use secret; only its hash is stored).
     *
     * @param array<string,mixed> $order
     */
    public static function artworkProof(array $order, string $proofUrl, string $message = ''): void
    {
        if (empty($order['customer_email'])) {
            return;
        }
        self::send($order, (string) $order['customer_email'], 'artwork_proof', 'artwork_proof', ['proofUrl' => $proofUrl, 'message' => $message], (string) $order['locale']);
    }

    /**
     * Quotation / invoice e-mail to the customer (link to view and download the document).
     *
     * @param array<string,mixed> $doc row with id, customer_email, customer_name, locale and order_number = document number
     * @param array<string,mixed> $extra
     */
    public static function document(array $doc, string $relatedType, string $template, array $extra): void
    {
        if (empty($doc['customer_email'])) {
            return;
        }
        self::send($doc, (string) $doc['customer_email'], $template, $template, $extra + ['related_type' => $relatedType], (string) ($doc['locale'] ?? 'fr'));
    }

    /** Internal alert (artwork feedback from the customer, etc.). @param array<string,mixed> $order */
    public static function internal(array $order, string $event, string $message): void
    {
        $admin = (string) (setting('admin_notification_email') ?: setting('company_email', ''));
        if ($admin !== '') {
            self::send($order, $admin, $event, 'internal_alert', ['message' => $message], (string) setting('default_language', 'fr'));
        }
    }

    /** @param array<string,mixed> $order @param array<string,mixed> $extra */
    private static function send(array $order, string $to, string $event, string $template, array $extra, string $locale): void
    {
        $previous = Translator::locale();
        Translator::setLocale($locale);
        try {
            $subject = __('emails.' . $template . '_subject', ['number' => (string) $order['order_number']]);
            $method = ($order['payment_method_id'] ?? null) !== null
                ? Database::instance()->fetch('SELECT code, name, instructions FROM payment_methods WHERE id = ?', [(int) $order['payment_method_id']])
                : null;
            $html = \App\Core\View::make('emails/' . $template, $extra + [
                'subject' => $subject,
                'order' => $order,
                'items' => isset($order['shipping_city']) ? OrderService::items((int) $order['id']) : [],
                'method' => $method,
            ]);
            $id = Database::instance()->insert('notifications', [
                'channel' => 'email', 'event' => $event, 'recipient' => $to, 'subject' => mb_substr($subject, 0, 255),
                'body' => $html, 'related_type' => $extra['related_type'] ?? 'order', 'related_id' => (int) $order['id'], 'status' => 'queued',
            ]);
            $ok = Mailer::send($to, $subject, $html);
            Database::instance()->update('notifications', [
                'status' => $ok ? 'sent' : 'queued', 'attempts' => 1, 'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
                'last_error' => $ok ? null : 'mail transport failed (will be retried by cron)',
            ], ['id' => $id]);
        } finally {
            Translator::setLocale($previous);
        }
    }

    /** Cron: retry queued e-mails (max 5 attempts). */
    public static function retryQueued(int $limit = 30): int
    {
        $sent = 0;
        foreach (Database::instance()->fetchAll("SELECT * FROM notifications WHERE channel = 'email' AND status = 'queued' AND attempts < 5 ORDER BY id LIMIT " . $limit) as $n) {
            $ok = Mailer::send((string) $n['recipient'], (string) $n['subject'], (string) $n['body']);
            Database::instance()->update('notifications', [
                'status' => $ok ? 'sent' : ((int) $n['attempts'] + 1 >= 5 ? 'failed' : 'queued'),
                'attempts' => (int) $n['attempts'] + 1, 'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
            ], ['id' => (int) $n['id']]);
            $sent += $ok ? 1 : 0;
        }
        return $sent;
    }
}
