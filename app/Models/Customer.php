<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Customer extends BaseModel
{
    protected string $table = 'customers';

    public function findByPhone(?string $phone): ?array
    {
        if (!$phone) return null;
        return $this->db()->fetch('SELECT * FROM customers WHERE phone = ? AND deleted_at IS NULL LIMIT 1', [$phone]);
    }

    public function findByEmail(?string $email): ?array
    {
        if (!$email) return null;
        return $this->db()->fetch('SELECT * FROM customers WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email]);
    }

    public function listWithStats(int $page, int $limit, ?string $search = null, ?bool $optedIn = null, string $trashed = 'hide'): array
    {
        [$where, $params] = $this->applyTrashedFilter('', 'c', $trashed);
        if ($search) {
            $where = $where === '' ? "(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)" : "{$where} AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($optedIn !== null) {
            $where = $where === '' ? "c.marketing_opt_in = " . ($optedIn ? '1' : '0') : "{$where} AND c.marketing_opt_in = " . ($optedIn ? '1' : '0');
        }
        $where = $where === '' ? '1=1' : $where;
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM customers c WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM bills b WHERE b.customer_id = c.id AND b.deleted_at IS NULL) AS bills_count
             FROM customers c
             WHERE {$where}
             ORDER BY c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function decrementLoyaltyPoints(string $customerId, int $points): int
    {
        return $this->db()->run(
            'UPDATE customers SET loyalty_points = loyalty_points - ?, updated_at = NOW() WHERE id = ? AND loyalty_points >= ?',
            [$points, $customerId, $points]
        )->rowCount();
    }

    public function incrementLoyaltyPoints(string $customerId, int $points): int
    {
        return $this->db()->run(
            'UPDATE customers SET loyalty_points = loyalty_points + ?, updated_at = NOW() WHERE id = ?',
            [$points, $customerId]
        )->rowCount();
    }
}
