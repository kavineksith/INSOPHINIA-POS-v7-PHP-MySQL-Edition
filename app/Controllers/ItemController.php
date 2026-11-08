<?php
/**
 * ItemController — CRUD for items, variants, sub-items.
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
use Insophinia\POS\Models\Category;
use Insophinia\POS\Models\Item;
use Insophinia\POS\Models\ItemVariant;
use Insophinia\POS\Models\ItemSubItem;
use Insophinia\POS\Models\StockMovement;

class ItemController extends Controller
{
    private Item $items;
    private Category $categories;
    private ItemVariant $variants;
    private ItemSubItem $subItems;

    public function __construct()
    {
        $this->items = new Item();
        $this->categories = new Category();
        $this->variants = new ItemVariant();
        $this->subItems = new ItemSubItem();
    }

    /** GET /api/items */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $categoryId = $request->query('category_id');
        $itemType = $request->query('item_type');
        $lowStock = $request->query('low_stock') === 'true' || $request->query('low_stock') === '1';
        $trashed = \Insophinia\POS\Models\BaseModel::parseTrashedParam($request->query('trashed'));

        $result = $this->items->listWithRelations(
            $p['page'], $p['limit'], $p['search'],
            $categoryId, $itemType, $lowStock ?: null, $trashed
        );
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/items */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_ITEM, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        // Validate category exists
        $cat = $this->categories->find($data['category_id']);
        if (!$cat || $cat['deleted_at']) {
            throw HttpException::badRequest('Category does not exist');
        }

        // PLU + barcode uniqueness
        if ($this->items->findByPluCode($data['plu_code'])) {
            throw HttpException::conflict('PLU code already exists');
        }
        if (!empty($data['barcode']) && $this->items->findByBarcode($data['barcode'])) {
            throw HttpException::conflict('Barcode already exists');
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $id = Util::uuid();
            $this->items->insert([
                'id'                  => $id,
                'plu_code'            => $data['plu_code'],
                'name'                => $data['name'],
                'description'         => $data['description'] ?? null,
                'category_id'         => $data['category_id'],
                'barcode'             => $data['barcode'] ?? null,
                'qr_code'             => $data['qr_code'] ?? null,
                'price'               => $data['price'],
                'cost_price'          => $data['cost_price'],
                'unit'                => $data['unit'] ?? 'pcs',
                'stock_quantity'      => $data['stock_quantity'] ?? 0,
                'total_quantity'      => $data['stock_quantity'] ?? 0,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 10,
                'is_active'           => 1,
                'has_discount'        => !empty($data['has_discount']) ? 1 : 0,
                'discount_percentage' => $data['discount_percentage'] ?? 0,
                'warranty_days'       => $data['warranty_days'] ?? 0,
                'item_type'           => $data['item_type'] ?? 'product',
                'version'             => 0,
            ]);

            // Initial stock movement if stock_quantity > 0
            $stockQty = (float)($data['stock_quantity'] ?? 0);
            if ($stockQty > 0) {
                (new StockMovement())->insert([
                    'id'             => Util::uuid(),
                    'item_id'        => $id,
                    'movement_type'  => 'in',
                    'quantity'       => $stockQty,
                    'reference_type' => 'manual',
                    'reference_id'   => null,
                    'notes'          => 'Initial stock',
                    'movement_date'  => date('Y-m-d H:i:s'),
                    'created_by'     => $this->userId(),
                ]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'ITEM_CREATE',
            'entity'     => 'item',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->items->findWithRelations($id), 'Item created', 201);
    }

    /** GET /api/items/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $item = $this->items->findWithRelations($params['id']);
        if (!$item) {
            throw HttpException::notFound('Item not found');
        }
        return Response::success($item);
    }

    /** PUT /api/items/{id} */
    public function update(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $item = $this->items->find($id);
        if (!$item || $item['deleted_at']) {
            throw HttpException::notFound('Item not found');
        }

        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_ITEM, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        if (!empty($data['plu_code']) && $data['plu_code'] !== $item['plu_code']) {
            $existing = $this->items->findByPluCode($data['plu_code']);
            if ($existing && $existing['id'] !== $id) {
                throw HttpException::conflict('PLU code already exists');
            }
        }
        if (!empty($data['barcode']) && $data['barcode'] !== ($item['barcode'] ?? '')) {
            $existing = $this->items->findByBarcode($data['barcode']);
            if ($existing && $existing['id'] !== $id) {
                throw HttpException::conflict('Barcode already exists');
            }
        }

        // Increment version
        $data['version'] = ((int)$item['version']) + 1;
        $this->items->update($id, $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'ITEM_UPDATE',
            'entity'     => 'item',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->items->findWithRelations($id), 'Item updated');
    }

    /** DELETE /api/items/{id} — soft delete by default, hard delete with ?force=true (master_admin only). */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $item = $this->items->find($id);
        if (!$item) {
            throw HttpException::notFound('Item not found');
        }

        $force = $request->query('force') === 'true' || $request->query('force') === '1';
        if ($force) {
            if (!\Insophinia\POS\Lib\Permissions::canForceDelete($this->userRole())) {
                throw HttpException::forbidden('Only master_admin can permanently delete records');
            }
            $this->items->forceDelete($id);
            Security::auditLog([
                'user_id' => $this->userId(), 'action' => 'ITEM_FORCE_DELETE',
                'entity' => 'item', 'entity_id' => $id,
                'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
                'details' => 'name=' . ($item['name'] ?? ''),
            ]);
            return Response::success(null, 'Item permanently deleted');
        }

        $this->items->softDelete($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'ITEM_DELETE',
            'entity' => 'item', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Item deleted');
    }

    /** PATCH /api/items/{id}/restore — restore a soft-deleted item. */
    public function restore(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $item = $this->items->find($id);
        if (!$item) throw HttpException::notFound('Item not found');
        if (empty($item['deleted_at'])) throw HttpException::badRequest('Item is not deleted');
        $this->items->restore($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'ITEM_RESTORE',
            'entity' => 'item', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success($this->items->findWithRelations($id), 'Item restored');
    }

    // ---- Variants ----

    /** GET /api/items/{id}/variants */
    public function variantsIndex(Request $request, array $params = []): Response
    {
        $rows = $this->variants->listByItem($params['id']);
        // Parse specs JSON
        foreach ($rows as &$r) {
            $r['specs'] = Util::jsonDecode($r['specs'], []);
        }
        return Response::success(['items' => $rows]);
    }

    /** POST /api/items/{id}/variants */
    public function variantsStore(Request $request, array $params = []): Response
    {
        $itemId = $params['id'];
        if (!$this->items->find($itemId)) {
            throw HttpException::notFound('Item not found');
        }
        $body = $request->json();
        $body['item_id'] = $itemId;
        $v = Validation::validate(Validation::SCHEMA_CREATE_VARIANT, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        if ($this->variants->findBySku($data['sku'])) {
            throw HttpException::conflict('SKU already exists');
        }

        $id = $this->variants->insert([
            'id'               => Util::uuid(),
            'item_id'          => $itemId,
            'sku'              => $data['sku'],
            'name'             => $data['name'],
            'price_adjustment' => $data['price_adjustment'] ?? 0,
            'stock_quantity'   => $data['stock_quantity'] ?? 0,
            'is_active'        => !empty($data['is_active']) ? 1 : 0,
            'specs'            => Util::jsonEncode($data['specs'] ?? []),
            'warranty_days'    => $data['warranty_days'] ?? 0,
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'VARIANT_CREATE',
            'entity'     => 'item_variant',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->variants->find($id), 'Variant created', 201);
    }

    /** PUT /api/items/{id}/variants/{variantId} */
    public function variantsUpdate(Request $request, array $params = []): Response
    {
        $variant = $this->variants->find($params['variantId']);
        if (!$variant) {
            throw HttpException::notFound('Variant not found');
        }
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_VARIANT, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        if (!empty($data['specs']) && is_array($data['specs'])) {
            $data['specs'] = Util::jsonEncode($data['specs']);
        }
        $this->variants->update($variant['id'], $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'VARIANT_UPDATE',
            'entity'     => 'item_variant',
            'entity_id'  => $variant['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->variants->find($variant['id']), 'Variant updated');
    }

    /** DELETE /api/items/{id}/variants/{variantId} */
    public function variantsDestroy(Request $request, array $params = []): Response
    {
        $variant = $this->variants->find($params['variantId']);
        if (!$variant) {
            throw HttpException::notFound('Variant not found');
        }
        $this->variants->update($variant['id'], ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'VARIANT_DELETE',
            'entity'     => 'item_variant',
            'entity_id'  => $variant['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success(null, 'Variant deactivated');
    }

    // ---- Sub-items ----

    /** GET /api/items/{id}/sub-items */
    public function subItemsIndex(Request $request, array $params = []): Response
    {
        $rows = $this->subItems->listByItem($params['id']);
        return Response::success(['items' => $rows]);
    }

    /** POST /api/items/{id}/sub-items */
    public function subItemsStore(Request $request, array $params = []): Response
    {
        $itemId = $params['id'];
        if (!$this->items->find($itemId)) {
            throw HttpException::notFound('Item not found');
        }
        $body = $request->json();
        $body['item_id'] = $itemId;
        $v = Validation::validate(Validation::SCHEMA_CREATE_SUB_ITEM, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        $id = $this->subItems->insert([
            'id'                 => Util::uuid(),
            'item_id'            => $itemId,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'base_price'         => $data['base_price'],
            'revisions_included' => $data['revisions_included'] ?? 2,
            'revision_price'     => $data['revision_price'] ?? 0,
            'estimated_hours'    => $data['estimated_hours'] ?? 0,
            'is_active'          => 1,
            'sort_order'         => $data['sort_order'] ?? 0,
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'SUBITEM_CREATE',
            'entity'     => 'item_sub_item',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->subItems->find($id), 'Sub-item created', 201);
    }

    /** PUT /api/items/{id}/sub-items/{subItemId} */
    public function subItemsUpdate(Request $request, array $params = []): Response
    {
        $subItem = $this->subItems->find($params['subItemId']);
        if (!$subItem) {
            throw HttpException::notFound('Sub-item not found');
        }
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_SUB_ITEM, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $this->subItems->update($subItem['id'], $v['data']);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'SUBITEM_UPDATE',
            'entity'     => 'item_sub_item',
            'entity_id'  => $subItem['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success($this->subItems->find($subItem['id']), 'Sub-item updated');
    }

    /** DELETE /api/items/{id}/sub-items/{subItemId} */
    public function subItemsDestroy(Request $request, array $params = []): Response
    {
        $subItem = $this->subItems->find($params['subItemId']);
        if (!$subItem) {
            throw HttpException::notFound('Sub-item not found');
        }
        $this->subItems->update($subItem['id'], ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'SUBITEM_DELETE',
            'entity'     => 'item_sub_item',
            'entity_id'  => $subItem['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success(null, 'Sub-item deactivated');
    }
}
