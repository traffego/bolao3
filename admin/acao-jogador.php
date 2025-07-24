<?php
/**
 * Processa ações relacionadas aos jogadores
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/admin/jogadores.php');
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Token de segurança inválido.';
    redirect(APP_URL . '/admin/jogadores.php');
}

// Verificar ação
$action = $_POST['action'] ?? '';
$jogador_id = (int)($_POST['jogador_id'] ?? 0);

if (!$jogador_id) {
    $_SESSION['error'] = 'ID do jogador inválido.';
    redirect(APP_URL . '/admin/jogadores.php');
}

// Buscar jogador
$jogador = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$jogador_id]);
if (!$jogador) {
    $_SESSION['error'] = 'Jogador não encontrado.';
    redirect(APP_URL . '/admin/jogadores.php');
}

// Processar ação
switch ($action) {
    case 'toggle_status':
        $novo_status = $jogador['status'] === 'ativo' ? 'inativo' : 'ativo';
        
        // Atualizar status
        $success = dbExecute(
            "UPDATE jogador SET status = ? WHERE id = ?",
            [$novo_status, $jogador_id]
        );
        
        if ($success) {
            // Registrar ação no log
            logAdminAction(
                'jogador',
                "Alterou status do jogador {$jogador['nome']} para {$novo_status}",
                [
                    'jogador_id' => $jogador_id,
                    'status_anterior' => $jogador['status'],
                    'novo_status' => $novo_status
                ]
            );
            
            $_SESSION['success'] = "Status do jogador alterado com sucesso.";
        } else {
            $_SESSION['error'] = "Erro ao alterar status do jogador.";
        }
        break;
        
    default:
        $_SESSION['error'] = 'Ação inválida.';
        break;
}

// Redirecionar de volta
redirect(APP_URL . '/admin/jogadores.php'); 