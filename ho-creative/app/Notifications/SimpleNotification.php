<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail built from an editable NotificationTemplate (Admin → Paramètres → Notifications).
 * Placeholders: {order} {quote} {total} {url} {version} {expires} {name} {status}.
 */
class SimpleNotification extends Notification
{
    public const DEFAULTS = [
        'order_placed' => ['subject' => ['fr' => 'Commande {order} reçue', 'ar' => 'تم استلام الطلب {order}'],
            'body' => ['fr' => "Merci pour votre commande {order} d'un montant de {total}.\nSuivez-la ici : {url}", 'ar' => "شكراً على طلبكم {order} بمبلغ {total}.\nتتبعوه هنا: {url}"]],
        'proof_ready' => ['subject' => ['fr' => 'BAT v{version} à valider – commande {order}', 'ar' => 'نموذج الطباعة v{version} للمصادقة – الطلب {order}'],
            'body' => ['fr' => "Votre bon à tirer (version {version}) est prêt. Merci de l'approuver ou de demander des corrections : {url}\nLa production démarre après votre validation.", 'ar' => "نموذج الطباعة (الإصدار {version}) جاهز. يرجى المصادقة عليه أو طلب تعديلات: {url}"]],
        'quote_sent' => ['subject' => ['fr' => 'Votre devis {quote}', 'ar' => 'عرض السعر {quote}'],
            'body' => ['fr' => "Votre devis {quote} ({total}) est disponible jusqu'au {expires} : {url}", 'ar' => "عرض السعر {quote} ({total}) متاح حتى {expires}: {url}"]],
        'order_status' => ['subject' => ['fr' => 'Commande {order} : {status}', 'ar' => 'الطلب {order}: {status}'],
            'body' => ['fr' => "Le statut de votre commande {order} a changé : {status}.\n{url}", 'ar' => "تغيرت حالة طلبكم {order}: {status}.\n{url}"]],
        'quote_request' => ['subject' => ['fr' => 'Demande de devis reçue', 'ar' => 'تم استلام طلب عرض السعر'],
            'body' => ['fr' => "Bonjour {name}, nous avons bien reçu votre demande. Un conseiller vous répond rapidement.", 'ar' => "مرحباً {name}، توصلنا بطلبكم وسيتواصل معكم مستشارنا قريباً."]],
    ];

    public function __construct(public string $key, public array $data, string $lang = 'fr')
    {
        $this->lang = $lang;
    }

    public string $lang;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $tpl = NotificationTemplate::where('key', $this->key)->where('is_active', true)->first();
        $subject = $tpl?->subject ?? self::DEFAULTS[$this->key]['subject'] ?? ['fr' => $this->key];
        $body = $tpl?->body ?? self::DEFAULTS[$this->key]['body'] ?? ['fr' => ''];
        $l = $this->lang;
        $rep = fn ($s) => strtr((string) ($s[$l] ?? $s['fr'] ?? ''), collect($this->data)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
        $mail = (new MailMessage)->subject($rep($subject))->greeting(setting('brand.name'));
        foreach (explode("\n", $rep($body)) as $line) {
            $mail->line($line);
        }
        if (! empty($this->data['url'])) {
            $mail->action($l === 'ar' ? 'فتح' : 'Ouvrir', $this->data['url']);
        }

        return $mail;
    }
}
