<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class RepairJob extends BaseModel
{
    protected string $table = 'repair_jobs';

    public function findByJobNumber(string $number): ?array
    {
        return $this->db()->fetch('SELECT * FROM repair_jobs WHERE job_number = ? LIMIT 1', [$number]);
    }

    public function nextJobNumber(int $maxAttempts = 3): string
    {
        $date = new \DateTime();
        $prefix = 'REP-' . $date->format('Ymd') . '-';
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $count = (int)$this->db()->fetchColumn(
                "SELECT COUNT(*) FROM repair_jobs WHERE job_number LIKE ?",
                [$prefix . '%']
            );
            $candidate = $prefix . sprintf('%04d', $count + 1);
            if (!$this->findByJobNumber($candidate)) return $candidate;
        }
        return $prefix . sprintf('%04d', rand(1000, 9999));
    }

    public function listWithRelations(int $page, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (rj.job_number LIKE ? OR rj.customer_name LIKE ? OR rj.customer_phone LIKE ? OR rj.item_description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status) {
            $where .= " AND rj.status = ?";
            $params[] = $status;
        }
        $total = (int)$this->db()->fetchColumn("SELECT COUNT(*) FROM repair_jobs rj WHERE {$where}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT rj.*, c.name AS customer_full_name, u.username AS technician_username,
                (SELECT COUNT(*) FROM repair_parts rp WHERE rp.repair_job_id = rj.id) AS parts_count
             FROM repair_jobs rj
             LEFT JOIN customers c ON c.id = rj.customer_id
             LEFT JOIN users u ON u.id = rj.technician_id
             WHERE {$where}
             ORDER BY rj.received_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function findFull(string $id): ?array
    {
        $job = $this->db()->fetch(
            "SELECT rj.*, c.name AS customer_full_name, u.username AS technician_username
             FROM repair_jobs rj
             LEFT JOIN customers c ON c.id = rj.customer_id
             LEFT JOIN users u ON u.id = rj.technician_id
             WHERE rj.id = ? LIMIT 1",
            [$id]
        );
        if (!$job) return null;
        $job['parts'] = $this->db()->fetchAll(
            "SELECT rp.*, i.name AS item_name
             FROM repair_parts rp LEFT JOIN items i ON i.id = rp.item_id
             WHERE rp.repair_job_id = ? ORDER BY rp.created_at ASC",
            [$id]
        );
        return $job;
    }
}
