<?php
/**
 * AuthMiddleware — validates JWT, populates Request attributes with the user payload.
 *
 * Returns Response on failure (sends 401) or null on success (continue).
 */

declare(strict_types=1);

namespace Insophinia\POS\Middleware;

use Insophinia\POS\Core\App;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Lib\Auth;
use Insophinia\POS\Lib\Security;

class AuthMiddleware
{
    /**
     * Require authentication. Optionally check a permission.
     * $options:
     *   'permission' => string|false  — require this permission (default: false = no specific perm)
     *   'rate_limit_endpoint' => string|null — apply per-IP rate limit using this endpoint key
     *   'audit_action' => string|null  — auto log audit on success
     *   'audit_entity'  => string|null
     */
    public static function requireAuth(array $options = []): callable
    {
        return function (Request $request, App $app) use ($options): ?Response {
            // Rate limit (per-IP) — applied BEFORE auth to prevent brute-force
            if (!empty($options['rate_limit_endpoint'])) {
                $ip = Security::getClientIp($request);
                Security::enforceRateLimit($ip, $options['rate_limit_endpoint']);
            }

            try {
                $result = Auth::authenticate($request);
            } catch (\Insophinia\POS\Core\HttpException $e) {
                Response::error($e->getMessage(), $e->getStatusCode())->send();
            }

            // Populate request attributes for the controller
            $request->setAttribute('auth_user', $result['user']);
            $request->setAttribute('auth_token', $result['token']);

            // Permission check
            if (!empty($options['permission'])) {
                $role = $result['user']['role'] ?? '';
                if (!\Insophinia\POS\Lib\Permissions::has($role, $options['permission'])) {
                    Response::error('Insufficient permissions', 403)->send();
                }
            }

            // Rotate cookie if a new token was issued
            if (!empty($result['newCookie'])) {
                header('Set-Cookie: ' . $result['newCookie'], false);
            }

            return null; // continue
        };
    }

    /** Public route — no auth required. */
    public static function publicRoute(?string $rateLimitEndpoint = null): callable
    {
        return function (Request $request, App $app) use ($rateLimitEndpoint): ?Response {
            if ($rateLimitEndpoint) {
                Security::enforceRateLimit(Security::getClientIp($request), $rateLimitEndpoint);
            }
            return null;
        };
    }
}
