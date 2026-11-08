<?php
/**
 * CsrfMiddleware — verify X-CSRF-Token on state-changing requests.
 * Skip for /api/auth/login (no session yet).
 */

declare(strict_types=1);

namespace Insophinia\POS\Middleware;

use Insophinia\POS\Core\App;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Lib\Csrf;

class CsrfMiddleware
{
    public static function verify(): callable
    {
        return function (Request $request, App $app): ?Response {
            // Always send the cookie first (so client can read it)
            Csrf::sendCookie();

            try {
                Csrf::verify($request);
            } catch (\Insophinia\POS\Core\HttpException $e) {
                Response::error($e->getMessage(), $e->getStatusCode())->send();
            }
            return null;
        };
    }
}
