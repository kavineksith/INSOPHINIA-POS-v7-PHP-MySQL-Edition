<?php
/**
 * Email — PHPMailer wrapper. Mirrors src/lib/email.ts.
 *
 * SMTP config is loaded from config.php. EMAIL_ENABLED master switch.
 *
 * Template rendering replaces {{var}} placeholders with HTML-escaped values.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Insophinia\POS\Core\HttpException;

class Email
{
    private static ?array $configCache = null;

    private static function config(): array
    {
        if (self::$configCache === null) {
            self::$configCache = require __DIR__ . '/../../config/config.php';
        }
        return self::$configCache;
    }

    public static function isEnabled(): bool
    {
        return (bool)(self::config()['email']['enabled'] ?? false);
    }

    /** Build a configured PHPMailer instance. */
    private static function createMailer(): PHPMailer
    {
        $cfg = self::config()['email'] ?? [];
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'] ?? 'localhost';
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->SMTPAuth   = !empty($cfg['username']);
        $mail->Username   = $cfg['username'] ?? '';
        $mail->Password   = $cfg['password'] ?? '';
        $mail->Timeout    = (int)($cfg['timeout_socket'] ?? 15);
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $secure = strtolower((string)($cfg['secure'] ?? 'tls'));
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // From
        $from = $cfg['from'] ?? 'INSOPHINIA <noreply@insophinia.lk>';
        [$name, $addr] = self::parseAddress($from);
        $mail->setFrom($addr, $name);

        return $mail;
    }

    /** Send an HTML email to a single recipient. */
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, ?array $attachments = []): void
    {
        if (!self::isEnabled()) {
            throw new HttpException('Email sending is disabled', 403);
        }
        if (!Validation::isValidEmail($to)) {
            throw new HttpException('Invalid recipient email', 400);
        }
        // Reject newlines in subject (anti-header-injection)
        if (str_contains($subject, "\n") || str_contains($subject, "\r")) {
            throw new HttpException('Invalid subject', 400);
        }

        try {
            $mail = self::createMailer();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?? strip_tags($htmlBody);
            $mail->isHTML(true);
            if (is_array($attachments)) {
                foreach ($attachments as $att) {
                    if (isset($att['string'])) {
                        $mail->addStringAttachment($att['string'], $att['name'], $att['encoding'] ?? 'base64', $att['type'] ?? 'application/pdf');
                    } elseif (isset($att['path'])) {
                        $mail->addAttachment($att['path'], $att['name'] ?? basename($att['path']));
                    }
                }
            }
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new HttpException('Email send failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Render a template by replacing {{var}} placeholders with HTML-escaped values.
     * Mirrors renderTemplate() in src/lib/email.ts.
     */
    public static function renderTemplate(string $html, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($vars) {
            $key = $m[1];
            $val = $vars[$key] ?? '';
            return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html) ?? $html;
    }

    /** Parse "Name <email@example.com>" into [name, email]. */
    public static function parseAddress(string $input): array
    {
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $input, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        return ['', trim($input)];
    }
}
