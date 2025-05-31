<?php
require_once '../config/config.php';
require_once '../includes/database.php';
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
$sql = "SELECT * FROM dados_boloes
        $whereClause
        ORDER BY data_criacao DESC
        LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$boloes = dbFetchAll($sql, $params);

// Include header
$pageTitle = "Gerenciar Bolões";
$currentPage = "boloes";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1>Gerenciar Bolões</h1>
        <div>
            <a href="atualizar-jogos.php" class="btn btn-success me-2">
                <i class="fas fa-sync-alt"></i> Atualizar Resultados dos Jogos
            </a>
            <a href="novo-bolao.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Bolão
            </a>
        </div>
    </div>
    
    <?php displayFlashMessages(); ?>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Bolões</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-1"></i>
                Lista de Bolões
            </div>
            <a href="novo-bolao.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Novo Bolão
            </a>
        </div>
        <div class="card-body">
            <!-- Search form -->
            <form action="" method="get" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nome" value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="boloes.php" class="btn btn-outline-secondary">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Data Início</th>
                        <th>Data Fim</th>
                        <th>Valor</th>
                        <th>Prêmio</th>
                        <th>Status</th>
                        <th>Jogos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($boloes)): ?>
                        <tr>
                            <td colspan="9" class="text-center">Nenhum bolão encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($boloes as $bolao): ?>
                            <?php 
                                // Calcular total de jogos do JSON
                                $jogosArray = json_decode($bolao['jogos'], true);
                                $totalJogos = is_array($jogosArray) ? count($jogosArray) : 0;
                            ?>
                            <tr>
                                <td><?= $bolao['id'] ?></td>
                                <td><?= htmlspecialchars($bolao['nome']) ?></td>
                                <td><?= formatDate($bolao['data_inicio']) ?></td>
                                <td><?= formatDate($bolao['data_fim']) ?></td>
                                <td><?= formatMoney($bolao['valor_participacao']) ?></td>
                                <td><?= formatMoney($bolao['premio_total']) ?></td>
                                <td>
                                    <?php if ($bolao['status'] == 1): ?>
                                        <a href="?status=0&id=<?= $bolao['id'] ?>" class="badge bg-success text-decoration-none" 
                                           title="Clique para desativar" data-bs-toggle="tooltip">Ativo</a>
                                    <?php else: ?>
                                        <a href="?status=1&id=<?= $bolao['id'] ?>" class="badge bg-danger text-decoration-none" 
                                           title="Clique para ativar" data-bs-toggle="tooltip">Inativo</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= $totalJogos ?></td>
                                <td>
                                    <div class="btn-group" role="group" aria-label="Ações">
                                        <a href="editar-bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-sm btn-info text-white" 
                                           data-bs-toggle="tooltip" title="Editar">
                                           <i class="fas fa-edit me-1"></i> Editar
                                        </a>
                                        <a href="jogos-bolao.php?bolao_id=<?= $bolao['id'] ?>" class="btn btn-sm btn-success" 
                                           data-bs-toggle="tooltip" title="Gerenciar Jogos">
                                           <i class="fas fa-futbol me-1"></i> Jogos
                                        </a>
                                        <a href="palpites-bolao.php?bolao_id=<?= $bolao['id'] ?>" class="btn btn-sm btn-warning text-dark" 
                                           data-bs-toggle="tooltip" title="Ver Palpites">
                                           <i class="fas fa-list-check me-1"></i> Palpites
                                        </a>
                                        <a href="ver-bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-sm btn-primary" 
                                           data-bs-toggle="tooltip" title="Visualizar">
                                           <i class="fas fa-eye me-1"></i> Ver
                                        </a>
                                        <a href="?delete=<?= $bolao['id'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Tem certeza que deseja excluir este bolão? Esta ação não pode ser desfeita.')"
                                           data-bs-toggle="tooltip" title="Excluir">
                                           <i class="fas fa-trash me-1"></i> Excluir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
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

<?php include '../templates/admin/footer.php'; ?> 