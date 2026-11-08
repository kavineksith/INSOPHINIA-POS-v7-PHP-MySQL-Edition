<?php
declare(strict_types=1);
namespace Insophinia\POS\Models;

class Setting extends BaseModel
{
    protected string $table = 'settings';

    public function findByKey(string $key): ?array
    {
        return $this->db()->fetch('SELECT * FROM settings WHERE `key` = ? LIMIT 1', [$key]);
    }

    public function allKeyed(): array
    {
        $rows = $this->db()->fetchAll('SELECT `key`, `value`, `type` FROM settings');
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }
}
