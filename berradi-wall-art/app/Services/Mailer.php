<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\View;

/**
 * E-mail sending via PHPMailer (SMTP or PHP mail()). Settings come from admin > settings > e-mail.
 * If PHPMailer is not installed (vendor missing) it falls back to PHP's mail() function.
 */
final class Mailer
{
    /** @var list<array{to:string,subject:string,html:string}> captured messages when in "log" driver (tests) */
    public static array $sent = [];

    /**
     * Render an e-mail template from resources/views/emails and send it.
     *
     * @param array<string,mixed> $data
     */
    public static function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $html = View::make('emails/' . $template, $data + ['subject' => $subject]);
        return self::send($to, $subject, $html);
    }

    public static function send(string $to, string $subject, string $html, ?string $replyTo = null): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('Mail not sent: invalid recipient');
            return false;
        }
        $driver = (string) setting('mail_driver', 'mail');
        $fromAddress = (string) (setting('mail_from_address') ?: Config::get('mail.from_address') ?: setting('company_email', ''));
        $fromName = (string) (setting('mail_from_name') ?: setting('company_name', 'BERRADI WALL ART'));

        if ($driver === 'log' || PHP_SAPI === 'cli' && getenv('BWA_TESTING') === '1') {
            self::$sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html];
            Logger::info('Mail (log driver) to ' . $to . ': ' . $subject);
            return true;
        }

        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->CharSet = 'UTF-8';
                if ($driver === 'smtp') {
                    $mail->isSMTP();
                    $mail->Host = (string) setting('mail_host', '');
                    $mail->Port = (int) setting('mail_port', 465);
                    $enc = (string) setting('mail_encryption', 'ssl');
                    $mail->SMTPSecure = $enc === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                        : ($enc === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : '');
                    $mail->SMTPAutoTLS = $enc !== 'none';
                    $user = (string) setting('mail_username', '');
                    if ($user !== '') {
                        $mail->SMTPAuth = true;
                        $mail->Username = $user;
                        $mail->Password = (string) setting('mail_password', '');
                    }
                    $mail->Timeout = 15;
                } else {
                    $mail->isMail();
                }
                if ($fromAddress !== '') {
                    $mail->setFrom($fromAddress, $fromName);
                }
                if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $mail->addReplyTo($replyTo);
                }
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $html;
                $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
                $mail->send();
                return true;
            } catch (\Throwable $e) {
                Logger::error('Mail failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
                return false;
            }
        }

        // fallback without PHPMailer
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];
        if ($fromAddress !== '') {
            $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddress . '>';
        }
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers));
        if (!$ok) {
            Logger::error('mail() failed', ['to' => $to]);
        }
        return $ok;
    }
}
