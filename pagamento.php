<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/EfiPixManager.php';
require_once __DIR__ . '/includes/classes/ContaManager.php';

// Verificar se tem palpite pendente na sessão
if (!isset($_SESSION['palpite_pendente'])) {
    redirect(APP_URL . '/meus-palpites.php');
}

// Se o usuário está logado, verificar dados da sessão
if (isLoggedIn()) {
    $currentUserId = getCurrentUserId();
    error_log("PAGAMENTO DEBUG - User ID: " . ($currentUserId ?? 'NULL'));
    error_log("PAGAMENTO DEBUG - Session data: " . json_encode($_SESSION));

    if (!$currentUserId) {
        error_log("PAGAMENTO ERROR - getCurrentUserId() retornou null ou vazio");
        setFlashMessage('danger', 'Erro na sessão do usuário. Por favor, faça login novamente.');
        redirect(APP_URL . '/login.php');
    }
}

$palpiteId = $_SESSION['palpite_pendente']['id'];
$bolaoId = $_SESSION['palpite_pendente']['bolao_id'];

// Buscar dados do palpite e bolão (sem filtrar por jogador_id para permitir acesso a não logados)
$sql = "
    SELECT p.*, b.valor_participacao, b.nome as bolao_nome
    FROM palpites p
    JOIN dados_boloes b ON b.id = p.bolao_id
    WHERE p.id = ?";

$palpite = dbFetchOne($sql, [$palpiteId]);

if (!$palpite) {
    setFlashMessage('danger', 'Palpite não encontrado.');
    redirect(APP_URL . '/meus-palpites.php');
}

// Se usuário logado, verificar se é dono do palpite
if (isLoggedIn() && $palpite['jogador_id'] != getCurrentUserId()) {
    setFlashMessage('danger', 'Você não tem permissão para acessar este palpite.');
    redirect(APP_URL . '/meus-palpites.php');
}

// Se palpite já estiver pago, redirecionar
if ($palpite['status'] === 'pago') {
    unset($_SESSION['palpite_pendente']);
    setFlashMessage('success', 'Palpite já está pago!');
    redirect(APP_URL . '/meus-palpites.php');
}

$error = null;
$qrcode = null;
$copiaCola = null;
$mostrarLogin = false;
$mostrarDeposito = false;
$saldo = 0;
$valorFaltante = 0;
$contaId = null;

// Se usuário está logado, verificar saldo e processar pagamento via saldo
if (isLoggedIn()) {
    $contaManager = new ContaManager();
    $conta = $contaManager->buscarContaPorJogador(getCurrentUserId());
    
    if (!$conta) {
        // Criar conta se não existir
        $contaId = $contaManager->criarConta(getCurrentUserId());
        $conta = $contaManager->buscarContaPorJogador(getCurrentUserId());
    }
    
    $contaId = $conta['id'];
    $saldo = $contaManager->getSaldo($contaId);
    
    // Se tem saldo suficiente, processar pagamento automaticamente
    if ($saldo >= $palpite['valor_participacao']) {
        try {
            // Processar pagamento via saldo
            $contaManager->processarAposta($contaId, $palpite['valor_participacao'], $bolaoId, $palpiteId);
            
            // Atualizar status do palpite
            $sql = "UPDATE palpites SET status = 'pago' WHERE id = ?";
            dbExecute($sql, [$palpiteId]);
            
            // Limpar sessão e redirecionar
            unset($_SESSION['palpite_pendente']);
            setFlashMessage('success', '🎉 SUCESSO! Pagamento confirmado via saldo! Seu palpite está ativo no bolão! 🏆');
            redirect(APP_URL . '/ver-palpite.php?id=' . $palpiteId);
            
        } catch (Exception $e) {
            $error = 'Erro ao processar pagamento: ' . $e->getMessage();
            error_log("PAGAMENTO ERROR - Erro ao processar via saldo: " . $e->getMessage());
        }
    } else {
        // Saldo insuficiente - mostrar modal de depósito
        $valorFaltante = $palpite['valor_participacao'] - $saldo;
        $mostrarDeposito = true;
    }
} else {
    // Usuário não logado - mostrar modal de login
    $mostrarLogin = true;
}

