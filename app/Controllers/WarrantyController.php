<?php
/**
 * WarrantyController — warranties + claims.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Util;
use Insophinia\POS\Lib\Validation;
use Insophinia\POS\Models\Warranty;
use Insophinia\POS\Models\WarrantyClaim;

class WarrantyController extends Controller
{
    /** GET /api/warranties */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $status = $request->query('status');
        $result = (new Warranty())->listWithRelations($p['page'], $p['limit'], $p['search'], $status);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** GET /api/warranties/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $w = (new Warranty())->findFull($params['id']);
        if (!$w) throw HttpException::notFound('Warranty not found');
        return Response::success($w);
    }

    /** PATCH /api/warranties/{id} */
    public function update(Request $request, array $params = []): Response
    {
        $w = (new Warranty())->find($params['id']);
        if (!$w) throw HttpException::notFound('Warranty not found');
        $body = $request->json();
        $allowed = array_intersect_key($body, array_flip(['serial_number', 'notes', 'status']));
        if (isset($allowed['status']) && !in_array($allowed['status'], ['active','expired','claimed','voided'], true)) {
            throw HttpException::badRequest('Invalid status');
        }
        (new Warranty())->update($w['id'], $allowed);
        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'WARRANTY_UPDATE',
            'entity'     => 'warranty',
            'entity_id'  => $w['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new Warranty())->find($w['id']), 'Warranty updated');
    }

    // ---- Claims ----

    /** GET /api/warranties/claims */
    public function claimsIndex(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $status = $request->query('status');
        $result = (new WarrantyClaim())->listWithRelations($p['page'], $p['limit'], $p['search'], $status);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/warranties/claims */
    public function claimsStore(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_WARRANTY_CLAIM, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $warranty = (new Warranty())->find($data['warranty_id']);
        if (!$warranty) throw HttpException::notFound('Warranty not found');
        if ($warranty['status'] === 'voided') {
            throw HttpException::badRequest('Cannot file claim on voided warranty');
        }
        if ($warranty['status'] === 'expired' || strtotime($warranty['expires_at']) < time()) {
            throw HttpException::badRequest('Warranty has expired');
        }
        $claimId = Util::uuid();
        $claimNumber = (new WarrantyClaim())->nextClaimNumber();
        (new WarrantyClaim())->insert([
            'id'                => $claimId,
            'claim_number'      => $claimNumber,
            'warranty_id'       => $warranty['id'],
            'customer_id'       => $warranty['customer_id'],
            'status'            => 'filed',
            'issue_description' => $data['issue_description'],
            'fault_category'    => $data['fault_category'] ?? null,
            'filed_at'          => date('Y-m-d H:i:s'),
        ]);
        // Set warranty status to claimed
        (new Warranty())->update($warranty['id'], ['status' => 'claimed']);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CLAIM_FILE',
            'entity'     => 'warranty_claim',
            'entity_id'  => $claimId,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new WarrantyClaim())->find($claimId), 'Claim filed', 201);
    }

    /** PATCH /api/warranties/claims/{id}/review */
    public function claimsReview(Request $request, array $params = []): Response
    {
        $claim = (new WarrantyClaim())->find($params['id']);
        if (!$claim) throw HttpException::notFound('Claim not found');
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_REVIEW_WARRANTY_CLAIM, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $update = [
            'status'           => $data['status'],
            'reviewed_at'      => date('Y-m-d H:i:s'),
            'reviewed_by'      => $this->userId(),
            'assessment_notes' => $data['assessment_notes'] ?? null,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolution_type'  => $data['resolution_type'] ?? null,
        ];
        if (in_array($data['status'], ['repaired', 'replaced', 'closed'], true)) {
            $update['resolved_at'] = date('Y-m-d H:i:s');
        }
        if ($data['status'] === 'closed') {
            $update['closed_at'] = date('Y-m-d H:i:s');
        }
        (new WarrantyClaim())->update($claim['id'], $update);
        // If rejected, revert warranty status to active
        if ($data['status'] === 'rejected') {
            (new Warranty())->update($claim['warranty_id'], ['status' => 'active']);
        }

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CLAIM_REVIEW',
            'entity'     => 'warranty_claim',
            'entity_id'  => $claim['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
            'details'    => "status={$data['status']}",
        ]);
        return Response::success((new WarrantyClaim())->find($claim['id']), 'Claim reviewed');
    }
}
