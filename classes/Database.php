<?php

/**
 * Database Connection Class
 * 
 * Singleton pattern database connection with connection pooling support
 * Handles MySQL connections and provides error handling
 */

class Database
{
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct()
    {
        $this->loadConfig();
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load database configuration
     */
    private function loadConfig()
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__));
        }

        $configFile = APP_ROOT . '/config/database.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            $this->config = $config['database']['connections']['mysql'];
        } else {
            // Default configuration
            $this->config = [
                'host'     => $_ENV['DB_HOST'] ?? 'localhost',
                'port'     => $_ENV['DB_PORT'] ?? '3306',
                'database' => $_ENV['DB_NAME'] ?? 'whatsapp_monitor',
                'username' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASS'] ?? '',
                'charset'  => 'utf8mb4',
                'options'  => [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            ];
        }
    }

    /**
     * Create database connection
     */
    private function connect()
    {
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";

            $options = $this->config['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    /**
     * Get database connection
     */
    public function getConnection()
    {
        // Check if connection is still alive
        if ($this->connection === null) {
            $this->connect();
        }

        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            // Reconnect if connection is lost
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Handle connection errors
     */
    private function handleConnectionError(PDOException $e)
    {
        $errorMessage = "Database connection failed: " . $e->getMessage();

        // Log error
        error_log($errorMessage);

        // In development, show detailed error
        if (($_ENV['APP_DEBUG'] ?? false) === 'true') {
            die("
                <h1>Database Connection Error</h1>
                <p><strong>Error:</strong> {$e->getMessage()}</p>
                <p><strong>Host:</strong> {$this->config['host']}</p>
                <p><strong>Database:</strong> {$this->config['database']}</p>
                <p><strong>Username:</strong> {$this->config['username']}</p>
                <hr>
                <p>Please check your database configuration in <code>.env</code> file.</p>
            ");
        } else {
            // In production, show generic error
            die("
                <h1>Service Temporarily Unavailable</h1>
                <p>Database connection failed. Please contact administrator.</p>
            ");
        }
    }

    /**
     * Test database connection
     */
    public function testConnection()
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query('SELECT 1 as test');
            $result = $stmt->fetch();
            return $result['test'] === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get database status information
     */
    public function getStatus()
    {
        try {
            $conn = $this->getConnection();

            // Get MySQL version
            $stmt = $conn->query('SELECT VERSION() as version');
            $version = $stmt->fetch()['version'];

            // Get connection info
            $stmt = $conn->query('SELECT CONNECTION_ID() as connection_id');
            $connectionId = $stmt->fetch()['connection_id'];

            // Get database name
            $stmt = $conn->query('SELECT DATABASE() as database_name');
            $database = $stmt->fetch()['database_name'];

            return [
                'connected' => true,
                'version' => $version,
                'connection_id' => $connectionId,
                'database' => $database,
                'host' => $this->config['host'],
                'port' => $this->config['port']
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a prepared statement safely
     */
    public function execute($sql, $params = [])
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->getConnection()->rollback();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId()
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
