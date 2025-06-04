<?php
/**
 * Database Configuration and Functions
 * Contains all database settings, connection handling, and helper functions
 */

// Determine environment and set database credentials
$isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || 
               strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0;

// Database Configuration based on environment
if ($isLocalhost) {
    // Local environment settings
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'bolao_football');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', ''); // Empty password for default XAMPP
} else {
    // Production environment settings
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'platafo5_bolao3');
    if (!defined('DB_USER')) define('DB_USER', 'platafo5_bolao3');
    if (!defined('DB_PASS')) define('DB_PASS', 'Traffego444#');
}

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection
 * @throws Exception if connection fails
 */
function getPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde.");
        }
    }
    
    return $pdo;
}

// Create global PDO instance
try {
    $pdo = getPDO();
} catch (Exception $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    throw $e;
}

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
 * Count records in a table
 * 
 * @param string $table Table name
 * @param string $where Optional WHERE clause with placeholders
 * @param array $params Optional parameters for WHERE clause
 * @return int Number of records
 */
function dbCount($table, $where = '1', $params = []) {
    global $pdo;
    try {
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (PDOException $e) {
        error_log("Database Error (dbCount): " . $e->getMessage());
        return 0;
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