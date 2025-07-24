<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/classes/LogFinanceiroManager.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Instanciar gerenciador de logs
$logManager = new LogFinanceiroManager();

// Processar filtros
$filtros = [];
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 20;
$offset = ($page - 1) * $limit;

if (isset($_GET['usuario_id']) && is_numeric($_GET['usuario_id'])) {
    $filtros['usuario_id'] = (int) $_GET['usuario_id'];
}

if (!empty($_GET['tipo'])) {
    $filtros['tipo'] = $_GET['tipo'];
}

if (!empty($_GET['data_inicio'])) {
    $filtros['data_inicio'] = $_GET['data_inicio'];
}

if (!empty($_GET['data_fim'])) {
    $filtros['data_fim'] = $_GET['data_fim'];
}

// Buscar logs
$logs = $logManager->buscarLogs($filtros, $limit, $offset);
$total = $logManager->contarLogs($filtros);
$totalPages = ceil($total / $limit);

// Page title
$pageTitle = "Logs Financeiros";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?= $pageTitle ?></h1>
    
    <?php displayFlashMessages(); ?>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filtros
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="usuario_id" class="form-label">Usuário</label>
                    <select name="usuario_id" id="usuario_id" class="form-select">
                        <option value="">Todos</option>
                        <?php
                        $usuarios = dbFetchAll("SELECT id, nome FROM jogador ORDER BY nome");
                        foreach ($usuarios as $usuario):
                            $selected = isset($_GET['usuario_id']) && $_GET['usuario_id'] == $usuario['id'] ? 'selected' : '';
                        ?>
                            <option value="<?= $usuario['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($usuario['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="deposito" <?= isset($_GET['tipo']) && $_GET['tipo'] == 'deposito' ? 'selected' : '' ?>>Depósito</option>
                        <option value="saque" <?= isset($_GET['tipo']) && $_GET['tipo'] == 'saque' ? 'selected' : '' ?>>Saque</option>
                        <option value="aposta" <?= isset($_GET['tipo']) && $_GET['tipo'] == 'aposta' ? 'selected' : '' ?>>Aposta</option>
                        <option value="premio" <?= isset($_GET['tipo']) && $_GET['tipo'] == 'premio' ? 'selected' : '' ?>>Prêmio</option>
                        <option value="estorno" <?= isset($_GET['tipo']) && $_GET['tipo'] == 'estorno' ? 'selected' : '' ?>>Estorno</option>
                        <option value="bonus" <?= isset($_GET['tipo']) && $_GET['tipo'] == 'bonus' ? 'selected' : '' ?>>Bônus</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                           value="<?= $_GET['data_inicio'] ?? '' ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" 
                           value="<?= $_GET['data_fim'] ?? '' ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Listagem -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Logs Financeiros
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info">
                    Nenhum log encontrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= formatDateTime($log['data_hora']) ?></td>
                                    <td><?= htmlspecialchars($log['usuario_nome']) ?></td>
                                    <td>
                                        <?php
                                            $tipo = str_replace('financeiro_', '', $log['tipo']);
                                            $tipoClass = match($tipo) {
                                                'deposito' => 'success',
                                                'saque' => 'warning',
                                                'aposta' => 'info',
                                                'premio' => 'primary',
                                                'estorno' => 'danger',
                                                'bonus' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $tipoClass ?>">
                                            <?= ucfirst($tipo) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['descricao']) ?></td>
                                    <td>
                                        <?php if ($log['dados_adicionais']): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalDetalhes"
                                                    data-detalhes='<?= htmlspecialchars($log['dados_adicionais']) ?>'>
                                                <i class="fas fa-info-circle"></i> Detalhes
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navegação de página" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
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

<!-- Modal Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre class="bg-light p-3 rounded"><code id="detalhesJson"></code></pre>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalDetalhes = document.getElementById('modalDetalhes');
    if (modalDetalhes) {
        modalDetalhes.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const detalhes = JSON.parse(button.getAttribute('data-detalhes'));
            document.getElementById('detalhesJson').textContent = 
                JSON.stringify(detalhes, null, 2);
        });
    }
});
</script>

<?php include '../templates/admin/footer.php'; ?> 