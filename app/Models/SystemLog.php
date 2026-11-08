<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class SystemLog extends BaseModel
{
    protected string $table = 'system_logs';

    public function log(string $level, string $category, string $action, string $message, ?array $metadata = null, ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->insert([
            'id'         => \Insophinia\POS\Lib\Util::uuid(),
            'level'      => $level,
            'category'   => $category,
            'action'     => $action,
            'message'    => $message,
            'metadata'   => $metadata ? json_encode($metadata) : null,
            'user_id'    => $userId,
            'ip_address' => $ipAddress,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
