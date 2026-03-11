<?php

namespace database\provider;

require ROOT . '/awt_db.php';

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
class DatabaseProvider implements IProvider
{
    private PDO $pdo;

    public function __construct()
    {
        global $shared;

        if (!isset($shared['DBEngine']['PDO'])) {
            $dsn = DB_TYPE . ':host=' . DB_HOSTNAME . ';dbname=' . DB_NAME;

            try {
                $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE,    PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_PERSISTENT, true);

                $shared['DBEngine']['PDO'] = $pdo;
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
            }
        }

        $this->pdo = $shared['DBEngine']['PDO'];
    }

    /**
     * {@inheritdoc}
     *
     * Integers are bound with PDO::PARAM_INT; everything else with PARAM_STR.
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        foreach ($bindings as $placeholder => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($placeholder, $value, $type);
        }

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            $stmt->closeCursor();

            if (defined('DEBUG') && DEBUG) {
                throw new RuntimeException(
                    "Query failed: {$e->getMessage()}\nSQL: {$sql}",
                    0,
                    $e
                );
            }

            throw $e;
        }

        return $stmt;
    }

    /** {@inheritdoc} */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
