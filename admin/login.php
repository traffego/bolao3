<?php
/**
 * Admin Login Page - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Se já estiver logado como admin, redirecionar para dashboard
if (isset($_SESSION['admin_id'])) {
    redirect(APP_URL . '/admin/');
}

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    try {
        // Buscar administrador pelo email
        $sql = "SELECT * FROM administrador WHERE email = ? AND status = 'ativo'";
        $admin = dbFetchOne($sql, [$email]);
        
        if ($admin && password_verify($senha, $admin['senha'])) {
            // Login bem sucedido
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            
            // Atualizar último login
            dbExecute("UPDATE administrador SET ultimo_login = NOW() WHERE id = ?", [$admin['id']]);
            
            // Verificar status da API Football
            $apiStatus = checkApiFootballStatus();
            $_SESSION['api_status'] = $apiStatus;
            
            if ($apiStatus['status'] !== 'online') {
                setFlashMessage('warning', 'Aviso: ' . $apiStatus['message']);
            }
            
            // Redirecionar para URL salva ou dashboard
            $redirect = $_SESSION['admin_redirect'] ?? APP_URL . '/admin/';
            unset($_SESSION['admin_redirect']);
            
            redirect($redirect);
        } else {
            throw new Exception('Email ou senha inválidos.');
        }
    } catch (Exception $e) {
        setFlashMessage('danger', $e->getMessage());
    }
}

$pageTitle = "Login Administrativo";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 15px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: #198754;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
            text-align: center;
        }
        .card-header i {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .card-body {
            padding: 30px;
        }
        .btn-success {
            width: 100%;
            padding: 12px;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-lock"></i>
                    <h4 class="mb-0">Área Administrativa</h4>
                </div>
                <div class="card-body">
                    <?php displayFlashMessages(); ?>
                    
                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-4">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-box-arrow-in-right"></i> Entrar
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="<?= APP_URL ?>" class="text-muted">
                    <i class="bi bi-arrow-left"></i> Voltar para o site
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 