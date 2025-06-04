<?php
/**
 * Admin Ranking - Bolão Football
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/ranking.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID from URL
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : 0;

if ($bolaoId <= 0) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get bolão data
$bolao = dbFetchOne(
    "SELECT b.*, a.nome as admin_nome FROM boloes b
     LEFT JOIN administrador a ON a.id = b.admin_id
     WHERE b.id = ?", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Handle recalculate ranking request
if (isset($_GET['recalcular']) && $_GET['recalcular'] == 1) {
    // Call the update ranking function
    $success = updateRanking($bolaoId);
    
    if ($success) {
        setFlashMessage('success', 'Ranking recalculado com sucesso!');
    } else {
        setFlashMessage('danger', 'Erro ao recalcular o ranking.');
    }
    
    redirect(APP_URL . '/admin/ranking.php?bolao_id=' . $bolaoId);
}

// Get ranking data
$ranking = dbFetchAll(
    "SELECT r.*, j.nome, j.email, 
            (SELECT COUNT(*) FROM palpites WHERE jogador_id = r.jogador_id AND bolao_id = r.bolao_id) as total_palpites,
            (SELECT COUNT(*) FROM jogos WHERE bolao_id = r.bolao_id) as total_jogos
     FROM ranking r
     JOIN jogador j ON j.id = r.jogador_id
     WHERE r.bolao_id = ?
     ORDER BY r.posicao ASC, r.pontos_total DESC", 
    [$bolaoId]
);

// If ranking is empty, try to auto-generate it
if (empty($ranking)) {
    updateRanking($bolaoId);
    
    // Try to get ranking data again
    $ranking = dbFetchAll(
        "SELECT r.*, j.nome, j.email, 
                (SELECT COUNT(*) FROM palpites WHERE jogador_id = r.jogador_id AND bolao_id = r.bolao_id) as total_palpites,
                (SELECT COUNT(*) FROM jogos WHERE bolao_id = r.bolao_id) as total_jogos
         FROM ranking r
         JOIN jogador j ON j.id = r.jogador_id
         WHERE r.bolao_id = ?
         ORDER BY r.posicao ASC, r.pontos_total DESC", 
        [$bolaoId]
    );
}

// Get total completed games
$completedGames = dbFetchOne(
    "SELECT COUNT(*) as total FROM jogos WHERE bolao_id = ? AND status = 'finalizado'",
    [$bolaoId]
);
$totalCompletedGames = $completedGames ? $completedGames['total'] : 0;

// Page title
$pageTitle = 'Ranking do Bolão: ' . $bolao['nome'];
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Ranking: <?= htmlspecialchars($bolao['nome']) ?></h1>
        <div>
            <a href="<?= APP_URL ?>/admin/ranking.php?bolao_id=<?= $bolaoId ?>&recalcular=1" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Recalcular Ranking
            </a>
            <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>" class="btn btn-info">
                <i class="fas fa-info-circle"></i> Detalhes do Bolão
            </a>
            <a href="<?= APP_URL ?>/admin/boloes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/boloes.php">Bolões</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>">Detalhes</a></li>
        <li class="breadcrumb-item active">Ranking</li>
    </ol>
    
    <?php $flashMessage = getFlashMessage(); ?>
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-trophy me-1"></i>
                Classificação dos Jogadores
            </div>
            <div>
                <span class="badge bg-info">
                    <i class="fas fa-futbol"></i> Jogos Finalizados: <?= $totalCompletedGames ?>
                </span>
                <span class="badge bg-success ms-2">
                    <i class="fas fa-users"></i> Participantes: <?= count($ranking) ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($ranking)): ?>
                <div class="alert alert-info">
                    Nenhum jogador participando deste bolão ou o ranking ainda não foi calculado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr class="table-dark">
                                <th>Posição</th>
                                <th>Jogador</th>
                                <th>Pontos</th>
                                <th>Acertos Exatos</th>
                                <th>Acertos Parciais</th>
                                <th>Palpites</th>
                                <th>Aproveitamento</th>
                                <th>Última Atualização</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking as $index => $item): ?>
                                <tr class="<?= $index < 3 ? 'table-success' : '' ?>">
                                    <td>
                                        <span class="badge rounded-pill bg-<?= getPosicaoClass($index) ?>">
                                            <?= $item['posicao'] ?>º
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($item['nome']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($item['email']) ?></small>
                                    </td>
                                    <td class="text-center fw-bold"><?= $item['pontos_total'] ?></td>
                                    <td class="text-center"><?= $item['acertos_exatos'] ?></td>
                                    <td class="text-center"><?= $item['acertos_parciais'] ?></td>
                                    <td class="text-center">
                                        <?= $item['total_palpites'] ?> / <?= $item['total_jogos'] ?>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" 
                                                style="width: <?= ($item['total_palpites'] / max(1, $item['total_jogos'])) * 100 ?>%;" 
                                                aria-valuenow="<?= $item['total_palpites'] ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="<?= $item['total_jogos'] ?>">
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $aproveitamento = 0;
                                            if ($totalCompletedGames > 0) {
                                                $aproveitamento = ($item['pontos_total'] / ($totalCompletedGames * 10)) * 100;
                                            }
                                            echo number_format($aproveitamento, 1) . '%';
                                        ?>
                                    </td>
                                    <td><?= formatDateTime($item['data_atualizacao']) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= APP_URL ?>/admin/jogador-palpites.php?jogador_id=<?= $item['jogador_id'] ?>&bolao_id=<?= $bolaoId ?>" 
                                               class="btn btn-sm btn-info" title="Ver Palpites">
                                                <i class="fas fa-list-ol"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/admin/jogador.php?id=<?= $item['jogador_id'] ?>" 
                                               class="btn btn-sm btn-primary" title="Perfil do Jogador">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>
            Informações do Ranking
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Regras de Pontuação</h5>
                    <ul>
                        <li><strong>Acerto exato (placar correto):</strong> 10 pontos</li>
                        <li><strong>Acerto do vencedor (sem placar exato):</strong> 5 pontos</li>
                        <li><strong>Acerto de empate (sem placar exato):</strong> 3 pontos</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Observações</h5>
                    <ul>
                        <li>O ranking é atualizado após o registro de cada resultado</li>
                        <li>Em caso de empate, o desempate é feito pelo número de acertos exatos</li>
                        <li>Se o empate persistir, o desempate é feito pelo número de acertos parciais</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to get the class for position badges
function getPosicaoClass($index) {
    switch ($index) {
        case 0: return 'warning'; // gold
        case 1: return 'secondary'; // silver
        case 2: return 'danger'; // bronze
        default: return 'primary';
    }
}
?>

<?php include '../templates/admin/footer.php'; ?> 