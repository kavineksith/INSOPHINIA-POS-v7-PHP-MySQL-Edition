<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class SystemState extends BaseModel
{
    protected string $table = 'system_state';

    public function getState(): ?array
    {
        return $this->db()->fetch('SELECT * FROM system_state LIMIT 1');
    }

    public function touchCheckin(): void
    {
        $this->db()->run('UPDATE system_state SET last_checkin_at = NOW(), updated_at = NOW() WHERE id = (SELECT id FROM (SELECT id FROM system_state LIMIT 1) AS s)');
    }
}
