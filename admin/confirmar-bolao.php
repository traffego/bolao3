<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Recebe os dados via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('danger', 'Acesso inválido. Use o formulário para criar um bolão.');
    redirect(APP_URL . '/admin/novo-bolao.php');
}

// Verifica se tem os dados necessários
if (empty($_POST['nome']) || empty($_POST['data_inicio']) || empty($_POST['data_fim'])) {
    setFlashMessage('danger', 'Dados obrigatórios não informados.');
    redirect(APP_URL . '/admin/novo-bolao.php');
}

// IDs para nomes dos campeonatos
$nomesCampeonatos = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    75 => 'Brasileirão Série C',
    76 => 'Brasileirão Série D',
    73 => 'Copa do Brasil',
    13 => 'Libertadores'
];

// Campeonatos selecionados (array de IDs)
$campeonatosSelecionados = isset($_POST['campeonatos']) ? (array)$_POST['campeonatos'] : [];
$nomesSelecionados = [];
foreach ($campeonatosSelecionados as $id) {
    $nomesSelecionados[] = $nomesCampeonatos[$id] ?? "Campeonato #$id";
}

// Recebe os jogos selecionados (array de IDs)
$jogosSelecionados = isset($_POST['jogos_selecionados']) ? (array)$_POST['jogos_selecionados'] : [];

// Debug - Verificar quais jogos foram recebidos
error_log("Jogos selecionados recebidos: " . print_r($jogosSelecionados, true));

// Se não temos jogos como array, mas temos o JSON, vamos usar isso
if (empty($jogosSelecionados) && isset($_POST['jogos_json']) && !empty($_POST['jogos_json'])) {
    $jogosSelecionados = json_decode($_POST['jogos_json'], true) ?? [];
    error_log("Jogos recuperados do JSON: " . print_r($jogosSelecionados, true));
}

// Se ainda não temos jogos, tente ver se há jogos no formato string
if (empty($jogosSelecionados) && isset($_POST['jogos_string']) && !empty($_POST['jogos_string'])) {
    $jogosSelecionados = explode(',', $_POST['jogos_string']);
    error_log("Jogos recuperados da string: " . print_r($jogosSelecionados, true));
}

// Buscar detalhes dos jogos selecionados da API
$jogosDetalhes = [];
if (!empty($jogosSelecionados)) {
    $apiConfig = getConfig('api_football');
    if ($apiConfig && !empty($apiConfig['api_key'])) {
        // Vamos buscar cada jogo individualmente para garantir
        foreach ($jogosSelecionados as $jogoId) {
            error_log("Buscando jogo ID: $jogoId");
            
            // API URL para obter detalhes de um jogo específico
            $apiUrl = api_football_url('fixtures?id=' . $jogoId);
            $headers = [
                "x-rapidapi-key: {$apiConfig['api_key']}",
                "x-rapidapi-host: v3.football.api-sports.io"
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['response']) && !empty($data['response'])) {
                    $jogo = $data['response'][0];
                    error_log("Jogo encontrado: " . $jogo['teams']['home']['name'] . " x " . $jogo['teams']['away']['name']);
                    
                    $jogosDetalhes[] = [
                        'id' => $jogo['fixture']['id'],
                        'campeonato' => $jogo['league']['name'],
                        'times' => $jogo['teams']['home']['name'] . ' x ' . $jogo['teams']['away']['name'],
                        'data' => date('d/m/Y H:i', strtotime($jogo['fixture']['date']))
                    ];
                }
            } else {
                error_log("Erro ao buscar jogo $jogoId. HTTP Code: $httpCode");
                error_log("Resposta: $response");
            }
        }
    } else {
        error_log('Configuração da API não encontrada ou chave inválida');
    }
}

// Se não encontramos nenhum jogo, registrar erro
if (empty($jogosDetalhes)) {
    error_log("ALERTA: Nenhum detalhe de jogo foi encontrado para os IDs selecionados: " . implode(', ', $jogosSelecionados));
    setFlashMessage('warning', 'Não foi possível obter detalhes dos jogos selecionados. Verifique a conexão com a API.');
}

// Formatar valores monetários
$valorParticipacao = !empty($_POST['valor_participacao']) ? 'R$ ' . number_format((float)str_replace(',', '.', $_POST['valor_participacao']), 2, ',', '.') : 'Gratuito';
$premioTotal = !empty($_POST['premio_total']) ? 'R$ ' . number_format((float)str_replace(',', '.', $_POST['premio_total']), 2, ',', '.') : 'Não definido';

// Formatar datas
$dataInicio = !empty($_POST['data_inicio']) ? date('d/m/Y', strtotime($_POST['data_inicio'])) : 'Não definida';
$dataFim = !empty($_POST['data_fim']) ? date('d/m/Y', strtotime($_POST['data_fim'])) : 'Não definida';

// Combinar data e hora limite para palpites
$dataLimitePalpitar = 'Não definida';
if (!empty($_POST['data_limite_palpitar'])) {
    $horaLimite = !empty($_POST['hora_limite_palpitar']) ? $_POST['hora_limite_palpitar'] : '23:59';
    $dataHoraLimite = $_POST['data_limite_palpitar'] . ' ' . $horaLimite;
    $dataLimitePalpitar = date('d/m/Y H:i', strtotime($dataHoraLimite));
    
    // Armazenar para passar para o próximo formulário
    $_POST['data_hora_limite_palpitar'] = $dataHoraLimite;
}

