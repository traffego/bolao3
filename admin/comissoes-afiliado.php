<?php
/**
 * Admin Comissões do Afiliado - Bolão Football
 * Visualização e gerenciamento das comissões de um afiliado
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/comissoes-afiliado.php';
    redirect(APP_URL . '/admin/login.php');
}

// Verificar ID do afiliado
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setError('ID do afiliado inválido');
    redirect(APP_URL . '/admin/afiliados.php');
}

// Buscar dados do afiliado
$afiliado = dbFetchOne("SELECT * FROM afiliados WHERE id = ?", [$id]);
if (!$afiliado) {
    setError('Afiliado não encontrado');
    redirect(APP_URL . '/admin/afiliados.php');
}

// Processar filtros
$filters = [
    'status' => isset($_GET['status']) && in_array($_GET['status'], ['pendente', 'pago', 'todos']) 
        ? $_GET['status'] 
        : 'pendente',
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'data_criacao',
    'order' => isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc'
];

// Configuração da paginação
$itemsPerPage = ITEMS_PER_PAGE;
$offset = ($filters['page'] - 1) * $itemsPerPage;

// Construir query base
$query = "SELECT 
    c.*,
    j.nome as jogador_nome,
    j.email as jogador_email,
    p.valor as valor_pagamento,
    p.data_pagamento as data_pagamento_original
FROM afiliados_comissoes c
JOIN jogador j ON c.jogador_id = j.id
JOIN pagamentos p ON c.pagamento_id = p.id
WHERE c.afiliado_id = ?";

$params = [$id];

// Aplicar filtros
if ($filters['status'] !== 'todos') {
    $query .= " AND c.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['search'])) {
    $query .= " AND (j.nome LIKE ? OR j.email LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

// Ordenação
$allowedSortFields = ['data_criacao', 'valor_comissao', 'status'];
$sortField = in_array($filters['sort'], $allowedSortFields) ? $filters['sort'] : 'data_criacao';
$query .= " ORDER BY c.{$sortField} {$filters['order']}";

// Contar total de registros
$countQuery = str_replace("SELECT c.*,", "SELECT COUNT(*) as total,", $query);
$countQuery = preg_replace('/ORDER BY.*$/', '', $countQuery);
$totalComissoes = dbFetchOne($countQuery, $params)['total'] ?? 0;
$totalPages = ceil($totalComissoes / $itemsPerPage);

// Ajustar página atual se necessário
if ($filters['page'] > $totalPages && $totalPages > 0) {
    $filters['page'] = $totalPages;
    $offset = ($filters['page'] - 1) * $itemsPerPage;
}

// Adicionar paginação à query
$query .= " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

// Buscar comissões
$comissoes = dbFetchAll($query, $params);

// Estatísticas
$stats = [
    'total_comissoes' => dbFetchOne(
        "SELECT COUNT(*) as total FROM afiliados_comissoes WHERE afiliado_id = ?",
        [$id]
    )['total'] ?? 0,
    'comissoes_pendentes' => dbFetchOne(
        "SELECT COUNT(*) as total FROM afiliados_comissoes WHERE afiliado_id = ? AND status = 'pendente'",
        [$id]
    )['total'] ?? 0,
    'comissoes_pagas' => dbFetchOne(
        "SELECT COUNT(*) as total FROM afiliados_comissoes WHERE afiliado_id = ? AND status = 'pago'",
        [$id]
    )['total'] ?? 0,
    'total_valor_comissoes' => dbFetchOne(
        "SELECT SUM(valor_comissao) as total FROM afiliados_comissoes WHERE afiliado_id = ?",
        [$id]
    )['total'] ?? 0,
    'total_valor_pendente' => dbFetchOne(
        "SELECT SUM(valor_comissao) as total FROM afiliados_comissoes WHERE afiliado_id = ? AND status = 'pendente'",
        [$id]
    )['total'] ?? 0,
    'total_valor_pago' => dbFetchOne(
        "SELECT SUM(valor_comissao) as total FROM afiliados_comissoes WHERE afiliado_id = ? AND status = 'pago'",
        [$id]
    )['total'] ?? 0
];

// Incluir header do admin
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section bg-success text-white py-4 mb-4" style="border-radius: 24px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="hero-icon me-4">
                            <i class="fas fa-money-bill-wave fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Comissões do Afiliado</h1>
                            <p class="lead mb-0">
                                Gerenciar comissões de <?= htmlspecialchars($afiliado['nome']) ?>
                                <small class="d-block">Código: <?= htmlspecialchars($afiliado['codigo_afiliado']) ?></small>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="<?= APP_URL ?>/admin/afiliados.php" class="btn btn-light btn-lg">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-list fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total_comissoes'] ?></h4>
                        <small>Total de Comissões</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['comissoes_pendentes'] ?></h4>
                        <small>Comissões Pendentes</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['comissoes_pagas'] ?></h4>
                        <small>Comissões Pagas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <h4 class="mb-0">R$ <?= number_format($stats['total_valor_comissoes'], 2, ',', '.') ?></h4>
                        <small>Total em Comissões</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros e Busca -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="search" class="form-control" 
                               placeholder="Buscar por nome ou email do jogador" 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                        <button class="btn btn-success" type="button" id="searchBtn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" id="statusFilter">
                        <button type="button" 
                           class="btn btn-outline-secondary <?= $filters['status'] === 'todos' ? 'active' : '' ?>" 
                           data-status="todos">
                            <i class="fas fa-list"></i>
                            <span>Todas (<?= $stats['total_comissoes'] ?>)</span>
                        </button>
                        <button type="button" 
                           class="btn btn-outline-warning <?= $filters['status'] === 'pendente' ? 'active' : '' ?>" 
                           data-status="pendente">
                            <i class="fas fa-clock"></i>
                            <span>Pendentes (<?= $stats['comissoes_pendentes'] ?>)</span>
                        </button>
                        <button type="button" 
                           class="btn btn-outline-success <?= $filters['status'] === 'pago' ? 'active' : '' ?>" 
                           data-status="pago">
                            <i class="fas fa-check-circle"></i>
                            <span>Pagas (<?= $stats['comissoes_pagas'] ?>)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Comissões -->
    <div class="card mb-4">
        <div class="card-header bg-green d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Lista de Comissões</h5>
            <div class="text-muted">
                <small>
                    Saldo Pendente: 
                    <strong class="text-warning">
                        R$ <?= number_format($stats['total_valor_pendente'], 2, ',', '.') ?>
                    </strong>
                </small>
                <span class="mx-2">|</span>
                <small>
                    Total Pago: 
                    <strong class="text-success">
                        R$ <?= number_format($stats['total_valor_pago'], 2, ',', '.') ?>
                    </strong>
                </small>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Jogador</th>
                            <th>Valor Pagamento</th>
                            <th>Comissão %</th>
                            <th>Valor Comissão</th>
                            <th>Status</th>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="data_criacao">
                                    Data
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comissoes as $comissao): ?>
                            <tr>
                                <td>
                                    <div>
                                        <?= htmlspecialchars($comissao['jogador_nome']) ?>
                                        <small class="d-block text-muted">
                                            <?= htmlspecialchars($comissao['jogador_email']) ?>
                                        </small>
                                    </div>
                                </td>
                                <td>R$ <?= number_format($comissao['valor_pagamento'], 2, ',', '.') ?></td>
                                <td><?= number_format($comissao['percentual_comissao'], 2) ?>%</td>
                                <td>R$ <?= number_format($comissao['valor_comissao'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="badge bg-<?= $comissao['status'] === 'pago' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($comissao['status']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($comissao['data_criacao']) ?></td>
                                <td>
                                    <?php if ($comissao['status'] === 'pendente'): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-success btn-pay" 
                                                data-id="<?= $comissao['id'] ?>"
                                                data-valor="<?= number_format($comissao['valor_comissao'], 2, ',', '.') ?>"
                                                title="Marcar como Pago">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-check-circle"></i>
                                            Pago em <?= formatDate($comissao['data_pagamento']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($comissoes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhuma comissão encontrada
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Navegação de páginas">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $filters['page'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= buildQueryString($filters, ['page' => $filters['page'] - 1]) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = max(1, $filters['page'] - 2); $i <= min($totalPages, $filters['page'] + 2); $i++): ?>
                    <li class="page-item <?= $i === $filters['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= buildQueryString($filters, ['page' => $i]) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $filters['page'] >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= buildQueryString($filters, ['page' => $filters['page'] + 1]) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Modal de Confirmação de Pagamento -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Confirmar pagamento da comissão no valor de <strong id="comissaoValor"></strong>?</p>
                <p class="text-muted">Esta ação não pode ser desfeita!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-afiliado.php" method="post" class="d-inline">
                    <input type="hidden" name="action" value="pagar_comissao">
                    <input type="hidden" name="comissao_id" id="payComissaoId">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="redirect" value="<?= APP_URL ?>/admin/comissoes-afiliado.php?id=<?= $id ?>">
                    <button type="submit" class="btn btn-success">Confirmar Pagamento</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos da página -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manipulador de busca
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('search');
    
    searchBtn.addEventListener('click', function() {
        const searchTerm = searchInput.value.trim();
        window.location.href = `?${buildQueryString({
            ...<?= json_encode($filters) ?>,
            id: <?= $id ?>,
            search: searchTerm,
            page: 1
        })}`;
    });
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchBtn.click();
        }
    });
    
    // Manipulador de filtro de status
    const statusButtons = document.querySelectorAll('#statusFilter button');
    statusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const status = this.dataset.status;
            window.location.href = `?${buildQueryString({
                ...<?= json_encode($filters) ?>,
                id: <?= $id ?>,
                status: status,
                page: 1
            })}`;
        });
    });
    
    // Manipulador de ordenação
    const sortButtons = document.querySelectorAll('.sortable');
    sortButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const sort = this.dataset.sort;
            const currentSort = <?= json_encode($filters['sort']) ?>;
            const currentOrder = <?= json_encode($filters['order']) ?>;
            const newOrder = sort === currentSort && currentOrder === 'asc' ? 'desc' : 'asc';
            
            window.location.href = `?${buildQueryString({
                ...<?= json_encode($filters) ?>,
                id: <?= $id ?>,
                sort: sort,
                order: newOrder
            })}`;
        });
    });
    
    // Manipulador do modal de pagamento
    const payModal = document.getElementById('payModal');
    const payButtons = document.querySelectorAll('.btn-pay');
    const modalComissaoValor = document.getElementById('comissaoValor');
    const payComissaoId = document.getElementById('payComissaoId');
    
    payButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const valor = this.dataset.valor;
            
            modalComissaoValor.textContent = `R$ ${valor}`;
            payComissaoId.value = id;
            
            const modal = new bootstrap.Modal(payModal);
            modal.show();
        });
    });
});

// Função auxiliar para construir query string
function buildQueryString(params) {
    return Object.keys(params)
        .filter(key => params[key] !== null && params[key] !== undefined && params[key] !== '')
        .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
        .join('&');
}
</script>

<?php include '../templates/admin/footer.php'; ?> 