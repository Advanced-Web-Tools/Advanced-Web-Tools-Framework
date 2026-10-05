<?php

namespace database\provider;

require CONFIG . '/awt_db.php';

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
class DatabaseProvider implements IProvider
{
    private ?PDO $pdo = null;

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
     * Integers are bound with PDO::PARAM_INT; everything else with PARAM_STR.
     * @throws ProviderException
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->connection()->prepare($sql);

        foreach ($bindings as $placeholder => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
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

    /** {@inheritdoc} */
    public function lastInsertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }
}
