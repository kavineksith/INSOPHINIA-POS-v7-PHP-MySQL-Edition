<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class ItemSubItem extends BaseModel
{
    protected string $table = 'item_sub_items';

    public function listByItem(string $itemId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM item_sub_items WHERE item_id = ? AND is_active = 1 ORDER BY sort_order ASC, created_at ASC',
            [$itemId]
        );
    }
}
