<?php
declare(strict_types=1);

/**
 * Database connection class for AstroTarot website
 * Handles MariaDB connection and provides PDO instance
 */
class Database
{
    private ?PDO $pdo = null;
    private string $host;
    private string $dbname;
    private string $username;
    private string $password;
    private int $port;

    public function __construct()
    {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = (int)(getenv('DB_PORT') ?: '3306');
        $this->dbname = getenv('DB_NAME') ?: 'astrotarot_db';
        $this->username = getenv('DB_USER') ?: 'astrotarot_user';
        $this->password = getenv('DB_PASSWORD') ?: '';

        $this->connect();
    }

    /**
     * Establish database connection
     * @throws Exception if connection fails
     */
    private function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Connection pooling
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_romanian_ci"
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO database connection
     * @return PDO
     */
    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Execute a prepared statement and return results
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return array Results
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Execute a prepared statement and return affected rows
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Execute failed: " . $e->getMessage());
        }
    }

    /**
     * Get last inserted ID
     * @return string Last inserted ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): void
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): void
    {
        $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): void
    {
        $this->getConnection()->rollBack();
    }

    /**
     * Check if table exists
     * @param string $tableName Table name
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->query($sql, [$tableName]);
        return !empty($result);
    }

    /**
     * Get database connection status
     * @return array Connection status information
     */
    public function getStatus(): array
    {
        try {
            $pdo = $this->getConnection();
            $version = $pdo->query("SELECT VERSION() as version")->fetch();
            $database = $pdo->query("SELECT DATABASE() as database")->fetch();
            
            return [
                'connected' => true,
                'version' => $version['version'] ?? 'Unknown',
                'database' => $database['database'] ?? 'Unknown',
                'host' => $this->host,
                'port' => $this->port
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
