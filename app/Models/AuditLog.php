<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class AuditLog extends BaseModel
{
    protected string $table = 'audit_logs';

    public function listRecent(int $page, int $limit, ?string $search = null): array
    {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (al.action LIKE ? OR al.entity LIKE ? OR al.details LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $total = (int)$this->db()->fetchColumn("SELECT COUNT(*) FROM audit_logs al WHERE {$where}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT al.*, u.username AS user_username
             FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             WHERE {$where}
             ORDER BY al.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }
}
