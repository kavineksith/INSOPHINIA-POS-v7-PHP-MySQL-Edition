<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class StockMovement extends BaseModel
{
    protected string $table = 'stock_movements';

    public function listByItem(string $itemId, int $limit = 100): array
    {
        return $this->db()->fetchAll(
            'SELECT sm.*, u.username AS created_by_username
             FROM stock_movements sm LEFT JOIN users u ON u.id = sm.created_by
             WHERE sm.item_id = ? ORDER BY sm.movement_date DESC LIMIT ?',
            [$itemId, $limit]
        );
    }
}
