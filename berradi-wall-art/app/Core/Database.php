<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * PDO wrapper. Every query goes through prepared statements.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $transactionLevel = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array{host:string,port?:int|string,database:string,username:string,password:string,charset?:string} $cfg
     */
    public static function connect(array $cfg): PDO
    {
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['database'],
            $charset
        );
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $tz = (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->format('P');
        $pdo->exec("SET time_zone = '{$tz}'");
        return $pdo;
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            $cfg = Config::get('database');
            self::$instance = new Database(self::connect($cfg));
        }
        return self::$instance;
    }

    public static function setInstance(?Database $db): void
    {
        self::$instance = $db;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<int|string,mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @param array<int|string,mixed> $params @return array<string,mixed>|null */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string,mixed> $params @return list<array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<int|string,mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $table = self::ident($table);
        $cols = array_map([self::class, 'ident'], array_keys($data));
        $placeholders = array_map(fn ($c) => ':' . $c, array_keys($data));
        $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where  column => value (AND)
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            return 0;
        }
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = self::ident($col) . ' = :s_' . $col;
            $params['s_' . $col] = $val;
        }
        $cond = [];
        foreach ($where as $col => $val) {
            $cond[] = self::ident($col) . ' = :w_' . $col;
            $params['w_' . $col] = $val;
        }
        $sql = 'UPDATE ' . self::ident($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $cond);
        return $this->query($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            return 0;
        }
        $cond = [];
        foreach ($where as $col => $val) {
            $cond[] = self::ident($col) . ' = :' . $col;
        }
        return $this->query('DELETE FROM ' . self::ident($table) . ' WHERE ' . implode(' AND ', $cond), $where)->rowCount();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Run a callback inside a transaction (supports nesting with savepoints).
     *
     * @template T
     * @param callable(Database):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->begin();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function begin(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT sp' . $this->transactionLevel);
        }
        $this->transactionLevel++;
    }

    public function commit(): void
    {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT sp' . $this->transactionLevel);
        }
    }

    public function rollBack(): void
    {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT sp' . $this->transactionLevel);
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * Quote an identifier (table / column). Only [A-Za-z0-9_] and optional "table.column" allowed.
     */
    public static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $name);
        }
        return implode('.', array_map(fn ($p) => '`' . $p . '`', explode('.', $name)));
    }
}
