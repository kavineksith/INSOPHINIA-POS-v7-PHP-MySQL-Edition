<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class LoginSession extends BaseModel
{
    protected string $table = 'login_sessions';

    public function createSession(array $data): string
    {
        return $this->insert($data);
    }

    public function revoke(string $sessionId, string $reason = 'user_logout'): int
    {
        return $this->db()->update('login_sessions', [
            'is_active'      => 0,
            'revoked_at'     => date('Y-m-d H:i:s'),
            'revoke_reason'  => $reason,
        ], 'id = ?', [$sessionId]);
    }
}
