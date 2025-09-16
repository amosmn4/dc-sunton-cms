<?php
/**
 * Database Configuration Class
 * Deliverance Church Management System
 * 
 * Handles database connections using PDO with error handling
 */
class Database {
    // Database configuration
    private $host = 'localhost';
    private $db_name = 'church_cms';
    private $username = 'root'; // Change to your DB username
    private $password = '';     // Change to your DB password
    private $charset = 'utf8mb4';
    
    // Singleton instance & PDO connection
    private static $instance = null;
    private $conn;

    /**
     * Constructor - private to enforce singleton pattern
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * Singleton access point
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Log successful connection
            error_log("✅ Database connected successfully at " . date('Y-m-d H:i:s'));
        } catch (PDOException $exception) {
            error_log("❌ Database connection error: " . $exception->getMessage());

            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die("Database connection failed: " . $exception->getMessage());
            } else {
                die("Database connection failed. Please contact the system administrator.");
            }
        }
    }

    /**
     * Get PDO connection
     * @return PDO
     */
    public function getConnection() {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    /**
     * Execute a prepared statement with parameters
     * @param string $query
     * @param array $params
     * @return PDOStatement
     */
    public function executeQuery($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("❌ Query execution error: " . $e->getMessage() . " | Query: " . $query);
            throw $e;
        }
    }

    /**
     * Get database statistics (size, tables)
     * @return array
     */
    public function getDatabaseStats() {
        try {
            $stats = [];

            // Get DB size
            $stmt = $this->conn->prepare("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ");
            $stmt->execute([$this->db_name]);
            $stats['size_mb'] = $stmt->fetchColumn();

            // Get table count
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS table_count
                FROM information_schema.tables 
                WHERE table_schema = ?
            ");
            $stmt->execute([$this->db_name]);
            $stats['table_count'] = $stmt->fetchColumn();

            return $stats;
        } catch (PDOException $e) {
            error_log("❌ Error getting database stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Transaction management
     */
    public function beginTransaction() { return $this->conn->beginTransaction(); }
    public function commit() { return $this->conn->commit(); }
    public function rollback() { return $this->conn->rollBack(); }

    /**
     * Get last insert ID
     */
    public function getLastInsertId() {
        return $this->conn->lastInsertId();
    }

    /**
     * Close connection
     */
    public function close() {
        $this->conn = null;
    }

    /**
     * Prevent cloning and unserialization
     */
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $stmt = $this->conn->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

/**
 * Helper function (backward compatibility)
 * @return PDO
 */
function getDbConnection() {
    return Database::getInstance()->getConnection();
}
