<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Verifica se está logado
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

$jogadorId = getCurrentUserId();

try {
    // Busca a conta do jogador
    $sql = "SELECT id FROM contas WHERE jogador_id = ?";
    $conta = dbFetchOne($sql, [$jogadorId]);
    
    if (!$conta) {
        throw new Exception('Conta não encontrada');
    }

    // Exclui as transações pendentes
    $sql = "DELETE FROM transacoes 
            WHERE conta_id = ? 
            AND status = 'pendente' 
            AND tipo = 'deposito'";
            
    $result = dbExecute($sql, [$conta['id']]);

    echo json_encode(['success' => true, 'message' => 'Transações pendentes excluídas com sucesso']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}