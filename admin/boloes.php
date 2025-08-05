<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bolaoId = (int) $_GET['delete'];
    
    // Delete bolão
    if (dbDelete('dados_boloes', 'id = ?', [$bolaoId])) {
        setFlashMessage('success', 'Bolão excluído com sucesso.');
    } else {
        setFlashMessage('danger', 'Erro ao excluir o bolão.');
    }
    
    redirect(APP_URL . '/admin/boloes.php');
}

// Handle status update
if (isset($_GET['status']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bolaoId = (int) $_GET['id'];
    $status = ($_GET['status'] == 1) ? 1 : 0;
    
    if (dbUpdate('dados_boloes', ['status' => $status], 'id = ?', [$bolaoId])) {
        setFlashMessage('success', 'Status do bolão atualizado com sucesso.');
    } else {
        setFlashMessage('danger', 'Erro ao atualizar o status do bolão.');
    }
    
    redirect(APP_URL . '/admin/boloes.php');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search filter
$search = $_GET['search'] ?? '';
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = " WHERE nome LIKE ? ";
    $params = ['%' . $search . '%'];
}

// Count total
$totalQuery = "SELECT COUNT(*) as total FROM dados_boloes $whereClause";
$totalResult = dbFetchOne($totalQuery, $params);
$total = $totalResult['total'];
$totalPages = ceil($total / $perPage);

// Get data
$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM palpites p WHERE p.bolao_id = b.id) as total_palpites
        FROM dados_boloes b
        $whereClause
        ORDER BY data_criacao DESC
        LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$boloes = dbFetchAll($sql, $params);

// Include header
$pageTitle = "Gerenciar Bolões";
$currentPage = "boloes";
$hasHeroSection = true;
include '../templates/admin/header.php';
?>
<style>
/* Base Styles */
.table-responsive {
    overflow-x: auto;
    position: relative;
    z-index: 1;
}
.table th,
.table td {
    vertical-align: middle !important;
    white-space: nowrap;
    padding: 0.5rem;
}
.table th {
    background-color: #f8f9fa;
    font-size: 0.875rem;
    font-weight: 600;
}
.table td {
    font-size: 0.875rem;
}

/* Search Form */
.search-form {
    position: relative;
    z-index: 1;
}
.search-form .input-group {
    max-width: 500px;
}

/* Modal Styles */
.modal {
    z-index: 1055 !important;
}
.modal-backdrop {
    z-index: 1050 !important;
}
.modal-dialog {
    z-index: 1056 !important;
}
.modal-actions {
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.modal-actions .modal-content {
    background: rgba(255, 255, 255, 0.95);
    border: none;
    border-radius: 1rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}
.modal-actions .modal-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    padding: 1rem 1.5rem;
}
.modal-actions .modal-body {
    padding: 1rem;
}
.modal-actions .btn-action {
    width: 100%;
    text-align: left;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    border: 1px solid rgba(0, 0, 0, 0.1);
    background: white;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
}
.modal-actions .btn-action:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}
.modal-actions .btn-action i {
    margin-right: 0.75rem;
    width: 1.5rem;
    text-align: center;
}
.modal-actions .btn-action.text-danger {
    color: #dc3545;
}
.modal-actions .btn-action.text-danger:hover {
    background: #dc3545;
    color: white;
}

/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #1a5f7a 0%, #0d2f3d 100%);
    border-radius: 1rem;
    padding: 2.5rem 0rem 1rem 1rem;
    margin: 1.5rem 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 10;
    height: fit-content;
}

.hero-content {
    margin-bottom: 2rem;
}

.hero-title {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    color: white;
}

.hero-title i {
    font-size: 2.5rem;
    color: #4fd1c5;
}

.hero-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.hero-actions {
    display: flex;
    gap: 1rem;
}
.hero-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    text-decoration: none;
}
.hero-btn-primary {
    background: #4fd1c5;
    color: #0d2f3d;
    border: none;
}
.hero-btn-primary:hover {
    background: #38b2ac;
    transform: translateY(-2px);
    color: #0d2f3d;
}
.hero-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.hero-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    color: white;
}

