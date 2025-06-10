<?php
/**
 * Admin Login Page - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// If admin is already logged in, redirect to admin dashboard
if (isAdmin()) {
    redirect(APP_URL . '/admin/');
}

$errors = [];
$email = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email)) {
        $errors[] = 'O campo e-mail é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Por favor, insira um e-mail válido.';
    }
    
    if (empty($password)) {
        $errors[] = 'O campo senha é obrigatório.';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        // Look up the admin
        $admin = dbFetchOne("SELECT id, nome, senha, status FROM administrador WHERE email = ?", [$email]);
        
        if ($admin && verifyPassword($password, $admin['senha'])) {
            // Check if admin is active
            if ($admin['status'] === 'inativo') {
                $errors[] = 'Sua conta está inativa. Por favor, entre em contato com o suporte.';
            } else {
                // Start admin session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_nome'] = $admin['nome'];
                
                // Update last login time
                dbUpdate('administrador', ['ultimo_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
                
                // Redirect to admin dashboard
                setFlashMessage('success', 'Login administrativo realizado com sucesso!');
                redirect(APP_URL . '/admin/');
            }
        } else {
            // Wrong email or password
            $errors[] = 'E-mail ou senha incorretos. Por favor, tente novamente.';
        }
    }
}

// Page title
$pageTitle = 'Admin Login';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-form {
            width: 100%;
            max-width: 420px;
            padding: 15px;
            margin: auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .admin-logo {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-form">
        <div class="card">
            <div class="card-body p-4">
                <div class="text-center">
                    <div class="admin-logo">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h2 class="mb-4">Admin Login</h2>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php $flashMessage = getFlashMessage(); ?>
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show">
                        <?= $flashMessage['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?= APP_URL ?>/admin/login.php" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?= sanitize($email) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Entrar</button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="<?= APP_URL ?>" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Voltar para o site
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 