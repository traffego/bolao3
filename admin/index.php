<?php
/**
 * Admin Dashboard - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Get dashboard data
$stats = [];

// Total bolões
$stats['total_boloes'] = dbCount('dados_boloes');
$stats['boloes_ativos'] = dbCount('dados_boloes', 'status = ?', [1]);
$stats['boloes_inativos'] = $stats['total_boloes'] - $stats['boloes_ativos'];

// Contadores de jogos
$stats['total_jogos'] = 0;
$stats['jogos_com_resultado'] = 0;
$stats['jogos_pendentes'] = 0;

// Buscar bolões ativos
$boloes = dbFetchAll("SELECT id, nome, jogos, data_limite_palpitar FROM dados_boloes WHERE status = 1 ORDER BY data_criacao DESC LIMIT 5");

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

// Bolões com prazo próximo do fim
$boloesComPrazoProximo = [];
foreach ($boloes as $bolao) {
    if (!empty($bolao['data_limite_palpitar'])) {
        $dataLimite = new DateTime($bolao['data_limite_palpitar']);
        $diferenca = $hoje->diff($dataLimite);
        
        // Se a data limite é no futuro e falta menos de 3 dias
        if ($dataLimite > $hoje && $diferenca->days < 3) {
            $bolao['dias_restantes'] = $diferenca->days;
            $boloesComPrazoProximo[] = $bolao;
        }
    }
}

// Include header
$pageTitle = "Dashboard Administrativo";
$currentPage = "dashboard";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Dashboard</h1>
    <?php displayFlashMessages(); ?>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
    
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-green text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Bolões Ativos
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stats['boloes_ativos'] ?></div>
                        </div>
    <div>
                            <i class="fas fa-futbol fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="boloes.php">Ver Detalhes</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
    </div>
</div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Jogos c/ Resultados
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stats['jogos_com_resultado'] ?></div>
                        </div>
                        <div>
                            <i class="fas fa-sync-alt fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="atualizar-jogos.php">Atualizar Resultados</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
            <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Total de Jogos
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stats['total_jogos'] ?></div>
                        </div>
                        <div>
                            <i class="fas fa-list fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="boloes.php">Ver Detalhes</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Jogos Pendentes
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stats['jogos_pendentes'] ?></div>
                        </div>
                        <div>
                            <i class="fas fa-clock fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="boloes.php">Ver Detalhes</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
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
                                        <?php
                                            $statusClass = 'secondary';
                                            $statusText = $jogo['status'];
                                            
                                            switch ($jogo['status']) {
                                                case 'NS':
                                                    $statusText = 'Não iniciado';
                                                    break;
                                                case 'LIVE':
                                                case '1H':
                                                case '2H':
                                                case 'HT':
                                                    $statusClass = 'danger';
                                                    $statusText = 'Em andamento';
                                                    break;
                                                case 'FT':
                                                case 'AET':
                                                case 'PEN':
                                                    $statusClass = 'success';
                                                    $statusText = 'Finalizado';
                                                    break;
                                                case 'SUSP':
                                                case 'INT':
                                                    $statusClass = 'warning';
                                                    $statusText = 'Suspenso';
                                                    break;
                                                case 'PST':
                                                case 'CANC':
                                                case 'ABD':
                                                    $statusClass = 'danger';
                                                    $statusText = 'Cancelado';
                                                    break;
                                            }
                                        ?>
                                        <tr class="<?= in_array($jogo['status'], ['1H', '2H', 'HT', 'LIVE']) ? 'table-warning' : '' ?>">
                                            <td><?= $jogo['data'] ?></td>
                                            <td>
                                                <strong class="d-block"><?= htmlspecialchars($jogo['time_casa']) ?> x <?= htmlspecialchars($jogo['time_visitante']) ?></strong>
                                                <small class="text-muted"><?= htmlspecialchars($jogo['bolao_nome']) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-green fs-5">
                                                    <?= $jogo['resultado_casa'] ?> x <?= $jogo['resultado_visitante'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= $statusText ?>
                                                </span>
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
                    <i class="fas fa-calendar-alt me-1"></i>
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
                                        <th>Campeonato</th>
                                        <th>Bolão</th>
                                </tr>
                            </thead>
                            <tbody>
                                    <?php foreach ($proximosJogos as $jogo): ?>
                                        <tr>
                                            <td><?= $jogo['data'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($jogo['time_casa']) ?></strong>
                                                x 
                                                <strong><?= htmlspecialchars($jogo['time_visitante']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?= htmlspecialchars($jogo['campeonato']) ?>
                                                </span>
                                        </td>
                                            <td>
                                                <a href="ver-bolao.php?id=<?= $jogo['bolao_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($jogo['bolao_nome']) ?>
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
    
    <!-- Bolões com Prazo Próximo -->
    <?php if (!empty($boloesComPrazoProximo)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Bolões com Prazo de Apostas Próximo do Fim
            </div>
            <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Bolão</th>
                                    <th>Data Limite</th>
                                    <th>Tempo Restante</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($boloesComPrazoProximo as $bolao): ?>
                                    <?php
                                        $dataLimite = new DateTime($bolao['data_limite_palpitar']);
                                        $agora = new DateTime();
                                        $diferenca = $agora->diff($dataLimite);
                                        
                                        $horasRestantes = $diferenca->h + ($diferenca->days * 24);
                                        
                                        $alertClass = '';
                                        if ($horasRestantes < 6) {
                                            $alertClass = 'table-danger';
                                        } elseif ($horasRestantes < 24) {
                                            $alertClass = 'table-warning';
                                        }
                                    ?>
                                    <tr class="<?= $alertClass ?>">
                                        <td><?= htmlspecialchars($bolao['nome']) ?></td>
                                        <td><?= formatDateTime($bolao['data_limite_palpitar']) ?></td>
                                        <td>
                                            <?php if ($diferenca->days > 0): ?>
                                                <?= $diferenca->days ?> dia(s) <?= $diferenca->h ?> hora(s)
                                            <?php else: ?>
                                                <?= $diferenca->h ?> hora(s) <?= $diferenca->i ?> minuto(s)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="ver-bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> Ver Bolão
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Ações Rápidas e Links -->
            <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tools me-1"></i>
                    Ações Rápidas
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="novo-bolao.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Criar Novo Bolão
                        </a>
                        <a href="atualizar-jogos.php" class="btn btn-success">
                            <i class="fas fa-sync-alt"></i> Atualizar Resultados dos Jogos
                        </a>
                        <a href="../boloes.php" target="_blank" class="btn btn-info">
                            <i class="fas fa-external-link-alt"></i> Ver Área Pública
                        </a>
                        <a href="configuracoes.php" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Configurações do Sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Informações do Sistema
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Versão do Sistema
                            <span class="badge bg-green"><?= APP_VERSION ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            API Football
                            <span class="badge bg-<?= $apiConfig && !empty($apiConfig['api_key']) ? 'success' : 'danger' ?>">
                                <?= $apiConfig && !empty($apiConfig['api_key']) ? 'Configurada' : 'Não Configurada' ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total de Bolões
                            <span class="badge bg-info"><?= $stats['total_boloes'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Data Atual
                            <span class="badge bg-dark"><?= date('d/m/Y H:i') ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 