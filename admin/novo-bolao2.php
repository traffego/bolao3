<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Initialize variables
$jogos = [];
$errors = [];
$apiConfig = getConfig('api_football');
$anoAtual = date('Y');
$prazo_dias = $_GET['prazo_dias'] ?? 30;

// IDs dos campeonatos brasileiros
$campeonatosBrasil = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    253 => 'Brasileirão Série C',
    254 => 'Brasileirão Série D',
    73 => 'Copa do Brasil'
];

// Default form data
$formData = [
    'nome' => '',
    'descricao' => '',
    'data_inicio' => date('Y-m-d'),
    'data_fim' => date('Y-m-d', strtotime('+30 days')),
    'valor_participacao' => '',
    'max_participantes' => '',
    'premio_total' => '',
    'publico' => 1,
    'quantidade_jogos' => '11',
    'incluir_sem_horario' => false
];

// Processar seleção de campeonato brasileiro
if (isset($_GET['campeonato_brasil']) && isset($campeonatosBrasil[$_GET['campeonato_brasil']])) {
    $campeonatoId = (int)$_GET['campeonato_brasil'];
    $incluirSemHorario = isset($_GET['incluir_sem_horario']) && $_GET['incluir_sem_horario'] == '1';
    
    // Buscar jogos não utilizados
    $jogos = buscarJogosNaoUtilizados($campeonatoId, $anoAtual, $incluirSemHorario);
}

/**
 * Busca jogos que não estão sendo utilizados em outros bolões
 */
function buscarJogosNaoUtilizados($campeonatoId, $ano, $incluirSemHorario = false) {
    global $pdo;
    
    try {
        // Buscar jogos da API
        $jogosApi = buscarJogosRodada($campeonatoId, $ano, null);
        
        if (empty($jogosApi)) {
            return [];
        }
        
        // Buscar IDs dos jogos já utilizados em bolões
        $sql = "SELECT DISTINCT fixture_id FROM bolao_jogos";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $jogosUtilizados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Filtrar jogos não utilizados
        $jogosDisponiveis = [];
        foreach ($jogosApi as $jogo) {
            $fixtureId = $jogo['fixture']['id'];
            
            // Pular se o jogo já está sendo usado
            if (in_array($fixtureId, $jogosUtilizados)) {
                continue;
            }
            
            // Verificar se deve incluir jogos sem horário definido
            if (!$incluirSemHorario) {
                $dataJogo = $jogo['fixture']['date'];
                $timestamp = strtotime($dataJogo);
                
                // Verificar se o horário é 00:00:00 (sem horário definido)
                if (date('H:i:s', $timestamp) === '00:00:00') {
                    continue;
                }
            }
            
            $jogosDisponiveis[] = $jogo;
        }
        
        return $jogosDisponiveis;
        
    } catch (Exception $e) {
        error_log("Erro ao buscar jogos não utilizados: " . $e->getMessage());
        return [];
    }
}