// Verificar se tem imagem
$temImagem = !empty($_POST['imagem_bolao_url']);
$imagemUrl = $temImagem ? $_POST['imagem_bolao_url'] : '';

// Título da página
$pageTitle = "Confirmar Bolão";
$currentPage = "boloes";
include '../templates/admin/header.php';
?>
<style>
.confirmacao-bolao {
    max-width: 900px;
    margin: 0 auto;
}
.futebol-card {
    border-radius: 18px;
    box-shadow: 0 4px 24px 0 rgba(34,139,34,0.10);
    border: 2px solid #43a047;
    background: linear-gradient(135deg, #e8f5e9 0%, #fff 100%);
}
.futebol-card .card-header {
    background: linear-gradient(90deg, #388e3c 60%, #ffd600 100%);
    color: #fff;
    font-family: 'Oswald', 'Montserrat', Arial, sans-serif;
    font-size: 1.5rem;
    border-radius: 16px 16px 0 0;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.jogo-item {
    background: #f5f5f5;
    border-left: 4px solid #43a047;
    margin-bottom: 8px;
    padding: 10px 15px;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.jogo-item .campeonato {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}
.jogo-item .times {
    font-weight: 600;
    color: #222;
}
.jogo-item .data {
    font-size: 0.9rem;
    color: #43a047;
    font-weight: 500;
}
.dados-bolao dt {
    color: #43a047;
    font-weight: 600;
}
.imagem-preview {
    max-width: 200px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
</style>

<div class="container-fluid px-4" style="padding-top: 4.5rem;">
    <div class="confirmacao-bolao">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mt-4" style="color: #388e3c;">
                <i class="fa-solid fa-circle-check"></i> Confirmar Bolão
            </h1>
            <?php if (function_exists('getFlashMessage') && getFlashMessage()): ?>
                <div class="alert alert-<?= getFlashMessageType() ?> alert-dismissible fade show" role="alert">
                    <?= getFlashMessage() ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mb-4 futebol-card">
            <div class="card-header">
                <i class="fa-solid fa-file-contract"></i> Dados do Bolão
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row dados-bolao">
                            <dt class="col-sm-5">Nome do Bolão</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($_POST['nome'] ?? 'Não informado') ?></dd>
                            
                            <dt class="col-sm-5">Valor Participação</dt>
                            <dd class="col-sm-7"><?= $valorParticipacao ?></dd>
                            
                            <dt class="col-sm-5">Prêmio Total</dt>
                            <dd class="col-sm-7"><?= $premioTotal ?></dd>
                            
                            <dt class="col-sm-5">Data de Início</dt>
                            <dd class="col-sm-7"><?= $dataInicio ?></dd>
                            
                            <dt class="col-sm-5">Data de Fim</dt>
                            <dd class="col-sm-7"><?= $dataFim ?></dd>
                            
                            <dt class="col-sm-5">Limite para Palpite</dt>
                            <dd class="col-sm-7"><?= $dataLimitePalpitar ?></dd>
                        </dl>
                    </div>
                    
                    <div class="col-md-6">
                        <dl class="row dados-bolao">
                            <dt class="col-sm-5">Quantidade de Jogos</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($_POST['quantidade_jogos'] ?? '0') ?></dd>
                            
                            <dt class="col-sm-5">Status</dt>
                            <dd class="col-sm-7">
                                <?php if (isset($_POST['status']) && $_POST['status']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-5">Visibilidade</dt>
                            <dd class="col-sm-7">
                                <?php if (isset($_POST['publico']) && $_POST['publico']): ?>
                                    <span class="badge bg-green">Público</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Privado</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-5">Campeonatos</dt>
                            <dd class="col-sm-7">
                                <?php foreach ($nomesSelecionados as $campeonato): ?>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($campeonato) ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($nomesSelecionados)): ?>
                                    <span class="text-muted">Nenhum selecionado</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>

                <?php if ($temImagem): ?>
                    <div class="mt-3 mb-4">
                        <h5 class="text-success">
                            <i class="fa-solid fa-image"></i> 
                            Imagem do Bolão
                        </h5>
                        <div class="d-flex flex-column">
                            <img src="<?= APP_URL ?>/<?= htmlspecialchars($imagemUrl) ?>" class="imagem-preview mb-2">
                            <small class="text-muted"><?= htmlspecialchars($imagemUrl) ?></small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <h5 class="text-success">
                        <i class="fa-solid fa-list-check"></i> 
                        Jogos Selecionados (<?= count($jogosDetalhes) ?>)
                    </h5>
                    
                    <?php if (empty($jogosDetalhes)): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            Nenhum jogo foi selecionado para este bolão.
                        </div>
                    <?php else: ?>
                        <div class="lista-jogos mt-3">
                            <?php foreach ($jogosDetalhes as $jogo): ?>
                                <div class="jogo-item">
                                    <div>
                                        <div class="campeonato"><?= htmlspecialchars($jogo['campeonato']) ?></div>
                                        <div class="times"><?= htmlspecialchars($jogo['times']) ?></div>
                                    </div>
                                    <div class="data"><?= htmlspecialchars($jogo['data_formatada'] ?? $jogo['data']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="salvar-bolao.php" method="post" class="mt-4">
                    <?php foreach ($_POST as $key => $value): ?>
                        <?php if (is_array($value)): ?>
                            <?php foreach ($value as $item): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($item) ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Voltar e Editar
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fa-solid fa-check-double"></i> Confirmar e Salvar Bolão
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 