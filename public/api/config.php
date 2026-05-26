<?php
/**
 * Database Configuration and Helper Functions
 * This file handles database connection and provides utility functions.
 * 
 * CONNECTION SETTINGS:
 * - Host: 127.0.0.1 (change via DB_HOST env variable)
 * - Port: 3306 (change via DB_PORT env variable)
 * - Database: canteen_pos (change via DB_NAME env variable)
 * - Username: root (change via DB_USER env variable)
 * - Password: '' (change via DB_PASS env variable)
 */

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Singleton Database class - ensures only one connection exists
class Database {
    // Static instance for singleton pattern
    private static $instance = null;
    // PDO connection object
    private $pdo;

    // Private constructor - prevents direct instantiation
    private function __construct() {
        // Get database credentials from environment variables or use defaults
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'canteen_pos';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        try {
            // Create PDO connection with UTF-8 support
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Return associative arrays
                PDO::ATTR_EMULATE_PREPARES => false  // Use real prepared statements
            ]);
        } catch (PDOException $e) {
            // Log error and show message
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed: " . $e->getMessage());
        }
    }

    // Get singleton instance - creates instance if doesn't exist
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get the raw PDO connection for advanced operations
    public function getConnection() {
        return $this->pdo;
    }

    // Execute a prepared query and return the statement
    // Usage: db()->query("SELECT * FROM users WHERE id = ?", [$id]);
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Get the ID of the last inserted row
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

// Shortcut function to get database instance
// Use this instead of Database::getInstance() for cleaner code
function db() {
    return Database::getInstance();
}

/**
 * Sanitize user input to prevent XSS attacks
 * Usage: $name = sanitize($_POST['name']);
 * @param string $input - The input to sanitize
 * @return string - Sanitized output with special chars converted to HTML entities
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format a price number as Philippine Peso
 * Usage: formatPrice(150) returns "₱150.00"
 * @param float $price - The price to format
 * @return string - Formatted price with ₱ symbol
 */
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

/**
 * Generate a unique order number
 * Format: ORD-YYYYMMDD-XXXX (e.g., ORD-20240415-0001)
 * @return string - Generated order number
 */
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}