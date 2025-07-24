<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/ContaManager.php';

// Verifica se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para acessar seu histórico.');
    redirect(APP_URL . '/login.php');
}

$contaManager = new ContaManager();
$jogadorId = getCurrentUserId();

// Configuração da paginação
$itensPorPagina = 50;
$paginaAtual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Filtros com validação
$filtroTipo = filter_input(INPUT_GET, 'tipo');
$filtroStatus = filter_input(INPUT_GET, 'status');

// Validação das datas
$dataInicio = filter_input(INPUT_GET, 'data_inicio');
$dataFim = filter_input(INPUT_GET, 'data_fim');

// Validar formato das datas
if ($dataInicio) {
    $date = DateTime::createFromFormat('Y-m-d', $dataInicio);
    if (!$date || $date->format('Y-m-d') !== $dataInicio) {
        $dataInicio = null;
    }
}

if ($dataFim) {
    $date = DateTime::createFromFormat('Y-m-d', $dataFim);
    if (!$date || $date->format('Y-m-d') !== $dataFim) {
        $dataFim = null;
    }
}

try {
    // Busca a conta do jogador primeiro
    $sql = "SELECT id FROM contas WHERE jogador_id = ?";
    $conta = dbFetchOne($sql, [getCurrentUserId()]);
    
    if (!$conta) {
        throw new Exception('Conta não encontrada');
    }

    error_log("Conta encontrada: " . print_r($conta, true));

    // Construir a query base
    $sqlBase = "
        FROM transacoes t
        WHERE t.conta_id = ?";
    $params = [$conta['id']];

    error_log("ID da conta usado na query: " . $conta['id']);

    // Adicionar filtros
    if ($filtroTipo) {
        $sqlBase .= " AND t.tipo = ?";
        $params[] = $filtroTipo;
    }
    if ($filtroStatus) {
        $sqlBase .= " AND t.status = ?";
        $params[] = $filtroStatus;
    }
    if ($dataInicio) {
        $sqlBase .= " AND DATE(t.data_processamento) >= ?";
        $params[] = $dataInicio;
    }
    if ($dataFim) {
        $sqlBase .= " AND DATE(t.data_processamento) <= ?";
        $params[] = $dataFim;
    }

    // Debug da query base
    error_log("Query Base: " . $sqlBase);
    error_log("Parâmetros: " . print_r($params, true));

    // Calcular totais filtrados primeiro
    $sqlTotais = "
        SELECT 
            COALESCE(SUM(CASE WHEN t.tipo = 'deposito' THEN t.valor ELSE 0 END), 0) as total_depositos,
            COALESCE(SUM(CASE WHEN t.tipo = 'saque' THEN t.valor ELSE 0 END), 0) as total_saques,
            COALESCE(SUM(CASE WHEN t.tipo = 'aposta' THEN t.valor ELSE 0 END), 0) as total_apostas,
            COALESCE(SUM(CASE WHEN t.tipo = 'premio' THEN t.valor ELSE 0 END), 0) as total_premios,
            COALESCE(SUM(CASE WHEN t.tipo = 'bonus' THEN t.valor ELSE 0 END), 0) as total_bonus,
            COALESCE(SUM(CASE WHEN t.tipo = 'deposito' AND t.status = 'pendente' THEN t.valor ELSE 0 END), 0) as depositos_pendentes,
            COALESCE(SUM(CASE WHEN t.tipo = 'saque' AND t.status = 'pendente' THEN t.valor ELSE 0 END), 0) as saques_pendentes
        " . $sqlBase;
    
    $totais = dbFetchOne($sqlTotais, $params);
    error_log("Totais: " . print_r($totais, true));

    // Se a query falhar, define valores padrão
    if (!$totais) {
        $totais = [
            'total_depositos' => 0,
            'total_saques' => 0,
            'total_apostas' => 0,
            'total_premios' => 0,
            'total_bonus' => 0,
            'depositos_pendentes' => 0,
            'saques_pendentes' => 0
        ];
    }

    // Garantir que todos os índices existam
    $totais = array_merge([
        'total_depositos' => 0,
        'total_saques' => 0,
        'total_apostas' => 0,
        'total_premios' => 0,
        'total_bonus' => 0,
        'depositos_pendentes' => 0,
        'saques_pendentes' => 0
    ], $totais);

    // Contar total de registros
    $sqlCount = "SELECT COUNT(*) as total " . $sqlBase;
    $resultado = dbFetchOne($sqlCount, $params);
    $totalRegistros = $resultado ? intval($resultado['total']) : 0;
    $totalPaginas = ceil($totalRegistros / $itensPorPagina);

    error_log("Total de registros: " . $totalRegistros);

    // Buscar transações
    $sql = "
        SELECT 
            t.*,
            CASE 
                WHEN t.tipo IN ('deposito', 'premio', 'bonus') THEN t.valor 
                WHEN t.tipo IN ('saque', 'aposta') THEN -t.valor 
            END as valor_ajustado,
            CASE
                WHEN t.tipo = 'deposito' AND t.txid IS NOT NULL THEN 'PIX'
                ELSE NULL
            END as metodo_pagamento
        " . $sqlBase . "
        ORDER BY t.data_processamento DESC 
        LIMIT ? OFFSET ?";
    
    $params[] = $itensPorPagina;
    $params[] = $offset;

    error_log("Query final: " . $sql);
    error_log("Parâmetros finais: " . print_r($params, true));
    
    try {
        $transacoes = dbFetchAll($sql, $params);
        error_log("Número de transações retornadas: " . count($transacoes));
    } catch (Exception $e) {
        error_log("Erro ao buscar transações: " . $e->getMessage());
        $transacoes = [];
    }

} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    setFlashMessage('danger', $e->getMessage());
    redirect(APP_URL);
}

