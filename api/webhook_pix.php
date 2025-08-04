<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

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
    
    // Buscar transação pelo TXID
    $stmt = $pdo->prepare("
        SELECT t.*, c.jogador_id, p.id as palpite_id
        FROM transacoes t
        INNER JOIN contas c ON t.conta_id = c.id
        LEFT JOIN palpites p ON t.palpite_id = p.id
        WHERE t.txid = ?
    ");
    $stmt->execute([$data['txid']]);
    $transacao = $stmt->fetch();
    
    if (!$transacao) {
        throw new Exception('Transação não encontrada para TXID: ' . $data['txid']);
    }

    // Verificar se o pagamento já foi processado
    if ($transacao['status'] === 'aprovado') {
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    // Atualiza o status da transação para 'aprovado' e define afeta_saldo = 1
    // O campo afeta_saldo é obrigatório para transações aprovadas devido à constraint do banco
    $stmt = $pdo->prepare("
        UPDATE transacoes 
        SET status = 'aprovado',
            data_processamento = NOW(),
            afeta_saldo = 1
        WHERE txid = ?
    ");
    $stmt->execute([$data['txid']]);

    // Se houver palpite associado, atualizar seu status
    if ($transacao['palpite_id']) {
        $stmt = $pdo->prepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
        $stmt->execute([$transacao['palpite_id']]);
    }

    // Registrar log da operação
    $stmt = $pdo->prepare("
        INSERT INTO logs 
            (tipo, descricao, usuario_id, data_hora, ip_address, dados_adicionais) 
        VALUES 
            (?, ?, ?, NOW(), ?, ?)
    ");
    
    $logData = [
        'tipo' => 'pagamento',
        'descricao' => 'Pagamento PIX confirmado via webhook',
        'usuario_id' => $transacao['jogador_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'webhook',
        'dados' => json_encode([
            'txid' => $data['txid'],
            'valor' => $transacao['valor'],
            'afeta_saldo' => $transacao['afeta_saldo'],
            'palpite_id' => $transacao['palpite_id']
        ])
    ];
    
    $stmt->execute([
        $logData['tipo'],
        $logData['descricao'],
        $logData['usuario_id'],
        $logData['ip'],
        $logData['dados']
    ]);
    
    // Commit da transação
    $pdo->commit();
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);

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