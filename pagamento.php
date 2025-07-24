<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/EfiPixManager.php';
require_once __DIR__ . '/includes/classes/ContaManager.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    redirect(APP_URL . '/login.php');
}

// Verificar se tem palpite pendente na sessão
if (!isset($_SESSION['palpite_pendente'])) {
    redirect(APP_URL . '/meus-palpites.php');
}

$palpiteId = $_SESSION['palpite_pendente']['id'];
$bolaoId = $_SESSION['palpite_pendente']['bolao_id'];

// Buscar dados do palpite e bolão
$sql = "
    SELECT p.*, b.valor_participacao, b.nome as bolao_nome
    FROM palpites p
    JOIN dados_boloes b ON b.id = p.bolao_id
    WHERE p.id = ? AND p.jogador_id = ?";

$palpite = dbFetchOne($sql, [$palpiteId, getCurrentUserId()]);

if (!$palpite) {
    setFlashMessage('danger', 'Palpite não encontrado.');
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

try {
    $efiPix = new EfiPixManager();
    
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
    $charge = $efiPix->createCharge($transacao['txid'], getCurrentUserId(), $palpite['valor_participacao']);
    
    // Gerar QR Code
    if (isset($charge['loc']['id'])) {
        $qrCodeData = $efiPix->getQrCode($charge['loc']['id']);
        $qrcode = $qrCodeData['imagemQrcode'];
        $copiaCola = $qrCodeData['qrcode'];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Verificar pagamento via AJAX
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    try {
        $status = $efiPix->checkPayment($transacao['txid']);
        if ($status['status'] === 'CONCLUIDA') {
            echo json_encode(['status' => 'paid']);
        } else {
            echo json_encode(['status' => 'waiting']);
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
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php else: ?>
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

                        <div class="text-center mb-4">
                            <h4 class="mb-3">QR Code PIX</h4>
                            <img src="<?php echo $qrcode; ?>" alt="QR Code PIX" class="img-fluid mb-3" style="max-width: 300px;">
                            
                            <div class="mt-3">
                                <p class="mb-2">Ou copie o código PIX:</p>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($copiaCola); ?>" id="pixCode" readonly>
                                    <button class="btn btn-outline-primary copy-button" type="button" onclick="copyPixCode()">
                                        <i class="fas fa-copy me-2"></i>Copiar
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

                        <div class="text-center">
                            <a href="<?php echo APP_URL; ?>/meus-palpites.php" class="btn btn-link text-muted">
                                <i class="fas fa-arrow-left me-2"></i>Voltar para Meus Palpites
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyPixCode() {
    const pixCode = document.getElementById('pixCode');
    pixCode.select();
    document.execCommand('copy');
    
    const button = document.querySelector('.copy-button');
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check me-2"></i>Copiado!';
    button.classList.remove('btn-outline-primary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-primary');
    }, 2000);
}

// Verifica o status do pagamento a cada 5 segundos
function checkPaymentStatus() {
    fetch('<?php echo APP_URL; ?>/pagamento.php?check=1')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                const status = document.getElementById('paymentStatus');
                status.classList.remove('alert-warning');
                status.classList.add('alert-success');
                status.innerHTML = '<i class="fas fa-check-circle me-2"></i>Pagamento confirmado! Redirecionando...';
                
                setTimeout(() => {
                    window.location.href = '<?php echo APP_URL; ?>/meus-palpites.php';
                }, 2000);
            } else {
                setTimeout(checkPaymentStatus, 5000);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            setTimeout(checkPaymentStatus, 5000);
        });
}

// Inicia a verificação
checkPaymentStatus();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?> 