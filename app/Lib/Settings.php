<?php
/**
 * Settings — Cache for the settings table. Loads once per request.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Insophinia\POS\Core\Database;

class Settings
{
    private static ?array $cache = null;

    public static function warm(): void
    {
        if (self::$cache !== null) return;
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll('SELECT `key`, `value`, `type` FROM settings');
            $map = [];
            foreach ($rows as $row) {
                $map[$row['key']] = self::cast($row['value'], $row['type']);
            }
            self::$cache = $map;
        } catch (\Throwable) {
            self::$cache = [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::warm();
        return self::$cache[$key] ?? $default;
    }

    public static function all(): array
    {
        self::warm();
        return self::$cache;
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $description = ''): void
    {
        $db = Database::getInstance();
        $stored = self::toStored($value, $type);
        $existing = $db->fetch('SELECT id FROM settings WHERE `key` = ?', [$key]);
        if ($existing) {
            $db->update('settings', ['value' => $stored, 'type' => $type, 'updated_at' => date('Y-m-d H:i:s')], '`key` = ?', [$key]);
        } else {
            $db->insert('settings', [
                'id'          => Util::uuid(),
                'key'         => $key,
                'value'       => $stored,
                'type'        => $type,
                'description' => $description,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
        self::$cache[$key] = $value;
    }

    public static function shopData(): array
    {
        return [
            'name'    => (string)(self::get('shop_name', 'INSOPHINIA')),
            'address' => (string)(self::get('shop_address', '')),
            'phone'   => (string)(self::get('shop_phone', '')),
            'email'   => (string)(self::get('shop_email', '')),
            'website' => (string)(self::get('shop_website', '')),
        ];
    }

    public static function billContentSettings(string $format = 'a4'): array
    {
        $prefix = $format === 'thermal' ? 'bill_thermal' : 'bill_a4';
        return [
            'show_variants'  => (bool)self::get("{$prefix}_show_variants", $format === 'a4'),
            'show_subitems'  => (bool)self::get("{$prefix}_show_subitems", $format === 'a4'),
            'show_warranty'  => (bool)self::get("{$prefix}_show_warranty", $format === 'a4'),
            'show_revisions' => (bool)self::get("{$prefix}_show_revisions", $format === 'a4'),
            'show_terms'     => (bool)self::get("{$prefix}_show_terms", $format === 'a4'),
        ];
    }

    private static function cast(?string $value, string $type): mixed
    {
        if ($value === null) return null;
        return match ($type) {
            'integer' => (int)$value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'    => Util::jsonDecode($value, null),
            default   => $value,
        };
    }

    private static function toStored(mixed $value, string $type): string
    {
        if ($type === 'boolean') return $value ? 'true' : 'false';
        if ($type === 'json') return Util::jsonEncode($value);
        if (is_bool($value)) return $value ? 'true' : 'false';
        return (string)$value;
    }

    public static function isAllowedKey(string $key): bool
    {
        $cfg = require __DIR__ . '/../../config/config.php';
        $blocked = $cfg['settings_blocked_key_regex'] ?? '/password|secret|api[_-]?key/i';
        if (preg_match($blocked, $key)) return false;
        $prefixes = $cfg['settings_allowed_prefixes'] ?? ['shop_', 'bill_', 'email_', 'whatsapp_', 'vat_', 'sscl_', 'loyalty_'];
        foreach ($prefixes as $p) {
            if (str_starts_with($key, $p)) return true;
        }
        return false;
    }
}
