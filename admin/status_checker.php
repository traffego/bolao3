<?php
/**
 * Status Checker for Admin Panel
 * Retorna status do banco de dados e API Football
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Se chamado via AJAX, retorna JSON
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    $response = [
        'database' => checkDatabaseStatus(),
        'api_football' => checkApiFootballStatus()
    ];
    
    echo json_encode($response);
    exit;
}
?> 