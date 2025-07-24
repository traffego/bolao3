<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/EfiPixManager.php';

// Garantir que sempre retorne JSON
header('Content-Type: application/json');

try {
    // Verificar se o TXID foi fornecido
    $txid = $_GET['txid'] ?? null;
    if (!$txid) {
        throw new Exception('TXID não fornecido');
    }

    // Buscar transação
    $sql = "SELECT * FROM transacoes WHERE txid = ?";
    $transacao = dbFetchOne($sql, [$txid]);

    if (!$transacao) {
        throw new Exception('Transação não encontrada');
    }

    // Se já estiver aprovada, retornar sucesso
    if ($transacao['status'] === 'aprovado') {
        echo json_encode([
            'success' => true,
            'status' => 'aprovado',
            'message' => 'Pagamento já confirmado'
        ]);
        exit;
    }

    // Verificar status na Efí
    $efiPix = new EfiPixManager();
    $status = $efiPix->checkPayment($txid);

    echo json_encode([
        'success' => true,
        'status' => $status['status'],
        'message' => 'Status atualizado com sucesso'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 