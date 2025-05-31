<?php
/**
 * Database Connection Handler
 * Creates and manages PDO database connection
 */

require_once __DIR__ . '/database_config.php';

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
            // Log error and throw exception
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
    // Se estiver em uma requisição AJAX, retornar erro em JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    // Se não for AJAX, relançar a exceção para ser tratada pelo manipulador de erros global
    throw $e;
} 