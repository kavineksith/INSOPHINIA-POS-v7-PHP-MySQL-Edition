<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class WarrantyClaim extends BaseModel
{
    protected string $table = 'warranty_claims';

    public function findByClaimNumber(string $number): ?array
    {
        return $this->db()->fetch('SELECT * FROM warranty_claims WHERE claim_number = ? LIMIT 1', [$number]);
    }

    public function nextClaimNumber(int $maxAttempts = 3): string
    {
        $date = new \DateTime();
        $prefix = 'CLM-' . $date->format('Ymd') . '-';
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $count = (int)$this->db()->fetchColumn(
                "SELECT COUNT(*) FROM warranty_claims WHERE claim_number LIKE ?",
                [$prefix . '%']
            );
            $candidate = $prefix . sprintf('%04d', $count + 1);
            if (!$this->findByClaimNumber($candidate)) return $candidate;
        }
        return $prefix . sprintf('%04d', rand(1000, 9999));
    }

    public function listWithRelations(int $page, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (wc.claim_number LIKE ? OR w.warranty_number LIKE ? OR w.item_name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status) {
            $where .= " AND wc.status = ?";
            $params[] = $status;
        }
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM warranty_claims wc
             INNER JOIN warranties w ON w.id = wc.warranty_id
             WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT wc.*, w.warranty_number, w.item_name, c.name AS customer_name, u.username AS reviewer_username
             FROM warranty_claims wc
             INNER JOIN warranties w ON w.id = wc.warranty_id
             LEFT JOIN customers c ON c.id = wc.customer_id
             LEFT JOIN users u ON u.id = wc.reviewed_by
             WHERE {$where}
             ORDER BY wc.filed_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }
}
