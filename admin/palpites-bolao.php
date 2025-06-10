<?php
/**
 * Admin Palpites do Bolão - Bolão Football
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/palpites-bolao.php';
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

// Parse filters
$jogoId = isset($_GET['jogo_id']) ? (int)$_GET['jogo_id'] : 0;
$jogadorId = isset($_GET['jogador_id']) ? (int)$_GET['jogador_id'] : 0;

// Build where clause based on filters
$whereClause = " WHERE p.bolao_id = ? ";
$params = [$bolaoId];

if ($jogoId > 0) {
    $whereClause .= " AND p.jogo_id = ? ";
    $params[] = $jogoId;
}

if ($jogadorId > 0) {
    $whereClause .= " AND p.jogador_id = ? ";
    $params[] = $jogadorId;
}

// Get palpites with related data
$palpites = dbFetchAll(
    "SELECT p.*, 
            j.nome as jogador_nome, j.email as jogador_email,
            g.time_casa, g.time_visitante, g.data_hora, g.local, g.status as jogo_status,
            r.gols_casa as resultado_casa, r.gols_visitante as resultado_visitante, r.status as resultado_status
     FROM palpites p
     JOIN jogador j ON j.id = p.jogador_id
     JOIN jogos g ON g.id = p.jogo_id
     LEFT JOIN resultados r ON r.jogo_id = g.id
     $whereClause
     ORDER BY g.data_hora ASC, j.nome ASC", 
    $params
);

// Get all games for the filter
$jogos = dbFetchAll(
    "SELECT id, time_casa, time_visitante, data_hora, status 
     FROM jogos 
     WHERE bolao_id = ? 
     ORDER BY data_hora ASC",
    [$bolaoId]
);

// Get all players for the filter
$jogadores = dbFetchAll(
    "SELECT j.id, j.nome, j.email
     FROM jogador j
     JOIN participacoes p ON p.jogador_id = j.id
     WHERE p.bolao_id = ?
     ORDER BY j.nome ASC",
    [$bolaoId]
);

// Statistics
$totalPalpites = count($palpites);
$acertosExatos = 0;
$acertosParciais = 0;
$erros = 0;
$pendentes = 0;

foreach ($palpites as $palpite) {
    if (!isset($palpite['resultado_casa']) || !isset($palpite['resultado_visitante'])) {
        $pendentes++;
    } else if ($palpite['pontos'] == 10) {
        $acertosExatos++;
    } else if ($palpite['pontos'] > 0) {
        $acertosParciais++;
    } else {
        $erros++;
    }
}

// Page title
$pageTitle = 'Palpites do Bolão: ' . $bolao['nome'];
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';

// Helper function to get result type (1=home wins, 0=draw, 2=away wins)
function getResultType($gols_casa, $gols_visitante) {
    if ($gols_casa > $gols_visitante) return "1";
    if ($gols_casa < $gols_visitante) return "2";
    return "0";
}

// Helper function to get result text
function getResultText($tipo) {
    switch ($tipo) {
        case "1": return "Casa vence";
        case "2": return "Visitante vence";
        case "0": return "Empate";
        default: return "Desconhecido";
    }
}

// Helper function to get result class
function getResultClass($tipo) {
    switch ($tipo) {
        case "1": return "text-success";
        case "2": return "text-danger";
        case "0": return "text-warning";
        default: return "text-muted";
    }
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Palpites: <?= htmlspecialchars($bolao['nome']) ?></h1>
        <div>
            <a href="<?= APP_URL ?>/admin/ranking.php?bolao_id=<?= $bolaoId ?>" class="btn btn-success">
                <i class="bi bi-trophy"></i> Ver Ranking
            </a>
            <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>" class="btn btn-info">
                <i class="bi bi-info-circle"></i> Detalhes do Bolão
            </a>
            <a href="<?= APP_URL ?>/admin/boloes.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/boloes.php">Bolões</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>">Detalhes</a></li>
        <li class="breadcrumb-item active">Palpites</li>
    </ol>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div>Total de Palpites</div>
                            <h2 class="mb-0"><?= $totalPalpites ?></h2>
                        </div>
                        <i class="bi bi-list-ol fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div>Acertos Exatos</div>
                            <h2 class="mb-0"><?= $acertosExatos ?></h2>
                            <small><?= $totalPalpites ? number_format(($acertosExatos / $totalPalpites) * 100, 1) : 0 ?>%</small>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div>Acertos Parciais</div>
                            <h2 class="mb-0"><?= $acertosParciais ?></h2>
                            <small><?= $totalPalpites ? number_format(($acertosParciais / $totalPalpites) * 100, 1) : 0 ?>%</small>
                        </div>
                        <i class="bi bi-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div>Erros</div>
                            <h2 class="mb-0"><?= $erros ?></h2>
                            <small><?= $totalPalpites ? number_format(($erros / $totalPalpites) * 100, 1) : 0 ?>%</small>
                        </div>
                        <i class="bi bi-x-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-filter me-1"></i>
            Filtros
        </div>
        <div class="card-body">
            <form method="get" action="" class="row">
                <input type="hidden" name="bolao_id" value="<?= $bolaoId ?>">
                
                <div class="col-md-5 mb-3">
                    <label for="jogo_id" class="form-label">Jogo</label>
                    <select class="form-select" id="jogo_id" name="jogo_id">
                        <option value="0">Todos os jogos</option>
                        <?php foreach ($jogos as $jogo): ?>
                            <option value="<?= $jogo['id'] ?>" <?= $jogo['id'] == $jogoId ? 'selected' : '' ?>>
                                <?= formatDateTime($jogo['data_hora']) ?> - 
                                <?= htmlspecialchars($jogo['time_casa']) ?> vs 
                                <?= htmlspecialchars($jogo['time_visitante']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-5 mb-3">
                    <label for="jogador_id" class="form-label">Jogador</label>
                    <select class="form-select" id="jogador_id" name="jogador_id">
                        <option value="0">Todos os jogadores</option>
                        <?php foreach ($jogadores as $jogador): ?>
                            <option value="<?= $jogador['id'] ?>" <?= $jogador['id'] == $jogadorId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($jogador['nome']) ?> (<?= htmlspecialchars($jogador['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="<?= APP_URL ?>/admin/palpites-bolao.php?bolao_id=<?= $bolaoId ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Palpites Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-clipboard-list me-1"></i>
            Lista de Palpites
        </div>
        <div class="card-body">
            <?php if (empty($palpites)): ?>
                <div class="alert alert-info">
                    Nenhum palpite encontrado para os filtros selecionados.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th>Jogo</th>
                                <th>Data/Hora</th>
                                <th>Palpite</th>
                                <th>Resultado</th>
                                <th>Pontos</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($palpites as $palpite): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($palpite['jogador_nome']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($palpite['jogador_email']) ?></small>
                                    </td>
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
                                    <td>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Jogo</th>
                                                        <th>Palpite</th>
                                                        <th>Resultado</th>
                                                        <th>Pontos</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($jogos as $jogo): ?>
                                                        <?php 
                                                            $palpiteJogo = $palpite['palpites_array'][$jogo['id']] ?? null;
                                                            if (!$palpiteJogo) continue;
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <small>
                                                                    <?= htmlspecialchars($jogo['time_casa']) ?> x 
                                                                    <?= htmlspecialchars($jogo['time_visitante']) ?>
                                                                    <br>
                                                                    <span class="text-muted"><?= formatDateTime($jogo['data_hora']) ?></span>
                                                                </small>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="fw-bold <?= getResultClass($palpiteJogo) ?>">
                                                                    <?= getResultText($palpiteJogo) ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if (isset($jogo['resultado_casa']) && isset($jogo['resultado_visitante'])): ?>
                                                                    <?php 
                                                                        $resultadoTipo = getResultType($jogo['resultado_casa'], $jogo['resultado_visitante']);
                                                                    ?>
                                                                    <span class="fw-bold <?= getResultClass($resultadoTipo) ?>">
                                                                        <?= getResultText($resultadoTipo) ?>
                                                                    </span>
                                                                    <?php if ($jogo['resultado_status'] === 'parcial'): ?>
                                                                        <span class="badge bg-warning ms-1">Parcial</span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Aguardando</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php
                                                                    if (isset($jogo['resultado_casa']) && isset($jogo['resultado_visitante'])) {
                                                                        $resultadoTipo = getResultType($jogo['resultado_casa'], $jogo['resultado_visitante']);
                                                                        if ($palpiteJogo === $resultadoTipo) {
                                                                            echo '<span class="badge bg-success">10 pontos</span>';
                                                                        } else {
                                                                            echo '<span class="badge bg-danger">0 pontos</span>';
                                                                        }
                                                                    } else {
                                                                        echo '<span class="badge bg-secondary">Pendente</span>';
                                                                    }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
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
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= APP_URL ?>/admin/jogador-palpites.php?jogador_id=<?= $palpite['jogador_id'] ?>&bolao_id=<?= $bolaoId ?>" 
                                               class="btn btn-sm btn-info" title="Ver todos os palpites deste jogador">
                                                <i class="bi bi-person-lines-fill"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/admin/jogo.php?id=<?= $palpite['jogo_id'] ?>" 
                                               class="btn btn-sm btn-primary" title="Ver detalhes do jogo">
                                                <i class="bi bi-trophy"></i>
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
</div>

<?php include '../templates/admin/footer.php'; ?> 