<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class RepairPart extends BaseModel
{
    protected string $table = 'repair_parts';

    public function listByJob(string $jobId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM repair_parts WHERE repair_job_id = ? ORDER BY created_at ASC',
            [$jobId]
        );
    }
}
