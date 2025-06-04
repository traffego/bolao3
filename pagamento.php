<?php
session_start();
require_once 'includes/EfiPixManager.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se já pagou
require_once 'config/config.php';
$stmt = $pdo->prepare("SELECT pagamento_confirmado FROM jogador WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

if ($usuario['pagamento_confirmado']) {
    header('Location: agradecimento.php');
    exit;
}

// Gera ou recupera TXID da sessão
if (!isset($_SESSION['txid'])) {
    // Gerar TXID que atenda aos requisitos da EFIBANK (26-35 caracteres alfanuméricos)
    $timestamp = time();
    $random = bin2hex(random_bytes(10)); // 20 caracteres
    $prefix = 'BOL'; // Prefixo para identificar transações do Bolão
    $userId = str_pad($_SESSION['user_id'], 3, '0', STR_PAD_LEFT); // 3 caracteres
    $_SESSION['txid'] = $prefix . $userId . $timestamp . $random;
    $_SESSION['txid'] = substr($_SESSION['txid'], 0, 35); // Garantir máximo de 35 caracteres
}

$error = null;
$qrcode = null;
$copiaCola = null;

try {
    $efiPix = new EfiPixManager();
    
    // Cria a cobrança
    $charge = $efiPix->createCharge($_SESSION['txid'], $_SESSION['user_id']);
    
    // Gera QR Code
    if (isset($charge['loc']['id'])) {
        $qrCodeData = $efiPix->getQrCode($charge['loc']['id']);
        $qrcode = $qrCodeData['imagemQrcode'];
        $copiaCola = $qrCodeData['qrcode'];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Verifica se o pagamento foi confirmado via AJAX
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    try {
        $status = $efiPix->checkPayment($_SESSION['txid']);
        if ($status['status'] === 'CONCLUIDA') {
            // Atualiza status do pagamento no banco
            $stmt = $pdo->prepare("UPDATE jogador SET pagamento_confirmado = 1 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['status' => 'paid']);
        } else {
            echo json_encode(['status' => 'waiting']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - Bolão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .qr-code-container {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code-container img {
            max-width: 300px;
            margin: 20px auto;
        }
        .copy-button {
            cursor: pointer;
        }
        .payment-status {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .waiting {
            background-color: #fff3cd;
            color: #856404;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="payment-container">
            <h2 class="text-center mb-4">Pagamento do Bolão</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <p class="mb-0">Valor a pagar: R$ <?php echo VALOR_BOLAO; ?></p>
                </div>

                <div class="qr-code-container">
                    <h4>QR Code PIX</h4>
                    <img src="<?php echo $qrcode; ?>" alt="QR Code PIX">
                    
                    <div class="mt-3">
                        <p class="mb-2">Ou copie o código PIX:</p>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($copiaCola); ?>" id="pixCode" readonly>
                            <button class="btn btn-outline-secondary copy-button" type="button" onclick="copyPixCode()">Copiar</button>
                        </div>
                    </div>
                </div>

                <div id="paymentStatus" class="payment-status waiting">
                    Aguardando pagamento...
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyPixCode() {
            const pixCode = document.getElementById('pixCode');
            pixCode.select();
            document.execCommand('copy');
            
            const button = document.querySelector('.copy-button');
            button.textContent = 'Copiado!';
            setTimeout(() => {
                button.textContent = 'Copiar';
            }, 2000);
        }

        // Verifica o status do pagamento a cada 5 segundos
        function checkPaymentStatus() {
            fetch('pagamento.php?check=1')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'paid') {
                        const status = document.getElementById('paymentStatus');
                        status.classList.remove('waiting');
                        status.classList.add('success');
                        status.textContent = 'Pagamento confirmado! Redirecionando...';
                        
                        setTimeout(() => {
                            window.location.href = 'agradecimento.php';
                        }, 2000);
                    } else {
                        setTimeout(checkPaymentStatus, 5000);
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        // Inicia a verificação
        checkPaymentStatus();
    </script>
</body>
</html> 