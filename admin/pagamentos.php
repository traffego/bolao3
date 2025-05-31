<?php
/**
 * Admin Pagamentos - Bolão Football
 */
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/pagamentos.php';
    redirect(APP_URL . '/admin/login.php');
}

// Handle status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'todos';
if (!in_array($status, ['pendente', 'aprovado', 'recusado', 'todos'])) {
    $status = 'todos';
}

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $itemsPerPage;

// Status clause for SQL
$statusClause = $status === 'todos' ? '1' : "p.status = '$status'";

// Date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$dateClause = '';

if (!empty($startDate) && !empty($endDate)) {
    $dateClause = " AND p.data_pagamento BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
} elseif (!empty($startDate)) {
    $dateClause = " AND p.data_pagamento >= '$startDate 00:00:00'";
} elseif (!empty($endDate)) {
    $dateClause = " AND p.data_pagamento <= '$endDate 23:59:59'";
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchClause = '';
$searchParams = [];

if (!empty($search)) {
    $searchClause = " AND (j.nome LIKE ? OR j.email LIKE ? OR b.nome LIKE ?)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// Count total pagamentos
$countQuery = "SELECT COUNT(*) as total FROM pagamentos p 
               JOIN jogador j ON p.jogador_id = j.id 
               LEFT JOIN boloes b ON p.bolao_id = b.id 
               WHERE $statusClause $dateClause $searchClause";

$totalResult = dbFetchOne($countQuery, $searchParams);
$totalPagamentos = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($totalPagamentos / $itemsPerPage);

// Make sure $page is within limits
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get pagamentos with pagination
$pagamentos = dbFetchAll(
    "SELECT p.*, j.nome as jogador_nome, j.email as jogador_email, b.nome as bolao_nome 
     FROM pagamentos p
     JOIN jogador j ON p.jogador_id = j.id
     LEFT JOIN boloes b ON p.bolao_id = b.id
     WHERE $statusClause $dateClause $searchClause
     ORDER BY p.data_pagamento DESC
     LIMIT ? OFFSET ?", 
    array_merge($searchParams, [$itemsPerPage, $offset])
);

// Calculate totals
$totalsQuery = "SELECT 
                SUM(CASE WHEN p.status = 'aprovado' THEN p.valor ELSE 0 END) as total_aprovado,
                SUM(CASE WHEN p.status = 'pendente' THEN p.valor ELSE 0 END) as total_pendente,
                SUM(CASE WHEN p.status = 'recusado' THEN p.valor ELSE 0 END) as total_recusado
                FROM pagamentos p
                JOIN jogador j ON p.jogador_id = j.id
                LEFT JOIN boloes b ON p.bolao_id = b.id
                WHERE 1 $dateClause $searchClause";

$totals = dbFetchOne($totalsQuery, $searchParams);

// Page title
$pageTitle = 'Gerenciar Pagamentos';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $pageTitle ?></h1>
    <div>
        <a href="<?= APP_URL ?>/admin/novo-pagamento.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Registrar Pagamento
        </a>
    </div>
</div>

<!-- Financial Summary -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h3 class="display-4 text-success"><?= formatMoney($totals['total_aprovado'] ?? 0) ?></h3>
                <p class="card-text">Total Aprovado</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h3 class="display-4 text-warning"><?= formatMoney($totals['total_pendente'] ?? 0) ?></h3>
                <p class="card-text">Total Pendente</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h3 class="display-4 text-danger"><?= formatMoney($totals['total_recusado'] ?? 0) ?></h3>
                <p class="card-text">Total Recusado</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Buscar por jogador ou bolão" value="<?= sanitize($search) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="row">
                    <div class="col-6">
                        <input type="date" name="start_date" class="form-control" placeholder="Data inicial" value="<?= $startDate ?>">
                    </div>
                    <div class="col-6">
                        <input type="date" name="end_date" class="form-control" placeholder="Data final" value="<?= $endDate ?>">
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="btn-group w-100">
                    <a href="?status=todos<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" class="btn btn-outline-secondary <?= $status === 'todos' ? 'active' : '' ?>">Todos</a>
                    <a href="?status=pendente<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" class="btn btn-outline-warning <?= $status === 'pendente' ? 'active' : '' ?>">Pendentes</a>
                    <a href="?status=aprovado<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" class="btn btn-outline-success <?= $status === 'aprovado' ? 'active' : '' ?>">Aprovados</a>
                    <a href="?status=recusado<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" class="btn btn-outline-danger <?= $status === 'recusado' ? 'active' : '' ?>">Recusados</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Pagamentos List -->
<div class="card">
    <div class="card-body">
        <?php if (count($pagamentos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Jogador</th>
                            <th>Bolão</th>
                            <th>Valor</th>
                            <th>Método</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Transação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentos as $pagamento): ?>
                            <tr>
                                <td><?= $pagamento['id'] ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/jogador.php?id=<?= $pagamento['jogador_id'] ?>" title="Ver jogador">
                                        <?= sanitize($pagamento['jogador_nome']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($pagamento['bolao_id']): ?>
                                        <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $pagamento['bolao_id'] ?>" title="Ver bolão">
                                            <?= sanitize($pagamento['bolao_nome']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatMoney($pagamento['valor']) ?></td>
                                <td><?= sanitize($pagamento['metodo']) ?></td>
                                <td>
                                    <?php if ($pagamento['status'] === 'aprovado'): ?>
                                        <span class="badge bg-success">Aprovado</span>
                                    <?php elseif ($pagamento['status'] === 'pendente'): ?>
                                        <span class="badge bg-warning">Pendente</span>
                                    <?php elseif ($pagamento['status'] === 'recusado'): ?>
                                        <span class="badge bg-danger">Recusado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Reembolsado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDateTime($pagamento['data_pagamento']) ?></td>
                                <td>
                                    <small><?= $pagamento['transacao_id'] ? sanitize($pagamento['transacao_id']) : 'N/A' ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= APP_URL ?>/admin/editar-pagamento.php?id=<?= $pagamento['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        
                                        <?php if ($pagamento['status'] === 'pendente'): ?>
                                            <button type="button" class="btn btn-sm btn-success approver" data-id="<?= $pagamento['id'] ?>" title="Aprovar">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger rejecter" data-id="<?= $pagamento['id'] ?>" title="Recusar">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php endif; ?>
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
                                <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $status ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $status ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" aria-label="Próximo">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">
                Nenhum pagamento encontrado<?= !empty($search) ? ' para a busca "' . sanitize($search) . '"' : '' ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Aprovar Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Aprovar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja aprovar este pagamento?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-pagamento.php" method="post">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="pagamento_id" id="approve_id" value="">
                    <button type="submit" class="btn btn-success">Aprovar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Recusar Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Recusar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja recusar este pagamento?</p>
                <div class="form-group">
                    <label for="reject_reason">Motivo da recusa (opcional):</label>
                    <textarea id="reject_reason" name="reject_reason" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-pagamento.php" method="post">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="pagamento_id" id="reject_id" value="">
                    <input type="hidden" name="motivo" id="reject_reason_hidden" value="">
                    <button type="submit" class="btn btn-danger">Recusar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle approve buttons
    const approveButtons = document.querySelectorAll('.approver');
    approveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            document.getElementById('approve_id').value = id;
            
            const modal = new bootstrap.Modal(document.getElementById('approveModal'));
            modal.show();
        });
    });
    
    // Handle reject buttons
    const rejectButtons = document.querySelectorAll('.rejecter');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            document.getElementById('reject_id').value = id;
            
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        });
    });
    
    // Update hidden field before form submission
    document.getElementById('rejectModal').addEventListener('submit', function() {
        const reason = document.getElementById('reject_reason').value;
        document.getElementById('reject_reason_hidden').value = reason;
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?> 