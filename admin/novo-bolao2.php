<?php
require_once '../config/config.php';
require_once '../includes/auth_admin.php';
require_once '../includes/functions.php';

// Inicializar variáveis
$paises = [];
$campeonatos = [];
$jogos = [];
$formData = [];
$anoAtual = date('Y');
$prazo_dias = $_GET['prazo_dias'] ?? 30;

// IDs dos campeonatos brasileiros
$campeonatosBrasileiros = [71, 72, 73, 13]; // Série A, B, Copa do Brasil, Libertadores

$campeonatosBrasil = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    253 => 'Brasileirão Série C',
    254 => 'Brasileirão Série D',
    73 => 'Copa do Brasil'
];

// IDs dos países da América Latina
$paisesAmericaLatina = [
    'Brazil' => 'Brasil',
    'Argentina' => 'Argentina', 
    'Chile' => 'Chile',
    'Colombia' => 'Colômbia',
    'Peru' => 'Peru',
    'Uruguay' => 'Uruguai',
    'Paraguay' => 'Paraguai',
    'Ecuador' => 'Equador',
    'Bolivia' => 'Bolívia',
    'Venezuela' => 'Venezuela',
    'Mexico' => 'México'
];

// Dados padrão do formulário
$formData = [
    'nome' => $_POST['nome'] ?? '',
    'descricao' => $_POST['descricao'] ?? '',
    'data_inicio' => $_POST['data_inicio'] ?? date('Y-m-d'),
    'data_fim' => $_POST['data_fim'] ?? date('Y-m-d', strtotime('+30 days')),
    'valor_participacao' => $_POST['valor_participacao'] ?? '',
    'max_participantes' => $_POST['max_participantes'] ?? '',
    'premio_total' => $_POST['premio_total'] ?? '',
    'publico' => $_POST['publico'] ?? 1,
    'quantidade_jogos' => $_POST['quantidade_jogos'] ?? '11',
    'incluir_sem_horario' => isset($_GET['incluir_sem_horario']) || isset($_POST['incluir_sem_horario']),
    'pais' => $_GET['pais'] ?? $_POST['pais'] ?? '',
    'campeonato' => $_GET['campeonato'] ?? $_POST['campeonato'] ?? '',
    'status' => $_GET['status'] ?? $_POST['status'] ?? 'NS'
];

// Carregar países se necessário
if (empty($paises)) {
    $dadosPaises = fetchApiFootballData('countries');
    if ($dadosPaises && isset($dadosPaises['response'])) {
        foreach ($dadosPaises['response'] as $pais) {
            if (isset($paisesAmericaLatina[$pais['name']])) {
                $paises[] = $pais;
            }
        }
    }
}

// Carregar campeonatos se um país foi selecionado
if (!empty($formData['pais'])) {
    $dadosCampeonatos = fetchApiFootballData('leagues', [
        'country' => $formData['pais'],
        'season' => $anoAtual
    ]);
    
    if ($dadosCampeonatos && isset($dadosCampeonatos['response'])) {
        $campeonatos = $dadosCampeonatos['response'];
    }
}

