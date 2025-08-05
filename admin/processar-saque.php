<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/classes/NotificacaoManager.php';
require_once '../includes/classes/LogFinanceiroManager.php';
require_once '../includes/classes/SecurityValidator.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('warning', 'Método inválido.');
    redirect(APP_URL . '/admin/saques.php');
}

// Validar campos obrigatórios
$saqueId = filter_input(INPUT_POST, 'saque_id', FILTER_VALIDATE_INT);
$acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_STRING);

if (!$saqueId || !$acao) {
    setFlashMessage('danger', 'Parâmetros inválidos.');
    redirect(APP_URL . '/admin/saques.php');
}

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Buscar transação
    $stmt = $pdo->prepare("
        SELECT t.*, c.jogador_id, j.nome as jogador_nome, j.email as jogador_email
        FROM transacoes t
        INNER JOIN contas c ON t.conta_id = c.id
        INNER JOIN jogador j ON c.jogador_id = j.id
        WHERE t.id = ? AND t.tipo = 'saque' AND t.status = 'pendente'
    ");
    $stmt->execute([$saqueId]);
    $saque = $stmt->fetch();
    
    if (!$saque) {
        throw new Exception('Saque não encontrado ou não está pendente.');
    }

    // Instanciar gerenciadores
    $notificacaoManager = new NotificacaoManager();
    $logManager = new LogFinanceiroManager();
    $securityValidator = new SecurityValidator();
    
    // Validar dados do usuário
    if (!$securityValidator->validarDadosUsuario($saque['jogador_id'])) {
        throw new Exception('Dados do usuário incompletos ou inválidos para processamento do saque.');
    }
    
    // Processar conforme ação
    switch ($acao) {
        case 'aprovar':
            // Validar transação
            $securityValidator->validarTransacao(
                $saque['conta_id'],
                'saque',
                $saque['valor'],
                [
                    'saque_id' => $saqueId,
                    'admin_id' => getCurrentAdminId()
                ]
            );
            
            // Upload do comprovante
            $comprovante = null;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/comprovantes/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (!in_array($ext, $allowedTypes)) {
                    throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG ou PDF.');
                }
                
                $filename = 'comprovante_' . time() . '_' . uniqid() . '.' . $ext;
                $uploadFile = $uploadDir . $filename;
                
                if (!move_uploaded_file($_FILES['comprovante']['tmp_name'], $uploadFile)) {
                    throw new Exception('Erro ao salvar comprovante.');
                }
                
                $comprovante = 'uploads/comprovantes/' . $filename;
            } else {
                throw new Exception('Comprovante é obrigatório.');
            }
            
            // Atualizar transação
            $stmt = $pdo->prepare("
                UPDATE transacoes 
                SET status = 'aprovado',
                    comprovante_url = ?,
                    descricao = ?,
                    data_processamento = NOW(),
                    afeta_saldo = 1,
                    processado_por = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $comprovante,
                $_POST['observacoes'] ?? null,
                getCurrentAdminId(),
                $saqueId
            ]);

            // Registrar log financeiro
            $logManager->registrarSaque(
                $saque['jogador_id'],
                $saque['valor'],
                'aprovado',
                [
                    'saque_id' => $saqueId,
                    'comprovante_url' => $comprovante,
                    'observacoes' => $_POST['observacoes'] ?? null,
                    'admin_id' => getCurrentAdminId(),
                    'conta_id' => $saque['conta_id']
                ]
            );

            // Notificar jogador
            $notificacaoManager->notificarSaqueAprovado(
                $saque['jogador_id'],
                $saque['valor'],
                [
                    'saque_id' => $saqueId,
                    'comprovante_url' => $comprovante,
                    'observacoes' => $_POST['observacoes'] ?? null
                ]
            );
            
            $mensagem = 'Saque aprovado com sucesso!';
            break;
            
        case 'rejeitar':
            if (empty($_POST['motivo'])) {
                throw new Exception('Motivo da rejeição é obrigatório.');
            }
            
            // Atualizar transação
            $stmt = $pdo->prepare("
                UPDATE transacoes 
                SET status = 'rejeitado',
                    descricao = ?,
                    data_processamento = NOW(),
                    afeta_saldo = 0,
                    processado_por = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['motivo'],
                getCurrentAdminId(),
                $saqueId
            ]);

            // Registrar log financeiro
            $logManager->registrarSaque(
                $saque['jogador_id'],
                $saque['valor'],
                'rejeitado',
                [
                    'saque_id' => $saqueId,
                    'motivo' => $_POST['motivo'],
                    'admin_id' => getCurrentAdminId(),
                    'conta_id' => $saque['conta_id']
                ]
            );

            // Notificar jogador
            $notificacaoManager->notificarSaqueRejeitado(
                $saque['jogador_id'],
                $saque['valor'],
                $_POST['motivo'],
                [
                    'saque_id' => $saqueId
                ]
            );
            
            $mensagem = 'Saque rejeitado com sucesso!';
            break;
            
        default:
            throw new Exception('Ação inválida.');
    }
    
    // Commit da transação
    $pdo->commit();
    
    setFlashMessage('success', $mensagem);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    setFlashMessage('danger', 'Erro ao processar saque: ' . $e->getMessage());
}

redirect(APP_URL . '/admin/saques.php'); 