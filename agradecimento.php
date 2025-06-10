<?php
require_once __DIR__ . '/config/config.php';

// Iniciar a sessão apenas se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Redirecionar para a página de login
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Verificar se veio da página de pagamento (adicionar uma camada extra de segurança)
if (!isset($_SESSION['payment_confirmed']) || $_SESSION['payment_confirmed'] !== true) {
    // Redirecionar para a página inicial
    header('Location: ' . APP_URL . '/boloes.php');
    exit;
}

// Pegar o ID do palpite que foi pago
$palpiteId = isset($_SESSION['palpite_pago_id']) ? (int)$_SESSION['palpite_pago_id'] : 0;

// Limpar a flag de pagamento confirmado após mostrar a página
// Isso evita que o usuário acesse a página novamente após sair
$_SESSION['payment_confirmed'] = false;
unset($_SESSION['palpite_pago_id']); // Limpar também o ID do palpite
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Confirmado - Bolão</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h2 class="card-title mb-4 fw-bold">Pagamento Confirmado!</h2>
                        <p class="card-text lead mb-5">
                            Seus palpites foram registrados com sucesso.<br>
                            Obrigado por participar do nosso bolão!
                        </p>
                        <div class="mt-4 d-flex flex-column gap-3">
                            <?php if ($palpiteId > 0): ?>
                            <a href="<?= APP_URL ?>/ver-palpite.php?id=<?= $palpiteId ?>" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-eye me-2"></i> Ver Meu Palpite
                            </a>
                            <?php endif; ?>
                            <a href="<?= APP_URL ?>/boloes.php" class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-trophy me-2"></i> Ver Todos os Bolões
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 