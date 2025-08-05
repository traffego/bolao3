<?php
// Previne que erros PHP sejam exibidos diretamente
ini_set('display_errors', 0);
error_reporting(0);

// Limpa qualquer output anterior
ob_clean();

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

    // Verificar se deve consultar a EFI Pay
    $forceCheck = filter_input(INPUT_GET, 'force_check', FILTER_VALIDATE_BOOLEAN);
    $shouldCheckEfi = $forceCheck || ($transacao['status'] === 'pendente');
    
    if ($shouldCheckEfi && $transacao['status'] !== 'aprovado') {
        try {
            error_log("DEBUG: Consultando EFI Pay para transação " . $transacao['id'] . " (force_check: " . ($forceCheck ? 'sim' : 'não') . ")");
            
            // Instancia EfiPixManager
            $efiPix = new EfiPixManager(defined('EFI_WEBHOOK_FATAL_FAILURE') ? EFI_WEBHOOK_FATAL_FAILURE : false);
            
            // Consulta status da cobrança
            $statusPix = $efiPix->checkPayment($transacao['txid']);
            error_log("DEBUG: Status retornado da EFI Pay: " . json_encode($statusPix));
            
            // Se pagamento foi confirmado, atualiza o status no banco de dados
            if ($statusPix['status'] === 'aprovado') {
                $updateSql = "UPDATE transacoes SET status = 'aprovado', data_processamento = NOW(), afeta_saldo = 1 WHERE id = ?";
                dbExecute($updateSql, [$transacaoId]);
                $transacao['status'] = 'aprovado'; // Atualiza a variável local para retorno imediato
            }
        } catch (Exception $e) {
            error_log("Erro ao verificar status no EfiPix: " . $e->getMessage());
            // Para verificação automática, não loga no console para evitar spam
            if ($forceCheck) {
                error_log("Erro na verificação manual: " . $e->getMessage());
            }
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