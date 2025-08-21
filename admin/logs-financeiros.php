<?php
/**
 * Logs Financeiros - Sistema de Visualização e Auditoria
 * 
 * Este arquivo gerencia a visualização dos logs financeiros do sistema,
 * permitindo filtros avançados, paginação e exportação de dados.
 * 
 * @author Sistema Bolão
 * @version 2.0
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/classes/LogFinanceiroManager.php';

// Verificação de autenticação administrativa
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
    exit;
}

/**
 * Classe para gerenciar a interface de logs financeiros
 */
class LogsFinanceirosController {
    private $logManager;
    private $filtros = [];
    private $paginacao = [];
    
    public function __construct() {
        $this->logManager = new LogFinanceiroManager();
        $this->processarParametros();
    }
    
    /**
     * Processa e valida os parâmetros de entrada
     */
    private function processarParametros() {
        // Validação e sanitização da paginação
        $this->paginacao['page'] = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
            'options' => ['default' => 1, 'min_range' => 1]
        ]);
        $this->paginacao['limit'] = 20;
        $this->paginacao['offset'] = ($this->paginacao['page'] - 1) * $this->paginacao['limit'];
        
        // Validação e sanitização dos filtros
        $this->processarFiltros();
        
        // Verificar se é uma requisição de exportação
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->exportarCSV();
        }
    }
    
    /**
     * Processa e valida os filtros de busca
     */
    private function processarFiltros() {
        // Filtro por usuário
        $usuarioId = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT);
        if ($usuarioId && $usuarioId > 0) {
            $this->filtros['usuario_id'] = $usuarioId;
        }
        
        // Filtro por tipo
        $tipo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($tipo) && $this->isValidTipo($tipo)) {
            $this->filtros['tipo'] = $tipo;
        }
        
        // Filtros de data
        $dataInicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($dataInicio) && $this->isValidDate($dataInicio)) {
            $this->filtros['data_inicio'] = $dataInicio;
        }
        
        $dataFim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($dataFim) && $this->isValidDate($dataFim)) {
            $this->filtros['data_fim'] = $dataFim;
        }
        
        // Validar intervalo de datas
        if (isset($this->filtros['data_inicio']) && isset($this->filtros['data_fim'])) {
            if (strtotime($this->filtros['data_inicio']) > strtotime($this->filtros['data_fim'])) {
                setFlashMessage('warning', 'Data de início não pode ser maior que data de fim.');
                unset($this->filtros['data_inicio'], $this->filtros['data_fim']);
            }
        }
    }
    
    /**
     * Valida se o tipo é permitido
     */
    private function isValidTipo($tipo) {
        $tiposPermitidos = ['deposito', 'saque', 'aposta', 'premio', 'estorno', 'bonus'];
        return in_array($tipo, $tiposPermitidos);
    }
    
    /**
     * Valida formato de data
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Busca os logs com base nos filtros
     */
    public function buscarLogs() {
        try {
            $logs = $this->logManager->buscarLogs(
                $this->filtros, 
                $this->paginacao['limit'], 
                $this->paginacao['offset']
            );
            
            $total = $this->logManager->contarLogs($this->filtros);
            $this->paginacao['total'] = $total;
            $this->paginacao['totalPages'] = ceil($total / $this->paginacao['limit']);
            
            return $logs;
        } catch (Exception $e) {
            error_log('Erro ao buscar logs financeiros: ' . $e->getMessage());
            setFlashMessage('danger', 'Erro ao carregar logs financeiros.');
            return [];
        }
    }
    
    /**
     * Exporta logs para CSV
     */
    private function exportarCSV() {
        try {
            // Buscar todos os logs sem paginação para exportação
            $logs = $this->logManager->buscarLogs($this->filtros, 10000, 0);
            
            $filename = 'logs_financeiros_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalhos
            fputcsv($output, [
                'Data/Hora',
                'Usuário',
                'Tipo',
                'Descrição',
                'Dados Adicionais'
            ], ';');
            
            // Dados
            foreach ($logs as $log) {
                $tipo = str_replace('financeiro_', '', $log['tipo']);
                fputcsv($output, [
                    formatDateTime($log['data_hora']),
                    $log['usuario_nome'] ?? 'N/A',
                    ucfirst($tipo),
                    $log['descricao'],
                    $log['dados_adicionais'] ? json_encode(json_decode($log['dados_adicionais']), JSON_PRETTY_PRINT) : ''
                ], ';');
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            error_log('Erro ao exportar CSV: ' . $e->getMessage());
            setFlashMessage('danger', 'Erro ao exportar dados.');
            redirect($_SERVER['PHP_SELF']);
        }
    }
    
    /**
     * Retorna os filtros atuais
     */
    public function getFiltros() {
        return $this->filtros;
    }
    
    /**
     * Retorna informações de paginação
     */
    public function getPaginacao() {
        return $this->paginacao;
    }
    
    /**
     * Busca usuários para o select
     */
    public function buscarUsuarios() {
        try {
            return dbFetchAll("SELECT id, nome FROM jogador ORDER BY nome");
        } catch (Exception $e) {
            error_log('Erro ao buscar usuários: ' . $e->getMessage());
            return [];
        }
    }
}

