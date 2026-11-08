<?php
/**
 * CategoryController — CRUD for categories.
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
use Insophinia\POS\Models\Category;
use Insophinia\POS\Models\Item;

class CategoryController extends Controller
{
    private Category $model;

    public function __construct()
    {
        $this->model = new Category();
    }

    /** GET /api/categories */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $trashed = \Insophinia\POS\Models\BaseModel::parseTrashedParam($request->query('trashed'));
        $result = $this->model->listWithItemCount($p['page'], $p['limit'], $p['search'], $trashed);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/categories */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_CATEGORY, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        // Check name uniqueness
        if ($this->model->findByName($data['name'])) {
            throw HttpException::conflict('A category with this name already exists');
        }

        $id = $this->model->insert([
            'id'          => Util::uuid(),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => 1,
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CATEGORY_CREATE',
            'entity'     => 'category',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->model->find($id), 'Category created', 201);
    }

    /** GET /api/categories/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $cat = $this->model->find($params['id']);
        if (!$cat || $cat['deleted_at']) {
            throw HttpException::notFound('Category not found');
        }
        return Response::success($cat);
    }

    /** PUT /api/categories/{id} */
    public function update(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $cat = $this->model->find($id);
        if (!$cat || $cat['deleted_at']) {
            throw HttpException::notFound('Category not found');
        }

        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_CATEGORY, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        // Name uniqueness (excluding self)
        if (!empty($data['name'])) {
            $existing = $this->model->findByName($data['name']);
            if ($existing && $existing['id'] !== $id) {
                throw HttpException::conflict('A category with this name already exists');
            }
        }

        $this->model->update($id, $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CATEGORY_UPDATE',
            'entity'     => 'category',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->model->find($id), 'Category updated');
    }

    /** DELETE /api/categories/{id} — soft delete by default. */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $cat = $this->model->find($id);
        if (!$cat) {
            throw HttpException::notFound('Category not found');
        }

        // If already soft-deleted and user has force_delete permission, allow hard delete via ?force=true
        $force = $request->query('force') === 'true' || $request->query('force') === '1';
        if ($force) {
            if (!\Insophinia\POS\Lib\Permissions::canForceDelete($this->userRole())) {
                throw HttpException::forbidden('Only master_admin can permanently delete records');
            }
            $this->model->forceDelete($id);
            Security::auditLog([
                'user_id' => $this->userId(), 'action' => 'CATEGORY_FORCE_DELETE',
                'entity' => 'category', 'entity_id' => $id,
                'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
                'details' => 'name=' . ($cat['name'] ?? ''),
            ]);
            return Response::success(null, 'Category permanently deleted');
        }

        // Block if items reference it
        $itemsCount = (int)Database_count_items_in_category($id);
        if ($itemsCount > 0) {
            throw HttpException::conflict("Cannot delete: {$itemsCount} item(s) still reference this category");
        }

        // Soft delete (works whether or not already soft-deleted — idempotent)
        $this->model->softDelete($id);

        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'CATEGORY_DELETE',
            'entity' => 'category', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success(null, 'Category deleted');
    }

    /** PATCH /api/categories/{id}/restore — restore a soft-deleted category. */
    public function restore(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $cat = $this->model->find($id);
        if (!$cat) {
            throw HttpException::notFound('Category not found');
        }
        if (empty($cat['deleted_at'])) {
            throw HttpException::badRequest('Category is not deleted');
        }
        $this->model->restore($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'CATEGORY_RESTORE',
            'entity' => 'category', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success($this->model->find($id), 'Category restored');
    }
}

/** Helper to count items in a category. */
function Database_count_items_in_category(string $categoryId): int
{
    $itemModel = new Item();
    return $itemModel->countWhere('category_id = ? AND deleted_at IS NULL', [$categoryId]);
}
