<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se o pagamento foi realmente confirmado
require_once 'config/database.php';
$stmt = $pdo->prepare("SELECT pagamento_confirmado FROM jogador WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

if (!$usuario['pagamento_confirmado']) {
    header('Location: pagamento.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agradecimento - Bolão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .thank-you-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 30px;
        }
        .thank-you-title {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .thank-you-text {
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .action-buttons {
            margin-top: 30px;
        }
        .action-buttons .btn {
            margin: 10px;
            padding: 12px 30px;
            font-size: 1.1rem;
        }
        .confetti {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            top: 0;
            left: 0;
        }
    </style>
</head>
<body class="bg-light">
    <div class="confetti" id="confetti"></div>
    <div class="container">
        <div class="thank-you-container">
            <i class="fas fa-check-circle success-icon"></i>
            <h1 class="thank-you-title">Pagamento Confirmado!</h1>
            <p class="thank-you-text">
                Muito obrigado por participar do nosso Bolão! Seu pagamento foi confirmado com sucesso.
                Agora você já pode começar a fazer seus palpites e competir com outros participantes.
            </p>
            <p class="thank-you-text">
                Desejamos boa sorte e que você se divirta participando!
            </p>
            <div class="action-buttons">
                <a href="bolao.php" class="btn btn-primary">Ir para o Bolão</a>
                <a href="meus-palpites.php" class="btn btn-outline-primary">Meus Palpites</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        // Efeito de confete ao carregar a página
        window.onload = function() {
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 }
            });
        };
    </script>
</body>
</html> 