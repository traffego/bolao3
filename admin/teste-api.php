<?php
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Obter a configuração da API
$apiConfig = getConfig('api_football');
$apiKey = $apiConfig['api_key'] ?? '';
$baseUrl = $apiConfig['base_url'] ?? 'https://v3.football.api-sports.io';

// Verificar se a API key está configurada
if (empty($apiKey)) {
    setFlashMessage('warning', 'A chave da API Football não está configurada. Configure-a primeiro.');
    redirect(APP_URL . '/admin/configuracoes.php?categoria=api_football');
}

// Testar a conexão com a API
$apiStatus = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Obter informações sobre a conta/status da API
$responseData = apiFootballRequest('status');

if ($responseData && isset($responseData['response'])) {
    $apiStatus['success'] = true;
    $apiStatus['data'] = $responseData['response'];
    $apiStatus['message'] = 'Conexão com a API estabelecida com sucesso!';
} else {
    $apiStatus['message'] = 'Falha ao conectar com a API. Verifique sua chave de API.';
}

// Consultas de teste adicionais se a conexão for bem-sucedida
$paises = [];
$ligas = [];
$jogos = [];

if ($apiStatus['success']) {
    // Buscar alguns países
    $paisesResponse = apiFootballRequest('countries');
    if ($paisesResponse && isset($paisesResponse['response'])) {
        $paises = array_slice($paisesResponse['response'], 0, 10);
    }
    
    // Buscar algumas ligas populares
    $ligasResponse = apiFootballRequest('leagues', ['type' => 'league']);
    if ($ligasResponse && isset($ligasResponse['response'])) {
        $ligas = array_slice($ligasResponse['response'], 0, 10);
    }
    
    // Tentar diferentes abordagens para buscar jogos
    
    // 1. Próximos jogos (sem especificar liga)
    $jogosResponse = apiFootballRequest('fixtures', [
        'next' => 11
    ]);
    
    // 2. Se a primeira abordagem não retornar jogos, tente alguns jogos ao vivo
    if (!$jogosResponse || !isset($jogosResponse['response']) || empty($jogosResponse['response'])) {
        $jogosResponse = apiFootballRequest('fixtures', [
            'live' => 'all'
        ]);
    }
    
    // 3. Se ainda não tiver jogos, tente algumas ligas principais com datas futuras
    if (!$jogosResponse || !isset($jogosResponse['response']) || empty($jogosResponse['response'])) {
        $jogosResponse = apiFootballRequest('fixtures', [
            'league' => 39, // Premier League Inglesa
            'season' => date('Y'),
            'from' => date('Y-m-d'),
            'to' => date('Y-m-d', strtotime('+30 days'))
        ]);
    }
    
    // 4. Uma última tentativa com o Brasileirão
    if (!$jogosResponse || !isset($jogosResponse['response']) || empty($jogosResponse['response'])) {
        $jogosResponse = apiFootballRequest('fixtures', [
            'league' => 71, // Brasileirão
            'season' => date('Y'),
            'from' => date('Y-m-d'),
            'to' => date('Y-m-d', strtotime('+30 days'))
        ]);
    }
    
    // Salvar informações para debug
    $debugInfo = [
        'requisicao' => $jogosResponse,
        'data_atual' => date('Y-m-d H:i:s'),
        'tentativas' => 4
    ];
    saveConfig('api_debug', $debugInfo);
    
    if ($jogosResponse && isset($jogosResponse['response'])) {
        $jogos = $jogosResponse['response'];
    }
}

