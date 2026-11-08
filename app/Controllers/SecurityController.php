<?php
/**
 * SecurityController — security events.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Models\SecurityEvent;

class SecurityController extends Controller
{
    /** GET /api/security/events */
    public function events(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $result = (new SecurityEvent())->listRecent($p['page'], $p['limit'], $p['search']);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }
}
