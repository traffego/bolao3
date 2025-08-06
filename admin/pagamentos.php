<?php
/**
 * Admin Pagamentos - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/pagamentos.php';
    redirect(APP_URL . '/admin/login.php');
}

// Handle status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'todos';
if (!in_array($status, ['pendente', 'aprovado', 'rejeitado', 'todos'])) {
    $status = 'todos';
}

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $itemsPerPage;

// Status clause for SQL  
$statusClause = $status === 'todos' ? '1' : "t.status = '$status'";

// Date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$dateClause = '';

if (!empty($startDate) && !empty($endDate)) {
    $dateClause = " AND t.data_solicitacao BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
} elseif (!empty($startDate)) {
    $dateClause = " AND t.data_solicitacao >= '$startDate 00:00:00'";
} elseif (!empty($endDate)) {
    $dateClause = " AND t.data_solicitacao <= '$endDate 23:59:59'";
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchClause = '';
$searchParams = [];

if (!empty($search)) {
    $searchClause = " AND (j.nome LIKE ? OR j.email LIKE ? OR t.descricao LIKE ?)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// Count total transacoes
$countQuery = "SELECT COUNT(*) as total FROM transacoes t 
               JOIN contas c ON t.conta_id = c.id
               JOIN jogador j ON c.jogador_id = j.id 
               WHERE t.tipo IN ('deposito', 'saque') AND $statusClause $dateClause $searchClause";

$totalResult = dbFetchOne($countQuery, $searchParams);
$totalTransacoes = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($totalTransacoes / $itemsPerPage);

// Make sure $page is within limits
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get transacoes with pagination
$transacoes = dbFetchAll(
    "SELECT t.*, j.nome as jogador_nome, j.email as jogador_email, 
            CASE 
                WHEN t.tipo = 'deposito' THEN 'Depósito'
                WHEN t.tipo = 'saque' THEN 'Saque'
                ELSE t.tipo
            END as metodo,
            t.data_solicitacao as data_pagamento,
            t.id as transacao_id
     FROM transacoes t
     JOIN contas c ON t.conta_id = c.id
     JOIN jogador j ON c.jogador_id = j.id
     WHERE t.tipo IN ('deposito', 'saque') AND $statusClause $dateClause $searchClause
     ORDER BY t.data_solicitacao DESC
     LIMIT ? OFFSET ?", 
    array_merge($searchParams, [$itemsPerPage, $offset])
);

// Calculate totals
$totalsQuery = "SELECT 
                SUM(CASE 
                    WHEN t.status = 'aprovado' AND t.tipo = 'deposito' THEN t.valor
                    WHEN t.status = 'aprovado' AND t.tipo = 'saque' THEN -t.valor
                    ELSE 0 
                END) as balanco_total,
                SUM(CASE WHEN t.status = 'aprovado' AND t.tipo = 'deposito' THEN t.valor ELSE 0 END) as total_depositos,
                SUM(CASE WHEN t.status = 'aprovado' AND t.tipo = 'saque' THEN t.valor ELSE 0 END) as total_saques,
                SUM(CASE WHEN t.status = 'pendente' THEN t.valor ELSE 0 END) as total_pendente,
                SUM(CASE WHEN t.status = 'rejeitado' THEN t.valor ELSE 0 END) as total_rejeitado
                FROM transacoes t
                JOIN contas c ON t.conta_id = c.id
                JOIN jogador j ON c.jogador_id = j.id
                WHERE t.tipo IN ('deposito', 'saque') $dateClause $searchClause";

$totals = dbFetchOne($totalsQuery, $searchParams);

// Page title
$pageTitle = 'Gerenciar Transações Financeiras';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $pageTitle ?></h1>
    <div>
        <a href="<?= APP_URL ?>/admin/nova-transacao.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Transação
        </a>
    </div>
</div>

<!-- Financial Summary -->
<div class="row mb-4">
    <!-- Balanço Total -->
    <div class="col-md-12 mb-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h2 class="display-3 <?= ($totals['balanco_total'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?> mb-2">
                    <i class="bi bi-cash-stack me-2"></i>
                    <?= formatMoney($totals['balanco_total'] ?? 0) ?>
                </h2>
                <p class="card-text fs-5 fw-bold text-primary">Balanço Total do Sistema</p>
                <small class="text-muted">
                    Depósitos - Saques (apenas transações aprovadas)
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Detalhamento por Tipo -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h4 class="display-6 text-success"><?= formatMoney($totals['total_depositos'] ?? 0) ?></h4>
                <p class="card-text">Total Depósitos</p>
                <small class="text-muted">Entradas aprovadas</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h4 class="display-6 text-danger"><?= formatMoney($totals['total_saques'] ?? 0) ?></h4>
                <p class="card-text">Total Saques</p>
                <small class="text-muted">Saídas aprovadas</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h4 class="display-6 text-warning"><?= formatMoney($totals['total_pendente'] ?? 0) ?></h4>
                <p class="card-text">Pendentes</p>
                <small class="text-muted">Aguardando aprovação</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h4 class="display-6 text-secondary"><?= formatMoney($totals['total_rejeitado'] ?? 0) ?></h4>
                <p class="card-text">Rejeitados</p>
                <small class="text-muted">Transações negadas</small>
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
                    <input type="text" name="search" class="form-control" placeholder="Buscar por jogador ou descrição" value="<?= sanitize($search) ?>">
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
                    <a href="?status=rejeitado<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($startDate) ? '&start_date=' . $startDate : '' ?><?= !empty($endDate) ? '&end_date=' . $endDate : '' ?>" class="btn btn-outline-danger <?= $status === 'rejeitado' ? 'active' : '' ?>">Rejeitados</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Pagamentos List -->
<div class="card">
    <div class="card-body">
        <?php if (count($transacoes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Jogador</th>
                            <th>Descrição</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Método</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>TXID</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transacoes as $transacao): ?>
                            <tr>
                                <td><?= $transacao['id'] ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/jogador.php?id=<?= $transacao['conta_id'] ?>" title="Ver jogador">
                                        <?= sanitize($transacao['jogador_nome']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($transacao['descricao']): ?>
                                        <small><?= sanitize($transacao['descricao']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($transacao['tipo'] === 'deposito'): ?>
                                        <span class="badge bg-success"><i class="bi bi-plus-circle me-1"></i>Depósito</span>
                                    <?php elseif ($transacao['tipo'] === 'saque'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-dash-circle me-1"></i>Saque</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= ucfirst($transacao['tipo'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($transacao['tipo'] === 'deposito'): ?>
                                        <span class="text-success">+<?= formatMoney($transacao['valor']) ?></span>
                                    <?php elseif ($transacao['tipo'] === 'saque'): ?>
                                        <span class="text-danger">-<?= formatMoney($transacao['valor']) ?></span>
                                    <?php else: ?>
                                        <?= formatMoney($transacao['valor']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($transacao['metodo']) ?></td>
                                <td>
                                    <?php if ($transacao['status'] === 'aprovado'): ?>
                                        <span class="badge bg-success">Aprovado</span>
                                    <?php elseif ($transacao['status'] === 'pendente'): ?>
                                        <span class="badge bg-warning">Pendente</span>
                                    <?php elseif ($transacao['status'] === 'rejeitado'): ?>
                                        <span class="badge bg-danger">Rejeitado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= ucfirst($transacao['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDateTime($transacao['data_pagamento']) ?></td>
                                <td>
                                    <small><?= $transacao['txid'] ? sanitize($transacao['txid']) : 'N/A' ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= APP_URL ?>/admin/editar-transacao.php?id=<?= $transacao['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil me-1"></i>Editar
                                        </a>
                                        
                                        <?php if ($transacao['status'] === 'pendente'): ?>
                                            <button type="button" class="btn btn-sm btn-success approver" data-id="<?= $transacao['id'] ?>" title="Aprovar">
                                                <i class="bi bi-check-lg me-1"></i>Aprovar
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger rejecter" data-id="<?= $transacao['id'] ?>" title="Rejeitar">
                                                <i class="bi bi-x-lg me-1"></i>Rejeitar
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
                Nenhuma transação encontrada<?= !empty($search) ? ' para a busca "' . sanitize($search) . '"' : '' ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Aprovar Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Aprovar Transação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja aprovar esta transação?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-transacao.php" method="post">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="transacao_id" id="approve_id" value="">
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
                <h5 class="modal-title">Recusar Transação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja recusar esta transação?</p>
                <div class="form-group">
                    <label for="reject_reason">Motivo da recusa (opcional):</label>
                    <textarea id="reject_reason" name="reject_reason" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-transacao.php" method="post">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="transacao_id" id="reject_id" value="">
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