// Se não houver erro e não for pagamento via saldo, tentar PIX (fallback)
if (!$error && !$mostrarLogin && !$mostrarDeposito && isLoggedIn()) {
    try {
        $efiPix = new EfiPixManager(defined('EFI_WEBHOOK_FATAL_FAILURE') ? EFI_WEBHOOK_FATAL_FAILURE : false);
        
        // Criar transação se ainda não existir
        $transacao = dbFetchOne(
            "SELECT * FROM transacoes WHERE palpite_id = ? AND tipo = 'aposta'",
            [$palpiteId]
        );

        if (!$transacao) {
            // Criar nova transação
            $dados = [
                'tipo' => 'aposta',
                'valor' => $palpite['valor_participacao'],
                'status' => 'pendente',
                'metodo_pagamento' => 'pix',
                'afeta_saldo' => false,
                'palpite_id' => $palpiteId,
                'descricao' => 'Pagamento do palpite #' . $palpiteId . ' no bolão ' . $palpite['bolao_nome']
            ];

            // Gerar TXID
            $timestamp = time();
            $random = bin2hex(random_bytes(10));
            $prefix = 'BOL';
            $userId = str_pad(getCurrentUserId(), 3, '0', STR_PAD_LEFT);
            $dados['txid'] = substr($prefix . $userId . $timestamp . $random, 0, 35);

            // Inserir transação
            $transacaoId = dbInsert('transacoes', $dados);
            $transacao = array_merge($dados, ['id' => $transacaoId]);
        }
        
        // Criar cobrança PIX
        error_log("PAGAMENTO DEBUG - Iniciando criação de cobrança PIX");
        $charge = $efiPix->createCharge(getCurrentUserId(), $palpite['valor_participacao'], $transacao['txid'], 'Pagamento de Palpite - ' . $palpite['bolao_nome']);
        error_log("PAGAMENTO DEBUG - Charge criado: " . json_encode($charge));
        
        // Gerar QR Code
        if (isset($charge['loc']['id'])) {
            error_log("PAGAMENTO DEBUG - Gerando QR Code para loc_id: " . $charge['loc']['id']);
            $qrCodeData = $efiPix->getQrCode($charge['loc']['id']);
            error_log("PAGAMENTO DEBUG - QR Code data: " . json_encode($qrCodeData));
            $qrcode = $qrCodeData['imagemQrcode'] ?? null;
            $copiaCola = $qrCodeData['qrcode'] ?? null;
            error_log("PAGAMENTO DEBUG - QR Code definido: " . ($qrcode ? 'SIM' : 'NÃO'));
            error_log("PAGAMENTO DEBUG - Copia e cola definido: " . ($copiaCola ? 'SIM' : 'NÃO'));
        } else {
            error_log("PAGAMENTO ERROR - Charge não contém loc.id: " . json_encode($charge));
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("PAGAMENTO ERROR - Exceção capturada: " . $error);
        error_log("PAGAMENTO ERROR - Stack trace: " . $e->getTraceAsString());
    }
}

// AJAX Endpoints
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email = $_POST['email'] ?? '';
                $senha = $_POST['senha'] ?? '';
                
                if (empty($email) || empty($senha)) {
                    echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
                    exit;
                }
                
                try {
                    $usuario = dbFetchOne("SELECT * FROM jogador WHERE email = ?", [$email]);
                    
                    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
                        echo json_encode(['success' => false, 'message' => 'Email ou senha incorretos']);
                        exit;
                    }
                    
                    // Set session data
                    $_SESSION['user_id'] = $usuario['id'];
                    $_SESSION['user_name'] = $usuario['nome'];
                    $_SESSION['user_email'] = $usuario['email'];
                    
                    echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao realizar login']);
                }
            }
            exit;
            
        case 'check_payment':
            try {
                if (isset($transacao)) {
                    $status = $efiPix->checkPayment($transacao['txid']);
                    if ($status['status'] === 'CONCLUIDA') {
                        echo json_encode(['status' => 'paid']);
                    } else {
                        echo json_encode(['status' => 'waiting']);
                    }
                } else {
                    echo json_encode(['status' => 'no_transaction']);
                }
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'process_payment':
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
                exit;
            }
            
            try {
                $contaManager = new ContaManager();
                $conta = $contaManager->buscarContaPorJogador(getCurrentUserId());
                
                if (!$conta) {
                    $contaId = $contaManager->criarConta(getCurrentUserId());
                    $conta = $contaManager->buscarContaPorJogador(getCurrentUserId());
                }
                
                $saldo = $contaManager->getSaldo($conta['id']);
                
                if ($saldo >= $palpite['valor_participacao']) {
                    // Processar pagamento via saldo
                    $contaManager->processarAposta($conta['id'], $palpite['valor_participacao'], $bolaoId, $palpiteId);
                    
                    // Atualizar status do palpite
                    $sql = "UPDATE palpites SET status = 'pago' WHERE id = ?";
                    dbExecute($sql, [$palpiteId]);
                    
                    echo json_encode(['success' => true, 'message' => 'Pagamento realizado com sucesso']);
                } else {
                    $valorFaltante = $palpite['valor_participacao'] - $saldo;
                    echo json_encode([
                        'success' => false, 
                        'insufficient_balance' => true,
                        'current_balance' => $saldo,
                        'required_amount' => $palpite['valor_participacao'],
                        'missing_amount' => $valorFaltante
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Verificar pagamento via AJAX (compatibilidade)
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    try {
        if (isset($transacao)) {
            $status = $efiPix->checkPayment($transacao['txid']);
            if ($status['status'] === 'CONCLUIDA') {
                echo json_encode(['status' => 'paid']);
            } else {
                echo json_encode(['status' => 'waiting']);
            }
        } else {
            echo json_encode(['status' => 'no_transaction']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Incluir header
require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Pagamento do Palpite</h2>
                    
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Bolão: <?php echo htmlspecialchars($palpite['bolao_nome']); ?></h6>
                                <small class="text-muted">Palpite #<?php echo $palpiteId; ?></small>
                            </div>
                            <div class="text-end">
                                <strong>Valor: R$ <?php echo number_format($palpite['valor_participacao'], 2, ',', '.'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div id="paymentContent">
                        <?php if ($mostrarLogin): ?>
                            <!-- Mostrar botão para abrir modal de login -->
                            <div class="text-center py-4">
                                <h4 class="mb-3">Para continuar, faça login na sua conta</h4>
                                <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                                    <i class="bi bi-person-circle me-2"></i>Fazer Login
                                </button>
                                <div class="mt-3">
                                    <p class="text-muted">Não tem conta? <a href="<?php echo APP_URL; ?>/cadastro.php">Cadastre-se aqui</a></p>
                                </div>
                            </div>
                        
                        <?php elseif ($mostrarDeposito): ?>
                            <!-- Mostrar informações de saldo insuficiente -->
                            <div class="text-center py-4">
                                <div class="alert alert-warning">
                                    <h5><i class="bi bi-exclamation-triangle me-2"></i>Saldo Insuficiente</h5>
                                    <p class="mb-2">Seu saldo atual: <strong>R$ <?php echo number_format($saldo, 2, ',', '.'); ?></strong></p>
                                    <p class="mb-0">Valor necessário: <strong>R$ <?php echo number_format($valorFaltante, 2, ',', '.'); ?></strong></p>
                                </div>
                                <button type="button" class="btn btn-success btn-lg" onclick="redirectToDeposit()">
                                    <i class="bi bi-plus-circle me-2"></i>Fazer Depósito
                                </button>
                            </div>
                        
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            
                        <?php elseif ($qrcode): ?>
                            <!-- Mostrar QR Code PIX -->
                            <div class="text-center mb-4">
                                <h4 class="mb-3">QR Code PIX</h4>
                                <img src="<?php echo $qrcode; ?>" alt="QR Code PIX" class="img-fluid mb-3" style="max-width: 300px;">
                                
                                <div class="mt-3">
                                    <p class="mb-2">Ou copie o código PIX:</p>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($copiaCola ?? ''); ?>" id="pixCode" readonly>
                                        <button class="btn btn-outline-primary copy-button" type="button" onclick="copyPixCode()">
                                            <i class="bi bi-clipboard me-2"></i>Copiar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="paymentStatus" class="alert alert-warning text-center">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                                Aguardando pagamento...
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center mt-4">
                        <a href="<?php echo APP_URL; ?>/meus-palpites.php" class="btn btn-link text-muted">
                            <i class="bi bi-arrow-left me-2"></i>Voltar para Meus Palpites
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Login -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="loginForm">
                    <div class="mb-3">
                        <label for="loginEmail" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="loginEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="loginPassword" name="senha" required>
                    </div>
                    <div id="loginError" class="alert alert-danger d-none"></div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <span class="login-button-text">Entrar</span>
                            <span class="login-spinner d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Entrando...
                            </span>
                        </button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <p class="mb-0">Não tem uma conta? <a href="<?php echo APP_URL; ?>/cadastro.php">Cadastre-se aqui</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyPixCode() {
    const pixCode = document.getElementById('pixCode');
    if (pixCode) {
        pixCode.select();
        document.execCommand('copy');
        
        const button = document.querySelector('.copy-button');
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check me-2"></i>Copiado!';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }
}

// Redirect to deposit page with the missing amount
function redirectToDeposit() {
    const valorFaltante = <?php echo $valorFaltante ?? 0; ?>;
    window.location.href = '<?php echo APP_URL; ?>/deposito.php?valor=' + valorFaltante.toFixed(2);
}

// Handle login form submission
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginModal = document.getElementById('loginModal');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const buttonText = submitBtn.querySelector('.login-button-text');
            const spinner = submitBtn.querySelector('.login-spinner');
            const errorDiv = document.getElementById('loginError');
            
            // Show loading state
            buttonText.classList.add('d-none');
            spinner.classList.remove('d-none');
            submitBtn.disabled = true;
            errorDiv.classList.add('d-none');
            
            fetch('<?php echo APP_URL; ?>/pagamento.php?action=login', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Login successful - reload page to trigger payment logic
                    window.location.reload();
                } else {
                    // Show error
                    errorDiv.textContent = data.message;
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                errorDiv.textContent = 'Erro ao fazer login. Tente novamente.';
                errorDiv.classList.remove('d-none');
            })
            .finally(() => {
                // Hide loading state
                buttonText.classList.remove('d-none');
                spinner.classList.add('d-none');
                submitBtn.disabled = false;
            });
        });
    }
});

// Verifica o status do pagamento a cada 5 segundos (apenas se há QR code)
function checkPaymentStatus() {
    <?php if (!empty($qrcode)): ?>
    fetch('<?php echo APP_URL; ?>/pagamento.php?action=check_payment')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                const status = document.getElementById('paymentStatus');
                if (status) {
                    status.classList.remove('alert-warning');
                    status.classList.add('alert-success');
                    status.innerHTML = '<i class="bi bi-check-circle me-2"></i>Pagamento confirmado! Redirecionando...';
                    
                    setTimeout(() => {
                        window.location.href = '<?php echo APP_URL; ?>/ver-palpite.php?id=<?php echo $palpiteId; ?>';
                    }, 2000);
                }
            } else if (data.status === 'waiting') {
                setTimeout(checkPaymentStatus, 5000);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            setTimeout(checkPaymentStatus, 5000);
        });
    <?php endif; ?>
}

// Inicia a verificação apenas se há QR code
<?php if (!empty($qrcode)): ?>
checkPaymentStatus();
<?php endif; ?>

// Show login modal automatically if needed
<?php if ($mostrarLogin): ?>
document.addEventListener('DOMContentLoaded', function() {
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>