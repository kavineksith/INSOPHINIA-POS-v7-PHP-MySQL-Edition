<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Warranty extends BaseModel
{
    protected string $table = 'warranties';

    public function findByWarrantyNumber(string $number): ?array
    {
        return $this->db()->fetch('SELECT * FROM warranties WHERE warranty_number = ? LIMIT 1', [$number]);
    }

    public function nextWarrantyNumber(int $maxAttempts = 3): string
    {
        $date = new \DateTime();
        $prefix = 'WAR-' . $date->format('Ymd') . '-';
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $count = (int)$this->db()->fetchColumn(
                "SELECT COUNT(*) FROM warranties WHERE warranty_number LIKE ?",
                [$prefix . '%']
            );
            $candidate = $prefix . sprintf('%04d', $count + 1);
            if (!$this->findByWarrantyNumber($candidate)) return $candidate;
        }
        return $prefix . sprintf('%04d', rand(1000, 9999));
    }

    public function listWithRelations(int $page, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (w.warranty_number LIKE ? OR w.item_name LIKE ? OR w.serial_number LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status) {
            $where .= " AND w.status = ?";
            $params[] = $status;
        }
        $total = (int)$this->db()->fetchColumn("SELECT COUNT(*) FROM warranties w WHERE {$where}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT w.*, c.name AS customer_name, b.bill_number,
                (SELECT COUNT(*) FROM warranty_claims wc WHERE wc.warranty_id = w.id) AS claims_count
             FROM warranties w
             LEFT JOIN customers c ON c.id = w.customer_id
             LEFT JOIN bills b ON b.id = w.bill_id
             WHERE {$where}
             ORDER BY w.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function findFull(string $id): ?array
    {
        $w = $this->db()->fetch(
            "SELECT w.*, c.name AS customer_name, b.bill_number
             FROM warranties w
             LEFT JOIN customers c ON c.id = w.customer_id
             LEFT JOIN bills b ON b.id = w.bill_id
             WHERE w.id = ? LIMIT 1",
            [$id]
        );
        if (!$w) return null;
        $w['claims'] = $this->db()->fetchAll(
            "SELECT wc.*, u.username AS reviewer_username
             FROM warranty_claims wc LEFT JOIN users u ON u.id = wc.reviewed_by
             WHERE wc.warranty_id = ? ORDER BY wc.filed_at DESC",
            [$id]
        );
        return $w;
    }
}
