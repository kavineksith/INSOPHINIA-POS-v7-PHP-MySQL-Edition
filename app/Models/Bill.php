<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Bill extends BaseModel
{
    protected string $table = 'bills';

    public function findByBillNumber(string $billNumber): ?array
    {
        return $this->db()->fetch('SELECT * FROM bills WHERE bill_number = ? LIMIT 1', [$billNumber]);
    }

    /** Generate next BILL-YYYYMMDD-NNNN. Retries on duplicate. */
    public function nextBillNumber(int $maxAttempts = 3): string
    {
        $date = new \DateTime();
        $prefix = 'BILL-' . $date->format('Ymd') . '-';
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $count = (int)$this->db()->fetchColumn(
                "SELECT COUNT(*) FROM bills WHERE bill_number LIKE ?",
                [$prefix . '%']
            );
            $candidate = $prefix . sprintf('%04d', $count + 1);
            // Verify uniqueness
            if (!$this->findByBillNumber($candidate)) {
                return $candidate;
            }
        }
        // Fallback with random suffix
        return $prefix . sprintf('%04d', rand(1000, 9999));
    }

    public function listWithRelations(int $page, int $limit, ?string $search = null, ?string $status = null, string $trashed = 'hide'): array
    {
        [$where, $params] = $this->applyTrashedFilter('', 'b', $trashed);
        if ($search) {
            $where = $where === '' ? "(b.bill_number LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.customer_email LIKE ?)" : "{$where} AND (b.bill_number LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.customer_email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status) {
            $where = $where === '' ? "b.status = ?" : "{$where} AND b.status = ?";
            $params[] = $status;
        }
        $where = $where === '' ? '1=1' : $where;
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM bills b WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT b.*, u.username AS staff_username, c.name AS customer_full_name
             FROM bills b
             LEFT JOIN users u ON u.id = b.staff_id
             LEFT JOIN customers c ON c.id = b.customer_id
             WHERE {$where}
             ORDER BY b.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function findFull(string $id): ?array
    {
        $bill = $this->db()->fetch(
            "SELECT b.*, u.username AS staff_username
             FROM bills b LEFT JOIN users u ON u.id = b.staff_id
             WHERE b.id = ? LIMIT 1",
            [$id]
        );
        if (!$bill) return null;
        $bill['items'] = $this->db->fetchAll(
            "SELECT bi.*, iv.sku AS variant_sku_lookup
             FROM bill_items bi
             LEFT JOIN item_variants iv ON iv.id = bi.variant_id
             WHERE bi.bill_id = ?
             ORDER BY bi.created_at ASC",
            [$id]
        );
        $bill['warranties'] = $this->db()->fetchAll(
            'SELECT * FROM warranties WHERE bill_id = ? ORDER BY created_at ASC',
            [$id]
        );
        return $bill;
    }

    /** Today's sales total (completed bills only). */
    public function todaySales(): float
    {
        return (float)$this->db()->fetchColumn(
            "SELECT COALESCE(SUM(total_amount), 0) FROM bills
             WHERE DATE(created_at) = CURDATE() AND status = 'completed' AND deleted_at IS NULL"
        );
    }

    public function todayBillCount(): int
    {
        return (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM bills
             WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL"
        );
    }
}