// Include header
$pageTitle = "Teste da API Football";
$currentPage = "configuracoes";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Teste da API Football</h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="configuracoes.php">Configurações</a></li>
        <li class="breadcrumb-item active">Teste da API</li>
    </ol>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-plug me-1"></i>
                    Status da Conexão com a API
                </div>
                <div class="card-body">
                    <?php if ($apiStatus['success']): ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> <?= $apiStatus['message'] ?></h5>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">Informações da Conta</div>
                                    <div class="card-body">
                                        <table class="table table-striped table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th>Plano</th>
                                                    <td><?= $apiStatus['data']['subscription']['plan'] ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Requisições Restantes</th>
                                                    <td><?= $apiStatus['data']['requests']['current'] ?> / <?= $apiStatus['data']['requests']['limit_day'] ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Validade</th>
                                                    <td><?= date('d/m/Y', strtotime($apiStatus['data']['subscription']['end'])) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">Estatísticas de Uso</div>
                                    <div class="card-body">
                                        <div class="progress mb-3" style="height: 25px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($apiStatus['data']['requests']['current'] / $apiStatus['data']['requests']['limit_day']) * 100 ?>%" 
                                                 aria-valuenow="<?= $apiStatus['data']['requests']['current'] ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?= $apiStatus['data']['requests']['limit_day'] ?>">
                                                <?= $apiStatus['data']['requests']['current'] ?> / <?= $apiStatus['data']['requests']['limit_day'] ?>
                                            </div>
                                        </div>
                                        <p class="text-muted">Dados atualizados em: <?= date('d/m/Y H:i:s') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle"></i> <?= $apiStatus['message'] ?></h5>
                            <p>Verifique se a chave da API está correta em <a href="configuracoes.php?categoria=api_football">Configurações</a>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($apiStatus['success']): ?>
                <div class="row">
                    <!-- Países -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-globe me-1"></i>
                                Países Disponíveis (Amostra)
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>País</th>
                                                <th>Código</th>
                                                <th>Bandeira</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($paises as $pais): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($pais['name']) ?></td>
                                                    <td><?= htmlspecialchars($pais['code'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php if (!empty($pais['flag'])): ?>
                                                            <img src="<?= $pais['flag'] ?>" alt="<?= $pais['name'] ?>" style="height: 20px;">
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ligas -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-trophy me-1"></i>
                                Ligas Disponíveis (Amostra)
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Liga</th>
                                                <th>País</th>
                                                <th>Logo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ligas as $liga): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($liga['league']['name']) ?></td>
                                                    <td><?= htmlspecialchars($liga['country']['name']) ?></td>
                                                    <td>
                                                        <?php if (!empty($liga['league']['logo'])): ?>
                                                            <img src="<?= $liga['league']['logo'] ?>" alt="<?= $liga['league']['name'] ?>" style="height: 20px;">
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Jogos -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-futbol me-1"></i>
                                Próximos Jogos <?= !empty($jogos) ? '(' . count($jogos) . ' encontrados)' : '(nenhum encontrado)' ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($jogos)): ?>
                                    <div class="alert alert-warning">
                                        <h5><i class="fas fa-exclamation-triangle"></i> Nenhum jogo encontrado</h5>
                                        <p>Possíveis razões:</p>
                                        <ul>
                                            <li>Não há jogos agendados para o período consultado</li>
                                            <li>A API pode ter um limite de requisições que foi atingido</li>
                                            <li>Os parâmetros de consulta podem precisar de ajustes</li>
                                        </ul>
                                        <p>Detalhes técnicos:</p>
                                        <pre><?php 
                                            $debug = getConfig('api_debug');
                                            if (isset($debug['requisicao']['errors'])) {
                                                echo "Erros reportados pela API:\n";
                                                print_r($debug['requisicao']['errors']);
                                            } else {
                                                echo "Nenhum erro específico reportado pela API.";
                                            }
                                        ?></pre>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Times</th>
                                                    <th>Data</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($jogos as $jogo): ?>
                                                    <tr>
                                                        <td>
                                                            <?= htmlspecialchars($jogo['teams']['home']['name']) ?> vs 
                                                            <?= htmlspecialchars($jogo['teams']['away']['name']) ?>
                                                        </td>
                                                        <td><?= date('d/m/Y H:i', strtotime($jogo['fixture']['date'])) ?></td>
                                                        <td><?= htmlspecialchars($jogo['fixture']['status']['long']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-code me-1"></i>
                        Exemplo de Uso em PHP
                    </div>
                    <div class="card-body">
                        <p>Para utilizar a API Football em suas páginas, utilize a função <code>apiFootballRequest()</code>:</p>
                        
                        <pre><code>// Exemplo: Buscar jogos de um campeonato específico
$jogos = apiFootballRequest('fixtures', [
    'league' => 71,  // ID da liga (ex: Brasileirão)
    'season' => 2023,  // Temporada
    'round' => 'Regular Season - 1'  // Rodada
]);

if ($jogos && isset($jogos['response'])) {
    foreach ($jogos['response'] as $jogo) {
        // Processar cada jogo
        $timeCasa = $jogo['teams']['home']['name'];
        $timeVisitante = $jogo['teams']['away']['name'];
        $dataHora = $jogo['fixture']['date'];
        
        // Faça algo com esses dados...
    }
}</code></pre>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                    <a href="configuracoes.php?categoria=api_football" class="btn btn-primary">
                        <i class="fas fa-cog me-2"></i> Voltar para Configurações
                    </a>
                    
                    <a href="novo-bolao.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-2"></i> Criar Novo Bolão com Jogos da API
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 