<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class TermsTemplate extends BaseModel
{
    protected string $table = 'terms_templates';

    public function listActive(int $page = 1, int $limit = 20, ?string $search = null, string $trashed = 'hide'): array
    {
        [$where, $params] = $this->applyTrashedFilter('', '', $trashed);
        if ($search) {
            $where = $where === '' ? "name LIKE ?" : "{$where} AND name LIKE ?";
            $params[] = "%{$search}%";
        }
        $where = $where === '' ? '1=1' : $where;
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM terms_templates WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT * FROM terms_templates WHERE {$where} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function findActiveForA4(): ?array
    {
        return $this->db()->fetch(
            "SELECT * FROM terms_templates
             WHERE is_active = 1 AND deleted_at IS NULL
               AND JSON_CONTAINS(applies_to, '\"a4\"')
             ORDER BY updated_at DESC LIMIT 1"
        );
    }
}
