<?php
/**
 * Database helper functions
 */

/**
 * Fetch a single row from the database
 * 
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array|false The row or false if not found
 */
function dbFetchOne($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error (dbFetchOne): " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch multiple rows from the database
 * 
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array Array of rows
 */
function dbFetchAll($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error (dbFetchAll): " . $e->getMessage());
        return [];
    }
}

/**
 * Insert a new record into the database
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|false The last insert ID or false on failure
 */
function dbInsert($table, $data) {
    global $pdo;
    try {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(array_values($data));
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Database Error (dbInsert): " . $e->getMessage());
        return false;
    }
}

/**
 * Update records in the database
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $where WHERE clause with placeholders
 * @param array $params Parameters for WHERE clause
 * @return bool True on success, false on failure
 */
function dbUpdate($table, $data, $where, $params = []) {
    global $pdo;
    try {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $query = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(array_merge(array_values($data), $params));
        
        return true;
    } catch (PDOException $e) {
        error_log("Database Error (dbUpdate): " . $e->getMessage());
        return false;
    }
}

/**
 * Delete records from the database
 * 
 * @param string $table Table name
 * @param string $where WHERE clause with placeholders
 * @param array $params Parameters for WHERE clause
 * @return bool True on success, false on failure
 */
function dbDelete($table, $where, $params = []) {
    global $pdo;
    try {
        $query = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return true;
    } catch (PDOException $e) {
        error_log("Database Error (dbDelete): " . $e->getMessage());
        return false;
    }
}

/**
 * Begin a transaction
 * 
 * @return bool True on success, false on failure
 */
function dbBeginTransaction() {
    global $pdo;
    return $pdo->beginTransaction();
}

/**
 * Commit a transaction
 * 
 * @return bool True on success, false on failure
 */
function dbCommit() {
    global $pdo;
    return $pdo->commit();
}

/**
 * Rollback a transaction
 * 
 * @return bool True on success, false on failure
 */
function dbRollback() {
    global $pdo;
    return $pdo->rollBack();
} 