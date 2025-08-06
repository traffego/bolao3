<?php
/**
 * Ações de transações - Aprovar/Rejeitar
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['error'] = 'Acesso negado.';
    redirect(APP_URL . '/admin/login.php');
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Método de requisição inválido.';
    redirect(APP_URL . '/admin/pagamentos.php');
}

// Get parameters
$action = $_POST['action'] ?? '';
$transacaoId = $_POST['transacao_id'] ?? '';
$motivo = $_POST['motivo'] ?? '';

// Validate parameters
if (empty($action) || empty($transacaoId)) {
    $_SESSION['error'] = 'Parâmetros inválidos.';
    redirect(APP_URL . '/admin/pagamentos.php');
}

if (!in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Ação inválida.';
    redirect(APP_URL . '/admin/pagamentos.php');
}

// Debug log
error_log("DEBUG TRANSACAO: Action = $action, TransacaoId = $transacaoId");

try {
    // Get transaction details
    $transacao = dbFetchOne(
        "SELECT t.*, c.jogador_id, j.nome as jogador_nome, j.email as jogador_email
         FROM transacoes t
         JOIN contas c ON t.conta_id = c.id
         JOIN jogador j ON c.jogador_id = j.id
         WHERE t.id = ? AND t.tipo IN ('deposito', 'saque')",
        [$transacaoId]
    );
    
    error_log("DEBUG TRANSACAO: Transacao encontrada = " . ($transacao ? 'SIM' : 'NAO'));

    if (!$transacao) {
        $_SESSION['error'] = 'Transação não encontrada.';
        redirect(APP_URL . '/admin/pagamentos.php');
    }

    if ($transacao['status'] !== 'pendente') {
        $_SESSION['error'] = 'Esta transação já foi processada.';
        redirect(APP_URL . '/admin/pagamentos.php');
    }

    // Start database transaction
    global $pdo;
    $pdo->beginTransaction();

    if ($action === 'approve') {
        // Aprovar transação
        $novoStatus = 'aprovado';
        $mensagem = 'Transação aprovada com sucesso!';
        
        // Get current admin user ID
        $adminId = getCurrentAdminId();
        if (!$adminId) {
            throw new Exception('Admin ID não encontrado na sessão');
        }
        error_log("DEBUG TRANSACAO: AdminId = $adminId, NovoStatus = $novoStatus");
        
        // Update transaction status
        $updateSql = "UPDATE transacoes SET 
                     status = ?, 
                     data_processamento = NOW(), 
                     processado_por = ?
                     WHERE id = ?";
        
        error_log("DEBUG TRANSACAO: SQL = $updateSql");
        error_log("DEBUG TRANSACAO: Params = " . json_encode([$novoStatus, $adminId, $transacaoId]));
        
        $result = dbExecute($updateSql, [$novoStatus, $adminId, $transacaoId]);
        
        error_log("DEBUG TRANSACAO: Update result = " . ($result ? 'SUCCESS' : 'FAILED'));
        
        // Verificar se a transação foi realmente atualizada
        $verificacao = dbFetchOne(
            "SELECT status FROM transacoes WHERE id = ?",
            [$transacaoId]
        );
        error_log("DEBUG TRANSACAO: Status após update = " . ($verificacao['status'] ?? 'NULL'));

        // If it's a deposit or withdrawal that affects balance, update it
        if ($transacao['tipo'] === 'deposito' || $transacao['tipo'] === 'saque') {
            // Calculate new balance
            $saldoAtual = dbFetchOne(
                "SELECT COALESCE(SUM(CASE 
                    WHEN tipo IN ('deposito', 'premio', 'bonus') THEN valor 
                    WHEN tipo IN ('saque', 'aposta') THEN -valor 
                END), 0) as saldo_atual
                FROM transacoes 
                WHERE conta_id = ? AND status = 'aprovado' AND afeta_saldo = TRUE",
                [$transacao['conta_id']]
            );
            
            $saldoAnterior = $saldoAtual ? floatval($saldoAtual['saldo_atual']) : 0;
            
            if ($transacao['tipo'] === 'deposito') {
                $novoSaldo = $saldoAnterior + $transacao['valor'];
            } else { // saque
                $novoSaldo = $saldoAnterior - $transacao['valor'];
            }
            
            // Update transaction with balance info
            dbExecute(
                "UPDATE transacoes SET 
                 saldo_anterior = ?, 
                 saldo_posterior = ?,
                 afeta_saldo = TRUE
                 WHERE id = ?",
                [$saldoAnterior, $novoSaldo, $transacaoId]
            );
        }
        
        // Create notification for user
        if (class_exists('NotificacaoManager')) {
            $notificacao = new NotificacaoManager();
            $titulo = 'Transação Aprovada';
            $mensagemNotif = sprintf(
                'Sua %s de %s foi aprovada.',
                strtolower($transacao['tipo']),
                formatMoney($transacao['valor'])
            );
            $notificacao->criar(
                $transacao['jogador_id'],
                $transacao['tipo'] === 'deposito' ? 'deposito_confirmado' : 'saque_aprovado',
                $titulo,
                $mensagemNotif
            );
        }

    } else { // reject
        // Rejeitar transação
        $novoStatus = 'rejeitado';
        $mensagem = 'Transação rejeitada com sucesso!';
        
        // Get current admin user ID
        $adminId = getCurrentAdminId();
        if (!$adminId) {
            throw new Exception('Admin ID não encontrado na sessão');
        }
        error_log("DEBUG TRANSACAO REJECT: AdminId = $adminId, NovoStatus = $novoStatus, Motivo = $motivo");
        
        // Update transaction status
        $motivoTexto = !empty($motivo) ? ": " . $motivo : "";
        $updateSql = "UPDATE transacoes SET 
                     status = ?, 
                     data_processamento = NOW(), 
                     processado_por = ?,
                     descricao = CONCAT(COALESCE(descricao, ''), ?)
                     WHERE id = ?";
        
        $params = [$novoStatus, $adminId, " [REJEITADO$motivoTexto]", $transacaoId];
        error_log("DEBUG TRANSACAO REJECT: SQL = $updateSql");
        error_log("DEBUG TRANSACAO REJECT: Params = " . json_encode($params));
        
        $result = dbExecute($updateSql, $params);
        
        error_log("DEBUG TRANSACAO REJECT: Update result = " . ($result ? 'SUCCESS' : 'FAILED'));
        
        // Verificar se a transação foi realmente atualizada
        $verificacao = dbFetchOne(
            "SELECT status FROM transacoes WHERE id = ?",
            [$transacaoId]
        );
        error_log("DEBUG TRANSACAO REJECT: Status após update = " . ($verificacao['status'] ?? 'NULL'));
        
        // Create notification for user
        if (class_exists('NotificacaoManager')) {
            $notificacao = new NotificacaoManager();
            $titulo = 'Transação Rejeitada';
            $mensagemNotif = sprintf(
                'Sua %s de %s foi rejeitada.' . (!empty($motivo) ? ' Motivo: ' . $motivo : ''),
                strtolower($transacao['tipo']),
                formatMoney($transacao['valor'])
            );
            $notificacao->criar(
                $transacao['jogador_id'],
                'saque_rejeitado', // usando mesmo tipo para ambos
                $titulo,
                $mensagemNotif
            );
        }
    }

    // Log the action
    if (class_exists('LogFinanceiroManager')) {
        $logManager = new LogFinanceiroManager();
        $descricaoLog = sprintf(
            'Transação #%d %s por admin #%d - %s de %s do jogador %s',
            $transacaoId,
            $action === 'approve' ? 'aprovada' : 'rejeitada',
            getCurrentUserId(),
            $transacao['tipo'],
            formatMoney($transacao['valor']),
            $transacao['jogador_nome']
        );
        
        $logManager->registrarOperacao(
            getCurrentAdminId(),
            'admin_' . $action,
            $descricaoLog,
            [
                'transacao_id' => $transacaoId,
                'jogador_id' => $transacao['jogador_id'],
                'valor' => $transacao['valor'],
                'tipo' => $transacao['tipo'],
                'motivo' => $motivo
            ]
        );
    }

    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = $mensagem;

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erro ao processar transação: ' . $e->getMessage());
    $_SESSION['error'] = 'Erro ao processar transação: ' . $e->getMessage();
}

// Redirect back to transactions page
redirect(APP_URL . '/admin/pagamentos.php');
?>