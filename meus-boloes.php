<?php
require_once 'config/config.php';require_once 'includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    redirect('/login.php');
}

// Page title
$pageTitle = 'Meus Bolões';

// Get user boloes
$userId = $_SESSION['user_id'];
$boloes = dbFetchAll("SELECT 
                        b.*,
                        COUNT(DISTINCT p2.jogador_id) as total_jogadores,
                        p.pontos,
                        (
                            SELECT COUNT(*) + 1 
                            FROM palpites p_rank 
                            WHERE p_rank.bolao_id = b.id 
                            AND p_rank.pontos > p.pontos
                        ) as posicao,
                        a.nome as admin_nome
                     FROM boloes b
                     JOIN palpites p ON p.bolao_id = b.id AND p.jogador_id = ?
                     LEFT JOIN palpites p2 ON p2.bolao_id = b.id
                     JOIN administrador a ON a.id = b.admin_id
                     GROUP BY b.id, p.pontos
                     ORDER BY b.data_fim DESC", [$userId]);

$palpites = dbFetchAll("
    SELECT p.*, b.nome as bolao_nome, b.data_inicio, b.data_fim, b.valor_participacao, b.premio_total
    FROM palpites p
    JOIN dados_boloes b ON p.bolao_id = b.id
    WHERE p.jogador_id = ?
    ORDER BY p.data_palpite DESC",
    [$userId]
);

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Meus Bolões</h1>
        <a href="<?= APP_URL ?>/boloes.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Participar de Novo Bolão
        </a>
    </div>

    <?php if (empty($boloes)): ?>
        <div class="alert alert-info">
            <p class="mb-0">Você ainda não participa de nenhum bolão. 
            <a href="<?= APP_URL ?>/boloes.php" class="alert-link">Clique aqui</a> para ver os bolões disponíveis.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($boloes as $bolao): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= sanitize($bolao['titulo']) ?></h5>
                            <?php if ($bolao['status'] === 'aberto'): ?>
                                <span class="badge bg-success">Aberto</span>
                            <?php elseif ($bolao['status'] === 'em_andamento'): ?>
                                <span class="badge bg-warning">Em Andamento</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Encerrado</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p class="mb-1"><strong>Sua Posição:</strong> <?= $bolao['posicao'] ?>º lugar</p>
                                <p class="mb-1"><strong>Seus Pontos:</strong> <?= $bolao['pontos'] ?? 0 ?></p>
                                <p class="mb-1"><strong>Total de Jogadores:</strong> <?= $bolao['total_jogadores'] ?></p>
                                <p class="mb-0"><strong>Organizador:</strong> <?= sanitize($bolao['admin_nome']) ?></p>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-primary flex-grow-1">
                                    Ver Detalhes
                                </a>
                                <a href="<?= APP_URL ?>/meus-palpites.php?bolao_id=<?= $bolao['id'] ?>" class="btn btn-outline-primary flex-grow-1">
                                    Meus Palpites
                                </a>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between text-muted">
                                <small>Início: <?= formatDate($bolao['data_inicio']) ?></small>
                                <small>Término: <?= formatDate($bolao['data_fim']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 