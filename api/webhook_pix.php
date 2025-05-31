<?php
require_once '../config/config.php';
require_once '../includes/database.php';

// Receber payload do webhook
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Verificar se é um payload válido
if (!$data || !isset($data['txid'])) {
    http_response_code(400);
    die('Invalid payload');
}

// Log do webhook (para debug)
$logFile = '../logs/pix_webhook.log';
$logData = date('Y-m-d H:i:s') . ' - ' . $payload . "\n";
file_put_contents($logFile, $logData, FILE_APPEND);

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Buscar usuário pelo TXID
    $stmt = $pdo->prepare("SELECT id FROM jogador WHERE txid_pagamento = ?");
    $stmt->execute([$data['txid']]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        // Atualizar status do pagamento
        $stmt = $pdo->prepare("UPDATE jogador SET pagamento_confirmado = 1 WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        
        // Commit da transação
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception('User not found for TXID: ' . $data['txid']);
    }
} catch (Exception $e) {
    // Rollback em caso de erro
    $pdo->rollBack();
    
    // Log do erro
    $errorLog = '../logs/pix_errors.log';
    $errorData = date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n";
    file_put_contents($errorLog, $errorData, FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 