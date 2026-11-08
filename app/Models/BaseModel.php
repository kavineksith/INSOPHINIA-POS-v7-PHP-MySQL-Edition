<?php
/**
 * Base Model — common CRUD operations using PDO.
 * Subclasses set $table and $primaryKey (defaults to 'id').
 *
 * Soft-delete behavior:
 *   - softDelete(id)  → sets deleted_at = NOW(), is_active = 0
 *   - restore(id)     → sets deleted_at = NULL, is_active = 1
 *   - forceDelete(id) → physical DELETE (irreversible; master_admin only)
 *
 * Trashed query support:
 *   - findActive(id)            → returns row only if not soft-deleted
 *   - findWithTrashed(id)       → returns row regardless of deleted_at
 *   - findOnlyTrashed(id)       → returns row only if soft-deleted
 *   - applyTrashedFilter($qb,$alias,$mode) — modifies WHERE clause
 *     $mode: 'hide' (default, exclude deleted) | 'with' (include) | 'only' (only deleted)
 */

declare(strict_types=1);

namespace Insophinia\POS\Models;

use Insophinia\POS\Core\Database;
use Insophinia\POS\Lib\Util;

abstract class BaseModel
{
    protected string $table;
    protected string $primaryKey = 'id';

    /** Whether this model has a deleted_at column (auto-detected on first use). */
    private ?bool $hasSoftDelete = null;

    protected function db(): Database
    {
        return Database::getInstance();
    }

    // ---- FINDERS ----

    /** Find a row regardless of trashed state. */
    public function find(string $id): ?array
    {
        return $this->db()->fetch(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1",
            [$id]
        );
    }

    /** Find a row only if NOT soft-deleted. */
    public function findActive(string $id): ?array
    {
        $row = $this->find($id);
        if (!$row) return null;
        if ($this->supportsSoftDelete() && !empty($row['deleted_at'])) return null;
        return $row;
    }

    /** Find a row only if soft-deleted. */
    public function findOnlyTrashed(string $id): ?array
    {
        $row = $this->find($id);
        if (!$row) return null;
        if (!$this->supportsSoftDelete() || empty($row['deleted_at'])) return null;
        return $row;
    }

    public function all(int $limit = 1000, int $offset = 0, string $orderBy = 'created_at', string $dir = 'DESC'): array
    {
        $orderBy = preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy) ?: 'created_at';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        return $this->db()->fetchAll(
            "SELECT * FROM `{$this->table}` ORDER BY `{$orderBy}` {$dir} LIMIT {$limit} OFFSET {$offset}"
        );
    }

    // ---- INSERT / UPDATE / DELETE ----

    public function insert(array $data): string
    {
        if (!isset($data[$this->primaryKey]) && $this->primaryKey === 'id') {
            $data[$this->primaryKey] = Util::uuid();
        }
        if (!isset($data['created_at']) && $this->hasColumn('created_at')) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at']) && $this->hasColumn('updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $this->db()->insert($this->table, $data);
        return $data[$this->primaryKey];
    }

    public function update(string $id, array $data): int
    {
        if (!isset($data['updated_at']) && $this->hasColumn('updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return $this->db()->update($this->table, $data, "`{$this->primaryKey}` = ?", [$id]);
    }

    /** Soft delete (default). Sets deleted_at + is_active=0. */
    public function delete(string $id): int
    {
        if ($this->supportsSoftDelete()) {
            return $this->softDelete($id);
        }
        return $this->forceDelete($id);
    }

    /** Soft delete — sets deleted_at + is_active=0. */
    public function softDelete(string $id): int
    {
        if (!$this->supportsSoftDelete()) {
            // Fall back to hard delete for tables without deleted_at
            return $this->forceDelete($id);
        }
        $data = [
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        // Set is_active = 0 if the column exists (most soft-deletable tables have it)
        if ($this->hasColumn('is_active')) {
            $data['is_active'] = 0;
        }
        return $this->db()->update($this->table, $data, "`{$this->primaryKey}` = ?", [$id]);
    }

    /** Restore a soft-deleted row. */
    public function restore(string $id): int
    {
        if (!$this->supportsSoftDelete()) return 0;
        $data = [
            'deleted_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->hasColumn('is_active')) {
            $data['is_active'] = 1;
        }
        return $this->db()->update($this->table, $data, "`{$this->primaryKey}` = ?", [$id]);
    }

    /** Hard (permanent) delete. Irreversible. Master-admin only. */
    public function forceDelete(string $id): int
    {
        return $this->db()->delete($this->table, "`{$this->primaryKey}` = ?", [$id]);
    }

    public function countWhere(string $where, array $params = []): int
    {
        return (int)$this->db()->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}`" . ($where !== '' ? " WHERE {$where}" : ''),
            $params
        );
    }

    // ---- TRASHED FILTER HELPER ----

    /**
     * Apply a trashed-mode filter to a WHERE clause.
     *
     * @param string $where Existing WHERE clause (without "WHERE" keyword). Will be appended to.
     * @param string $alias Optional table alias (e.g., "i" for items). Empty = use raw column names.
     * @param string $mode  One of: 'hide' (exclude deleted), 'with' (include all), 'only' (only deleted).
     * @return array Returns [updated_where, params] — params is always empty (no new binds needed).
     */
    public function applyTrashedFilter(string $where, string $alias, string $mode): array
    {
        if (!$this->supportsSoftDelete()) {
            return [$where, []];
        }
        $col = $alias ? "{$alias}.deleted_at" : 'deleted_at';
        $clause = match ($mode) {
            'with'  => '', // no filter — include trashed
            'only'  => "{$col} IS NOT NULL",
            default => "{$col} IS NULL", // 'hide' — default
        };
        if ($clause === '') {
            return [$where, []];
        }
        if ($where === '' || $where === '1=1') {
            return [$clause, []];
        }
        return ["{$where} AND {$clause}", []];
    }

    /** Parse the trashed query param from request. Default 'hide'. */
    public static function parseTrashedParam(?string $value): string
    {
        $value = strtolower((string)$value);
        return in_array($value, ['hide', 'with', 'only'], true) ? $value : 'hide';
    }

    // ---- SCHEMA INTROSPECTION ----

    /** Does this table have a deleted_at column? */
    public function supportsSoftDelete(): bool
    {
        if ($this->hasSoftDelete === null) {
            $this->hasSoftDelete = $this->hasColumn('deleted_at');
        }
        return $this->hasSoftDelete;
    }

    /** Cache of column names per table for hasColumn checks. */
    private static array $columnCache = [];

    protected function hasColumn(string $column): bool
    {
        if (!isset(self::$columnCache[$this->table])) {
            try {
                $cols = $this->db()->fetchAll("SHOW COLUMNS FROM `{$this->table}`");
                self::$columnCache[$this->table] = array_column($cols, 'Field');
            } catch (\Throwable) {
                self::$columnCache[$this->table] = [];
            }
        }
        return in_array($column, self::$columnCache[$this->table], true);
    }
}