$pageTitle = "Histórico de Transações";
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Histórico de Transações</h1>
                <a href="minha-conta.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="">Todos</option>
                                <option value="deposito" <?= $filtroTipo === 'deposito' ? 'selected' : '' ?>>Depósito</option>
                                <option value="saque" <?= $filtroTipo === 'saque' ? 'selected' : '' ?>>Saque</option>
                                <option value="aposta" <?= $filtroTipo === 'aposta' ? 'selected' : '' ?>>Aposta</option>
                                <option value="premio" <?= $filtroTipo === 'premio' ? 'selected' : '' ?>>Prêmio</option>
                                <option value="bonus" <?= $filtroTipo === 'bonus' ? 'selected' : '' ?>>Bônus</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                <option value="aprovado" <?= $filtroStatus === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                <option value="rejeitado" <?= $filtroStatus === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                                <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                <option value="processando" <?= $filtroStatus === 'processando' ? 'selected' : '' ?>>Processando</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" 
                                   name="data_inicio" 
                                   class="form-control" 
                                   value="<?= $dataInicio ? htmlspecialchars($dataInicio) : '' ?>"
                                   max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" 
                                   name="data_fim" 
                                   class="form-control" 
                                   value="<?= $dataFim ? htmlspecialchars($dataFim) : '' ?>"
                                   max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumo dos Totais -->
            <div class="row mb-4">
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total Depositado</h6>
                            <h4 class="text-success mb-0">
                                <i class="fas fa-arrow-circle-down"></i>
                                R$ <?= number_format(floatval($totais['total_depositos']), 2, ',', '.') ?>
                            </h4>
                            <?php if ($totais['depositos_pendentes'] > 0): ?>
                            <small class="text-warning">
                                <i class="fas fa-clock"></i>
                                Pendente: R$ <?= number_format(floatval($totais['depositos_pendentes']), 2, ',', '.') ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total Sacado</h6>
                            <h4 class="text-danger mb-0">
                                <i class="fas fa-arrow-circle-up"></i>
                                R$ <?= number_format(floatval($totais['total_saques']), 2, ',', '.') ?>
                            </h4>
                            <?php if ($totais['saques_pendentes'] > 0): ?>
                            <small class="text-warning">
                                <i class="fas fa-clock"></i>
                                Pendente: R$ <?= number_format(floatval($totais['saques_pendentes']), 2, ',', '.') ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total em Apostas</h6>
                            <h4 class="text-warning mb-0">
                                <i class="fas fa-ticket-alt"></i>
                                R$ <?= number_format(floatval($totais['total_apostas']), 2, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total em Prêmios</h6>
                            <h4 class="text-primary mb-0">
                                <i class="fas fa-trophy"></i>
                                R$ <?= number_format(floatval($totais['total_premios']), 2, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <?php if ($totais['total_bonus'] > 0): ?>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total em Bônus</h6>
                            <h4 class="text-info mb-0">
                                <i class="fas fa-gift"></i>
                                R$ <?= number_format(floatval($totais['total_bonus']), 2, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabela de Transações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($transacoes)): ?>
                        <p class="text-muted text-center py-4">Nenhuma transação encontrada</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Detalhes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transacoes as $transacao): ?>
                                        <tr>
                                            <td>
                                                <div><?= formatDate($transacao['data_processamento']) ?></div>
                                                <small class="text-muted"><?= formatTime($transacao['data_processamento']) ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                    $tipoClass = '';
                                                    switch ($transacao['tipo']) {
                                                        case 'deposito':
                                                            $tipoClass = 'success';
                                                            $tipoIcon = 'plus-circle';
                                                            break;
                                                        case 'saque':
                                                            $tipoClass = 'danger';
                                                            $tipoIcon = 'minus-circle';
                                                            break;
                                                        case 'aposta':
                                                            $tipoClass = 'warning';
                                                            $tipoIcon = 'ticket-alt';
                                                            break;
                                                        case 'premio':
                                                            $tipoClass = 'primary';
                                                            $tipoIcon = 'trophy';
                                                            break;
                                                        case 'bonus':
                                                            $tipoClass = 'info';
                                                            $tipoIcon = 'gift';
                                                            break;
                                                    }
                                                ?>
                                                <span class="text-<?= $tipoClass ?>">
                                                    <i class="fas fa-<?= $tipoIcon ?> me-1"></i>
                                                    <?= ucfirst($transacao['tipo']) ?>
                                                </span>
                                                <?php if ($transacao['metodo_pagamento']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                        <?= $transacao['metodo_pagamento'] ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-<?= $transacao['valor_ajustado'] > 0 ? 'success' : 'danger' ?>">
                                                    <?= $transacao['valor_ajustado'] > 0 ? '+' : '' ?>
                                                    R$ <?= number_format(abs($transacao['valor_ajustado']), 2, ',', '.') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    switch ($transacao['status']) {
                                                        case 'pendente':
                                                            $statusClass = 'warning';
                                                            $statusIcon = 'clock';
                                                            break;
                                                        case 'aprovado':
                                                            $statusClass = 'success';
                                                            $statusIcon = 'check-circle';
                                                            break;
                                                        case 'rejeitado':
                                                            $statusClass = 'danger';
                                                            $statusIcon = 'times-circle';
                                                            break;
                                                        case 'cancelado':
                                                            $statusClass = 'secondary';
                                                            $statusIcon = 'ban';
                                                            break;
                                                        case 'processando':
                                                            $statusClass = 'info';
                                                            $statusIcon = 'spinner';
                                                            break;
                                                    }
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <i class="fas fa-<?= $statusIcon ?> me-1"></i>
                                                    <?= ucfirst($transacao['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($transacao['txid']): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-fingerprint me-1"></i>
                                                        PIX ID: <?= substr($transacao['txid'], 0, 10) ?>...
                                                    </small>
                                                    <?php if ($transacao['status'] === 'pendente'): ?>
                                                        <a href="deposito.php?id=<?= $transacao['id'] ?>" class="btn btn-sm btn-outline-primary mt-1">
                                                            <i class="fas fa-sync-alt"></i> Verificar
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <?= $transacao['descricao'] ?: '-' ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($totalPaginas > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($paginaAtual > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaAtual - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $inicio = max(1, $paginaAtual - 2);
                                $fim = min($totalPaginas, $paginaAtual + 2);
                                
                                if ($inicio > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => 1])) . '">1</a></li>';
                                    if ($inicio > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $inicio; $i <= $fim; $i++) {
                                    echo '<li class="page-item ' . ($i == $paginaAtual ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $i])) . '">' . $i . '</a>';
                                    echo '</li>';
                                }

                                if ($fim < $totalPaginas) {
                                    if ($fim < $totalPaginas - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])) . '">' . $totalPaginas . '</a></li>';
                                }
                                ?>

                                <?php if ($paginaAtual < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaAtual + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?> 