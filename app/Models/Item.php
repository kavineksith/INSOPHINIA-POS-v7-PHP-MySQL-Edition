<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Item extends BaseModel
{
    protected string $table = 'items';

    public function findByPluCode(string $plu): ?array
    {
        return $this->db()->fetch('SELECT * FROM items WHERE plu_code = ? LIMIT 1', [$plu]);
    }

    public function findByBarcode(?string $barcode): ?array
    {
        if (!$barcode) return null;
        return $this->db()->fetch('SELECT * FROM items WHERE barcode = ? LIMIT 1', [$barcode]);
    }

    public function listWithRelations(int $page, int $limit, ?string $search = null, ?string $categoryId = null, ?string $itemType = null, ?bool $lowStock = null, string $trashed = 'hide'): array
    {
        [$where, $params] = $this->applyTrashedFilter('', 'i', $trashed);
        if ($search) {
            $where = $where === '' ? "(i.name LIKE ? OR i.plu_code LIKE ? OR i.barcode LIKE ?)" : "{$where} AND (i.name LIKE ? OR i.plu_code LIKE ? OR i.barcode LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($categoryId) {
            $where = $where === '' ? "i.category_id = ?" : "{$where} AND i.category_id = ?";
            $params[] = $categoryId;
        }
        if ($itemType) {
            $where = $where === '' ? "i.item_type = ?" : "{$where} AND i.item_type = ?";
            $params[] = $itemType;
        }
        if ($lowStock) {
            $where = $where === '' ? "i.stock_quantity <= i.low_stock_threshold" : "{$where} AND i.stock_quantity <= i.low_stock_threshold";
        }
        $where = $where === '' ? '1=1' : $where;
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM items i WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT i.*,
                c.name AS category_name,
                (SELECT COUNT(*) FROM item_variants v WHERE v.item_id = i.id AND v.is_active = 1) AS variants_count,
                (SELECT COUNT(*) FROM item_sub_items s WHERE s.item_id = i.id AND s.is_active = 1) AS sub_items_count
             FROM items i
             LEFT JOIN categories c ON c.id = i.category_id
             WHERE {$where}
             ORDER BY i.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function findWithRelations(string $id): ?array
    {
        $item = $this->db()->fetch(
            "SELECT i.*, c.name AS category_name
             FROM items i LEFT JOIN categories c ON c.id = i.category_id
             WHERE i.id = ? AND i.deleted_at IS NULL LIMIT 1",
            [$id]
        );
        if (!$item) return null;
        $item['variants'] = $this->db()->fetchAll(
            'SELECT * FROM item_variants WHERE item_id = ? AND is_active = 1 ORDER BY created_at ASC',
            [$id]
        );
        $item['sub_items'] = $this->db()->fetchAll(
            'SELECT * FROM item_sub_items WHERE item_id = ? AND is_active = 1 ORDER BY sort_order ASC, created_at ASC',
            [$id]
        );
        return $item;
    }

    public function decrementStock(string $itemId, float $qty): int
    {
        // Returns number of affected rows. 0 = insufficient stock.
        return $this->db()->run(
            'UPDATE items SET stock_quantity = stock_quantity - ?, sell_quantity = sell_quantity + ?, updated_at = NOW()
             WHERE id = ? AND stock_quantity >= ?',
            [$qty, $qty, $itemId, $qty]
        )->rowCount();
    }

    public function incrementStock(string $itemId, float $qty): int
    {
        return $this->db()->run(
            'UPDATE items SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?',
            [$qty, $itemId]
        )->rowCount();
    }
}
