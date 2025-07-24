<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/NotificacaoManager.php';

// Verificar se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para ver suas notificações.');
    redirect(APP_URL . '/login.php');
}

$jogadorId = getCurrentUserId();
$notificacaoManager = new NotificacaoManager();

// Marcar como lida se solicitado
if (isset($_GET['marcar_lida'])) {
    $notificacaoId = filter_input(INPUT_GET, 'marcar_lida', FILTER_VALIDATE_INT);
    if ($notificacaoId) {
        $notificacaoManager->marcarComoLida($notificacaoId, $jogadorId);
    }
}

// Marcar todas como lidas se solicitado
if (isset($_GET['marcar_todas'])) {
    $notificacaoManager->marcarTodasComoLidas($jogadorId);
    setFlashMessage('success', 'Todas as notificações foram marcadas como lidas.');
    redirect(APP_URL . '/notificacoes.php');
}

// Buscar notificações
$notificacoes = $notificacaoManager->buscarPorJogador($jogadorId, false, 50);
$naoLidas = $notificacaoManager->contarNaoLidas($jogadorId);

// Page title
$pageTitle = "Minhas Notificações";

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $pageTitle ?></h1>
        <?php if ($naoLidas > 0): ?>
            <a href="?marcar_todas=1" class="btn btn-secondary">
                <i class="fas fa-check-double"></i> Marcar Todas Como Lidas
            </a>
        <?php endif; ?>
    </div>

    <?php displayFlashMessages(); ?>

    <?php if (empty($notificacoes)): ?>
        <div class="alert alert-info">
            Você não tem nenhuma notificação.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($notificacoes as $notificacao): ?>
                <div class="col-12 mb-3">
                    <div class="card <?= $notificacao['lida'] ? 'bg-light' : 'border-primary' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title mb-1">
                                    <?php if (!$notificacao['lida']): ?>
                                        <span class="badge bg-primary me-2">Nova</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($notificacao['titulo']) ?>
                                </h5>
                                <small class="text-muted">
                                    <?= formatDateTime($notificacao['data_criacao']) ?>
                                </small>
                            </div>
                            
                            <p class="card-text mt-2">
                                <?= nl2br(htmlspecialchars($notificacao['mensagem'])) ?>
                            </p>
                            
                            <?php 
                            $dados = json_decode($notificacao['dados_adicionais'], true);
                            if ($dados && isset($dados['comprovante_url'])): 
                            ?>
                                <div class="mt-2">
                                    <a href="<?= $dados['comprovante_url'] ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-file-alt"></i> Ver Comprovante
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!$notificacao['lida']): ?>
                                <div class="text-end mt-2">
                                    <a href="?marcar_lida=<?= $notificacao['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-check"></i> Marcar Como Lida
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?> 