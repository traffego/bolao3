<?php
/**
 * Admin Afiliados - Bolão Football
 * Gerenciamento de afiliados do sistema
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/afiliados.php';
    redirect(APP_URL . '/admin/login.php');
}

// Processar filtros
$filters = [
    'status' => isset($_GET['status']) && in_array($_GET['status'], ['ativo', 'inativo', 'todos']) 
        ? $_GET['status'] 
        : 'ativo',
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'nome',
    'order' => isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc'
];

// Configuração da paginação
$itemsPerPage = ITEMS_PER_PAGE;
$offset = ($filters['page'] - 1) * $itemsPerPage;

// Construir query base
$query = "SELECT 
    a.*,
    (SELECT COUNT(*) FROM afiliados_indicacoes WHERE afiliado_id = a.id) as total_indicacoes,
    (SELECT COUNT(*) FROM afiliados_comissoes WHERE afiliado_id = a.id) as total_comissoes,
    (SELECT SUM(valor_comissao) FROM afiliados_comissoes WHERE afiliado_id = a.id AND status = 'pago') as total_pago,
    (SELECT SUM(valor_comissao) FROM afiliados_comissoes WHERE afiliado_id = a.id AND status = 'pendente') as total_pendente
FROM afiliados a
WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filters['status'] !== 'todos') {
    $query .= " AND a.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['search'])) {
    $query .= " AND (a.nome LIKE ? OR a.email LIKE ? OR a.codigo_afiliado LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// Ordenação
$allowedSortFields = ['nome', 'email', 'data_cadastro', 'status', 'saldo'];
$sortField = in_array($filters['sort'], $allowedSortFields) ? $filters['sort'] : 'nome';
$query .= " ORDER BY a.{$sortField} {$filters['order']}";

// Contar total de registros
$countQuery = "SELECT COUNT(*) as total FROM afiliados a WHERE 1=1";
if ($filters['status'] !== 'todos') {
    $countQuery .= " AND a.status = ?";
}
if (!empty($filters['search'])) {
    $countQuery .= " AND (a.nome LIKE ? OR a.email LIKE ? OR a.codigo_afiliado LIKE ?)";
}

$totalAfiliados = dbFetchOne($countQuery, $params)['total'] ?? 0;
$totalPages = ceil($totalAfiliados / $itemsPerPage);

// Ajustar página atual se necessário
if ($filters['page'] > $totalPages && $totalPages > 0) {
    $filters['page'] = $totalPages;
    $offset = ($filters['page'] - 1) * $itemsPerPage;
}

// Adicionar paginação à query
$query .= " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

// Buscar afiliados
$afiliados = dbFetchAll($query, $params);

// Estatísticas
$stats = [
    'total' => dbFetchOne("SELECT COUNT(*) as total FROM afiliados")['total'] ?? 0,
    'ativos' => dbFetchOne("SELECT COUNT(*) as total FROM afiliados WHERE status = 'ativo'")['total'] ?? 0,
    'inativos' => dbFetchOne("SELECT COUNT(*) as total FROM afiliados WHERE status = 'inativo'")['total'] ?? 0,
    'total_indicacoes' => dbFetchOne("SELECT COUNT(*) as total FROM afiliados_indicacoes")['total'] ?? 0,
    'total_comissoes_pagas' => dbFetchOne("SELECT SUM(valor_comissao) as total FROM afiliados_comissoes WHERE status = 'pago'")['total'] ?? 0
];

// Incluir header do admin
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section bg-primary text-white py-4 mb-4" style="border-radius: 24px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="hero-icon me-4">
                            <i class="fas fa-handshake fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Gerenciar Afiliados</h1>
                            <p class="lead mb-0">Gerencie o programa de afiliados do sistema</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="<?= APP_URL ?>/admin/novo-afiliado.php" class="btn btn-light btn-lg">
                            <i class="fas fa-plus"></i> Novo Afiliado
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total'] ?></h4>
                        <small>Total de Afiliados</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['ativos'] ?></h4>
                        <small>Afiliados Ativos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-user-plus fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total_indicacoes'] ?></h4>
                        <small>Total de Indicações</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                        <h4 class="mb-0">R$ <?= number_format($stats['total_comissoes_pagas'], 2, ',', '.') ?></h4>
                        <small>Total Comissões Pagas</small>
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
                               placeholder="Buscar por nome, email ou código" 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                        <button class="btn btn-primary" type="button" id="searchBtn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" id="statusFilter">
                        <button type="button" 
                           class="btn btn-outline-secondary <?= $filters['status'] === 'todos' ? 'active' : '' ?>" 
                           data-status="todos">
                            <i class="fas fa-users"></i>
                            <span>Todos (<?= $stats['total'] ?>)</span>
                        </button>
                        <button type="button" 
                           class="btn btn-outline-success <?= $filters['status'] === 'ativo' ? 'active' : '' ?>" 
                           data-status="ativo">
                            <i class="fas fa-user-check"></i>
                            <span>Ativos (<?= $stats['ativos'] ?>)</span>
                        </button>
                        <button type="button" 
                           class="btn btn-outline-danger <?= $filters['status'] === 'inativo' ? 'active' : '' ?>" 
                           data-status="inativo">
                            <i class="fas fa-user-slash"></i>
                            <span>Inativos (<?= $stats['inativos'] ?>)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Afiliados -->
    <div class="card mb-4">
        <div class="card-header bg-green">
            <h5 class="mb-0">Lista de Afiliados</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="afiliadosTable">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="nome">
                                    Nome
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="email">
                                    Email
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Código</th>
                            <th>Status</th>
                            <th>Indicações</th>
                            <th>Comissão %</th>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="saldo">
                                    Saldo
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Total Pago</th>
                            <th>
                                <a href="#" class="text-dark text-decoration-none sortable" data-sort="data_cadastro">
                                    Cadastro
                                    <i class="fas fa-sort"></i>
                                </a>
                            </th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($afiliados as $afiliado): ?>
                            <tr>
                                <td><?= htmlspecialchars($afiliado['nome']) ?></td>
                                <td><?= htmlspecialchars($afiliado['email']) ?></td>
                                <td><code><?= htmlspecialchars($afiliado['codigo_afiliado']) ?></code></td>
                                <td>
                                    <span class="badge bg-<?= $afiliado['status'] === 'ativo' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($afiliado['status']) ?>
                                    </span>
                                </td>
                                <td><?= $afiliado['total_indicacoes'] ?></td>
                                <td><?= number_format($afiliado['comissao_percentual'], 2) ?>%</td>
                                <td>R$ <?= number_format($afiliado['saldo'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($afiliado['total_pago'] ?? 0, 2, ',', '.') ?></td>
                                <td><?= formatDate($afiliado['data_cadastro']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= APP_URL ?>/admin/editar-afiliado.php?id=<?= $afiliado['id'] ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/admin/comissoes-afiliado.php?id=<?= $afiliado['id'] ?>" 
                                           class="btn btn-sm btn-success" 
                                           title="Comissões">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/admin/indicacoes-afiliado.php?id=<?= $afiliado['id'] ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Indicações">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger btn-delete" 
                                                data-id="<?= $afiliado['id'] ?>"
                                                data-nome="<?= htmlspecialchars($afiliado['nome']) ?>"
                                                title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($afiliados)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum afiliado encontrado
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

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o afiliado <strong id="afiliadoNome"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-afiliado.php" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="afiliado_id" id="deleteAfiliadoId">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <button type="submit" class="btn btn-danger">Excluir</button>
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
                sort: sort,
                order: newOrder
            })}`;
        });
    });
    
    // Manipulador do modal de exclusão
    const deleteModal = document.getElementById('deleteModal');
    const deleteButtons = document.querySelectorAll('.btn-delete');
    const modalAfiliadoNome = document.getElementById('afiliadoNome');
    const deleteAfiliadoId = document.getElementById('deleteAfiliadoId');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nome = this.dataset.nome;
            
            modalAfiliadoNome.textContent = nome;
            deleteAfiliadoId.value = id;
            
            const modal = new bootstrap.Modal(deleteModal);
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