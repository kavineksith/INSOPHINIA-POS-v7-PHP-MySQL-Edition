<?php
/**
 * LogController — audit logs.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Models\AuditLog;

class LogController extends Controller
{
    /** GET /api/logs */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $result = (new AuditLog())->listRecent($p['page'], $p['limit'], $p['search']);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }
}
