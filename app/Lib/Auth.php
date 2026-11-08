<?php
/**
 * Auth — JWT (HS256), bcrypt password hashing, password policy,
 * token blacklist, role hierarchy, session management.
 *
 * Mirrors src/lib/auth.ts from the Next.js original.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;

class Auth
{
    private static ?array $configCache = null;

    private static function config(): array
    {
        if (self::$configCache === null) {
            self::$configCache = require __DIR__ . '/../../config/config.php';
        }
        return self::$configCache;
    }

    // ---- Password Hashing (OWASP A02) ----

    public static function hashPassword(string $password): string
    {
        $cost = self::config()['bcrypt_rounds'] ?? 12;
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ---- Password Policy (OWASP A07) ----

    /** Returns array of error messages (empty = valid). */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        $cfg = self::config();
        $min = $cfg['password_min_len'] ?? 12;
        $max = $cfg['password_max_len'] ?? 30;

        if (strlen($password) < $min) $errors[] = "Password must be at least {$min} characters";
        if (strlen($password) > $max) $errors[] = "Password must not exceed {$max} characters";
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain at least one uppercase letter';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain at least one lowercase letter';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain at least one number';
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        return $errors;
    }

    // ---- JWT Token Management ----

    public static function generateToken(array $payload): string
    {
        $cfg = self::config();
        $secret = $cfg['jwt_secret'];
        $expiration = $cfg['jwt_expiration'] ?? 3600;
        $algorithm = $cfg['jwt_algorithm'] ?? 'HS256';

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $expiration;

        return JWT::encode($payload, $secret, $algorithm);
    }

    public static function validateToken(string $token): ?array
    {
        try {
            $cfg = self::config();
            $decoded = JWT::decode($token, new Key($cfg['jwt_secret'], $cfg['jwt_algorithm'] ?? 'HS256'));
            return (array)$decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Decode a token WITHOUT verifying signature (used to read exp for blacklist expiry). */
    public static function decodeUnverified(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key('dummy', 'HS256'));
            return (array)$decoded;
        } catch (\Throwable) {
            // Try without verifying — use json decode fallback
            $parts = explode('.', $token);
            if (count($parts) !== 3) return null;
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            return is_array($payload) ? $payload : null;
        }
    }

    // ---- Token Extraction ----

    public static function getTokenFromRequest(Request $request): ?string
    {
        // 1. Authorization header (Bearer)
        $token = $request->bearerToken();
        if ($token) return $token;
        // 2. Cookie
        return $request->cookie('auth_token');
    }

    // ---- Auth Cookie Management ----

    public static function setAuthCookie(string $token): string
    {
        $cfg = self::config();
        $maxAge = $cfg['jwt_expiration'] ?? 3600;
        $secure = !empty($cfg['cookie_secure']) ? ' Secure;' : '';
        $samesite = $cfg['cookie_samesite'] ?? 'Strict';
        $path = $cfg['cookie_path'] ?? '/';
        return "auth_token={$token}; HttpOnly;{$secure} SameSite={$samesite}; Path={$path}; Max-Age={$maxAge}";
    }

    public static function clearAuthCookie(): string
    {
        $cfg = self::config();
        $samesite = $cfg['cookie_samesite'] ?? 'Strict';
        $path = $cfg['cookie_path'] ?? '/';
        return "auth_token=; HttpOnly; SameSite={$samesite}; Path={$path}; Max-Age=0";
    }

    // ---- Token Blacklist ----

    public static function isTokenBlacklisted(string $token): bool
    {
        $hash = Util::sha256($token);
        $db = Database::getInstance();
        $row = $db->fetch('SELECT 1 FROM token_blacklist WHERE token_hash = ? LIMIT 1', [$hash]);
        return $row !== null;
    }

    public static function blacklistToken(string $token, string $userId, string $reason = 'logout'): void
    {
        try {
            $hash = Util::sha256($token);
            $payload = self::decodeUnverified($token);
            $expiresAt = isset($payload['exp'])
                ? date('Y-m-d H:i:s', (int)$payload['exp'])
                : date('Y-m-d H:i:s', time() + (self::config()['jwt_expiration'] ?? 3600));
            $db = Database::getInstance();
            $db->insert('token_blacklist', [
                'id'         => Util::uuid(),
                'token_hash' => $hash,
                'user_id'    => $userId,
                'reason'     => $reason,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Token blacklist error: ' . $e->getMessage());
        }
    }

    // ---- Auth Middleware Helper ----

    /**
     * Authenticate the request. Returns [user payload, token, newCookie?]
     * or throws HttpException(401) on failure.
     */
    public static function authenticate(Request $request): array
    {
        $token = self::getTokenFromRequest($request);
        if (!$token) {
            throw HttpException::unauthorized('Authentication required');
        }

        if (self::isTokenBlacklisted($token)) {
            throw HttpException::unauthorized('Token has been revoked. Please login again.');
        }

        $payload = self::validateToken($token);
        if (!$payload) {
            throw HttpException::unauthorized('Invalid or expired token');
        }

        // Validate session is still active
        if (!empty($payload['session_id'])) {
            $db = Database::getInstance();
            $session = $db->fetch(
                'SELECT is_active, expires_at, last_activity_at FROM login_sessions WHERE id = ?',
                [$payload['session_id']]
            );
            if (!$session || !$session['is_active'] || strtotime($session['expires_at']) <= time()) {
                throw HttpException::unauthorized('Session has been revoked or expired.');
            }

            // Throttle last_activity_at update to once per minute
            $now = time();
            $lastActivity = strtotime($session['last_activity_at']);
            if (($now - $lastActivity) > 60) {
                $db->update('login_sessions',
                    ['last_activity_at' => date('Y-m-d H:i:s', $now)],
                    'id = ?',
                    [$payload['session_id']]
                );
            }

            // Rotate token if older than 30 minutes
            $iat = $payload['iat'] ?? 0;
            $newCookie = null;
            $finalToken = $token;
            $rotationAge = self::config()['token_rotation_age_seconds'] ?? 1800;
            if ($now - $iat > $rotationAge) {
                $finalToken = self::generateToken([
                    'user_id'    => $payload['user_id'],
                    'username'   => $payload['username'],
                    'role'       => $payload['role'],
                    'session_id' => $payload['session_id'],
                ]);
                $newCookie = self::setAuthCookie($finalToken);
                $payload = self::validateToken($finalToken);
            }

            return ['user' => $payload, 'token' => $finalToken, 'newCookie' => $newCookie];
        }

        return ['user' => $payload, 'token' => $token, 'newCookie' => null];
    }

    // ---- Role Check Helpers ----

    private static array $roleHierarchy = [
        'cashier'       => 1,
        'supervisor'    => 2,
        'admin'         => 3,
        'master_admin'  => 4,
    ];

    public static function checkRole(string $userRole, string $requiredRole): bool
    {
        $userLevel = self::$roleHierarchy[$userRole] ?? 0;
        $requiredLevel = self::$roleHierarchy[$requiredRole] ?? 0;
        return $userLevel >= $requiredLevel;
    }

    public static function authorizeRole(string $userRole, string $requiredRole): void
    {
        if (!self::checkRole($userRole, $requiredRole)) {
            throw HttpException::forbidden('Insufficient permissions');
        }
    }
}
