<?php
/**
 * Util — Misc helpers: UUID generation, sanitization, JSON, etc.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Ramsey\Uuid\Uuid;

class Util
{
    /** Generate a new UUID v4 string. */
    public static function uuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    /** Validate UUID v4 (case-insensitive). */
    public static function isValidUuid(?string $value): bool
    {
        if ($value === null || $value === '') return false;
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * Sanitize a string: trim, normalize newlines, HTML-escape &, <, >, ", '.
     * Equivalent to the Next.js sanitizeString() helper.
     */
    public static function sanitizeString(mixed $input): string
    {
        if ($input === null) return '';
        $str = (string)$input;
        $str = trim($str);
        $str = str_replace(["\r\n", "\r"], "\n", $str);
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize template HTML — strips <script>, on*=handlers, javascript: URIs.
     * Throws if input exceeds 100KB.
     */
    public static function sanitizeTemplateHtml(string $html): string
    {
        if (strlen($html) > 102400) {
            throw new \InvalidArgumentException('Template HTML exceeds 100KB limit');
        }
        // Strip <script>…</script>
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        // Strip on*=handler attributes (onmouseover="..." etc.)
        $html = preg_replace('#\s+on[a-z]+\s*=\s*"[^"]*"#i', '', $html) ?? $html;
        $html = preg_replace("#\s+on[a-z]+\s*=\s*'[^']*'#i", '', $html) ?? $html;
        $html = preg_replace('#\s+on[a-z]+\s*=\s*[^\s>]+#i', '', $html) ?? $html;
        // Strip javascript: URIs in href/src
        $html = preg_replace('#(href|src)\s*=\s*["\']javascript:[^"\']*["\']#i', '', $html) ?? $html;
        return $html;
    }

    /** Generate a random hex string of N bytes. */
    public static function randomHex(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** SHA-256 hex digest. */
    public static function sha256(string $input): string
    {
        return hash('sha256', $input);
    }

    /** Constant-time string compare. */
    public static function timingSafeEqual(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) return false;
        return hash_equals($a, $b);
    }

    /** Parse a JSON column safely. Returns the decoded value or $default. */
    public static function jsonDecode(?string $value, mixed $default = null): mixed
    {
        if ($value === null || $value === '') return $default;
        $decoded = json_decode($value, true);
        return $decoded === null ? $default : $decoded;
    }

    /** JSON encode with consistent options. */
    public static function jsonEncode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Format a decimal as a 2-place float string ("12.34"). */
    public static function decimal(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    /** Format a 3-place quantity string ("12.345"). */
    public static function qty(mixed $value): string
    {
        return number_format((float)$value, 3, '.', '');
    }

    /** Generate a sequential document number: PREFIX-YYYYMMDD-NNNN. */
    public static function docNumber(string $prefix, \DateTimeInterface $date, int $seq): string
    {
        return sprintf('%s-%s-%04d', $prefix, $date->format('Ymd'), $seq);
    }

    /** Get today's date in YYYY-MM-DD (server TZ). */
    public static function todayDate(): string
    {
        return date('Y-m-d');
    }

    /** Get current timestamp (Y-m-d H:i:s). */
    public static function timestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Strip non-printable ASCII control chars (except \n \t \r). */
    public static function stripControlChars(string $input): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input) ?? $input;
    }

    /**
     * Strip HTML tags to plain text, converting common block tags to newlines.
     * Used for rendering Terms content as plain text in PDFs.
     */
    public static function htmlToPlain(string $html): string
    {
        // h1-6 → double newline
        $html = preg_replace('#<h[1-6][^>]*>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#</h[1-6]>#i', '', $html) ?? $html;
        // li → bullet + newline
        $html = preg_replace('#<li[^>]*>#i', "• ", $html) ?? $html;
        $html = preg_replace('#</li>#i', "\n", $html) ?? $html;
        // br → newline
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        // p → double newline
        $html = preg_replace('#<p[^>]*>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#</p>#i', '', $html) ?? $html;
        // Strip remaining tags
        $text = strip_tags($html);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
