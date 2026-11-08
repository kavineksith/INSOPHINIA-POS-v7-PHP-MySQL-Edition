<?php
/**
 * RepairController — repair jobs + parts.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Util;
use Insophinia\POS\Lib\Validation;
use Insophinia\POS\Models\RepairJob;
use Insophinia\POS\Models\RepairPart;
use Insophinia\POS\Models\Item;
use Insophinia\POS\Models\StockMovement;

class RepairController extends Controller
{
    /** GET /api/repairs */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $status = $request->query('status');
        $result = (new RepairJob())->listWithRelations($p['page'], $p['limit'], $p['search'], $status);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/repairs */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_REPAIR_JOB, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $id = Util::uuid();
        $jobNumber = (new RepairJob())->nextJobNumber();
        (new RepairJob())->insert([
            'id'                   => $id,
            'job_number'           => $jobNumber,
            'customer_id'          => $data['customer_id'] ?? null,
            'customer_name'        => $data['customer_name'],
            'customer_phone'       => $data['customer_phone'] ?? null,
            'customer_email'       => $data['customer_email'] ?? null,
            'item_description'     => $data['item_description'],
            'item_serial'          => $data['item_serial'] ?? null,
            'item_id'              => $data['item_id'] ?? null,
            'fault_description'    => $data['fault_description'],
            'access_password'      => $data['access_password'] ?? null,
            'accessories'          => $data['accessories'] ?? null,
            'condition_notes'      => $data['condition_notes'] ?? null,
            'scope_of_work'        => $data['scope_of_work'] ?? null,
            'estimated_cost'       => $data['estimated_cost'] ?? 0,
            'estimated_hours'      => $data['estimated_hours'] ?? 0,
            'labor_cost'           => $data['labor_cost'] ?? 0,
            'repair_warranty_days' => $data['repair_warranty_days'] ?? 0,
            'technician_id'        => $data['technician_id'] ?? null,
            'notes'                => $data['notes'] ?? null,
            'status'               => 'received',
            'received_at'          => date('Y-m-d H:i:s'),
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'REPAIR_CREATE',
            'entity'     => 'repair_job',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success((new RepairJob())->findFull($id), 'Repair job created', 201);
    }

    /** GET /api/repairs/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $job = (new RepairJob())->findFull($params['id']);
        if (!$job) throw HttpException::notFound('Repair job not found');
        return Response::success($job);
    }

    /** PUT /api/repairs/{id} */
    public function update(Request $request, array $params = []): Response
    {
        $job = (new RepairJob())->find($params['id']);
        if (!$job) throw HttpException::notFound('Repair job not found');
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_REPAIR_JOB, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        // Auto-timestamps based on status
        if (!empty($data['status'])) {
            $oldStatus = $job['status'];
            $newStatus = $data['status'];
            if ($newStatus === 'in_progress' && $oldStatus === 'received' && empty($job['started_at'])) {
                $data['started_at'] = date('Y-m-d H:i:s');
            }
            if (in_array($newStatus, ['completed'], true) && empty($job['completed_at'])) {
                $data['completed_at'] = date('Y-m-d H:i:s');
            }
            if (in_array($newStatus, ['completed', 'returned_unrepaired'], true) && empty($job['delivered_at'])) {
                $data['delivered_at'] = date('Y-m-d H:i:s');
            }
        }
        (new RepairJob())->update($job['id'], $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'REPAIR_UPDATE',
            'entity'     => 'repair_job',
            'entity_id'  => $job['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new RepairJob())->findFull($job['id']), 'Repair job updated');
    }

    /** POST /api/repairs/{id} — add a part. */
    public function addPart(Request $request, array $params = []): Response
    {
        $job = (new RepairJob())->find($params['id']);
        if (!$job) throw HttpException::notFound('Repair job not found');
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_REPAIR_PART, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $qty = (float)($data['quantity'] ?? 1);
        $unitPrice = (float)($data['unit_price'] ?? 0);
        $subtotal = $qty * $unitPrice;

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $partId = Util::uuid();
            (new RepairPart())->insert([
                'id'             => $partId,
                'repair_job_id'  => $job['id'],
                'item_id'        => $data['item_id'] ?? null,
                'part_name'      => $data['part_name'],
                'part_sku'       => $data['part_sku'] ?? null,
                'quantity'       => $qty,
                'unit_price'     => $unitPrice,
                'subtotal'       => $subtotal,
                'from_inventory' => !empty($data['from_inventory']) ? 1 : 0,
            ]);

            // If from_inventory, decrement stock and create StockMovement
            if (!empty($data['from_inventory']) && !empty($data['item_id'])) {
                $affected = (new Item())->decrementStock($data['item_id'], $qty);
                if ($affected === 0) {
                    throw HttpException::conflict('Insufficient stock for part: ' . $data['part_name']);
                }
                (new StockMovement())->insert([
                    'id'             => Util::uuid(),
                    'item_id'        => $data['item_id'],
                    'movement_type'  => 'out',
                    'quantity'       => $qty,
                    'reference_type' => 'repair',
                    'reference_id'   => $job['id'],
                    'notes'          => 'Repair ' . $job['job_number'],
                    'movement_date'  => date('Y-m-d H:i:s'),
                    'created_by'     => $this->userId(),
                ]);
            }

            // Recompute parts_cost and total_cost
            $partsCost = (float)$db->fetchColumn(
                'SELECT COALESCE(SUM(subtotal), 0) FROM repair_parts WHERE repair_job_id = ?',
                [$job['id']]
            );
            $laborCost = (float)$job['labor_cost'];
            $totalCost = $partsCost + $laborCost;
            $db->update('repair_jobs', [
                'parts_cost' => $partsCost,
                'total_cost' => $totalCost,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$job['id']]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'REPAIR_PART_ADD',
            'entity'     => 'repair_part',
            'entity_id'  => $partId,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new RepairJob())->findFull($job['id']), 'Part added', 201);
    }
}
