<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    redirect(APP_URL . '/login.php');
}

// Título da página
$pageTitle = 'Palpite Confirmado';
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="card">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h2 class="card-title mb-4">Palpite Confirmado com Sucesso!</h2>
                    <p class="card-text mb-4">
                        Seu palpite foi registrado e confirmado em nosso sistema.
                        Agora é só torcer para seus resultados!
                    </p>
                    <div class="d-grid gap-3 col-md-6 mx-auto">
                        <a href="<?= APP_URL ?>/meus-palpites.php" class="btn btn-primary">
                            <i class="bi bi-list-check"></i> Ver Meus Palpites
                        </a>
                        <a href="<?= APP_URL ?>/boloes.php" class="btn btn-outline-primary">
                            <i class="bi bi-trophy"></i> Ver Outros Bolões
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 