// Processar busca de jogos
if (isset($_GET['campeonato_brasil']) && isset($campeonatosBrasil[$_GET['campeonato_brasil']])) {
    $campeonatoId = (int)$_GET['campeonato_brasil'];
    $incluirSemHorario = isset($_GET['incluir_sem_horario']) && $_GET['incluir_sem_horario'] == '1';
    
    // Buscar jogos não utilizados
    $jogos = buscarJogosNaoUtilizados($campeonatoId, $anoAtual, $incluirSemHorario);
} elseif (isset($_GET['buscar']) || isset($_POST['buscar_jogos'])) {
    $incluirSemHorario = $formData['incluir_sem_horario'];
    
    // Parâmetros base para busca
    $parametros = [
        'season' => $anoAtual,
        'from' => $formData['data_inicio'],
        'to' => $formData['data_fim']
    ];
    
    // Adicionar filtros específicos
    if (!empty($formData['pais'])) {
        $parametros['country'] = $formData['pais'];
    }
    
    if (!empty($formData['campeonato'])) {
        $parametros['league'] = $formData['campeonato'];
    }
    
    if (!empty($formData['status'])) {
        $parametros['status'] = $formData['status'];
    }
    
    // Se nenhum filtro específico, usar campeonatos brasileiros
    if (empty($formData['pais']) && empty($formData['campeonato'])) {
        foreach ($campeonatosBrasileiros as $ligaId) {
            $parametros['league'] = $ligaId;
            $dadosJogos = fetchApiFootballData('fixtures', $parametros);
            
            if ($dadosJogos && isset($dadosJogos['response'])) {
                foreach ($dadosJogos['response'] as $jogo) {
                    // Verificar se o jogo já está sendo usado em outro bolão
                    $jogoJaUsado = verificarJogoJaUsado($jogo['fixture']['id']);
                    
                    if (!$jogoJaUsado) {
                        // Verificar se deve incluir jogos sem horário definido
                        $temHorarioDefinido = !empty($jogo['fixture']['date']) && 
                                            $jogo['fixture']['date'] !== '1970-01-01T00:00:00+00:00' &&
                                            !in_array($jogo['fixture']['status']['short'], ['TBD', 'TBA']);
                        
                        if ($incluirSemHorario || $temHorarioDefinido) {
                            $jogos[] = $jogo;
                        }
                    }
                }
            }
            unset($parametros['league']); // Remove para próxima iteração
        }
    } else {
        // Busca com filtros específicos
        $dadosJogos = fetchApiFootballData('fixtures', $parametros);
        
        if ($dadosJogos && isset($dadosJogos['response'])) {
            foreach ($dadosJogos['response'] as $jogo) {
                // Verificar se o jogo já está sendo usado em outro bolão
                $jogoJaUsado = verificarJogoJaUsado($jogo['fixture']['id']);
                
                if (!$jogoJaUsado) {
                    // Verificar se deve incluir jogos sem horário definido
                    $temHorarioDefinido = !empty($jogo['fixture']['date']) && 
                                        $jogo['fixture']['date'] !== '1970-01-01T00:00:00+00:00' &&
                                        !in_array($jogo['fixture']['status']['short'], ['TBD', 'TBA']);
                    
                    if ($incluirSemHorario || $temHorarioDefinido) {
                        $jogos[] = $jogo;
                    }
                }
            }
        }
    }
    
    // Ordenar jogos por data
    usort($jogos, function($a, $b) {
        return strtotime($a['fixture']['date']) - strtotime($b['fixture']['date']);
    });
    
    // Limitar quantidade de jogos
    if (count($jogos) > 100) {
        $jogos = array_slice($jogos, 0, 100);
    }
}

/**
 * Busca jogos de uma rodada específica
 */
