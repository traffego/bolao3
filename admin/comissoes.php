<?php
/**
 * Painel Administrativo - Gestão de Comissões de Afiliados
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['error'] = 'Acesso negado.';
    redirect(APP_URL . '/admin/login.php');
}

// Filtros
$filtroAfiliado = $_GET['afiliado'] ?? '';
$filtroMes = $_GET['mes'] ?? date('Y-m');
$filtroStatus = $_GET['status'] ?? '';

// Buscar estatísticas gerais
$statsGerais = [
    'total_comissoes' => 0,
    'comissoes_mes' => 0,
    'valor_total' => 0,
    'valor_mes' => 0
];

// Total de comissões
$totalComissoes = dbFetchOne(
    "SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor_total 
     FROM transacoes 
     WHERE tipo = 'comissao' AND status = 'aprovado'"
);
$statsGerais['total_comissoes'] = $totalComissoes['total'] ?? 0;
$statsGerais['valor_total'] = $totalComissoes['valor_total'] ?? 0;

// Comissões do mês atual
$comissoesMes = dbFetchOne(
    "SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor_mes 
     FROM transacoes 
     WHERE tipo = 'comissao' AND status = 'aprovado'
     AND YEAR(data_processamento) = YEAR(CURDATE()) 
     AND MONTH(data_processamento) = MONTH(CURDATE())"
);
$statsGerais['comissoes_mes'] = $comissoesMes['total'] ?? 0;
$statsGerais['valor_mes'] = $comissoesMes['valor_mes'] ?? 0;

// Construir query para listagem de comissões
$whereConditions = ["t.tipo = 'comissao'"];
$params = [];

if (!empty($filtroAfiliado)) {
    $whereConditions[] = "j.nome LIKE ?";
    $params[] = "%{$filtroAfiliado}%";
}

if (!empty($filtroMes)) {
    $whereConditions[] = "DATE_FORMAT(t.data_processamento, '%Y-%m') = ?";
    $params[] = $filtroMes;
}

if (!empty($filtroStatus)) {
    $whereConditions[] = "t.status = ?";
    $params[] = $filtroStatus;
}

$whereClause = implode(' AND ', $whereConditions);

// Buscar comissões com paginação
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$comissoes = dbFetchAll(
    "SELECT t.*, 
            c.jogador_id,
            j.nome as afiliado_nome,
            j.email as afiliado_email,
            j.codigo_afiliado,
            DATE_FORMAT(t.data_processamento, '%d/%m/%Y %H:%i') as data_formatada
     FROM transacoes t
     INNER JOIN contas c ON t.conta_id = c.id
     INNER JOIN jogador j ON c.jogador_id = j.id
     WHERE {$whereClause}
     ORDER BY t.data_processamento DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

// Contar total para paginação
$totalRegistros = dbFetchOne(
    "SELECT COUNT(*) as total
     FROM transacoes t
     INNER JOIN contas c ON t.conta_id = c.id
     INNER JOIN jogador j ON c.jogador_id = j.id
     WHERE {$whereClause}",
    $params
);
$totalPages = ceil(($totalRegistros['total'] ?? 0) / $limit);

// Buscar top afiliados do mês
$topAfiliados = dbFetchAll(
    "SELECT j.nome, j.codigo_afiliado, 
            COUNT(t.id) as total_comissoes,
            COALESCE(SUM(t.valor), 0) as valor_total
     FROM transacoes t
     INNER JOIN contas c ON t.conta_id = c.id
     INNER JOIN jogador j ON c.jogador_id = j.id
     WHERE t.tipo = 'comissao' AND t.status = 'aprovado'
     AND YEAR(t.data_processamento) = YEAR(CURDATE()) 
     AND MONTH(t.data_processamento) = MONTH(CURDATE())
     GROUP BY j.id, j.nome, j.codigo_afiliado
     ORDER BY valor_total DESC
     LIMIT 10"
);

$pageTitle = 'Gestão de Comissões';
include 'templates/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-cash-stack me-2"></i>
                        Gestão de Comissões
                    </h1>
                    <p class="text-muted mb-0">Controle e acompanhamento das comissões de afiliados</p>
                </div>
                <div>
                    <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $statsGerais['total_comissoes'] ?></h4>
                            <p class="mb-0">Total de Comissões</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash-coin" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= formatMoney($statsGerais['valor_total']) ?></h4>
                            <p class="mb-0">Valor Total</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $statsGerais['comissoes_mes'] ?></h4>
                            <p class="mb-0">Comissões este Mês</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-month" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= formatMoney($statsGerais['valor_mes']) ?></h4>
                            <p class="mb-0">Valor este Mês</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash-stack" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Lista de Comissões -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Histórico de Comissões
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="afiliado" class="form-label">Afiliado</label>
                            <input type="text" class="form-control" id="afiliado" name="afiliado" 
                                   value="<?= sanitize($filtroAfiliado) ?>" placeholder="Nome do afiliado">
                        </div>
                        <div class="col-md-3">
                            <label for="mes" class="form-label">Mês</label>
                            <input type="month" class="form-control" id="mes" name="mes" 
                                   value="<?= sanitize($filtroMes) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="aprovado" <?= $filtroStatus === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                <option value="rejeitado" <?= $filtroStatus === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Tabela de Comissões -->
                    <?php if (empty($comissoes)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-cash-stack text-muted" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">Nenhuma comissão encontrada</h6>
                            <p class="text-muted mb-0">Ajuste os filtros ou aguarde novas comissões serem geradas.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Afiliado</th>
                                        <th>Descrição</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comissoes as $comissao): ?>
                                        <tr>
                                            <td>
                                                <small><?= $comissao['data_formatada'] ?></small>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= sanitize($comissao['afiliado_nome']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= sanitize($comissao['codigo_afiliado']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 250px;" 
                                                     title="<?= sanitize($comissao['descricao']) ?>">
                                                    <?= sanitize($comissao['descricao']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-success fw-bold">
                                                    <?= formatMoney($comissao['valor']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($comissao['status'] === 'aprovado'): ?>
                                                    <span class="badge bg-success">Aprovado</span>
                                                <?php elseif ($comissao['status'] === 'pendente'): ?>
                                                    <span class="badge bg-warning">Pendente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= ucfirst($comissao['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Paginação">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&afiliado=<?= urlencode($filtroAfiliado) ?>&mes=<?= urlencode($filtroMes) ?>&status=<?= urlencode($filtroStatus) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Afiliados -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-trophy me-2"></i>
                        Top Afiliados do Mês
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topAfiliados)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-trophy text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">Nenhuma comissão este mês</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topAfiliados as $index => $afiliado): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="d-flex align-items-center">
                                        <div class="badge bg-primary rounded-pill me-3">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= sanitize($afiliado['nome']) ?></h6>
                                            <small class="text-muted"><?= sanitize($afiliado['codigo_afiliado']) ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success"><?= formatMoney($afiliado['valor_total']) ?></div>
                                        <small class="text-muted"><?= $afiliado['total_comissoes'] ?> comissões</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>