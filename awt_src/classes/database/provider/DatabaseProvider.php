<?php

namespace database\provider;

require_once CONFIG . '/awt_db.php';

use database\exceptions\ProviderException;
use database\interface\IProvider;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * DatabaseProvider
 *
 * Manages the PDO connection and is the only class allowed to talk to the
 * database directly.  It reuses a single PDO instance stored in the global
 * $shared registry (the same strategy as the original DatabaseManager) so
 * that you never open more connections than necessary across a request.
 *
 * Throws RuntimeException on connection failure instead of calling die(),
 * allowing calling code to handle the error gracefully.
 */
class DatabaseProvider implements \database\interface\ITransactionalProvider
{
    private ?PDO $pdo = null;
    /** Commit callbacks are shared by providers using the same PDO connection. */
    private static ?\WeakMap $transactionFrames = null;

    /**
     * @throws ProviderException
     */
    public function __construct() {}

    private function connection(): PDO
    {
        if ($this->pdo !== null) return $this->pdo;
        global $shared;
        if (!isset($shared['DBEngine']['PDO'])) {
            $dsn = DB_TYPE . ':host=' . DB_HOSTNAME . ';dbname=' . DB_NAME;
            try {
                $shared['DBEngine']['PDO'] = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => true,
                ]);
            } catch (PDOException $e) {
                throw new ProviderException("Default", $e);
            }
        }
        return $this->pdo = $shared['DBEngine']['PDO'];
    }

    /**
     * {@inheritdoc}
     *
     * Nulls, booleans and integers use their PDO types; other scalars use PARAM_STR.
     * @throws ProviderException
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->connection()->prepare($sql);

        foreach ($bindings as $placeholder => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($placeholder, $value, $type);
        }

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            $stmt->closeCursor();

            throw new ProviderException("Default", $e);
        }

        return $stmt;
    }

    public function inTransaction(): bool { return $this->connection()->inTransaction(); }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        self::$transactionFrames ??= new \WeakMap();
        $frames = self::$transactionFrames[$pdo] ?? [];
        $depth = count($frames);
        if ($depth === 0 && $pdo->inTransaction()) {
            throw new \LogicException('A transaction is already active outside the provider.');
        }
        $savepoint = 'awt_transaction_' . $depth;
        if ($depth === 0) $pdo->beginTransaction();
        else $pdo->exec('SAVEPOINT ' . $savepoint);
        $frames[] = [];
        self::$transactionFrames[$pdo] = $frames;
        try {
            $result = $callback();
            if ($depth === 0) $pdo->commit();
            else $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                if ($depth === 0) $pdo->rollBack();
                else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }
            $frames = self::$transactionFrames[$pdo];
            array_pop($frames);
            self::$transactionFrames[$pdo] = $frames;
            throw $e;
        }
        $frames = self::$transactionFrames[$pdo];
        $callbacks = array_pop($frames);
        if ($depth > 0) $frames[$depth - 1] = [...$frames[$depth - 1], ...$callbacks];
        self::$transactionFrames[$pdo] = $frames;
        // Callback failures happen after commit and must never trigger rollback.
        $failure = null;
        if ($depth === 0) foreach ($callbacks as $afterCommit) {
            try { $afterCommit(); } catch (\Throwable $e) { $failure ??= $e; }
        }
        if ($failure !== null) throw $failure;
        return $result;
    }

    public function afterCommit(callable $callback): void
    {
        $pdo = $this->connection();
        $frames = self::$transactionFrames[$pdo] ?? [];
        if ($frames === []) { $callback(); return; }
        $frames[count($frames) - 1][] = $callback;
        self::$transactionFrames[$pdo] = $frames;
    }

    /** {@inheritdoc} */
    public function lastInsertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }
}
