<?php
/**
 * Database — PDO singleton wrapper.
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        $db = $config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'] ?? 3306,
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );

        try {
            $this->pdo = new PDO($dsn, $db['user'], $db['pass'], $db['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('Database not initialized: no config provided');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /** Run a parameterized query and return a PDOStatement. */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch one row (assoc array) or null. */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows. */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Fetch a single column value (first row, first column) or null. */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $val = $this->run($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    /** INSERT and return the new auto-increment ID (or the supplied UUID). */
    public function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $cols),
            implode(', ', $placeholders)
        );
        $this->run($sql, $data);
        return (string)$this->pdo->lastInsertId();
    }

    /** UPDATE with WHERE conditions. Returns affected row count. */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`{$col}` = :{$col}";
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        return $this->run($sql, array_merge($data, $whereParams))->rowCount();
    }

    /** DELETE with WHERE. Returns affected row count. */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
