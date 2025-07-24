<?php
/**
 * Admin Indicações do Afiliado - Bolão Football
 * Visualização das indicações de um afiliado
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/indicacoes-afiliado.php';
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
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'data_indicacao',
    'order' => isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc'
];

// Configuração da paginação
$itemsPerPage = ITEMS_PER_PAGE;
$offset = ($filters['page'] - 1) * $itemsPerPage;

// Construir query base
$query = "SELECT 
    i.*,
    j.nome as jogador_nome,
    j.email as jogador_email,
    j.telefone as jogador_telefone,
    j.status as jogador_status,
    (SELECT COUNT(*) FROM palpites WHERE jogador_id = j.id) as total_palpites,
    (SELECT COUNT(*) FROM pagamentos WHERE jogador_id = j.id AND status = 'confirmado') as total_pagamentos,
    (SELECT SUM(valor) FROM pagamentos WHERE jogador_id = j.id AND status = 'confirmado') as valor_total_pagamentos
FROM afiliados_indicacoes i
JOIN jogador j ON i.jogador_id = j.id
WHERE i.afiliado_id = ?";

$params = [$id];

// Aplicar filtros
if (!empty($filters['search'])) {
    $query .= " AND (j.nome LIKE ? OR j.email LIKE ? OR j.telefone LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// Ordenação
$allowedSortFields = ['data_indicacao', 'jogador_nome', 'total_palpites'];
$sortField = in_array($filters['sort'], $allowedSortFields) ? $filters['sort'] : 'data_indicacao';
$query .= " ORDER BY {$sortField} {$filters['order']}";

// Contar total de registros
$countQuery = str_replace("SELECT i.*,", "SELECT COUNT(*) as total,", $query);
$countQuery = preg_replace('/ORDER BY.*$/', '', $countQuery);
$totalIndicacoes = dbFetchOne($countQuery, $params)['total'] ?? 0;
$totalPages = ceil($totalIndicacoes / $itemsPerPage);

// Ajustar página atual se necessário
if ($filters['page'] > $totalPages && $totalPages > 0) {
    $filters['page'] = $totalPages;
    $offset = ($filters['page'] - 1) * $itemsPerPage;
}

// Adicionar paginação à query
$query .= " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

// Buscar indicações
$indicacoes = dbFetchAll($query, $params);

// Estatísticas
$stats = [
    'total_indicacoes' => $totalIndicacoes,
    'indicacoes_ativas' => dbFetchOne(
        "SELECT COUNT(*) as total 
         FROM afiliados_indicacoes i 
         JOIN jogador j ON i.jogador_id = j.id 
         WHERE i.afiliado_id = ? AND j.status = 'ativo'",
        [$id]
    )['total'] ?? 0,
    'total_palpites' => dbFetchOne(
        "SELECT COUNT(*) as total 
         FROM palpites p 
         JOIN afiliados_indicacoes i ON p.jogador_id = i.jogador_id 
         WHERE i.afiliado_id = ?",
        [$id]
    )['total'] ?? 0,
    'total_pagamentos' => dbFetchOne(
        "SELECT SUM(p.valor) as total 
         FROM pagamentos p 
         JOIN afiliados_indicacoes i ON p.jogador_id = i.jogador_id 
         WHERE i.afiliado_id = ? AND p.status = 'confirmado'",
        [$id]
    )['total'] ?? 0
];

// Incluir header do admin
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section bg-info text-white py-4 mb-4" style="border-radius: 24px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="hero-icon me-4">
                            <i class="fas fa-users fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Indicações do Afiliado</h1>
                            <p class="lead mb-0">
                                Jogadores indicados por <?= htmlspecialchars($afiliado['nome']) ?>
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
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total_indicacoes'] ?></h4>
                        <small>Total de Indicações</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['indicacoes_ativas'] ?></h4>
                        <small>Indicações Ativas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-list-ol fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total_palpites'] ?></h4>
                        <small>Total de Palpites</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <h4 class="mb-0">R$ <?= number_format($stats['total_pagamentos'], 2, ',', '.') ?></h4>
                        <small>Total em Pagamentos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros e Busca -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="input-group">
                        <input type="text" id="search" class="form-control" 
                               placeholder="Buscar por nome, email ou telefone do jogador" 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                        <button class="btn btn-info" type="button" id="searchBtn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Indicações -->
    <div class="card mb-4">
        <div class="card-header bg-green">
            <h5 class="mb-0">Lista de Indicações</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="jogador_nome">
                                    Jogador
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Contato</th>
                            <th>Status</th>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="total_palpites">
                                    Palpites
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Pagamentos</th>
                            <th>Total Pago</th>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="data_indicacao">
                                    Data Indicação
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indicacoes as $indicacao): ?>
                            <tr>
                                <td><?= htmlspecialchars($indicacao['jogador_nome']) ?></td>
                                <td>
                                    <div>
                                        <small class="d-block">
                                            <i class="fas fa-envelope"></i>
                                            <?= htmlspecialchars($indicacao['jogador_email']) ?>
                                        </small>
                                        <?php if ($indicacao['jogador_telefone']): ?>
                                            <small class="d-block">
                                                <i class="fas fa-phone"></i>
                                                <?= htmlspecialchars($indicacao['jogador_telefone']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $indicacao['jogador_status'] === 'ativo' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($indicacao['jogador_status']) ?>
                                    </span>
                                </td>
                                <td><?= $indicacao['total_palpites'] ?></td>
                                <td><?= $indicacao['total_pagamentos'] ?></td>
                                <td>R$ <?= number_format($indicacao['valor_total_pagamentos'] ?? 0, 2, ',', '.') ?></td>
                                <td><?= formatDate($indicacao['data_indicacao']) ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/jogador-palpites.php?id=<?= $indicacao['jogador_id'] ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="Ver Palpites">
                                        <i class="fas fa-list"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($indicacoes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhuma indicação encontrada
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