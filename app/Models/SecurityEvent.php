<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class SecurityEvent extends BaseModel
{
    protected string $table = 'security_events';

    public function listRecent(int $page, int $limit, ?string $search = null): array
    {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (se.event_type LIKE ? OR se.ip_address LIKE ? OR se.details LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $total = (int)$this->db()->fetchColumn("SELECT COUNT(*) FROM security_events se WHERE {$where}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT se.*, u.username AS user_username
             FROM security_events se LEFT JOIN users u ON u.id = se.user_id
             WHERE {$where}
             ORDER BY se.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }
}
