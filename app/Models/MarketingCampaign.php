<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class MarketingCampaign extends BaseModel
{
    protected string $table = 'marketing_campaigns';

    public function listWithRelations(int $page, int $limit, ?string $search = null): array
    {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (mc.name LIKE ? OR mc.recipient_email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $total = (int)$this->db()->fetchColumn("SELECT COUNT(*) FROM marketing_campaigns mc WHERE {$where}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->db()->fetchAll(
            "SELECT mc.*, et.name AS template_name, u.username AS creator_username
             FROM marketing_campaigns mc
             LEFT JOIN email_templates et ON et.id = mc.template_id
             LEFT JOIN users u ON u.id = mc.created_by
             WHERE {$where}
             ORDER BY mc.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return ['items' => $rows, 'total' => $total];
    }

    public function findFull(string $id): ?array
    {
        $c = $this->db()->fetch(
            "SELECT mc.*, et.name AS template_name, et.subject AS template_subject,
                    et.html_body AS template_html, et.default_variables AS template_default_variables,
                    u.username AS creator_username
             FROM marketing_campaigns mc
             LEFT JOIN email_templates et ON et.id = mc.template_id
             LEFT JOIN users u ON u.id = mc.created_by
             WHERE mc.id = ? LIMIT 1",
            [$id]
        );
        if (!$c) return null;
        $c['recipients'] = $this->db->fetchAll(
            'SELECT * FROM marketing_recipients WHERE campaign_id = ? ORDER BY created_at DESC LIMIT 50',
            [$id]
        );
        return $c;
    }
}
