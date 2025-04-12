<?php
class Database {
    private $conn;
    private $inTransaction = false;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function fetchOne($sql, $params = []) {
        try {
            // Ensure connection is alive
            $this->ensureConnection();
            
            $stmt = $this->prepareAndBind($sql, $params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        } catch (Exception $e) {
            error_log("Fetch One Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function fetchAll($sql, $params = []) {
        try {
            // Ensure connection is alive
            $this->ensureConnection();
            
            $stmt = $this->prepareAndBind($sql, $params);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            return $rows;
        } catch (Exception $e) {
            error_log("Fetch All Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ensure the database connection is active
     */
    private function ensureConnection() {
        // Check if connection is closed or invalid
        if (!$this->conn || $this->conn->connect_error) {
            $this->connect();
        }
        
        // Ping the server to check connection
        if (!$this->conn->ping()) {
            $this->connect();
        }
    }

    public function insertAndGetId($sql, $params = []) {
        try {
            // Ensure connection is alive
            $this->ensureConnection();
            
            $stmt = $this->prepareAndBind($sql, $params);
            $success = $stmt->execute();
            
            if (!$success) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            
            $id = $this->conn->insert_id;
            $stmt->close();
            return $id;
        } catch (Exception $e) {
            error_log("Insert Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function insert($sql, $params = []) {
        return $this->insertAndGetId($sql, $params);
    }

    public function execute($sql, $params = []) {
        try {
            // Ensure connection is alive
            $this->ensureConnection();
            
            $stmt = $this->prepareAndBind($sql, $params);
            $success = $stmt->execute();
            
            if (!$success) {
                throw new Exception("Query execution failed: " . $stmt->error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            return $affectedRows;
        } catch (Exception $e) {
            error_log("Execute Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function query($sql, $params = []) {
        try {
            // Ensure connection is alive
            $this->ensureConnection();
            
            $stmt = $this->prepareAndBind($sql, $params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Query Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getConnection() {
        $this->ensureConnection();
        return $this->conn;
    }

    private function prepareAndBind($sql, $params) {
        try {
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error . " SQL: " . $sql);
            }
            
            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } elseif (is_string($param)) {
                        $types .= 's';
                    } else {
                        $types .= 'b';
                    }
                }
                
                $stmt->bind_param($types, ...$params);
            }
            
            return $stmt;
        } catch (Exception $e) {
            error_log("Prepare and Bind Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function beginTransaction() {
        if (!$this->inTransaction) {
            $this->ensureConnection();
            $this->conn->begin_transaction();
            $this->inTransaction = true;
        }
    }

    public function commit() {
        if ($this->inTransaction) {
            $this->conn->commit();
            $this->inTransaction = false;
        }
    }

    public function rollback() {
        if ($this->inTransaction) {
            $this->conn->rollback();
            $this->inTransaction = false;
        }
    }

    public function inTransaction() {
        return $this->inTransaction;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    // Ensure connection is closed when object is destroyed
    public function __destruct() {
        $this->closeConnection();
    }

        
    public function isConnected() {
        return $this->connection !== null;
    }
}

/**
 * Logging function for system activities
 * @param int $user_id User performing the activity
 * @param string $activity_type Type of activity
 * @param string $description Activity description
 */
function logActivity($user_id, $activity_type, $description) {
    try {
        $db = new Database();
        $query = "INSERT INTO activity_logs (user_id, activity_type, description) VALUES (?, ?, ?)";
        $db->execute($query, [$user_id, $activity_type, $description]);
    } catch (Exception $e) {
        error_log("Activity Logging Error: " . $e->getMessage());
    }
}