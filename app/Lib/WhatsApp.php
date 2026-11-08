<?php
/**
 * WhatsApp — Phone normalization + wa.me click-to-chat URL builder.
 * Mirrors src/lib/whatsapp.ts.
 *
 * No external API integration; just builds a wa.me URL the client can open.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

class WhatsApp
{
    /**
     * Normalize a phone number to international format (no leading +).
     * Sri Lankan local numbers (07XXXXXXXX, 7XXXXXXXX) → 94XXXXXXXXX.
     * Strips leading 00, +, dashes, parens, spaces.
     * Returns null if the result is <10 or >15 digits.
     */
    public static function normalizePhone(?string $input): ?string
    {
        if (!$input) return null;
        $phone = preg_replace('/[^\d]/', '', $input) ?? '';
        if ($phone === '') return null;

        // Remove leading 00 (international prefix)
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }
        // Sri Lankan local format: 07XXXXXXXX → 94 7XXXXXXXX
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '94' . substr($phone, 1);
        }
        // Sri Lankan local format without leading 0: 7XXXXXXXX (9 digits) → 94 7XXXXXXXX
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            $phone = '94' . $phone;
        }

        if (strlen($phone) < 10 || strlen($phone) > 15) return null;
        return $phone;
    }

    public static function buildUrl(?string $phone, string $message): string
    {
        $normalized = self::normalizePhone($phone);
        if (!$normalized) return '';
        return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($message);
    }

    public static function buildPdfUrl(?string $phone, string $message, string $pdfUrl): string
    {
        $fullMessage = $message . "\n\nDownload PDF: " . $pdfUrl;
        return self::buildUrl($phone, $fullMessage);
    }

    public static function isEnabled(): bool
    {
        $cfg = require __DIR__ . '/../../config/config.php';
        return (bool)($cfg['whatsapp_enabled'] ?? true);
    }
}
