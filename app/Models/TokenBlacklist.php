<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class TokenBlacklist extends BaseModel
{
    protected string $table = 'token_blacklist';

    public function isBlacklisted(string $tokenHash): bool
    {
        return $this->db()->fetch(
            'SELECT 1 FROM token_blacklist WHERE token_hash = ? LIMIT 1',
            [$tokenHash]
        ) !== null;
    }
}
