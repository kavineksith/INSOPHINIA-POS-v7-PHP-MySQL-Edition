<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class User extends BaseModel
{
    protected string $table = 'users';

    public function findByUsername(string $username): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1',
            [$username]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [$email]
        );
    }

    public function findActiveById(string $id): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }
}
