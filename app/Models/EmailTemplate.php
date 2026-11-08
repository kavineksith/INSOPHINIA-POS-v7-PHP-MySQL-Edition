<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class EmailTemplate extends BaseModel
{
    protected string $table = 'email_templates';

    public function listWithStats(int $page, int $limit, ?string $search = null, ?string $type = null, string $trashed = 'hide'): array
    {
        [$where, $params] = $this->applyTrashedFilter('', 'et', $trashed);
        if ($search) {
            $where = $where === '' ? "(et.name LIKE ? OR et.subject LIKE ?)" : "{$where} AND (et.name LIKE ? OR et.subject LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($type) {
            $where = $where === '' ? "et.type = ?" : "{$where} AND et.type = ?";
            $params[] = $type;
        }
        $where = $where === '' ? '1=1' : $where;
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM email_templates et WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT et.*,
                (SELECT COUNT(*) FROM marketing_campaigns mc WHERE mc.template_id = et.id) AS campaigns_count
             FROM email_templates et
             WHERE {$where}
             ORDER BY et.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }
}
