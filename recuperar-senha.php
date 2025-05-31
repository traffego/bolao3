<?php
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Page title
$pageTitle = 'Recuperar Senha';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $message = 'Por favor, informe seu e-mail.';
        $messageType = 'danger';
    } else {
        // Verificar se o e-mail existe
        $user = dbFetchRow("SELECT id, nome FROM usuarios WHERE email = ?", [$email]);
        
        if ($user) {
            // Gerar token de recuperação
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Salvar token no banco
            dbQuery("UPDATE usuarios SET 
                    reset_token = ?,
                    reset_token_expiry = ?
                    WHERE email = ?", 
                    [$token, $expiry, $email]);
            
            // Enviar e-mail (simulado aqui - em produção usar PHPMailer ou similar)
            $resetLink = APP_URL . '/redefinir-senha.php?token=' . $token;
            
            // Em produção, enviar e-mail real
            // Por enquanto, apenas mostra a mensagem de sucesso
            $message = 'Um e-mail foi enviado com as instruções para redefinir sua senha. Por favor, verifique sua caixa de entrada.';
            $messageType = 'success';
            
        } else {
            // Não informar se o e-mail existe ou não por segurança
            $message = 'Se o e-mail estiver cadastrado em nosso sistema, você receberá as instruções para redefinir sua senha.';
            $messageType = 'info';
        }
    }
}

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h1 class="h3 mb-4">Recuperar Senha</h1>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>" role="alert">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= APP_URL ?>/recuperar-senha.php" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   autocomplete="email">
                            <div class="form-text">
                                Informe o e-mail cadastrado em sua conta para receber as instruções de recuperação de senha.
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Enviar</button>
                        </div>
                    </form>

                    <div class="mt-4 text-center">
                        <p class="mb-0">Lembrou sua senha? <a href="<?= APP_URL ?>/login.php">Faça login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 