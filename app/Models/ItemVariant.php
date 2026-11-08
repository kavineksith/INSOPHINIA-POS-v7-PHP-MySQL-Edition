<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class ItemVariant extends BaseModel
{
    protected string $table = 'item_variants';

    public function findBySku(string $sku): ?array
    {
        return $this->db()->fetch('SELECT * FROM item_variants WHERE sku = ? LIMIT 1', [$sku]);
    }

    public function listByItem(string $itemId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM item_variants WHERE item_id = ? AND is_active = 1 ORDER BY created_at ASC',
            [$itemId]
        );
    }

    public function decrementStock(string $variantId, float $qty): int
    {
        return $this->db()->run(
            'UPDATE item_variants SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE id = ? AND stock_quantity >= ?',
            [$qty, $variantId, $qty]
        )->rowCount();
    }
}
