<?php
require_once __DIR__ . '/config/config.php';

$errorCode = $_GET['code'] ?? '404';
$errorMessages = [
    '400' => 'Requisição inválida',
    '401' => 'Não autorizado',
    '403' => 'Acesso negado',
    '404' => 'Página não encontrada',
    '500' => 'Erro interno do servidor'
];

$errorDescriptions = [
    '400' => 'O servidor não entendeu a requisição devido a uma sintaxe inválida.',
    '401' => 'É necessário fazer login para acessar este recurso.',
    '403' => 'Você não tem permissão para acessar este recurso.',
    '404' => 'A página que você está procurando não foi encontrada.',
    '500' => 'Ocorreu um erro interno no servidor. Por favor, tente novamente mais tarde.'
];

$errorTitle = $errorMessages[$errorCode] ?? 'Erro desconhecido';
$errorDescription = $errorDescriptions[$errorCode] ?? 'Ocorreu um erro inesperado.';

// Registrar erro no log se for 500
if ($errorCode === '500') {
    error_log("Erro 500 - URI: " . $_SERVER['REQUEST_URI'] . " - Referrer: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A'));
}

$pageTitle = "Erro $errorCode - $errorTitle";
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="error-page mb-4">
                <h1 class="display-1 text-danger mb-4"><?= $errorCode ?></h1>
                <h2 class="h3 mb-4"><?= htmlspecialchars($errorTitle) ?></h2>
                <p class="lead text-muted mb-5"><?= htmlspecialchars($errorDescription) ?></p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                    <a href="<?= APP_URL ?>" class="btn btn-primary">
                        <i class="bi bi-house"></i> Página Inicial
                    </a>
                </div>
            </div>

            <?php if ($errorCode === '500'): ?>
            <div class="mt-4">
                <p class="text-muted small">
                    <i class="bi bi-info-circle"></i>
                    Se o problema persistir, entre em contato com o suporte.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.error-page {
    padding: 40px;
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}
.error-page h1 {
    font-weight: bold;
    color: #dc3545;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}
</style>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 