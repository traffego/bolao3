<?php
/**
 * Admin Dashboard - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Get dashboard data
$stats = [];

try {
    // Total bolões
    $stats['total_boloes'] = dbCount('dados_boloes') ?? 0;
    $stats['boloes_ativos'] = dbCount('dados_boloes', 'status = ?', [1]) ?? 0;
    $stats['boloes_inativos'] = $stats['total_boloes'] - $stats['boloes_ativos'];

    // Contadores de jogos
    $stats['total_jogos'] = 0;
    $stats['jogos_com_resultado'] = 0;
    $stats['jogos_pendentes'] = 0;

    // Buscar bolões ativos
    $boloes = dbFetchAll("SELECT id, nome, jogos FROM dados_boloes WHERE status = 1 ORDER BY data_criacao DESC LIMIT 5") ?? [];

    // Jogos recém atualizados
    $jogosAtualizados = [];
    $proximosJogos = [];

    // Data atual
    $hoje = new DateTime();

    // Percorrer bolões para encontrar jogos
    foreach ($boloes as $bolao) {
        $jogos = json_decode($bolao['jogos'], true) ?: [];
        
        // Contabilizar o número total de jogos
        $stats['total_jogos'] += count($jogos);
        
        foreach ($jogos as $jogo) {
            // Adicionar nome do bolão para referência
            $jogo['bolao_nome'] = $bolao['nome'];
            $jogo['bolao_id'] = $bolao['id'];
            
            // Verificar se tem resultado para listar como atualizado
            if (isset($jogo['resultado_casa']) && $jogo['resultado_casa'] !== null && 
                isset($jogo['resultado_visitante']) && $jogo['resultado_visitante'] !== null) {
                $jogosAtualizados[] = $jogo;
                $stats['jogos_com_resultado']++;
            } else {
                $stats['jogos_pendentes']++;
            }
            
            // Verificar se é um próximo jogo (data no futuro)
            if (isset($jogo['data_iso'])) {
                $dataJogo = new DateTime($jogo['data_iso']);
                if ($dataJogo > $hoje) {
                    $proximosJogos[] = $jogo;
                }
            }
        }
    }

    // Ordenar jogos atualizados pelos mais recentes (considerando status - em andamento primeiro)
    usort($jogosAtualizados, function($a, $b) {
        // Jogos em andamento têm prioridade
        if (in_array($a['status'], ['1H', '2H', 'HT', 'LIVE']) && !in_array($b['status'], ['1H', '2H', 'HT', 'LIVE'])) {
            return -1;
        }
        if (!in_array($a['status'], ['1H', '2H', 'HT', 'LIVE']) && in_array($b['status'], ['1H', '2H', 'HT', 'LIVE'])) {
            return 1;
        }
        
        // Em seguida, jogos finalizados recentemente
        if (isset($a['data_iso']) && isset($b['data_iso'])) {
            return strtotime($b['data_iso']) - strtotime($a['data_iso']);
        }
        return 0;
    });

    // Ordenar próximos jogos pela data (mais próximos primeiro)
    usort($proximosJogos, function($a, $b) {
        if (isset($a['data_iso']) && isset($b['data_iso'])) {
            return strtotime($a['data_iso']) - strtotime($b['data_iso']);
        }
        return 0;
    });

    // Limitar para os 5 jogos mais recentes/próximos
    $jogosAtualizados = array_slice($jogosAtualizados, 0, 5);
    $proximosJogos = array_slice($proximosJogos, 0, 5);

} catch (Exception $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    setFlashMessage('warning', 'Alguns dados podem estar indisponíveis no momento.');
}

// Include header
$pageTitle = "Dashboard";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <?php displayFlashMessages(); ?>
    
    <?php if (isset($_SESSION['api_status']) && $_SESSION['api_status']['status'] !== 'online'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Status da API Football:</strong> <?= $_SESSION['api_status']['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <!-- Bolões Ativos -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-lg rounded-circle bg-green-soft">
                                <i class="fas fa-futbol text-green"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted text-uppercase mb-1 small">Bolões Ativos</h6>
                            <h4 class="mb-0 fw-bold"><?= $stats['boloes_ativos'] ?? 0 ?></h4>
                            <a href="boloes.php" class="stretched-link text-decoration-none">
                                <span class="small text-muted">Ver Detalhes <i class="fas fa-arrow-right ms-1"></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jogos com Resultados -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-lg rounded-circle bg-warning-soft">
                                <i class="fas fa-sync-alt text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted text-uppercase mb-1 small">Jogos c/ Resultados</h6>
                            <h4 class="mb-0 fw-bold"><?= $stats['jogos_com_resultado'] ?? 0 ?></h4>
                            <a href="atualizar-jogos.php" class="stretched-link text-decoration-none">
                                <span class="small text-muted">Atualizar Resultados <i class="fas fa-arrow-right ms-1"></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total de Jogos -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-lg rounded-circle bg-success-soft">
                                <i class="fas fa-list text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted text-uppercase mb-1 small">Total de Jogos</h6>
                            <h4 class="mb-0 fw-bold"><?= $stats['total_jogos'] ?? 0 ?></h4>
                            <a href="boloes.php" class="stretched-link text-decoration-none">
                                <span class="small text-muted">Ver Detalhes <i class="fas fa-arrow-right ms-1"></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jogos Pendentes -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-lg rounded-circle bg-danger-soft">
                                <i class="fas fa-clock text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted text-uppercase mb-1 small">Jogos Pendentes</h6>
                            <h4 class="mb-0 fw-bold"><?= $stats['jogos_pendentes'] ?? 0 ?></h4>
                            <a href="boloes.php" class="stretched-link text-decoration-none">
                                <span class="small text-muted">Ver Detalhes <i class="fas fa-arrow-right ms-1"></i></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Jogos com Resultados -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-table me-1"></i>
                        Últimos Resultados
                    </div>
                    <a href="atualizar-jogos.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-sync-alt"></i> Atualizar
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($jogosAtualizados)): ?>
                        <div class="alert alert-info mb-0">
                            Nenhum jogo com resultado disponível no momento.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Partida</th>
                                        <th>Resultado</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jogosAtualizados as $jogo): ?>
                                        <tr>
                                            <td><?= isset($jogo['data_iso']) ? date('d/m/Y H:i', strtotime($jogo['data_iso'])) : 'N/A' ?></td>
                                            <td>
                                                <a href="ver-bolao.php?id=<?= $jogo['bolao_id'] ?? 0 ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($jogo['time_casa'] ?? '') ?> x <?= htmlspecialchars($jogo['time_visitante'] ?? '') ?>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($jogo['bolao_nome'] ?? '') ?></small>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <?= $jogo['resultado_casa'] ?? '-' ?> x <?= $jogo['resultado_visitante'] ?? '-' ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $statusClass = 'secondary';
                                                    $statusText = $jogo['status'] ?? 'N/A';
                                                    
                                                    switch ($statusText) {
                                                        case 'FT':
                                                            $statusClass = 'success';
                                                            $statusText = 'Finalizado';
                                                            break;
                                                        case '1H':
                                                            $statusClass = 'warning';
                                                            $statusText = '1º Tempo';
                                                            break;
                                                        case '2H':
                                                            $statusClass = 'warning';
                                                            $statusText = '2º Tempo';
                                                            break;
                                                        case 'HT':
                                                            $statusClass = 'info';
                                                            $statusText = 'Intervalo';
                                                            break;
                                                        case 'LIVE':
                                                            $statusClass = 'warning';
                                                            $statusText = 'Ao Vivo';
                                                            break;
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

        <!-- Próximos Jogos -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar me-1"></i>
                    Próximos Jogos
                </div>
                <div class="card-body">
                    <?php if (empty($proximosJogos)): ?>
                        <div class="alert alert-info mb-0">
                            Nenhum jogo agendado no momento.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Partida</th>
                                        <th>Bolão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proximosJogos as $jogo): ?>
                                        <tr>
                                            <td><?= isset($jogo['data_iso']) ? date('d/m/Y H:i', strtotime($jogo['data_iso'])) : 'N/A' ?></td>
                                            <td>
                                                <?= htmlspecialchars($jogo['time_casa'] ?? '') ?> x <?= htmlspecialchars($jogo['time_visitante'] ?? '') ?>
                                            </td>
                                            <td>
                                                <a href="ver-bolao.php?id=<?= $jogo['bolao_id'] ?? 0 ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($jogo['bolao_nome'] ?? '') ?>
                                                </a>
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
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 