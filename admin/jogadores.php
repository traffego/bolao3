<?php
/**
 * Admin Jogadores - Bolão Football
 * Gerenciamento de jogadores do sistema
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/jogadores.php';
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
    j.*,
    (SELECT COUNT(*) FROM palpites WHERE jogador_id = j.id) as total_palpites,
    (SELECT COUNT(*) FROM pagamentos WHERE jogador_id = j.id AND status = 'confirmado') as total_pagamentos,
    (SELECT SUM(valor) FROM pagamentos WHERE jogador_id = j.id AND status = 'confirmado') as valor_total_pagamentos
FROM jogador j
WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filters['status'] !== 'todos') {
    $query .= " AND j.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['search'])) {
    $query .= " AND (j.nome LIKE ? OR j.email LIKE ? OR j.telefone LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// Ordenação
$allowedSortFields = ['nome', 'email', 'data_cadastro', 'status'];
$sortField = in_array($filters['sort'], $allowedSortFields) ? $filters['sort'] : 'nome';
$query .= " ORDER BY j.{$sortField} {$filters['order']}";

// Contar total de registros
$countQuery = "SELECT COUNT(*) as total FROM jogador j WHERE 1=1";
if ($filters['status'] !== 'todos') {
    $countQuery .= " AND j.status = ?";
}
if (!empty($filters['search'])) {
    $countQuery .= " AND (j.nome LIKE ? OR j.email LIKE ? OR j.telefone LIKE ?)";
}

$totalJogadores = dbFetchOne($countQuery, $params)['total'] ?? 0;
$totalPages = ceil($totalJogadores / $itemsPerPage);

// Ajustar página atual se necessário
if ($filters['page'] > $totalPages && $totalPages > 0) {
    $filters['page'] = $totalPages;
    $offset = ($filters['page'] - 1) * $itemsPerPage;
}

// Adicionar paginação à query
$query .= " LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

// Buscar jogadores
$jogadores = dbFetchAll($query, $params);

// Estatísticas
$stats = [
    'total' => dbFetchOne("SELECT COUNT(*) as total FROM jogador")['total'] ?? 0,
    'ativos' => dbFetchOne("SELECT COUNT(*) as total FROM jogador WHERE status = 'ativo'")['total'] ?? 0,
    'inativos' => dbFetchOne("SELECT COUNT(*) as total FROM jogador WHERE status = 'inativo'")['total'] ?? 0,
    'total_palpites' => dbFetchOne("SELECT COUNT(*) as total FROM palpites")['total'] ?? 0
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
                            <i class="fas fa-users fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Gerenciar Jogadores</h1>
                            <p class="lead mb-0">Gerencie todos os jogadores do sistema</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="<?= APP_URL ?>/admin/novo-jogador.php" class="btn btn-light btn-lg">
                            <i class="fas fa-plus"></i> Novo Jogador
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
                        <small>Total de Jogadores</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['ativos'] ?></h4>
                        <small>Jogadores Ativos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-user-times fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['inativos'] ?></h4>
                        <small>Jogadores Inativos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-list-ol fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total_palpites'] ?></h4>
                        <small>Total de Palpites</small>
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
                               placeholder="Buscar por nome, email ou telefone" 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                        <button class="btn btn-success" type="button" id="searchBtn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" id="statusFilter">
                        <button type="button" 
                           class="btn btn-outline-secondary active d-inline-flex align-items-center justify-content-center gap-2" 
                           data-status="todos">
                            <i class="fas fa-users"></i>
                            <span>Todos (<?= $stats['total'] ?>)</span>
                        </button>
                        <button type="button" 
                           class="btn btn-outline-success d-inline-flex align-items-center justify-content-center gap-2" 
                           data-status="ativo">
                            <i class="fas fa-user-check"></i>
                            <span>Ativos (<?= $stats['ativos'] ?>)</span>
                        </button>
                        <button type="button" 
                           class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center gap-2" 
                           data-status="inativo">
                            <i class="fas fa-user-slash"></i>
                            <span>Inativos (<?= $stats['inativos'] ?>)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Jogadores -->
    <div class="card mb-4">
        <div class="card-header bg-green">
            <h5 class="mb-0">Lista de Jogadores</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="jogadoresTable">
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
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Palpites</th>
                            <th>Pagamentos</th>
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
                        <?php foreach ($jogadores as $jogador): ?>
                            <tr data-status="<?= $jogador['status'] ?>" 
                                data-nome="<?= htmlspecialchars($jogador['nome']) ?>"
                                data-email="<?= htmlspecialchars($jogador['email']) ?>"
                                data-telefone="<?= htmlspecialchars($jogador['telefone'] ?? '') ?>">
                                <td><?= htmlspecialchars($jogador['nome']) ?></td>
                                <td><?= htmlspecialchars($jogador['email']) ?></td>
                                <td><?= htmlspecialchars($jogador['telefone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-<?= $jogador['status'] === 'ativo' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($jogador['status']) ?>
                                    </span>
                                </td>
                                <td><?= $jogador['total_palpites'] ?></td>
                                <td><?= $jogador['total_pagamentos'] ?></td>
                                <td>R$ <?= number_format($jogador['valor_total_pagamentos'] ?? 0, 2, ',', '.') ?></td>
                                <td data-date="<?= $jogador['data_cadastro'] ?>"><?= formatDate($jogador['data_cadastro']) ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="<?= APP_URL ?>/admin/editar-jogador.php?id=<?= $jogador['id'] ?>" 
                                           class="btn btn-warning d-inline-flex align-items-center gap-2 px-3" 
                                           title="Editar informações do jogador">
                                            <div class="d-flex align-items-center position-relative">
                                                <i class="fas fa-user fa-fw"></i>
                                                <i class="fas fa-pencil-alt fa-fw position-absolute" style="font-size: 0.7em; right: -4px; bottom: -2px;"></i>
                                            </div>
                                            <span>Editar</span>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-<?= $jogador['status'] === 'ativo' ? 'danger' : 'success' ?> toggle-status d-inline-flex align-items-center gap-2 px-3" 
                                                data-id="<?= $jogador['id'] ?>" 
                                                data-status="<?= $jogador['status'] ?>" 
                                                title="<?= $jogador['status'] === 'ativo' ? 'Desativar acesso do jogador' : 'Reativar acesso do jogador' ?>">
                                            <div class="d-flex align-items-center position-relative">
                                                <i class="fas fa-user fa-fw"></i>
                                                <?php if ($jogador['status'] === 'ativo'): ?>
                                                    <i class="fas fa-ban fa-fw position-absolute text-white" style="font-size: 1.2em; left: -2px; top: -2px; opacity: 0.8;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle fa-fw position-absolute text-white" style="font-size: 1em; right: -4px; bottom: -2px;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <span><?= $jogador['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Estado vazio -->
            <div id="emptyState" class="alert alert-info d-none">
                <div class="text-center py-4">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h5>Nenhum jogador encontrado</h5>
                    <p class="mb-0 text-muted">Tente ajustar os filtros ou termos de busca.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Confirmar Alteração</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center gap-3">
                    <div class="fs-3 position-relative">
                        <i class="fas fa-user fa-fw"></i>
                        <i class="fas fa-exclamation-triangle fa-fw position-absolute text-warning" style="font-size: 0.7em; right: -8px; bottom: -4px;"></i>
                    </div>
                    <div>
                        <strong id="modal-alert-text">Esta ação irá alterar o status do jogador.</strong>
                        <br>
                        <span id="modal-consequence" class="text-muted"></span>
                    </div>
                </div>
                <p class="mb-0 text-center fs-5">Tem certeza que deseja <strong><span id="action-text">desativar</span></strong> este jogador?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-2"></i>
                    Cancelar
                </button>
                <form action="<?= APP_URL ?>/admin/acao-jogador.php" method="post">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="jogador_id" id="jogador_id" value="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <button type="submit" class="btn btn-lg px-4" id="confirm-btn">
                        <i class="fas fa-check me-2"></i>
                        Confirmar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('jogadoresTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const searchInput = document.getElementById('search');
    const searchBtn = document.getElementById('searchBtn');
    const statusButtons = document.querySelectorAll('#statusFilter button');
    const sortableHeaders = document.querySelectorAll('.sortable');
    const emptyState = document.getElementById('emptyState');

    let currentStatus = 'todos';
    let currentSort = {
        column: 'nome',
        direction: 'asc'
    };

    // Função para atualizar os contadores dos botões
    function updateCounters() {
        const visibleRows = rows.filter(row => !row.classList.contains('d-none'));
        const counts = {
            todos: visibleRows.length,
            ativo: visibleRows.filter(row => row.dataset.status === 'ativo').length,
            inativo: visibleRows.filter(row => row.dataset.status === 'inativo').length
        };

        statusButtons.forEach(button => {
            const status = button.dataset.status;
            const countSpan = button.querySelector('span');
            const text = status === 'todos' ? 'Todos' : status === 'ativo' ? 'Ativos' : 'Inativos';
            countSpan.textContent = `${text} (${counts[status]})`;
        });
    }

    // Função para filtrar as linhas
    function filterRows() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleRows = 0;

        rows.forEach(row => {
            const status = row.dataset.status;
            const nome = row.dataset.nome.toLowerCase();
            const email = row.dataset.email.toLowerCase();
            const telefone = row.dataset.telefone.toLowerCase();
            
            const matchesStatus = currentStatus === 'todos' || status === currentStatus;
            const matchesSearch = searchTerm === '' || 
                                nome.includes(searchTerm) || 
                                email.includes(searchTerm) || 
                                telefone.includes(searchTerm);

            if (matchesStatus && matchesSearch) {
                row.classList.remove('d-none');
                visibleRows++;
            } else {
                row.classList.add('d-none');
            }
        });

        // Mostrar/esconder estado vazio
        if (visibleRows === 0) {
            table.classList.add('d-none');
            emptyState.classList.remove('d-none');
        } else {
            table.classList.remove('d-none');
            emptyState.classList.add('d-none');
        }

        // Atualizar contadores
        updateCounters();
    }

    // Função para ordenar as linhas
    function sortRows(column) {
        const direction = currentSort.column === column && currentSort.direction === 'asc' ? 'desc' : 'asc';
        
        rows.sort((a, b) => {
            let valueA, valueB;

            if (column === 'data_cadastro') {
                valueA = new Date(a.querySelector(`td[data-date]`).dataset.date);
                valueB = new Date(b.querySelector(`td[data-date]`).dataset.date);
            } else {
                valueA = a.dataset[column].toLowerCase();
                valueB = b.dataset[column].toLowerCase();
            }

            if (valueA < valueB) return direction === 'asc' ? -1 : 1;
            if (valueA > valueB) return direction === 'asc' ? 1 : -1;
            return 0;
        });

        // Atualizar ícones de ordenação
        sortableHeaders.forEach(header => {
            const headerColumn = header.dataset.sort;
            const icon = header.querySelector('i');
            
            if (headerColumn === column) {
                icon.className = `fas fa-sort-${direction === 'asc' ? 'up' : 'down'}`;
            } else {
                icon.className = 'fas fa-sort';
            }
        });

        // Reordenar DOM
        rows.forEach(row => tbody.appendChild(row));

        currentSort = { column, direction };
    }

    // Event Listeners
    searchInput.addEventListener('input', filterRows);
    searchBtn.addEventListener('click', filterRows);

    statusButtons.forEach(button => {
        button.addEventListener('click', () => {
            statusButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            currentStatus = button.dataset.status;
            filterRows();
        });
    });

    sortableHeaders.forEach(header => {
        header.addEventListener('click', (e) => {
            e.preventDefault();
            sortRows(header.dataset.sort);
        });
    });

    // Inicializar com a primeira ordenação
    sortRows('nome');
});
</script>

<?php include '../templates/admin/footer.php'; ?> 