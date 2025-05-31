<?php
/**
 * Database Connection and Helper Functions
 */

/**
 * Create a database connection
 * 
 * @return mysqli Database connection object
 */
function dbConnect() {
    static $conn;
    
    if ($conn === NULL) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die('Database Connection Error: ' . $conn->connect_error);
        }
        
        $conn->set_charset('utf8mb4');
    }
    
    return $conn;
}

/**
 * Execute a query and return the result
 * 
 * @param string $sql SQL query to execute
 * @param array $params Parameters to bind to the query
 * @param string $types Types of parameters (i: integer, d: double, s: string, b: blob)
 * @return mysqli_result|bool Result object or boolean
 */
function dbQuery($sql, $params = [], $types = '') {
    $conn = dbConnect();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die('Query Preparation Error: ' . $conn->error);
    }
    
    // If we have parameters to bind
    if (!empty($params)) {
        // Auto-generate types string if not provided
        if (empty($types)) {
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
        }
        
        // Bind parameters
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute the query
    if (!$stmt->execute()) {
        die('Query Execution Error: ' . $stmt->error);
    }
    
    // Return the result
    $result = $stmt->get_result();
    return ($result !== false) ? $result : true;
}

/**
 * Fetch all rows from a query
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @param string $types Types of parameters
 * @return array Array of rows
 */
function dbFetchAll($sql, $params = [], $types = '') {
    $result = dbQuery($sql, $params, $types);
    
    if ($result === true) {
        return [];
    }
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fetch a single row from a query
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @param string $types Types of parameters
 * @return array|null Row data or null if no row found
 */
function dbFetchOne($sql, $params = [], $types = '') {
    $result = dbQuery($sql, $params, $types);
    
    if ($result === true) {
        return null;
    }
    
    return $result->fetch_assoc();
}

/**
 * Insert a record and return the inserted ID
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @return int|bool Last inserted ID or false on failure
 */
function dbInsert($table, $data) {
    $columns = array_keys($data);
    $values = array_values($data);
    
    $placeholders = array_fill(0, count($values), '?');
    
    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $conn = dbConnect();
    $result = dbQuery($sql, $values);
    
    return ($result === true) ? $conn->insert_id : false;
}

/**
 * Update records in the database
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @param string $where WHERE clause
 * @param array $whereParams Parameters for WHERE clause
 * @return bool True on success, false on failure
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    $set = [];
    $values = [];
    
    foreach ($data as $column => $value) {
        $set[] = "$column = ?";
        $values[] = $value;
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
    
    // Combine values and where parameters
    $params = array_merge($values, $whereParams);
    
    $result = dbQuery($sql, $params);
    
    return ($result === true);
}

/**
 * Delete records from the database
 * 
 * @param string $table Table name
 * @param string $where WHERE clause
 * @param array $params Parameters for WHERE clause
 * @return bool True on success, false on failure
 */
function dbDelete($table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    
    $result = dbQuery($sql, $params);
    
    return ($result === true);
}

/**
 * Count records that match a condition
 * 
 * @param string $table Table name
 * @param string $where WHERE clause (optional)
 * @param array $params Parameters for WHERE clause (optional)
 * @return int Number of matching records
 */
function dbCount($table, $where = '1', $params = []) {
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $where";
    
    $result = dbFetchOne($sql, $params);
    
    return (int) $result['count'];
}

/**
 * Escape a string for safe use in SQL queries
 * 
 * @param string $value Value to escape
 * @return string Escaped value
 */
function dbEscape($value) {
    $conn = dbConnect();
    return $conn->real_escape_string($value);
}

/**
 * Begin a transaction
 * 
 * @return bool True on success, false on failure
 */
function dbBeginTransaction() {
    $conn = dbConnect();
    return $conn->begin_transaction();
}

/**
 * Commit a transaction
 * 
 * @return bool True on success, false on failure
 */
function dbCommit() {
    $conn = dbConnect();
    return $conn->commit();
}

/**
 * Rollback a transaction
 * 
 * @return bool True on success, false on failure
 */
function dbRollback() {
    $conn = dbConnect();
    return $conn->rollback();
} 