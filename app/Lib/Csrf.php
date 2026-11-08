<?php
/**
 * CSRF — Token generation and timing-safe validation.
 * Mirrors src/lib/csrf.ts.
 *
 * Token is stored in the PHP session and sent as a cookie (non-HttpOnly so JS
 * can read it). The client sends it back via the X-CSRF-Token header on every
 * state-changing request. The middleware compares the header value against the
 * session value using hash_equals() (timing-safe).
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Core\Request;

class Csrf
{
    private static ?array $configCache = null;

    private static function config(): array
    {
        if (self::$configCache === null) {
            self::$configCache = require __DIR__ . '/../../config/config.php';
        }
        return self::$configCache;
    }

    /** Generate a new random hex token and persist it in the session. */
    public static function generateToken(): string
    {
        $cfg = self::config()['csrf_token_length'] ?? 32;
        $token = bin2hex(random_bytes($cfg));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_expires'] = time() + (self::config()['csrf_lifetime'] ?? 3600);
        return $token;
    }

    /** Get the current valid token (or generate a new one if expired/missing). */
    public static function currentToken(): string
    {
        if (
            empty($_SESSION['csrf_token'])
            || empty($_SESSION['csrf_token_expires'])
            || $_SESSION['csrf_token_expires'] < time()
        ) {
            return self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }

    /** Send the CSRF cookie. Called early in every request. */
    public static function sendCookie(): void
    {
        $token = self::currentToken();
        $cfg = self::config();
        $secure = !empty($cfg['cookie_secure']) ? ' Secure;' : '';
        $samesite = $cfg['cookie_samesite'] ?? 'Strict';
        $path = $cfg['cookie_path'] ?? '/';
        $lifetime = $cfg['csrf_lifetime'] ?? 3600;
        // Non-HttpOnly so client JS can read it
        header("Set-Cookie: csrf_token={$token};{$secure} SameSite={$samesite}; Path={$path}; Max-Age={$lifetime}", false);
    }

    /** Timing-safe comparison. */
    public static function validateToken(?string $token, ?string $storedToken): bool
    {
        if (!$token || !$storedToken) return false;
        if (strlen($token) !== strlen($storedToken)) return false;
        return hash_equals($storedToken, $token);
    }

    /** Verify the request's CSRF token against the session. State-changing methods only. */
    public static function verify(Request $request): void
    {
        $method = $request->method;
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        // Skip CSRF for the login endpoint (token not yet established) — login still has rate limiting
        $path = $request->path;
        if ($path === '/api/auth/login') {
            return;
        }
        $headerName = 'x-csrf-token';
        $headerToken = $request->header($headerName);
        $cookieToken = $request->cookie('csrf_token');
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        // Either header must match session, OR cookie must match session (header is preferred)
        $provided = $headerToken ?? $cookieToken;
        if (!self::validateToken($provided, $sessionToken)) {
            throw new HttpException('Invalid CSRF token', 419);
        }
    }
}
