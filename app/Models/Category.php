<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Category extends BaseModel
{
    protected string $table = 'categories';

    public function findByName(string $name): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM categories WHERE name = ? LIMIT 1',
            [$name]
        );
    }

    public function listWithItemCount(int $page, int $limit, ?string $search = null, string $trashed = 'hide'): array
    {
        [$where, $params] = $this->applyTrashedFilter('', 'c', $trashed);
        if ($search) {
            $where = $where === '' ? "c.name LIKE ?" : "{$where} AND c.name LIKE ?";
            $params[] = "%{$search}%";
        }
        $where = $where === '' ? '1=1' : $where;
        $total = (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM categories c WHERE {$where}",
            $params
        );
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id AND i.deleted_at IS NULL AND i.is_active = 1) AS items_count
             FROM categories c
             WHERE {$where}
             ORDER BY c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }
}
