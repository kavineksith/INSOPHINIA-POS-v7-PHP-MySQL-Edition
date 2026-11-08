<?php
/**
 * CustomerController — CRUD for customers.
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
use Insophinia\POS\Models\Customer;
use Insophinia\POS\Models\Bill;

class CustomerController extends Controller
{
    private Customer $model;

    public function __construct()
    {
        $this->model = new Customer();
    }

    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $optedIn = $request->query('opted_in');
        $optedInFlag = null;
        if ($optedIn === 'true' || $optedIn === '1') $optedInFlag = true;
        elseif ($optedIn === 'false' || $optedIn === '0') $optedInFlag = false;
        $trashed = \Insophinia\POS\Models\BaseModel::parseTrashedParam($request->query('trashed'));

        $result = $this->model->listWithStats($p['page'], $p['limit'], $p['search'], $optedInFlag, $trashed);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_CUSTOMER, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        if (!empty($data['phone']) && $this->model->findByPhone($data['phone'])) {
            throw HttpException::conflict('Phone number already exists');
        }
        if (!empty($data['email']) && $this->model->findByEmail($data['email'])) {
            throw HttpException::conflict('Email already exists');
        }

        $optIn = !empty($data['marketing_opt_in']);
        $id = $this->model->insert([
            'id'                 => Util::uuid(),
            'name'               => $data['name'],
            'phone'              => $data['phone'] ?? null,
            'email'              => !empty($data['email']) ? strtolower($data['email']) : null,
            'address'            => $data['address'] ?? null,
            'loyalty_points'     => $data['loyalty_points'] ?? 0,
            'marketing_opt_in'   => $optIn ? 1 : 0,
            'marketing_opt_in_at'=> $optIn ? date('Y-m-d H:i:s') : null,
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CUSTOMER_CREATE',
            'entity'     => 'customer',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->model->find($id), 'Customer created', 201);
    }

    public function show(Request $request, array $params = []): Response
    {
        $customer = $this->model->find($params['id']);
        if (!$customer || $customer['deleted_at']) {
            throw HttpException::notFound('Customer not found');
        }
        // Last 10 bills
        $customer['bills'] = (new Bill())->db()->fetchAll(
            'SELECT * FROM bills WHERE customer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10',
            [$customer['id']]
        );
        return Response::success($customer);
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $customer = $this->model->find($id);
        if (!$customer || $customer['deleted_at']) {
            throw HttpException::notFound('Customer not found');
        }
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_CUSTOMER, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        if (!empty($data['phone']) && $data['phone'] !== ($customer['phone'] ?? '')) {
            $existing = $this->model->findByPhone($data['phone']);
            if ($existing && $existing['id'] !== $id) {
                throw HttpException::conflict('Phone number already exists');
            }
        }
        if (!empty($data['email']) && $data['email'] !== ($customer['email'] ?? '')) {
            $existing = $this->model->findByEmail(strtolower($data['email']));
            if ($existing && $existing['id'] !== $id) {
                throw HttpException::conflict('Email already exists');
            }
        }

        // Marketing opt-in/out timestamps
        if (array_key_exists('marketing_opt_in', $data)) {
            $newOpt = !empty($data['marketing_opt_in']);
            $oldOpt = (bool)$customer['marketing_opt_in'];
            if ($newOpt && !$oldOpt) {
                $data['marketing_opt_in_at'] = date('Y-m-d H:i:s');
                $data['marketing_opt_out_at'] = null;
            } elseif (!$newOpt && $oldOpt) {
                $data['marketing_opt_out_at'] = date('Y-m-d H:i:s');
            }
        }
        if (!empty($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }

        $this->model->update($id, $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CUSTOMER_UPDATE',
            'entity'     => 'customer',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->model->find($id), 'Customer updated');
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $customer = $this->model->find($id);
        if (!$customer) {
            throw HttpException::notFound('Customer not found');
        }

        $force = $request->query('force') === 'true' || $request->query('force') === '1';
        if ($force) {
            if (!\Insophinia\POS\Lib\Permissions::canForceDelete($this->userRole())) {
                throw HttpException::forbidden('Only master_admin can permanently delete records');
            }
            $this->model->forceDelete($id);
            Security::auditLog([
                'user_id' => $this->userId(), 'action' => 'CUSTOMER_FORCE_DELETE',
                'entity' => 'customer', 'entity_id' => $id,
                'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
                'details' => 'name=' . ($customer['name'] ?? ''),
            ]);
            return Response::success(null, 'Customer permanently deleted');
        }

        $this->model->softDelete($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'CUSTOMER_DELETE',
            'entity' => 'customer', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Customer deleted');
    }

    /** PATCH /api/customers/{id}/restore */
    public function restore(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $customer = $this->model->find($id);
        if (!$customer) throw HttpException::notFound('Customer not found');
        if (empty($customer['deleted_at'])) throw HttpException::badRequest('Customer is not deleted');
        $this->model->restore($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'CUSTOMER_RESTORE',
            'entity' => 'customer', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success($this->model->find($id), 'Customer restored');
    }
}
