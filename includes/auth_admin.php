<?php
/**
 * Arquivo de autenticação para área administrativa
 * Verifica se o usuário está logado como administrador
 */

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION['admin_id'])) {
    // Se não estiver logado, salvar URL atual para redirecionamento após login
    $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'];
    
    // Redirecionar para página de login administrativa
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Buscar informações do administrador
$adminId = $_SESSION['admin_id'];
$sql = "SELECT * FROM administradores WHERE id = ? AND status = 'ativo'";
$admin = dbFetchOne($sql, [$adminId]);

// Se administrador não existe ou está inativo
if (!$admin) {
    // Destruir sessão
    session_destroy();
    
    // Redirecionar para login com mensagem
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Sua sessão expirou ou sua conta foi desativada.'
    ];
    
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
} 