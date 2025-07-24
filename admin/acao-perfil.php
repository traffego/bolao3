<?php
/**
 * Processamento de ações relacionadas ao perfil do administrador
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

// Verificar token CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setError('Token de segurança inválido');
        redirect(APP_URL . '/admin/perfil.php');
    }
}

$action = $_POST['action'] ?? '';
$admin_id = $_SESSION['admin_id'];

switch ($action) {
    case 'update_profile':
        // Validar dados
        $required_fields = ['nome', 'email'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                setError("O campo {$field} é obrigatório");
                redirect(APP_URL . '/admin/perfil.php');
            }
        }

        $data = [
            'nome' => trim($_POST['nome']),
            'email' => trim($_POST['email']),
            'telefone' => trim($_POST['telefone'] ?? '')
        ];

        // Validar email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            setError('Email inválido');
            redirect(APP_URL . '/admin/perfil.php');
        }

        // Verificar se o email já está em uso por outro admin
        $existingAdmin = dbFetchOne(
            "SELECT id FROM administrador WHERE email = ? AND id != ?", 
            [$data['email'], $admin_id]
        );

        if ($existingAdmin) {
            setError('Este email já está em uso por outro administrador');
            redirect(APP_URL . '/admin/perfil.php');
        }

        try {
            // Atualizar dados do admin
            $success = dbUpdate('administrador', $data, 'id = ?', [$admin_id]);
            
            if ($success) {
                // Atualizar nome na sessão
                $_SESSION['admin_nome'] = $data['nome'];
                
                logAdminAction('atualizar', 'Perfil atualizado', $data);
                setSuccess('Perfil atualizado com sucesso');
            } else {
                throw new Exception('Erro ao atualizar perfil');
            }
        } catch (Exception $e) {
            setError('Erro ao atualizar perfil: ' . $e->getMessage());
        }
        break;

    case 'change_password':
        // Validar dados
        $required_fields = ['senha_atual', 'nova_senha', 'confirmar_senha'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                setError("Todos os campos de senha são obrigatórios");
                redirect(APP_URL . '/admin/perfil.php');
            }
        }

        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        // Verificar se as senhas conferem
        if ($nova_senha !== $confirmar_senha) {
            setError('As senhas não conferem');
            redirect(APP_URL . '/admin/perfil.php');
        }

        // Verificar tamanho mínimo da senha
        if (strlen($nova_senha) < 8) {
            setError('A nova senha deve ter no mínimo 8 caracteres');
            redirect(APP_URL . '/admin/perfil.php');
        }

        try {
            // Buscar senha atual do admin
            $admin = dbFetchOne("SELECT senha FROM administrador WHERE id = ?", [$admin_id]);
            
            if (!$admin) {
                throw new Exception('Administrador não encontrado');
            }

            // Verificar senha atual
            if (!verifyPassword($senha_atual, $admin['senha'])) {
                setError('Senha atual incorreta');
                redirect(APP_URL . '/admin/perfil.php');
            }

            // Atualizar senha
            $success = dbUpdate(
                'administrador',
                ['senha' => hashPassword($nova_senha)],
                'id = ?',
                [$admin_id]
            );

            if ($success) {
                logAdminAction('atualizar', 'Senha alterada');
                setSuccess('Senha alterada com sucesso');
            } else {
                throw new Exception('Erro ao alterar senha');
            }
        } catch (Exception $e) {
            setError('Erro ao alterar senha: ' . $e->getMessage());
        }
        break;

    default:
        setError('Ação inválida');
}

// Redirecionar de volta
redirect(APP_URL . '/admin/perfil.php'); 