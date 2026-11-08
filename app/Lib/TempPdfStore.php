<?php
/**
 * TempPdfStore — Filesystem-based temp PDF cache (10-min TTL).
 *
 * Original used an in-memory Map — for PHP we use the filesystem so it works
 * across requests and survives PHP-FPM worker recycling.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

class TempPdfStore
{
    private static ?array $configCache = null;

    private static function config(): array
    {
        if (self::$configCache === null) {
            self::$configCache = require __DIR__ . '/../../config/config.php';
        }
        return self::$configCache;
    }

    private static function dir(): string
    {
        $cfg = self::config();
        $dir = $cfg['storage']['temp_pdfs'] ?? __DIR__ . '/../../storage/temp_pdfs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Store a PDF and return a 16-byte hex key. */
    public static function put(string $pdfBytes, ?string $billId = null): string
    {
        self::cleanup();
        $cfg = self::config()['temp_pdf'] ?? [];
        $key = bin2hex(random_bytes((int)($cfg['key_length'] ?? 16)));
        $path = self::dir() . '/' . $key . '.pdf';
        file_put_contents($path, $pdfBytes);
        $meta = [
            'bill_id'    => $billId,
            'created_at' => time(),
        ];
        file_put_contents($path . '.meta', json_encode($meta));
        return $key;
    }

    /** Retrieve a PDF by key, or null if not found / expired. */
    public static function get(string $key): ?string
    {
        if (!preg_match('/^[0-9a-f]+$/', $key)) return null;
        $path = self::dir() . '/' . $key . '.pdf';
        if (!is_file($path)) return null;
        $ttl = self::config()['temp_pdf']['ttl_seconds'] ?? 600;
        if (time() - filemtime($path) > $ttl) {
            @unlink($path);
            @unlink($path . '.meta');
            return null;
        }
        return (string)file_get_contents($path);
    }

    /** Remove expired entries. */
    public static function cleanup(): void
    {
        $dir = self::dir();
        $cfg = self::config()['temp_pdf'] ?? [];
        $ttl = $cfg['ttl_seconds'] ?? 600;
        $maxEntries = $cfg['max_entries'] ?? 100;
        $files = glob($dir . '/*.pdf');
        if (!$files) return;

        // Sort by mtime ascending (oldest first)
        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

        // Delete expired
        foreach ($files as $f) {
            if (time() - filemtime($f) > $ttl) {
                @unlink($f);
                @unlink($f . '.meta');
            }
        }

        // Cap total entries
        $files = glob($dir . '/*.pdf');
        if (!$files) return;
        if (count($files) > $maxEntries) {
            usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
            $toDelete = count($files) - $maxEntries;
            for ($i = 0; $i < $toDelete; $i++) {
                @unlink($files[$i]);
                @unlink($files[$i] . '.meta');
            }
        }
    }
}
