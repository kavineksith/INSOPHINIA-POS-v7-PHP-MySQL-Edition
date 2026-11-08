<?php
/**
 * CsrfController — issue a fresh CSRF token cookie.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Lib\Csrf;

class CsrfController extends Controller
{
    /** GET /api/csrf */
    public function index(Request $request, array $params = []): Response
    {
        $token = Csrf::currentToken();
        Csrf::sendCookie();
        return Response::success(['token' => $token]);
    }
}
