<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/classes/Logger.php';

/**
 * Process PIX webhook payload
 * 
 * @param array $data Decoded JSON payload
 * @param Logger $logger Logger instance
 * @param PDO $pdo Database connection
 * @return array Processing result
 */
function processPixWebhook($data, $logger, $pdo) {
    // Log do payload completo recebido
    $logger->info('Payload webhook recebido', [
        'payload_decoded' => $data,
        'payload_size' => strlen(json_encode($data))
    ]);

    // Verificar se é um payload válido e extrair txid com múltiplos fallbacks
    $txid = null;
    $pixData = null;

    // Formato 1: Oficial da EFI Pay - { "pix": [{ "txid": "...", "endToEndId": "...", ... }] }
    if ($data && isset($data['pix']) && is_array($data['pix']) && count($data['pix']) > 0) {
        $pixData = $data['pix'][0]; // Primeiro PIX do array
        
        // Buscar txid no formato oficial da EFI Pay
        if (isset($pixData['txid'])) {
            $txid = $pixData['txid'];
            $logger->info('TXID encontrado no campo txid', ['txid' => $txid]);
        } elseif (isset($pixData['endToEndId'])) {
            $txid = $pixData['endToEndId'];
            $logger->info('TXID encontrado no campo endToEndId', ['txid' => $txid]);
        }
    }

    // Formato 2: Fallback - txid diretamente no root
    if (!$txid && $data && isset($data['txid'])) {
        $txid = $data['txid'];
        $logger->info('TXID encontrado no root do payload', ['txid' => $txid]);
    }

    // Formato 3: Fallback - endToEndId diretamente no root
    if (!$txid && $data && isset($data['endToEndId'])) {
        $txid = $data['endToEndId'];
        $logger->info('TXID encontrado como endToEndId no root', ['txid' => $txid]);
    }

    // Normalizar TXID (remover espaços e converter para maiúscula)
    if ($txid) {
        $txid = strtoupper(trim($txid));
        $logger->info('TXID normalizado', ['txid_normalized' => $txid]);
    }

    if (!$txid) {
        $logger->error('TXID não encontrado no payload', [
            'payload_structure' => array_keys($data ?: []),
            'pix_data_structure' => $pixData ? array_keys($pixData) : null
        ]);
        throw new Exception('Invalid payload - txid not found');
    }

    // Log adicional do webhook para arquivo (mantendo compatibilidade)
    $logFile = '../logs/pix_webhook.log';
    $logData = date('Y-m-d H:i:s') . ' - TXID: ' . $txid . ' - ' . json_encode($data) . "\n";
    file_put_contents($logFile, $logData, FILE_APPEND);

    try {
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Buscar transação pelo TXID (com fallback para case-insensitive)
        $stmt = $pdo->prepare("
            SELECT t.*, c.jogador_id, p.id as palpite_id
            FROM transacoes t
            INNER JOIN contas c ON t.conta_id = c.id
            LEFT JOIN palpites p ON t.palpite_id = p.id
            WHERE UPPER(t.txid) = UPPER(?)
        ");
        $stmt->execute([$txid]);
        $transacao = $stmt->fetch();
        
        $logger->info('Busca por transação', [
            'txid' => $txid,
            'transacao_encontrada' => $transacao ? 'sim' : 'não',
            'transacao_id' => $transacao ? $transacao['id'] : null
        ]);
        
        if (!$transacao) {
            $logger->error('Transação não encontrada', [
                'txid' => $txid,
                'payload' => $data
            ]);
            throw new Exception('Transação não encontrada para TXID: ' . $txid);
        }

        // Verificar se o pagamento já foi processado
        if ($transacao['status'] === 'aprovado') {
            $logger->info('Pagamento já processado', [
                'txid' => $txid,
                'transacao_id' => $transacao['id'],
                'status_atual' => $transacao['status']
            ]);
            
            return [
                'status' => 'already_processed',
                'txid' => $txid,
                'http_code' => 200
            ];
        }

        // Atualiza o status da transação para 'aprovado' e define afeta_saldo = 1
        // O campo afeta_saldo é obrigatório para transações aprovadas devido à constraint do banco
        $stmt = $pdo->prepare("
            UPDATE transacoes 
            SET status = 'aprovado',
                data_processamento = NOW(),
                afeta_saldo = 1
            WHERE UPPER(txid) = UPPER(?)
        ");
        $stmt->execute([$txid]);
        
        $logger->info('Transação atualizada para aprovado', [
            'txid' => $txid,
            'transacao_id' => $transacao['id'],
            'valor' => $transacao['valor'],
            'jogador_id' => $transacao['jogador_id']
        ]);

        // Se houver palpite associado, atualizar seu status
        if ($transacao['palpite_id']) {
            $stmt = $pdo->prepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
            $stmt->execute([$transacao['palpite_id']]);
            
            $logger->info('Palpite atualizado para pago', [
                'palpite_id' => $transacao['palpite_id'],
                'txid' => $txid
            ]);
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
                'txid' => $txid,
                'valor' => $transacao['valor'],
                'afeta_saldo' => 1,
                'palpite_id' => $transacao['palpite_id'],
                'payload_original' => $data
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
        
        $logger->info('Webhook processado com sucesso', [
            'txid' => $txid,
            'transacao_id' => $transacao['id'],
            'valor' => $transacao['valor']
        ]);
        
        return [
            'status' => 'success',
            'txid' => $txid,
            'transacao_id' => $transacao['id'],
            'http_code' => 200
        ];

    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log detalhado do erro
        $logger->error('Erro no processamento do webhook', [
            'txid' => $txid ?? 'não_identificado',
            'erro' => $e->getMessage(),
            'linha' => $e->getLine(),
            'arquivo' => $e->getFile(),
            'payload' => $data,
            'trace' => $e->getTraceAsString()
        ]);
        
        // Log do erro para arquivo (mantendo compatibilidade)
        $errorLog = '../logs/pix_errors.log';
        $errorData = date('Y-m-d H:i:s') . ' - TXID: ' . ($txid ?? 'N/A') . ' - ' . $e->getMessage() . " - Line: " . $e->getLine() . "\n";
        file_put_contents($errorLog, $errorData, FILE_APPEND);
        
        throw $e; // Re-throw para permitir captura no contexto principal
    }
}

// Se este arquivo for acessado diretamente (não via include), processa o webhook
if (!isset($GLOBALS['is_webhook_test'])) {
    // Inicializar logger
    $logger = Logger::getInstance();

    // Log inicial do webhook
    $logger->info('Webhook PIX iniciado', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ]);

    // Receber payload do webhook
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    try {
        $result = processPixWebhook($data, $logger, $pdo);
        
        http_response_code($result['http_code']);
        echo json_encode([
            'status' => $result['status'],
            'txid' => $result['txid'],
            'transacao_id' => $result['transacao_id'] ?? null
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'txid' => $txid ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
?>