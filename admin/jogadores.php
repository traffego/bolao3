<?php
/**
 * Admin Jogadores - Bolão Football
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/jogadores.php';
    redirect(APP_URL . '/admin/login.php');
}

// Handle status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'ativo';
if (!in_array($status, ['ativo', 'inativo', 'todos'])) {
    $status = 'ativo';
}

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $itemsPerPage;

// Status clause for SQL
$statusClause = $status === 'todos' ? '1' : "status = '$status'";

// Count total jogadores
$totalJogadores = dbCount('jogador', $statusClause);
$totalPages = ceil($totalJogadores / $itemsPerPage);

// Make sure $page is within limits
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchClause = '';
$searchParams = [];

if (!empty($search)) {
    $searchClause = " AND (nome LIKE ? OR email LIKE ?)";
    $searchParams = ["%$search%", "%$search%"];
}

// Get jogadores with pagination
$jogadores = dbFetchAll(
    "SELECT j.*, 
            (SELECT COUNT(*) FROM participacoes WHERE jogador_id = j.id) as total_boloes,
            (SELECT COUNT(*) FROM pagamentos WHERE jogador_id = j.id AND status = 'aprovado') as total_pagamentos 
     FROM jogador j
     WHERE $statusClause $searchClause
     ORDER BY j.nome ASC
     LIMIT ? OFFSET ?", 
    array_merge($searchParams, [$itemsPerPage, $offset])
);

// Page title
$pageTitle = 'Gerenciar Jogadores';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $pageTitle ?></h1>
    <div>
        <a href="<?= APP_URL ?>/admin/novo-jogador.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Novo Jogador
        </a>
    </div>
</div>

<!-- Filters and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nome ou email" value="<?= sanitize($search) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <div class="btn-group w-100">
                    <a href="?status=ativo<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline-success <?= $status === 'ativo' ? 'active' : '' ?>">Ativos</a>
                    <a href="?status=inativo<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline-danger <?= $status === 'inativo' ? 'active' : '' ?>">Inativos</a>
                    <a href="?status=todos<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline-secondary <?= $status === 'todos' ? 'active' : '' ?>">Todos</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Jogadores List -->
<div class="card">
    <div class="card-body">
        <?php if (count($jogadores) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Bolões</th>
                            <th>Pagamentos</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogadores as $jogador): ?>
                            <tr>
                                <td><?= $jogador['id'] ?></td>
                                <td><?= sanitize($jogador['nome']) ?></td>
                                <td><?= sanitize($jogador['email']) ?></td>
                                <td><?= sanitize($jogador['telefone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($jogador['status'] === 'ativo'): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $jogador['total_boloes'] ?></td>
                                <td><?= $jogador['total_pagamentos'] ?></td>
                                <td><?= formatDate($jogador['data_cadastro']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= APP_URL ?>/admin/jogador.php?id=<?= $jogador['id'] ?>" class="btn btn-sm btn-primary" title="Ver detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/admin/editar-jogador.php?id=<?= $jogador['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger toggle-status" data-id="<?= $jogador['id'] ?>" data-status="<?= $jogador['status'] ?>" title="<?= $jogador['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?>">
                                            <i class="bi bi-<?= $jogador['status'] === 'ativo' ? 'lock' : 'unlock' ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-4">
                    <nav>
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $status ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $status ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Próximo">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">
                Nenhum jogador encontrado<?= !empty($search) ? ' para a busca "' . sanitize($search) . '"' : '' ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Alteração</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja <span id="action-text">desativar</span> este jogador?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-jogador.php" method="post">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="jogador_id" id="jogador_id" value="">
                    <button type="submit" class="btn btn-danger">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle status toggle buttons
    const toggleButtons = document.querySelectorAll('.toggle-status');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const status = this.getAttribute('data-status');
            const actionText = status === 'ativo' ? 'desativar' : 'ativar';
            
            document.getElementById('jogador_id').value = id;
            document.getElementById('action-text').textContent = actionText;
            
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        });
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?> 