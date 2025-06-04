<?php
/**
 * Login Page - Bolão Football
 */
require_once 'config/config.php';require_once 'includes/functions.php';

// If user is already logged in, redirect to home
if (isLoggedIn()) {
    // Se tiver URL de redirecionamento na sessão, usar essa
    if (isset($_SESSION['login_redirect'])) {
        $redirect = $_SESSION['login_redirect'];
        unset($_SESSION['login_redirect']);
        redirect($redirect);
    } else {
        redirect(APP_URL);
    }
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
        // Look up the user
        $user = dbFetchOne("SELECT id, nome, senha, status FROM jogador WHERE email = ?", [$email]);
        
        if ($user && verifyPassword($password, $user['senha'])) {
            // Check if user is active
            if ($user['status'] === 'inativo') {
                $errors[] = 'Sua conta está inativa. Por favor, entre em contato com o suporte.';
            } else {
                // Start user session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                
                // Update last login time
                dbUpdate('jogador', ['ultimo_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
                
                // Redirect to specific page if set in session, or fallback to home
                if (isset($_SESSION['login_redirect'])) {
                    $redirect = $_SESSION['login_redirect'];
                    unset($_SESSION['login_redirect']);
                } elseif (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                } else {
                    $redirect = APP_URL;
                }
                
                setFlashMessage('success', 'Login realizado com sucesso!');
                redirect($redirect);
            }
        } else {
            // Wrong email or password
            $errors[] = 'E-mail ou senha incorretos. Por favor, tente novamente.';
        }
    }
}

// Verificar se há uma mensagem para mostrar sobre palpites temporários
$tempPalpitesMessage = '';
if (isset($_SESSION['palpites_temp']) && !empty($_SESSION['palpites_temp'])) {
    $tempPalpitesMessage = 'Você tem palpites temporários salvos. Faça login para salvá-los permanentemente.';
}

// Page title
$pageTitle = 'Login';

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Login</h4>
            </div>
            <div class="card-body">
                <?php displayFlashMessages(); ?>
                
                <?php if (!empty($tempPalpitesMessage)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?= $tempPalpitesMessage ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?= APP_URL ?>/login.php" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= sanitize($email) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Lembrar-me
                            </label>
                        </div>
                        <div>
                            <a href="<?= APP_URL ?>/recuperar-senha.php">Esqueceu a senha?</a>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Entrar</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Ainda não tem uma conta? <a href="<?= APP_URL ?>/cadastro.php">Cadastre-se</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include TEMPLATE_DIR . '/footer.php';
?> 