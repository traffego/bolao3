<?php
/**
 * Registration Page - Bolão Vitimba
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Incluir sistema de debug
require_once 'debug_cadastro_real.php';

// Capturar parâmetro de referência de afiliado e guardar em variável
$referralCode = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referralCode = trim($_GET['ref']);
    $_SESSION['referral_code'] = $referralCode; // Manter na sessão também para compatibilidade
} elseif (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
    $referralCode = $_SESSION['referral_code'];
}

// If user is already logged in, redirect to home
if (isLoggedIn()) {
    redirect(APP_URL);
}

$errors = [];
$formData = [
    'nome' => '',
    'email' => '',
    'telefone' => '',
    'referral_code' => $referralCode
];

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'nome' => trim($_POST['nome'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'referral_code' => !empty(trim($_POST['referral_code'] ?? '')) ? trim($_POST['referral_code'] ?? '') : $referralCode
    ];
    
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($formData['nome'])) {
        $errors[] = 'O campo nome é obrigatório.';
    }
    
    if (empty($formData['email'])) {
        $errors[] = 'O campo e-mail é obrigatório.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Por favor, insira um e-mail válido.';
    } else {
        // Check if email already exists
        $existingUser = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$formData['email']]);
        if ($existingUser) {
            $errors[] = 'Este e-mail já está cadastrado. Por favor, utilize outro ou faça login.';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'O campo senha é obrigatório.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'As senhas não coincidem.';
    }
    
    // Validate referral code if provided
    if (!empty($formData['referral_code'])) {
        $referral = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'", 
                               [$formData['referral_code']]);
        if (!$referral) {
            $errors[] = 'Código de afiliado inválido.';
        }
    }
    
    // If no errors, create account
    if (empty($errors)) {
        // Gerar código de afiliado único
        do {
            $codigoAfiliado = 'af' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            $existeCodigo = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$codigoAfiliado]);
        } while ($existeCodigo);
        
        // Todos os novos usuários são afiliados ativos por padrão
        $afiliadoStatus = 'ativo';
        
        $userData = [
            'nome' => $formData['nome'],
            'email' => $formData['email'],
            'senha' => hashPassword($password),
            'telefone' => $formData['telefone'],
            'data_cadastro' => date('Y-m-d H:i:s'),
            'status' => 'ativo',
            'codigo_afiliado' => $codigoAfiliado,
            'ref_indicacao' => !empty($formData['referral_code']) ? $formData['referral_code'] : null,
            'afiliado_ativo' => $afiliadoStatus
        ];
        
        $userId = debugDbInsert('jogador', $userData);
        
        // Limpar código de referência da sessão após o cadastro
        if (isset($_SESSION['referral_code'])) {
            unset($_SESSION['referral_code']);
        }
        
        if ($userId) {
            // Create account in contas table
            try {
                $contaData = [
                    'jogador_id' => $userId,
                    'status' => 'ativo'
                ];
                
                $contaId = debugDbInsert('contas', $contaData);
                
                if (!$contaId) {
                    error_log("Erro ao criar conta para usuário ID: $userId");
                }
            } catch (Exception $e) {
                error_log("Erro ao criar conta para usuário ID $userId: " . $e->getMessage());
            }
            
            // Log the user in
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_nome'] = $formData['nome'];
            
            setFlashMessage('success', 'Cadastro realizado com sucesso! Bem-vindo ao Bolão Vitimba.');
            redirect(APP_URL);
        } else {
            $errors[] = 'Ocorreu um erro ao criar sua conta. Por favor, tente novamente.';
        }
    }
}

// Page title
$pageTitle = 'Cadastro';

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-header bg-green text-white">
                <h4 class="mb-0">Cadastre-se</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?= APP_URL ?>/cadastro.php" novalidate>
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitize($formData['nome']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= sanitize($formData['email']) ?>" required>
                        <small class="form-text text-muted">Será usado para login e comunicações importantes</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone (opcional)</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= sanitize($formData['telefone']) ?>">
                        <small class="form-text text-muted">Formato: (00) 00000-0000</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="form-text text-muted">Mínimo de 6 caracteres</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Senha</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referral_code" class="form-label">Código de Afiliado (opcional)</label>
                        <input type="text" class="form-control" id="referral_code" name="referral_code" value="<?= sanitize($formData['referral_code']) ?>">
                        <small class="form-text text-muted">Se você foi indicado por um afiliado, insira o código aqui</small>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            Concordo com os <a href="<?= APP_URL ?>/termos.php" target="_blank">Termos de Uso</a> e 
                            <a href="<?= APP_URL ?>/privacidade.php" target="_blank">Política de Privacidade</a>
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Cadastrar</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Já tem uma conta? <a href="<?= APP_URL ?>/login.php">Faça login</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include TEMPLATE_DIR . '/footer.php';
?>