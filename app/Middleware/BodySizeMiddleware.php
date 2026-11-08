<?php
/**
 * BodySizeMiddleware — reject requests with content-length > max_body_size.
 */

declare(strict_types=1);

namespace Insophinia\POS\Middleware;

use Insophinia\POS\Core\App;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Lib\Security;

class BodySizeMiddleware
{
    public static function check(): callable
    {
        return function (Request $request, App $app): ?Response {
            if (!Security::validateBodySize($request)) {
                Response::error('Request body too large', 413)->send();
            }
            return null;
        };
    }
}
