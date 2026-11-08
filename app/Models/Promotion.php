<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Promotion extends BaseModel
{
    protected string $table = 'promotions';

    public function findActiveForItem(string $itemId): ?array
    {
        return $this->db()->fetch(
            "SELECT * FROM promotions
             WHERE item_id = ? AND is_active = 1 AND deleted_at IS NULL
               AND start_date <= NOW() AND end_date >= NOW()
             LIMIT 1",
            [$itemId]
        );
    }
}