// Include header
$pageTitle = "Criar Bolão Simplificado";
$currentPage = "novo-bolao2";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4" style="padding-top: 4.5rem;">
    <h1 class="mt-4">Criar Bolão Simplificado</h1>
    
    <?php $flashMessage = getFlashMessage(); ?>
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <!-- Formulário de Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-filter me-1"></i>
            Filtros de Jogos
        </div>
        <div class="card-body">
            <form method="get" action="" id="formFiltros">
                <div class="row">
                    <!-- Campeonato -->
                    <div class="col-md-4 mb-3">
                        <label for="campeonato_brasil" class="form-label">Campeonato</label>
                        <select class="form-select" id="campeonato_brasil" name="campeonato_brasil">
                            <option value="">Selecione um campeonato</option>
                            <?php foreach ($campeonatosBrasil as $id => $nome): ?>
                                <option value="<?= $id ?>" 
                                        <?= isset($_GET['campeonato_brasil']) && $_GET['campeonato_brasil'] == $id ? 'selected' : '' ?>>
                                    <?= $nome ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Prazo em dias -->
                    <div class="col-md-3 mb-3">
                        <label for="prazo_dias" class="form-label">Prazo (dias)</label>
                        <select class="form-select" id="prazo_dias" name="prazo_dias">
                            <option value="7" <?= $prazo_dias == 7 ? 'selected' : '' ?>>7 dias</option>
                            <option value="15" <?= $prazo_dias == 15 ? 'selected' : '' ?>>15 dias</option>
                            <option value="30" <?= $prazo_dias == 30 ? 'selected' : '' ?>>30 dias</option>
                            <option value="60" <?= $prazo_dias == 60 ? 'selected' : '' ?>>60 dias</option>
                        </select>
                    </div>
                    
                    <!-- Checkbox para incluir jogos sem horário -->
                    <div class="col-md-3 mb-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" 
                                   id="incluir_sem_horario" name="incluir_sem_horario" value="1"
                                   <?= isset($_GET['incluir_sem_horario']) && $_GET['incluir_sem_horario'] == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="incluir_sem_horario">
                                Incluir jogos sem horário definido
                            </label>
                        </div>
                    </div>
                    
                    <!-- Botão de buscar -->
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn btn-primary mt-4">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulário de Criação do Bolão -->
    <?php if (!empty($jogos)): ?>
    <form method="post" action="salvar-bolao.php" enctype="multipart/form-data" id="formBolao">
        <div class="row">
            <!-- Informações Básicas -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-info-circle me-1"></i>
                        Informações Básicas
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Bolão *</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?= htmlspecialchars($formData['nome']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"><?= htmlspecialchars($formData['descricao']) ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="data_inicio" class="form-label">Data de Início *</label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                                       value="<?= $formData['data_inicio'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_fim" class="form-label">Data de Fim *</label>
                                <input type="date" class="form-control" id="data_fim" name="data_fim" 
                                       value="<?= $formData['data_fim'] ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="valor_participacao" class="form-label">Valor de Participação (R$) *</label>
                                <input type="number" class="form-control" id="valor_participacao" name="valor_participacao" 
                                       step="0.01" min="0" value="<?= $formData['valor_participacao'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="max_participantes" class="form-label">Máx. Participantes</label>
                                <input type="number" class="form-control" id="max_participantes" name="max_participantes" 
                                       min="1" value="<?= $formData['max_participantes'] ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="premio_total" class="form-label">Prêmio Total (R$)</label>
                            <input type="number" class="form-control" id="premio_total" name="premio_total" 
                                   step="0.01" min="0" value="<?= $formData['premio_total'] ?>">
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="publico" name="publico" value="1" 
                                   <?= $formData['publico'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="publico">
                                Bolão Público
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seleção de Jogos -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-futbol me-1"></i>
                        Jogos Disponíveis (<?= count($jogos) ?>)
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="quantidade_jogos" class="form-label">Quantidade de Jogos</label>
                            <input type="number" class="form-control" id="quantidade_jogos" name="quantidade_jogos" 
                                   min="1" max="<?= count($jogos) ?>" value="<?= $formData['quantidade_jogos'] ?>">
                        </div>
                        
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th width="40">Sel.</th>
                                        <th>Data/Hora</th>
                                        <th>Jogo</th>
                                    </tr>
                                </thead>
                                <tbody id="jogos-tbody">
                                    <?php foreach ($jogos as $index => $jogo): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input jogo-checkbox" 
                                                       name="jogos_selecionados[]" 
                                                       value="<?= $jogo['fixture']['id'] ?>"
                                                       data-index="<?= $index ?>">
                                            </td>
                                            <td>
                                                <?php 
                                                $dataJogo = $jogo['fixture']['date'];
                                                $timestamp = strtotime($dataJogo);
                                                if (date('H:i:s', $timestamp) === '00:00:00') {
                                                    echo date('d/m/Y', $timestamp) . '<br><small class="text-muted">Horário a definir</small>';
                                                } else {
                                                    echo date('d/m/Y H:i', $timestamp);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= htmlspecialchars($jogo['teams']['home']['name']) ?>
                                                    <strong>vs</strong>
                                                    <?= htmlspecialchars($jogo['teams']['away']['name']) ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="selecionar-todos">
                                Selecionar Todos
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="limpar-selecao">
                                Limpar Seleção
                            </button>
                            <span class="ms-3 text-muted" id="contador-jogos">0 jogos selecionados</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botões de Ação -->
        <div class="card">
            <div class="card-body text-center">
                <button type="submit" class="btn btn-success btn-lg me-3">
                    <i class="fas fa-save"></i> Criar Bolão
                </button>
                <a href="boloes.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
        
        <!-- Campos ocultos para dados dos jogos -->
        <input type="hidden" id="jogos-data" name="jogos_data" value="<?= htmlspecialchars(json_encode($jogos)) ?>">
    </form>
    <?php elseif (!empty($_GET)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Nenhum jogo disponível encontrado com os filtros selecionados.
    </div>
    <?php endif; ?>
</div>

<script src="../public/js/bolao-creator2.js"></script>

<?php include '../templates/admin/footer.php'; ?>