// Inicializar controller
$controller = new LogsFinanceirosController();
$logs = $controller->buscarLogs();
$filtros = $controller->getFiltros();
$paginacao = $controller->getPaginacao();
$usuarios = $controller->buscarUsuarios();

// Configurações da página
$pageTitle = "Logs Financeiros";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1 class="mb-0"><?= $pageTitle ?></h1>
        <div class="text-muted">
            <i class="fas fa-clock"></i> Última atualização: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
    
    <?php displayFlashMessages(); ?>
    
    <!-- Estatísticas Resumidas -->
    <?php if (!empty($logs) || !empty($filtros)): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total de Registros</h6>
                                <h4 class="mb-0"><?= number_format($paginacao['total'] ?? 0) ?></h4>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-list fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Página Atual</h6>
                                <h4 class="mb-0"><?= $paginacao['page'] ?? 1 ?> de <?= $paginacao['totalPages'] ?? 1 ?></h4>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Filtros Ativos</h6>
                                <h4 class="mb-0"><?= count($filtros) ?></h4>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-filter fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Registros/Página</h6>
                                <h4 class="mb-0"><?= $paginacao['limit'] ?? 20 ?></h4>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-eye fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
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
                        <?php foreach ($usuarios as $usuario): 
                            $selected = isset($filtros['usuario_id']) && $filtros['usuario_id'] == $usuario['id'] ? 'selected' : '';
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
                        <?php 
                        $tiposOperacao = [
                            'deposito' => 'Depósito',
                            'saque' => 'Saque', 
                            'aposta' => 'Aposta',
                            'premio' => 'Prêmio',
                            'estorno' => 'Estorno',
                            'bonus' => 'Bônus'
                        ];
                        
                        foreach ($tiposOperacao as $valor => $label):
                            $selected = isset($filtros['tipo']) && $filtros['tipo'] == $valor ? 'selected' : '';
                        ?>
                            <option value="<?= $valor ?>" <?= $selected ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                           value="<?= $filtros['data_inicio'] ?? '' ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" 
                           value="<?= $filtros['data_fim'] ?? '' ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                    <?php if (!empty($logs)): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                           class="btn btn-success" title="Exportar dados filtrados">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    <?php endif; ?>
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
                <?php if ($paginacao['totalPages'] > 1): ?>
                    <nav aria-label="Navegação de página" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- Primeira página -->
                            <?php if ($paginacao['page'] > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $paginacao['page'] - 1])) ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Páginas numeradas -->
                            <?php 
                            $startPage = max(1, $paginacao['page'] - 2);
                            $endPage = min($paginacao['totalPages'], $paginacao['page'] + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?= $i == $paginacao['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Última página -->
                            <?php if ($paginacao['page'] < $paginacao['totalPages']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $paginacao['page'] + 1])) ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $paginacao['totalPages']])) ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Informações da paginação -->
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Mostrando <?= ($paginacao['offset'] + 1) ?> a <?= min($paginacao['offset'] + $paginacao['limit'], $paginacao['total']) ?> 
                                de <?= $paginacao['total'] ?> registros
                            </small>
                        </div>
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
    // Modal de detalhes
    const modalDetalhes = document.getElementById('modalDetalhes');
    if (modalDetalhes) {
        modalDetalhes.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            try {
                const detalhes = JSON.parse(button.getAttribute('data-detalhes'));
                document.getElementById('detalhesJson').textContent = 
                    JSON.stringify(detalhes, null, 2);
            } catch (e) {
                document.getElementById('detalhesJson').textContent = 
                    'Erro ao carregar detalhes: ' + e.message;
            }
        });
    }
    
    // Confirmação para exportação CSV
    const exportBtn = document.querySelector('a[href*="export=csv"]');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            if (!confirm('Deseja exportar os dados filtrados para CSV?')) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-submit do formulário quando mudar as datas
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Pequeno delay para permitir que o usuário termine de selecionar
            setTimeout(() => {
                if (this.value) {
                    this.form.submit();
                }
            }, 500);
        });
    });
    
    // Highlight da linha ao passar o mouse
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?>