<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envoi d'e-mails sans dépendance externe.
 * - SMTP (AUTH LOGIN, STARTTLS ou SSL) si un serveur est configuré dans Paramètres › E-mail ;
 * - sinon fonction mail() de PHP (disponible sur la plupart des hébergements cPanel).
 * Prend en charge HTML + texte et les pièces jointes (devis / factures PDF en Phase 5).
 */
final class Mailer
{
    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * @param array<int, array{name: string, content: string, mime: string}> $attachments
     */
    public function send(string $to, string $subject, string $html, array $attachments = [], ?string $replyTo = null): bool
    {
        $this->lastError = '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Adresse du destinataire invalide.';
            return false;
        }
        $fromAddress = (string) Settings::get('mail_from_address', '') ?: (string) Settings::get('business_email', '');
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Adresse d\'expédition non configurée (Paramètres › E-mail).';
            return false;
        }
        $fromName = (string) Settings::get('mail_from_name', '') ?: (string) Settings::get('business_name', 'HO CREATIVE');

        [$headers, $body] = $this->build($fromAddress, $fromName, $to, $subject, $html, $attachments, $replyTo);

        try {
            if ((string) Settings::get('smtp_host', '') !== '') {
                return $this->sendSmtp($fromAddress, $to, $headers, $body);
            }
            $headerLines = $headers;
            unset($headerLines['To'], $headerLines['Subject']);
            $ok = mail($to, $headers['Subject'], $body, self::implodeHeaders($headerLines), '-f' . $fromAddress);
            if (!$ok) {
                $this->lastError = 'La fonction mail() du serveur a refusé l\'envoi.';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Logger::error('Envoi e-mail échoué : ' . $e->getMessage(), ['to' => $to]);
            return false;
        }
    }

    private function build(string $from, string $fromName, string $to, string $subject, string $html, array $attachments, ?string $replyTo): array
    {
        $boundaryMixed = 'mix_' . bin2hex(random_bytes(8));
        $boundaryAlt = 'alt_' . bin2hex(random_bytes(8));
        $text = trim(html_entity_decode(strip_tags(preg_replace(['#<br\s*/?>#i', '#</p>#i', '#</tr>#i'], "\n", $html) ?? ''), ENT_QUOTES, 'UTF-8'));

        $headers = [
            'Date'         => date('r'),
            'From'         => self::encodeHeader($fromName) . ' <' . $from . '>',
            'To'           => $to,
            'Subject'      => self::encodeHeader($subject),
            'Message-ID'   => '<' . bin2hex(random_bytes(12)) . '@' . (substr(strrchr($from, '@') ?: '@localhost', 1)) . '>',
            'MIME-Version' => '1.0',
            'X-Mailer'     => 'HO CREATIVE ERP',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers['Reply-To'] = $replyTo;
        }

        $alt = "--{$boundaryAlt}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--{$boundaryAlt}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--{$boundaryAlt}--\r\n";

        if ($attachments === []) {
            $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundaryAlt . '"';
            return [$headers, $alt];
        }

        $headers['Content-Type'] = 'multipart/mixed; boundary="' . $boundaryMixed . '"';
        $body = "--{$boundaryMixed}\r\nContent-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n" . $alt;
        foreach ($attachments as $a) {
            $name = preg_replace('/[^\w.\-]/u', '_', $a['name']) ?: 'piece-jointe';
            $body .= "--{$boundaryMixed}\r\nContent-Type: {$a['mime']}; name=\"{$name}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
                . chunk_split(base64_encode($a['content']));
        }
        $body .= "--{$boundaryMixed}--\r\n";
        return [$headers, $body];
    }

    private function sendSmtp(string $from, string $to, array $headers, string $body): bool
    {
        $host = (string) Settings::get('smtp_host');
        $port = (int) Settings::get('smtp_port', 587);
        $encryption = (string) Settings::get('smtp_encryption', 'tls');
        $user = (string) Settings::get('smtp_username', '');
        $pass = Crypto::decrypt((string) Settings::get('smtp_password', ''));

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \RuntimeException("Connexion SMTP impossible ({$host}:{$port}) : {$errstr}");
        }
        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $ehloHost = parse_url((string) Config::get('app.url', ''), PHP_URL_HOST) ?: 'localhost';
            $this->command($socket, 'EHLO ' . $ehloHost, [250]);
            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('Échec de la négociation TLS avec le serveur SMTP.');
                }
                $this->command($socket, 'EHLO ' . $ehloHost, [250]);
            }
            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($user), [334]);
                $this->command($socket, base64_encode($pass), [235], true);
            }
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            // Transparence SMTP : une ligne commençant par "." est doublée
            $data = self::implodeHeaders($headers) . "\r\n\r\n" . preg_replace('/^\./m', '..', $body);
            fwrite($socket, $data . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
            return true;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $line, array $expected, bool $secret = false): string
    {
        fwrite($socket, $line . "\r\n");
        try {
            return $this->expect($socket, $expected);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(($secret ? 'Authentification SMTP refusée' : 'Commande SMTP refusée') . ' : ' . $e->getMessage());
        }
    }

    /** @param resource $socket */
    private function expect($socket, array $codes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException(trim($response) ?: 'pas de réponse du serveur');
        }
        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private static function implodeHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . str_replace(["\r", "\n"], '', (string) $v);
        }
        return implode("\r\n", $lines);
    }

    /** Gabarit HTML simple aux couleurs de l'entreprise. */
    public static function template(string $title, string $bodyHtml): string
    {
        $name = htmlspecialchars((string) Settings::get('business_name', 'HO CREATIVE'), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="fr"><body style="margin:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0"><tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden">'
            . '<tr><td style="background:#0b0b0c;color:#fff;padding:20px 28px;font-weight:bold;font-size:18px;border-bottom:4px solid #ff3d00">' . $name . '</td></tr>'
            . '<tr><td style="padding:28px"><h1 style="font-size:20px;margin:0 0 16px">' . $title . '</h1>' . $bodyHtml . '</td></tr>'
            . '<tr><td style="padding:16px 28px;color:#71717a;font-size:12px;border-top:1px solid #e4e4e7">' . $name . ' — message automatique, merci de ne pas y répondre directement.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
