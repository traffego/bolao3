<?php
/**
 * Login Page - Bolão Vitimba
 */
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/SecurityValidator.php';

// Capturar parâmetro de referência de afiliado e guardar em variável
$referralCode = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referralCode = trim($_GET['ref']);
    $_SESSION['referral_code'] = $referralCode; // Manter na sessão também para compatibilidade
} elseif (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
    $referralCode = $_SESSION['referral_code'];
}

// Se já está logado, redireciona
if (isLoggedIn()) {
    redirect(APP_URL);
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    try {
        $securityValidator = new SecurityValidator();
        
        // Validar tentativas de login
        $securityValidator->validarTentativasLogin($email);
        
        // Buscar usuário
        $stmt = $pdo->prepare("SELECT * FROM jogador WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && verifyPassword($senha, $usuario['senha'])) {
            // Login bem sucedido
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nome'];
            
            // Verificar se usuário ainda não tem ref_indicacao e há código de referência na sessão
            if (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
                // Verificar se o usuário ainda não tem ref_indicacao
                $temRef = dbFetchOne("SELECT ref_indicacao FROM jogador WHERE id = ?", [$usuario['id']]);
                
                if (empty($temRef['ref_indicacao'])) {
                    // Verificar se o código de referência existe e está ativo
                    $afiliado = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'", [$_SESSION['referral_code']]);
                    
                    if ($afiliado) {
                        // Atualizar ref_indicacao do usuário
                        dbExecute("UPDATE jogador SET ref_indicacao = ? WHERE id = ?", [$_SESSION['referral_code'], $usuario['id']]);
                    }
                }
                
                // Limpar código de referência da sessão
                unset($_SESSION['referral_code']);
            }
            
            // Registrar log de sucesso
            dbInsert('logs', [
                'tipo' => 'login_sucesso',
                'descricao' => 'Login realizado com sucesso',
                'usuario_id' => $usuario['id'],
                'data_hora' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            // Processar palpites temporários se existirem
            if (isset($_SESSION['palpites_temp'])) {
                // Se houver redirecionamento definido, usar ele
                if (isset($_SESSION['login_redirect'])) {
                    $redirect = $_SESSION['login_redirect'];
                    unset($_SESSION['login_redirect']);
                } else {
                    // Se não houver redirecionamento definido, redirecionar para salvar-palpite.php
                    $redirect = APP_URL . '/salvar-palpite.php';
                }
                
                // Redirecionar para processar os palpites
                redirect($redirect);
            } else {
                // Redirecionar normalmente
                $redirect = $_SESSION['login_redirect'] ?? APP_URL;
                unset($_SESSION['login_redirect']);
                redirect($redirect);
            }
            
        } else {
            // Registrar falha
            $securityValidator->registrarFalhaLogin($email);
            throw new Exception('Email ou senha inválidos.');
        }
        
    } catch (Exception $e) {
        setFlashMessage('danger', $e->getMessage());
    }
}

// Page title
$pageTitle = "Login";

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 text-center mb-4">Login</h1>
                    
                    <?php displayFlashMessages(); ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="senha" 
                                   name="senha" 
                                   required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="<?= APP_URL ?>/recuperar-senha.php" class="text-decoration-none">
                                Esqueceu sua senha?
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer text-center py-3 bg-light">
                    Não tem uma conta? 
                    <a href="<?= APP_URL ?>/cadastro.php" class="text-decoration-none">
                        Cadastre-se
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>