/* Stats Section */
.stats-row {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
}

.stat-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1.25rem;
    color: #4fd1c5;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: white;
    margin: 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}
.badge.bg-success {
    background-color: #198754 !important;
}
.badge.bg-danger {
    background-color: #dc3545 !important;
}
.badge.bg-info {
    background-color: #0dcaf0 !important;
    color: #000;
}

/* Action Button */
.btn-actions {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Container adjustments */
.container-fluid {
    position: relative;
}

/* Card Styles */
.card {
    position: relative;
    z-index: 1;
    border-radius: 1rem;
    overflow: hidden;
}

.card.table-card {
    margin-top: -2rem;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
}

.card-header {
    background: white !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.card-body {
    background: white;
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    background-color: #f8f9fa;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 1rem;
}

.table td {
    font-size: 0.875rem;
    padding: 1rem;
    vertical-align: middle !important;
}

/* Search Form */
.search-form {
    position: relative;
    z-index: 1;
}

.search-form .input-group {
    max-width: 500px;
}

.form-control-lg {
    height: 3rem;
    font-size: 1rem;
}

.btn-lg {
    height: 3rem;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
}

/* Bolão Card Styles */
.bolao-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 1rem;
}

.bolao-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.bolao-card .card-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(45deg, #f8f9fa, #ffffff);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 1rem 1rem 0 0;
}

.bolao-card .card-body {
    padding: 1.25rem;
}

.bolao-card .card-footer {
    padding: 1rem 1.25rem;
    background: #f8f9fa;
    border-radius: 0 0 1rem 1rem;
}

.bolao-card small.text-muted {
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.bolao-card .fw-bold {
    font-size: 0.95rem;
}

.bolao-card .fw-semibold {
    font-size: 0.9rem;
}
</style>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container-fluid">
            <div class="hero-content">
                <div class="row">
                    <div class="col-lg-8">
                        <h1 class="hero-title">
                            <i class="fas fa-trophy"></i>
                            Gerenciamento de Bolões
                        </h1>
                        <p class="hero-subtitle">
                            Gerencie todos os bolões, acompanhe participantes e resultados em um só lugar
                        </p>
                        <div class="hero-actions">
                            <a href="novo-bolao.php" class="hero-btn hero-btn-primary">
                                <i class="fas fa-plus"></i>
                                Criar Novo Bolão
                            </a>
                            <a href="atualizar-jogos.php" class="hero-btn hero-btn-secondary">
                                <i class="fas fa-sync-alt"></i>
                                Atualizar Jogos
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $total ?? 0 ?></div>
                        <div class="stat-label">Total de Bolões</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <?php 
                        $premios = dbFetchOne("SELECT SUM(premio_total) as total FROM dados_boloes");
                        $totalPremios = $premios ? formatMoney($premios['total']) : 'R$ 0,00';
                        ?>
                        <div class="stat-value"><?= $totalPremios ?></div>
                        <div class="stat-label">Total em Prêmios</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <?php 
                        $premiosRodada = dbFetchOne("SELECT SUM(premio_rodada) as total FROM dados_boloes");
                        $totalPremiosRodada = $premiosRodada ? formatMoney($premiosRodada['total']) : 'R$ 0,00';
                        ?>
                        <div class="stat-value"><?= $totalPremiosRodada ?></div>
                        <div class="stat-label">Prêmios por Rodada</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <?php 
                        $participantes = dbFetchOne("SELECT COUNT(DISTINCT jogador_id) as total FROM palpites");
                        $totalParticipantes = $participantes ? $participantes['total'] : 0;
                        ?>
                        <div class="stat-value"><?= $totalParticipantes ?></div>
                        <div class="stat-label">Participantes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php displayFlashMessages(); ?>
    
    <div class="table-card">
        <div class="card-header bg-white py-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <i class="fas fa-table text-primary me-2"></i>
                <span class="h5 mb-0">Lista de Bolões</span>
            </div>
       
        </div>
        <div class="card-body p-4">
            <!-- Search form -->
            <form action="" method="get" class="mb-4 search-form">
                <div class="input-group">
                    <input type="text" name="search" class="form-control form-control-lg" placeholder="Buscar por nome" value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="boloes.php" class="btn btn-outline-secondary btn-lg">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if (empty($boloes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhum bolão encontrado</h5>
                    <p class="text-muted">Crie seu primeiro bolão para começar.</p>
                    <a href="novo-bolao.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Criar Novo Bolão
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($boloes as $bolao): ?>
                        <?php 
                            $jogosArray = json_decode($bolao['jogos'], true);
                            $totalJogos = is_array($jogosArray) ? count($jogosArray) : 0;
                        ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="card bolao-card h-100 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-trophy text-primary me-2"></i>
                                        <span class="fw-bold text-truncate"><?= htmlspecialchars($bolao['nome']) ?></span>
                                    </div>
                                    <span class="badge text-muted">#<?= $bolao['id'] ?></span>
                                </div>
                                
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Data Início</small>
                                            <span class="fw-semibold"><?= formatDate($bolao['data_inicio']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Data Fim</small>
                                            <span class="fw-semibold"><?= formatDate($bolao['data_fim']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Valor Participação</small>
                                            <span class="fw-bold text-success"><?= formatMoney($bolao['valor_participacao']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Status</small>
                                            <?php if ($bolao['status'] == 1): ?>
                                                <a href="?status=0&id=<?= $bolao['id'] ?>" class="badge bg-success text-decoration-none" 
                                                   title="Clique para desativar" data-bs-toggle="tooltip">Ativo</a>
                                            <?php else: ?>
                                                <a href="?status=1&id=<?= $bolao['id'] ?>" class="badge bg-danger text-decoration-none" 
                                                   title="Clique para ativar" data-bs-toggle="tooltip">Inativo</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Prêmio Total</small>
                                            <span class="fw-bold text-warning"><?= formatMoney($bolao['premio_total']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Prêmio/Rodada</small>
                                            <span class="fw-bold text-info"><?= formatMoney($bolao['premio_rodada']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Total de Jogos</small>
                                            <span class="fw-semibold"><?= $totalJogos ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Palpites</small>
                                            <a href="palpites-bolao.php?bolao_id=<?= $bolao['id'] ?>" class="badge bg-info text-decoration-none fs-6">
                                                <?= $bolao['total_palpites'] ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-light border-top-0">
                                    <button class="btn btn-primary w-100" type="button" data-bs-toggle="modal" data-bs-target="#bolaoActionsModal<?= $bolao['id'] ?>">
                                        <i class="fas fa-cog"></i> Gerenciar Bolão
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    Anterior
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    Próxima
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modais de Ações -->
<?php if (!empty($boloes)): ?>
    <?php foreach ($boloes as $bolao): ?>
        <div class="modal fade modal-actions" id="bolaoActionsModal<?= $bolao['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ações do Bolão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <a href="editar-bolao.php?id=<?= $bolao['id'] ?>" class="btn-action">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <a href="jogos-bolao.php?id=<?= $bolao['id'] ?>" class="btn-action">
                            <i class="fas fa-futbol"></i> Jogos
                        </a>
                        <a href="palpites-bolao.php?bolao_id=<?= $bolao['id'] ?>" class="btn-action">
                            <i class="fas fa-list"></i> Palpites
                        </a>
                        <a href="javascript:void(0)" onclick="if(confirm('Tem certeza que deseja excluir este bolão?')) window.location.href='?delete=<?= $bolao['id'] ?>'" class="btn-action text-danger">
                            <i class="fas fa-trash-alt"></i> Excluir
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa os tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?> 