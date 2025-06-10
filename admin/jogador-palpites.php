<?php
/**
 * Admin Jogador Palpites - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/jogador-palpites.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get request params
$jogadorId = isset($_GET['jogador_id']) ? (int)$_GET['jogador_id'] : 0;
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : 0;

if ($jogadorId <= 0 || $bolaoId <= 0) {
    setFlashMessage('danger', 'Parâmetros inválidos.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get jogador data
$jogador = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$jogadorId]);

if (!$jogador) {
    setFlashMessage('danger', 'Jogador não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get bolão data
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ?", [$bolaoId]);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Check if jogador is participating in this bolão
$participacao = dbFetchOne(
    "SELECT * FROM participacoes WHERE jogador_id = ? AND bolao_id = ?",
    [$jogadorId, $bolaoId]
);

if (!$participacao) {
    setFlashMessage('warning', 'Este jogador não está participando deste bolão.');
    redirect(APP_URL . '/admin/bolao.php?id=' . $bolaoId);
}

// Get jogador's palpites with game and result information
$palpites = dbFetchAll(
    "SELECT p.*, 
            j.time_casa, j.time_visitante, j.data_hora, j.local, j.status as jogo_status,
            r.gols_casa as resultado_casa, r.gols_visitante as resultado_visitante, r.status as resultado_status
     FROM palpites p
     JOIN jogos j ON j.id = p.jogo_id
     LEFT JOIN resultados r ON r.jogo_id = j.id
     WHERE p.jogador_id = ? AND p.bolao_id = ?
     ORDER BY j.data_hora ASC", 
    [$jogadorId, $bolaoId]
);

// Get total points for this jogador in this bolão
$totalPoints = dbFetchOne(
    "SELECT SUM(pontos) as total FROM palpites WHERE jogador_id = ? AND bolao_id = ?",
    [$jogadorId, $bolaoId]
);
$pontuacaoTotal = $totalPoints ? $totalPoints['total'] : 0;

// Get jogador's ranking position
$ranking = dbFetchOne(
    "SELECT * FROM ranking WHERE jogador_id = ? AND bolao_id = ?",
    [$jogadorId, $bolaoId]
);

// Page title
$pageTitle = 'Palpites de ' . $jogador['nome'];
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Palpites de <?= htmlspecialchars($jogador['nome']) ?></h1>
        <div>
            <a href="<?= APP_URL ?>/admin/ranking.php?bolao_id=<?= $bolaoId ?>" class="btn btn-success">
                <i class="fas fa-trophy"></i> Voltar ao Ranking
            </a>
            <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>" class="btn btn-info">
                <i class="fas fa-info-circle"></i> Detalhes do Bolão
            </a>
        </div>
    </div>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/boloes.php">Bolões</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>">Detalhes</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/ranking.php?bolao_id=<?= $bolaoId ?>">Ranking</a></li>
        <li class="breadcrumb-item active">Palpites de <?= htmlspecialchars($jogador['nome']) ?></li>
    </ol>
    
    <!-- Jogador Info Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    Informações do Jogador
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nome:</strong> <?= htmlspecialchars($jogador['nome']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($jogador['email']) ?></p>
                            <p><strong>Telefone:</strong> <?= htmlspecialchars($jogador['telefone'] ?? 'Não informado') ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?= $jogador['status'] === 'ativo' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($jogador['status']) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Data de Entrada:</strong> <?= formatDateTime($participacao['data_entrada']) ?></p>
                            <p><strong>Total de Palpites:</strong> <?= count($palpites) ?></p>
                            <p><strong>Pontuação Total:</strong> <span class="badge bg-green"><?= $pontuacaoTotal ?> pontos</span></p>
                            <?php if ($ranking): ?>
                                <p><strong>Posição no Ranking:</strong> 
                                    <span class="badge bg-warning"><?= $ranking['posicao'] ?>º lugar</span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Estatísticas de Palpites
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <?php if ($ranking): ?>
                                <p><strong>Acertos Exatos:</strong> <?= $ranking['acertos_exatos'] ?></p>
                                <p><strong>Acertos Parciais:</strong> <?= $ranking['acertos_parciais'] ?></p>
                                <p><strong>Taxa de Acerto:</strong> 
                                    <?php
                                        $jogosComResultado = 0;
                                        $acertos = 0;
                                        foreach ($palpites as $palpite) {
                                            if (isset($palpite['resultado_casa']) && isset($palpite['resultado_visitante'])) {
                                                $jogosComResultado++;
                                                if ($palpite['pontos'] > 0) $acertos++;
                                            }
                                        }
                                        $taxaAcerto = $jogosComResultado > 0 ? ($acertos / $jogosComResultado) * 100 : 0;
                                        echo number_format($taxaAcerto, 1) . '%';
                                    ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted">Estatísticas não disponíveis</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Bolão:</strong> <?= htmlspecialchars($bolao['nome']) ?></p>
                            <p><strong>Status do Bolão:</strong> 
                                <span class="badge bg-<?= getBolaoStatusClass($bolao['status']) ?>">
                                    <?= ucfirst($bolao['status']) ?>
                                </span>
                            </p>
                            <p><strong>Período:</strong> <?= formatDate($bolao['data_inicio']) ?> a <?= formatDate($bolao['data_fim']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Palpites Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list-ol me-1"></i>
            Lista de Palpites
        </div>
        <div class="card-body">
            <?php if (empty($palpites)): ?>
                <div class="alert alert-info">
                    Este jogador ainda não fez nenhum palpite neste bolão.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Jogo</th>
                                <th>Data/Hora</th>
                                <th>Palpite</th>
                                <th>Resultado</th>
                                <th>Pontos</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($palpites as $palpite): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="text-end me-2 fw-bold"><?= htmlspecialchars($palpite['time_casa']) ?></div>
                                            <div class="text-center text-muted">vs</div>
                                            <div class="ms-2 fw-bold"><?= htmlspecialchars($palpite['time_visitante']) ?></div>
                                        </div>
                                        <?php if ($palpite['local']): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($palpite['local']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatDateTime($palpite['data_hora']) ?></td>
                                    <td class="text-center fw-bold">
                                        <?= $palpite['gols_casa'] ?> x <?= $palpite['gols_visitante'] ?>
                                        <small class="text-muted d-block">
                                            <?= formatDateTime($palpite['data_palpite']) ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php if (isset($palpite['resultado_casa']) && isset($palpite['resultado_visitante'])): ?>
                                            <span class="fw-bold">
                                                <?= $palpite['resultado_casa'] ?> x <?= $palpite['resultado_visitante'] ?>
                                            </span>
                                            <?php if ($palpite['resultado_status'] === 'parcial'): ?>
                                                <span class="badge bg-warning ms-1">Parcial</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($palpite['pontos'] > 0): ?>
                                            <span class="badge bg-success"><?= $palpite['pontos'] ?> pontos</span>
                                        <?php elseif (isset($palpite['resultado_casa']) && isset($palpite['resultado_visitante'])): ?>
                                            <span class="badge bg-danger">0 pontos</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pendente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = $palpite['jogo_status'];
                                            $statusClass = '';
                                            $statusText = '';
                                            
                                            switch ($status) {
                                                case 'agendado':
                                                    $statusClass = 'primary';
                                                    $statusText = 'Agendado';
                                                    break;
                                                case 'em_andamento':
                                                    $statusClass = 'warning';
                                                    $statusText = 'Em andamento';
                                                    break;
                                                case 'finalizado':
                                                    $statusClass = 'success';
                                                    $statusText = 'Finalizado';
                                                    break;
                                                case 'cancelado':
                                                    $statusClass = 'danger';
                                                    $statusText = 'Cancelado';
                                                    break;
                                                default:
                                                    $statusClass = 'secondary';
                                                    $statusText = 'Desconhecido';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
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

<?php
// Helper function to get class for bolão status
function getBolaoStatusClass($status) {
    switch ($status) {
        case 'aberto': return 'success';
        case 'fechado': return 'warning';
        case 'finalizado': return 'secondary';
        default: return 'primary';
    }
}
?>

<?php include '../templates/admin/footer.php'; ?> 