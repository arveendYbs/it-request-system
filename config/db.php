<?php
/**
 * Database Configuration
 * config/db.php
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'it_request_system';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
            exit();
        }
        
        return $this->conn;
    }
}

// Global database instance
$database = new Database();
$pdo = $database->getConnection();

// Helper function for secure queries
function executeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// Helper function for single row queries
function fetchOne($pdo, $query, $params = []) {
    $stmt = executeQuery($pdo, $query, $params);
    return $stmt ? $stmt->fetch() : false;
}

// Helper function for multiple row queries
function fetchAll($pdo, $query, $params = []) {
    $stmt = executeQuery($pdo, $query, $params);
    return $stmt ? $stmt->fetchAll() : [];
}
?>