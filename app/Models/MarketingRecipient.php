<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class MarketingRecipient extends BaseModel
{
    protected string $table = 'marketing_recipients';

    public function listByCampaign(string $campaignId, int $limit = 50): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM marketing_recipients WHERE campaign_id = ? ORDER BY created_at DESC LIMIT ?',
            [$campaignId, $limit]
        );
    }

    public function countByCampaign(string $campaignId): int
    {
        return (int)$this->db()->fetchColumn(
            'SELECT COUNT(*) FROM marketing_recipients WHERE campaign_id = ?',
            [$campaignId]
        );
    }
}
