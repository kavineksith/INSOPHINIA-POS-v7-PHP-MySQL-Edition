<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class BillItem extends BaseModel
{
    protected string $table = 'bill_items';

    public function listByBill(string $billId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at ASC',
            [$billId]
        );
    }
}
