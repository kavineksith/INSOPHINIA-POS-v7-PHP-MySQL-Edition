<?php
/**
 * Security — Rate limiting, audit logging, security event logging,
 * IP extraction, content-type & body-size validation.
 *
 * Mirrors src/lib/security.ts.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Core\Request;

class Security
{
    // ---- Event types ----
    public const EVENT_LOGIN_SUCCESS         = 'LOGIN_SUCCESS';
    public const EVENT_LOGIN_FAILED          = 'LOGIN_FAILED';
    public const EVENT_LOGIN_LOCKED          = 'LOGIN_LOCKED';
    public const EVENT_LOGOUT                = 'LOGOUT';
    public const EVENT_PASSWORD_CHANGED      = 'PASSWORD_CHANGED';
    public const EVENT_PASSWORD_RESET        = 'PASSWORD_RESET';
    public const EVENT_ACCOUNT_LOCKED        = 'ACCOUNT_LOCKED';
    public const EVENT_ACCOUNT_UNLOCKED      = 'ACCOUNT_UNLOCKED';
    public const EVENT_BRUTE_FORCE_DETECTED  = 'BRUTE_FORCE_DETECTED';
    public const EVENT_SUSPICIOUS_ACTIVITY   = 'SUSPICIOUS_ACTIVITY';
    public const EVENT_NEW_DEVICE_LOGIN      = 'NEW_DEVICE_LOGIN';
    public const EVENT_SESSION_EXPIRED       = 'SESSION_EXPIRED';
    public const EVENT_SESSION_REVOKED       = 'SESSION_REVOKED';
    public const EVENT_ALL_SESSIONS_REVOKED  = 'ALL_SESSIONS_REVOKED';

    private static ?array $configCache = null;

    private static function config(): array
    {
        if (self::$configCache === null) {
            self::$configCache = require __DIR__ . '/../../config/config.php';
        }
        return self::$configCache;
    }

    // ---- Rate Limiting (persistent, via security_events table) ----

    /**
     * Check if identifier is allowed to proceed.
     * $endpoint must be a key in config('rate_limit.endpoints') or null for default.
     */
    public static function checkRateLimit(string $identifier, ?string $endpoint = null): array
    {
        $cfg = self::config()['rate_limit'] ?? [];
        $limits = $cfg['endpoints'] ?? [];
        $defaultMax = $cfg['default_max'] ?? 100;
        $defaultWindowMs = $cfg['default_window_ms'] ?? 60000;

        $max = $defaultMax;
        $windowMs = $defaultWindowMs;
        $eventType = self::EVENT_SUSPICIOUS_ACTIVITY;

        if ($endpoint !== null && isset($limits[$endpoint])) {
            $cfg2 = $limits[$endpoint];
            $max = $cfg2['max'];
            $windowMs = $cfg2['window_ms'];
            $eventType = $cfg2['event'] ?? self::EVENT_SUSPICIOUS_ACTIVITY;
        }

        $nowMs = (int)(microtime(true) * 1000);
        $cutoffDate = date('Y-m-d H:i:s', (int)(($nowMs - $windowMs) / 1000));

        try {
            $db = Database::getInstance();
            $count = (int)$db->fetchColumn(
                'SELECT COUNT(*) FROM security_events
                 WHERE ip_address = ? AND event_type = ? AND created_at >= ?',
                [$identifier, $eventType, $cutoffDate]
            );
            if ($count >= $max) {
                return ['allowed' => false, 'remaining' => 0, 'reset_time' => $nowMs + $windowMs];
            }
            return ['allowed' => true, 'remaining' => $max - $count - 1, 'reset_time' => $nowMs + $windowMs];
        } catch (\Throwable) {
            // Fail-open to prevent lockouts during DB outages
            return ['allowed' => true, 'remaining' => 1, 'reset_time' => $nowMs + $windowMs];
        }
    }

    // ---- Audit Logging (OWASP A09) ----

    public static function auditLog(array $params): void
    {
        try {
            $db = Database::getInstance();
            $db->insert('audit_logs', [
                'id'         => Util::uuid(),
                'user_id'    => $params['user_id'] ?? null,
                'action'     => $params['action'],
                'entity'     => $params['entity'],
                'entity_id'  => $params['entity_id'] ?? null,
                'details'    => $params['details'] ?? null,
                'ip_address' => $params['ip_address'] ?? null,
                'user_agent' => $params['user_agent'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit log error: ' . $e->getMessage());
        }
    }

    // ---- Security Event Logging ----

    public static function logSecurityEvent(array $params): void
    {
        try {
            $db = Database::getInstance();
            $db->insert('security_events', [
                'id'         => Util::uuid(),
                'user_id'    => $params['user_id'] ?? null,
                'event_type' => $params['event_type'],
                'ip_address' => $params['ip_address'] ?? '',
                'user_agent' => $params['user_agent'] ?? '',
                'details'    => $params['details'] ?? null,
                'severity'   => $params['severity'] ?? 'low',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Security event log error: ' . $e->getMessage());
        }
    }

    // ---- IP & UA ----

    public static function getClientIp(?Request $request = null): string
    {
        if ($request !== null) {
            return $request->header('x-forwarded-for')
                ? trim(explode(',', $request->header('x-forwarded-for'))[0])
                : ($request->header('x-real-ip') ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        }
        return client_ip();
    }

    public static function getUserAgent(?Request $request = null): string
    {
        if ($request !== null) return $request->header('user-agent') ?? '';
        return user_agent();
    }

    // ---- Body Size Validation ----

    public static function validateBodySize(Request $request): bool
    {
        $max = self::config()['max_request_body_size'] ?? 1048576;
        return $request->contentLength() <= $max;
    }

    public static function validateContentType(Request $request, string $expected = 'application/json'): bool
    {
        $ct = $request->header('content-type') ?? '';
        return str_contains($ct, $expected);
    }

    public static function enforceRateLimit(string $identifier, ?string $endpoint = null): void
    {
        $rl = self::checkRateLimit($identifier, $endpoint);
        if (!$rl['allowed']) {
            throw new HttpException(
                'Rate limit exceeded. Please try again later.',
                429
            );
        }
    }
}
