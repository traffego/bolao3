<?php
// Previne que erros PHP sejam exibidos diretamente
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Garante que a resposta será sempre JSON
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/EfiPixManager.php';

try {
    // Verifica se está logado
    if (!isLoggedIn()) {
        throw new Exception('Usuário não autenticado', 401);
    }

    // Obtém ID da transação
    $transacaoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$transacaoId) {
        throw new Exception('ID da transação inválido', 400);
    }

    // Busca transação
    $sql = "
        SELECT t.*, c.jogador_id
        FROM transacoes t
        INNER JOIN contas c ON t.conta_id = c.id
        WHERE t.id = ? AND t.tipo = 'deposito'
    ";
    $transacao = dbFetchOne($sql, [$transacaoId]);
    
    // Verifica se transação existe e pertence ao usuário logado
    if (!$transacao || $transacao['jogador_id'] != getCurrentUserId()) {
        throw new Exception('Transação não encontrada', 404);
    }

    // Se solicitada verificação manual e transação ainda não está aprovada
    $forceCheck = filter_input(INPUT_GET, 'force_check', FILTER_VALIDATE_BOOLEAN);
    if ($forceCheck && $transacao['status'] !== 'aprovado') {
        try {
            // Instancia EfiPixManager
            $efiPix = new EfiPixManager();
            
            // Consulta status da cobrança
            $statusPix = $efiPix->checkPayment($transacao['txid']);
            
            // Se pagamento foi confirmado
            if ($statusPix['status'] === 'aprovado') {
                $transacao['status'] = 'aprovado';
            }
        } catch (Exception $e) {
            error_log("Erro ao verificar status no EfiPix: " . $e->getMessage());
            // Não propaga o erro, apenas continua e retorna o status atual
        }
    }
    
    // Retorna status atual
    echo json_encode([
        'status' => $transacao['status'],
        'data_atualizacao' => $transacao['data_processamento']
    ]);
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $code
    ]);
} catch (Error $e) {
    error_log("Erro crítico em status_deposito.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'code' => 500
    ]);
} 