<?php
/**
 * Admin Jogos de Bolão - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/jogos-bolao.php';
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
    "SELECT b.*, a.nome as admin_nome FROM dados_boloes b
     LEFT JOIN administrador a ON a.id = b.admin_id
     WHERE b.id = ?", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Handle delete game request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $jogoId = (int) $_GET['delete'];
    
    // Check if game has bets
    $hasBets = dbFetchOne("SELECT COUNT(*) as total FROM palpites WHERE jogo_id = ?", [$jogoId]);
    
    if ($hasBets && $hasBets['total'] > 0) {
        setFlashMessage('warning', 'Este jogo não pode ser excluído pois possui palpites registrados.');
    } else {
        // Delete jogo
        if (dbDelete('jogos', 'id = ? AND bolao_id = ?', [$jogoId, $bolaoId])) {
            // Also delete related result if exists
            dbDelete('resultados', 'jogo_id = ?', [$jogoId]);
            setFlashMessage('success', 'Jogo excluído com sucesso.');
        } else {
            setFlashMessage('danger', 'Erro ao excluir o jogo.');
        }
    }
    
    redirect(APP_URL . '/admin/jogos-bolao.php?bolao_id=' . $bolaoId);
}

// Get jogos do bolão
$jogos = dbFetchAll(
    "SELECT j.*, 
            r.gols_casa, r.gols_visitante, r.status as resultado_status,
            (SELECT COUNT(*) FROM palpites WHERE jogo_id = j.id) as total_palpites
     FROM jogos j
     LEFT JOIN resultados r ON r.jogo_id = j.id
     WHERE j.bolao_id = ?
     ORDER BY j.data_hora ASC", 
    [$bolaoId]
);

// Page title
$pageTitle = 'Jogos do Bolão: ' . $bolao['nome'];
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Jogos: <?= htmlspecialchars($bolao['nome']) ?></h1>
        <div>
            <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Novo Jogo
            </a>
            <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>" class="btn btn-primary">
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
        <li class="breadcrumb-item active">Jogos</li>
    </ol>
    
    <?php $flashMessage = getFlashMessage(); ?>
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-futbol me-1"></i>
            Lista de Jogos
        </div>
        <div class="card-body">
            <?php if (empty($jogos)): ?>
                <div class="alert alert-info">
                    Nenhum jogo cadastrado para este bolão. 
                    <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="alert-link">Adicionar jogos</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Times</th>
                                <th>Data/Hora</th>
                                <th>Local</th>
                                <th>Status</th>
                                <th>Resultado</th>
                                <th>Palpites</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jogos as $jogo): ?>
                                <tr>
                                    <td><?= $jogo['id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($jogo['time_casa']) ?></div>
                                        <div class="text-muted">vs</div>
                                        <div class="fw-bold"><?= htmlspecialchars($jogo['time_visitante']) ?></div>
                                    </td>
                                    <td><?= formatDateTime($jogo['data_hora']) ?></td>
                                    <td><?= htmlspecialchars($jogo['local'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($jogo['status'] === 'agendado'): ?>
                                            <span class="badge bg-green">Agendado</span>
                                        <?php elseif ($jogo['status'] === 'em_andamento'): ?>
                                            <span class="badge bg-warning">Em andamento</span>
                                        <?php elseif ($jogo['status'] === 'finalizado'): ?>
                                            <span class="badge bg-success">Finalizado</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancelado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($jogo['gols_casa']) && isset($jogo['gols_visitante'])): ?>
                                            <span class="fw-bold"><?= $jogo['gols_casa'] ?> x <?= $jogo['gols_visitante'] ?></span>
                                            <?php if ($jogo['resultado_status'] === 'parcial'): ?>
                                                <small class="text-warning d-block">(Parcial)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $jogo['total_palpites'] ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= APP_URL ?>/admin/editar-jogo.php?id=<?= $jogo['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/admin/resultado-jogo.php?id=<?= $jogo['id'] ?>" class="btn btn-sm btn-info" title="Resultado">
                                                <i class="fas fa-clipboard-check"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/admin/palpites-jogo.php?id=<?= $jogo['id'] ?>" class="btn btn-sm btn-success" title="Ver Palpites">
                                                <i class="fas fa-list-ol"></i>
                                            </a>
                                            <?php if ($jogo['total_palpites'] == 0): ?>
                                                <a href="?bolao_id=<?= $bolaoId ?>&delete=<?= $jogo['id'] ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Tem certeza que deseja excluir este jogo?')" title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
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
</div>

<?php include '../templates/admin/footer.php'; ?> 