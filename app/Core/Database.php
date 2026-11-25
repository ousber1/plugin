<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Accès base de données (PDO, requêtes préparées exclusivement).
 * Les noms de tables/colonnes passés aux helpers insert()/update() sont
 * validés par liste blanche de caractères pour éviter toute injection.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $transactionLevel = 0;

    private function __construct(array $cfg)
    {
        $this->pdo = self::connect($cfg);
    }

    /**
     * Crée une connexion PDO sécurisée à partir d'un tableau de configuration.
     * Utilisée aussi par l'installateur.
     */
    public static function connect(array $cfg, bool $withDatabase = true): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4%s',
            $cfg['host'] ?? 'localhost',
            (int) ($cfg['port'] ?? 3306),
            $withDatabase && !empty($cfg['name']) ? ';dbname=' . $cfg['name'] : ''
        );
        $pdo = new PDO($dsn, (string) ($cfg['user'] ?? ''), (string) ($cfg['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . self::tzOffset() . "', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
        ]);
        return $pdo;
    }

    /** Décalage horaire courant de PHP (ex. +01:00) pour aligner MySQL. */
    private static function tzOffset(): string
    {
        return (new \DateTimeImmutable('now'))->format('P');
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database((array) Config::get('db', []));
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function insert(string $table, array $data): int
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        array_walk($columns, [self::class, 'assertIdentifier']);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_map(static fn($c) => ':' . $c, $columns))
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param string $where clause avec paramètres nommés, ex. "id = :id"
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdentifier($table);
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            self::assertIdentifier($column);
            $sets[] = "`$column` = :set_$column";
            $params["set_$column"] = $value;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->query($sql, array_merge($params, $whereParams))->rowCount();
    }

    /**
     * Exécute un bloc dans une transaction (imbrication gérée par compteur).
     */
    public function transaction(callable $callback): mixed
    {
        $this->begin();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function begin(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        }
        $this->transactionLevel++;
    }

    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            return;
        }
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel > 0 && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->transactionLevel = 0;
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $name)) {
            throw new \InvalidArgumentException('Identifiant SQL invalide : ' . $name);
        }
    }
}