function buscarJogosRodada($campeonatoId, $temporada, $rodada) {
    return fetchApiFootballData('fixtures', [
        'league' => $campeonatoId,
        'season' => $temporada,
        'round' => $rodada
    ]) ?? [];
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

// Função para verificar se um jogo já está sendo usado
function verificarJogoJaUsado($fixtureId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bolao_jogos WHERE fixture_id = ?");
        $stmt->execute([$fixtureId]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar jogo usado: " . $e->getMessage());
        return false;
    }
}

// Include header
$pageTitle = "Criar Bolão Completo";
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
    <div class="card mb-4 futebol-card" style="border: 2px solid #ffd600;">
        <div class="card-header" style="background: linear-gradient(90deg, #ffd600 60%, #43a047 100%); color: #222;">
            <i class="fas fa-filter me-1"></i>
            Filtros de Jogos
        </div>
        <div class="card-body">
            <form method="get" action="" id="formFiltros">
                <!-- Seção de Campeonatos Principais -->
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Campeonatos Principais</label>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="71" id="checkA">
                                    <label class="form-check-label" for="checkA">
                                        <i class="fas fa-shield-halved"></i> Brasileirão Série A
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="72" id="checkB">
                                    <label class="form-check-label" for="checkB">
                                        <i class="fas fa-shield-halved"></i> Brasileirão Série B
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="73" id="checkCopa">
                                    <label class="form-check-label" for="checkCopa">
                                        <i class="fas fa-trophy"></i> Copa do Brasil
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="13" id="checkLib">
                                    <label class="form-check-label" for="checkLib">
                                        <i class="fas fa-globe"></i> Libertadores
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- País -->
                    <div class="col-md-3 mb-3">
                        <label for="pais" class="form-label">País</label>
                        <select class="form-select" id="pais" name="pais">
                            <option value="">Todos os países</option>
                            <?php foreach ($paises as $pais): ?>
                                <option value="<?= htmlspecialchars($pais['name']) ?>" 
                                        <?= $formData['pais'] == $pais['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($paisesAmericaLatina[$pais['name']] ?? $pais['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Campeonato -->
                    <div class="col-md-3 mb-3">
                        <label for="campeonato" class="form-label">Campeonato</label>
                        <select class="form-select" id="campeonato" name="campeonato">
                            <option value="">Todos os campeonatos</option>
                            <?php foreach ($campeonatos as $campeonato): ?>
                                <option value="<?= $campeonato['league']['id'] ?>" 
                                        <?= $formData['campeonato'] == $campeonato['league']['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($campeonato['league']['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Campeonato Brasil (fallback) -->
                    <div class="col-md-3 mb-3">
                        <label for="campeonato_brasil" class="form-label">Campeonatos Brasil</label>
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
                    
                    <!-- Status do Jogo -->
                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="" <?= $formData['status'] == '' ? 'selected' : '' ?>>Todos</option>
                            <option value="NS" <?= $formData['status'] == 'NS' ? 'selected' : '' ?>>Não iniciado</option>
                            <option value="LIVE" <?= $formData['status'] == 'LIVE' ? 'selected' : '' ?>>Ao vivo</option>
                            <option value="FT" <?= $formData['status'] == 'FT' ? 'selected' : '' ?>>Finalizado</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Data Início -->
                    <div class="col-md-3 mb-3">
                        <label for="data_inicio" class="form-label">Data Início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                               value="<?= $formData['data_inicio'] ?>">
                    </div>
                    
                    <!-- Data Fim -->
                    <div class="col-md-3 mb-3">
                        <label for="data_fim" class="form-label">Data Fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" 
                               value="<?= $formData['data_fim'] ?>">
                    </div>
                    
                    <!-- Checkbox para incluir jogos sem horário -->
                    <div class="col-md-3 mb-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" 
                                   id="incluir_sem_horario" name="incluir_sem_horario" value="1"
                                   <?= $formData['incluir_sem_horario'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="incluir_sem_horario">
                                Incluir jogos sem horário definido
                            </label>
                        </div>
                    </div>
                    
                    <!-- Botão de buscar -->
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn futebol-btn mt-4" name="buscar">
                            <i class="fas fa-search"></i> Buscar Jogos
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
                <div class="card mb-4 futebol-card" style="border: 2px solid #c8e6c9;">
                    <div class="card-header" style="background: linear-gradient(90deg, #c8e6c9 60%, #43a047 100%); color: #222;">
                        <i class="fas fa-info-circle me-1"></i>
                        Informações Básicas
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="nome" class="form-label futebol-label">Nome do Bolão *</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?= htmlspecialchars($formData['nome']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label futebol-label">Descrição</label>
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
                                <label for="valor_participacao" class="form-label futebol-label">Valor de Participação (R$) *</label>
                                <input type="number" class="form-control" id="valor_participacao" name="valor_participacao" 
                                       step="0.01" min="0" value="<?= $formData['valor_participacao'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="max_participantes" class="form-label futebol-label">Máx. Participantes</label>
                                <input type="number" class="form-control" id="max_participantes" name="max_participantes" 
                                       min="1" value="<?= $formData['max_participantes'] ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="premio_total" class="form-label">Prêmio Total (R$)</label>
                                <input type="number" class="form-control" id="premio_total" name="premio_total" 
                                       step="0.01" min="0" value="<?= $formData['premio_total'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="premio_rodada" class="form-label">Prêmio da Rodada (R$)</label>
                                <input type="number" class="form-control" id="premio_rodada" name="premio_rodada" 
                                       step="0.01" min="0" value="<?= $formData['premio_rodada'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="publico" name="publico" value="1" 
                                           <?= $formData['publico'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="publico">
                                        Bolão Público
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="imagem_bolao" class="form-label">Imagem do Bolão</label>
                                <input type="file" class="form-control" id="imagem_bolao" name="imagem_bolao" accept="image/*">
                                <div id="preview-imagem-bolao" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seleção de Jogos -->
            <div class="col-md-6">
                <div class="card mb-4 futebol-card" style="border: 2px solid #81c784;">
                    <div class="card-header" style="background: linear-gradient(90deg, #81c784 60%, #43a047 100%); color: #222;">
                        <i class="fas fa-futbol me-1"></i>
                        Jogos Disponíveis (<?= count($jogos) ?>)
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="quantidade_jogos" class="form-label">Quantidade de Jogos</label>
                                <input type="number" class="form-control" id="quantidade_jogos" name="quantidade_jogos" 
                                       min="1" max="<?= count($jogos) ?>" value="<?= $formData['quantidade_jogos'] ?? 11 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Jogos Disponíveis</label>
                                <div class="form-control-plaintext">
                                    <strong><?= count($jogos) ?></strong> jogos encontrados
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Jogos Selecionados</label>
                                <div class="form-control-plaintext">
                                    <strong id="contador-selecionados">0</strong> jogos selecionados
                                </div>
                            </div>
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
        <div class="card futebol-card">
            <div class="card-body text-center">
                <button type="submit" class="btn futebol-btn btn-lg me-3">
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

<style>
/* Estilos personalizados do futebol */
.futebol-card {
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.futebol-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.futebol-label {
    font-weight: 600;
    color: #2e7d32;
    margin-bottom: 8px;
}

.futebol-btn {
    background: linear-gradient(45deg, #43a047, #66bb6a);
    border: none;
    color: white;
    font-weight: 600;
    padding: 12px 24px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.futebol-btn:hover {
    background: linear-gradient(45deg, #388e3c, #43a047);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(67, 160, 71, 0.4);
    color: white;
}

.campeonato-checkbox:checked + label {
    color: #2e7d32;
    font-weight: 600;
}

.form-check-label {
    cursor: pointer;
}

.form-check-label i {
    margin-right: 5px;
    color: #ffd600;
}
</style>

<script>
// Preview da imagem do bolão
const inputImagem = document.getElementById('imagem_bolao');
const previewDiv = document.getElementById('preview-imagem-bolao');
if (inputImagem) {
    inputImagem.addEventListener('change', function(e) {
        previewDiv.innerHTML = '';
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                previewDiv.innerHTML = `<img src='${ev.target.result}' alt='Preview' style='max-width: 100%; max-height: 120px; border-radius: 10px; box-shadow: 0 2px 8px #ccc;'>`;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}

// Atualizar contador de jogos selecionados
function atualizarContador() {
    const checkboxes = document.querySelectorAll('.jogo-checkbox:checked');
    const contador = document.getElementById('contador-selecionados');
    if (contador) {
        contador.textContent = checkboxes.length;
    }
    
    // Atualizar também o contador original
    const contadorOriginal = document.getElementById('contador-jogos');
    if (contadorOriginal) {
        contadorOriginal.textContent = checkboxes.length + ' jogos selecionados';
    }
}

// Adicionar event listeners aos checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', atualizarContador);
    });
    
    // Botões de seleção
    const btnSelecionarTodos = document.getElementById('selecionar-todos');
    const btnLimparSelecao = document.getElementById('limpar-selecao');
    
    if (btnSelecionarTodos) {
        btnSelecionarTodos.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            atualizarContador();
        });
    }
    
    if (btnLimparSelecao) {
        btnLimparSelecao.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            atualizarContador();
        });
    }
});
</script>

<!-- Loader overlay -->
<div id="loader-overlay" style="display: none;">
    <div class="loader-content">
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <h4 class="mt-3 text-light">BUSCANDO INFORMAÇÕES DOS JOGOS</h4>
    </div>
</div>

<style>
/* Loader styles */
#loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.loader-content {
    text-align: center;
    padding: 2rem;
    border-radius: 1rem;
    background: rgba(0, 0, 0, 0.5);
}

/* Animação de fade */
.fade-in {
    animation: fadeIn 0.3s ease-in;
}

.fade-out {
    animation: fadeOut 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>

<script>
// Adicionar loader ao botão de buscar jogos
document.addEventListener('DOMContentLoaded', function() {
    const buscarJogosBtn = document.querySelector('button[name="buscar"]');
    const loaderOverlay = document.getElementById('loader-overlay');
    
    if (buscarJogosBtn && loaderOverlay) {
        buscarJogosBtn.addEventListener('click', function(e) {
            // Mostra o loader com animação
            loaderOverlay.style.display = 'flex';
            loaderOverlay.classList.add('fade-in');
            
            // Esconde o loader após um delay para garantir que os dados foram carregados
            setTimeout(() => {
                loaderOverlay.classList.add('fade-out');
                setTimeout(() => {
                    loaderOverlay.style.display = 'none';
                    loaderOverlay.classList.remove('fade-in', 'fade-out');
                }, 300);
            }, 2000); // 2 segundos de delay
        });
    }
});
</script>

<?php include '../templates/admin/footer.php'; ?>