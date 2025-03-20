<?php
class Database {
    private $conn;
    private $inTransaction = false;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->prepareAndBind($sql, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function insert($sql, $params = []) {
        $stmt = $this->prepareAndBind($sql, $params);
        $success = $stmt->execute();
        
        if (!$success) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        
        $id = $this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    private function prepareAndBind($sql, $params) {
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
    }

    public function beginTransaction() {
        if (!$this->inTransaction) {
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
        $this->conn->close();
    }
}