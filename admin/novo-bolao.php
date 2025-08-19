<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Initialize variables
$paises = [];
$campeonatos = [];
$temporadas = [];
$rodadas = [];
$jogos = [];
$errors = [];
$apiConfig = getConfig('api_football');
$anoAtual = date('Y');
$prazo_dias = $_GET['prazo_dias'] ?? 30; // Valor padrão de 30 dias se não especificado

// IDs dos campeonatos brasileiros
$campeonatosBrasil = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    253 => 'Brasileirão Série C',
    254 => 'Brasileirão Série D',
    73 => 'Copa do Brasil'
];

// Lista de países da América Latina
$paisesAmericaLatina = [
    'Argentina',
    'Bolivia',
    'Brazil',
    'Chile',
    'Colombia',
    'Ecuador',
    'Paraguay',
    'Peru',
    'Uruguay',
    'Venezuela',
    'Mexico',
    'Costa Rica',
    'Honduras',
    'Panama',
    'El Salvador',
    'Guatemala',
    'Nicaragua'
];

// Default form data
$formData = [
    'nome' => '',
    'descricao' => '',
    'data_inicio' => date('Y-m-d'),
    'data_fim' => date('Y-m-d', strtotime('+30 days')),
    'valor_participacao' => '',
    'regras' => '',
    'status' => 1, // Default to active
    'max_participantes' => '',
    'premio_total' => '',
    'premio_rodada' => '', // New field for round prize
    'publico' => 1, // Default to public
    'jogos_selecionados' => [],
    'quantidade_jogos' => '11', // Updated to 11 games
    'data_inicio' => date('Y-m-d'),
    'data_fim' => date('Y-m-d', strtotime('+30 days'))
];

// Processar seleção de campeonato brasileiro
if (isset($_GET['campeonato_brasil']) && isset($campeonatosBrasil[$_GET['campeonato_brasil']])) {
    $campeonatoId = (int)$_GET['campeonato_brasil'];
    $rodadas = buscarRodasBrasileirao($campeonatoId, $anoAtual);
    
    // Se uma rodada específica foi selecionada
    if (isset($_GET['rodada']) && !empty($_GET['rodada'])) {
        $jogos = buscarJogosRodada($campeonatoId, $anoAtual, $_GET['rodada']);
    }
}

// Load API data if API key is configured
if ($apiConfig && !empty($apiConfig['api_key'])) {
    try {
        // Calcular datas com base no prazo selecionado
        $dataInicio = date('Y-m-d');
        $dataFim = date('Y-m-d', strtotime("+{$prazo_dias} days"));
        
        // Carregar e filtrar lista de países
        $paisesResponse = fetchApiFootballData('countries') ?? [];
        $paises = array_filter($paisesResponse, function($pais) use ($paisesAmericaLatina) {
            return in_array($pais['name'], $paisesAmericaLatina);
        });
        
        // Se temos país selecionado, buscar jogos
        if (!empty($_GET['pais'])) {
            // Verificar se o país selecionado é da América Latina
            if (!in_array($_GET['pais'], $paisesAmericaLatina)) {
                setFlashMessage('warning', 'Por favor, selecione um país da América Latina.');
                redirect(APP_URL . '/admin/novo-bolao.php');
            }
            
            // Primeiro buscar campeonatos do país
            $campeonatos = fetchApiFootballData('leagues', ['country' => $_GET['pais']]) ?? [];
            
            if (!empty($campeonatos)) {
                // Filtrar campeonatos por série se especificado
                if (!empty($serie)) {
                    $campeonatos = array_filter($campeonatos, function($liga) use ($serie) {
                        $nome = strtolower($liga['league']['name']);
                        switch ($serie) {
                            case 'primeira':
                                return strpos($nome, 'série a') !== false || 
                                       strpos($nome, 'primeira') !== false ||
                                       strpos($nome, 'premier') !== false;
                            case 'segunda':
                                return strpos($nome, 'série b') !== false || 
                                       strpos($nome, 'segunda') !== false;
                            case 'copa':
                                return strpos($nome, 'copa') !== false || 
                                       strpos($nome, 'cup') !== false;
                            default:
                                return true;
                        }
                    });
                }
                
                // Buscar jogos de todos os campeonatos filtrados
                $jogos = [];
                foreach ($campeonatos as $campeonato) {
                    $jogosTemp = fetchApiFootballData('fixtures', [
                        'league' => $campeonato['league']['id'],
                        'season' => date('Y'),
                        'from' => $dataInicio,
                        'to' => $dataFim,
                        'status' => 'NS' // Not Started
                    ]) ?? [];
                    
                    if (!empty($jogosTemp)) {
                        $jogos = array_merge($jogos, $jogosTemp);
                    }
                }
                
                // Ordenar jogos conforme selecionado
                if (!empty($jogos)) {
                    usort($jogos, function($a, $b) use ($ordenacao) {
                        switch ($ordenacao) {
                            case 'data_desc':
                                return strtotime($b['fixture']['date']) - strtotime($a['fixture']['date']);
                            case 'campeonato':
                                $comp = strcmp($a['league']['name'], $b['league']['name']);
                                return $comp !== 0 ? $comp : strtotime($a['fixture']['date']) - strtotime($b['fixture']['date']);
                            case 'data_asc':
                            default:
                                return strtotime($a['fixture']['date']) - strtotime($b['fixture']['date']);
                        }
                    });
                    
                    // Limitar quantidade de jogos
                    $jogos = array_slice($jogos, 0, $jogos_por_pagina);
                }
            }
        } else {
            // Carregar lista de países
            $paises = fetchApiFootballData('countries') ?? [];
        }
        
    } catch (Exception $e) {
        setFlashMessage('danger', 'Erro ao conectar com a API: ' . $e->getMessage());
    }
}

/**
 * Process form submission
 */
function processFormSubmission() {
    global $formData, $errors;
    
    // Get form data
    $formData['nome'] = $_POST['nome'] ?? '';
    $formData['descricao'] = $_POST['descricao'] ?? '';
    $formData['data_inicio'] = $_POST['data_inicio'] ?? '';
    $formData['data_fim'] = $_POST['data_fim'] ?? '';
    $formData['valor_participacao'] = $_POST['valor_participacao'] ?? '';
    $formData['regras'] = $_POST['regras'] ?? '';
    $formData['status'] = isset($_POST['status']) ? 1 : 0;
    $formData['max_participantes'] = $_POST['max_participantes'] ?? null;
    $formData['premio_total'] = $_POST['premio_total'] ?? '';
    $formData['premio_rodada'] = $_POST['premio_rodada'] ?? ''; // Get premio_rodada
    $formData['publico'] = isset($_POST['publico']) ? 1 : 0;
    $formData['jogos_selecionados'] = isset($_POST['jogos_selecionados']) ? $_POST['jogos_selecionados'] : [];
    
    // Validate form data
    validateFormData();
    
    // If no errors, save to database
    if (empty($errors)) {
        saveBolao();
    }
}

/**
 * Validate form data
 */
function validateFormData() {
    global $formData, $errors;
    
    // Validate name
    if (empty($formData['nome'])) {
        $errors['nome'] = 'O nome do bolão é obrigatório.';
    } elseif (strlen($formData['nome']) < 3) {
        $errors['nome'] = 'O nome deve ter pelo menos 3 caracteres.';
    }
    
    // Validate dates
    if (empty($formData['data_inicio'])) {
        $errors['data_inicio'] = 'A data de início é obrigatória.';
    }
    
    if (empty($formData['data_fim'])) {
        $errors['data_fim'] = 'A data de término é obrigatória.';
    } elseif ($formData['data_fim'] < $formData['data_inicio']) {
        $errors['data_fim'] = 'A data de término deve ser posterior à data de início.';
    }
    
    // Validate value
    if (!empty($formData['valor_participacao']) && !is_numeric(str_replace(',', '.', $formData['valor_participacao']))) {
        $errors['valor_participacao'] = 'O valor deve ser um número.';
    } else {
        // Convert to proper decimal format for DB
        $formData['valor_participacao'] = !empty($formData['valor_participacao']) ? 
                                          (float)str_replace(',', '.', $formData['valor_participacao']) : 0;
    }
    
    // Validate premio_total
    if (!empty($formData['premio_total']) && !is_numeric(str_replace(',', '.', $formData['premio_total']))) {
        $errors['premio_total'] = 'O valor do prêmio deve ser um número.';
    } else {
        // Convert to proper decimal format for DB
        $formData['premio_total'] = !empty($formData['premio_total']) ? 
                                    (float)str_replace(',', '.', $formData['premio_total']) : 0;
    }
    
    // Validate premio_rodada
    if (!empty($formData['premio_rodada']) && !is_numeric(str_replace(',', '.', $formData['premio_rodada']))) {
        $errors['premio_rodada'] = 'O valor do prêmio da rodada deve ser um número.';
    } else {
        // Convert to proper decimal format for DB
        $formData['premio_rodada'] = !empty($formData['premio_rodada']) ? 
                                    (float)str_replace(',', '.', $formData['premio_rodada']) : 0;
    }
    
    // Validate selected games
    if (empty($formData['jogos_selecionados'])) {
        $errors['jogos'] = 'Selecione pelo menos um jogo para o bolão.';
    }
}

/**
 * Save bolao to database
 */
function saveBolao() {
    global $formData;
    
    // Generate slug from name
    $slug = slugify($formData['nome']);
    
    // Check for duplicate slug
    $existingSlug = dbFetchOne("SELECT id FROM dados_boloes WHERE slug = ?", [$slug]);
    if ($existingSlug) {
        $slug = $slug . '-' . time();
    }
    
    // Add slug and creation date
    $formData['slug'] = $slug;
    $formData['data_criacao'] = date('Y-m-d H:i:s');
    $formData['admin_id'] = getCurrentAdminId();
    
    // Ensure status is active by default if not set
    if (!isset($formData['status']) || $formData['status'] == 0) {
        $formData['status'] = 1;
    }
    
    // Start transaction
    dbBeginTransaction();
    
    try {
        // Remove jogos_selecionados from formData before inserting into dados_boloes table
        $jogosSelecionados = $formData['jogos_selecionados'];
        unset($formData['jogos_selecionados']);
        
        // Insert into database
        $bolaoId = dbInsert('dados_boloes', $formData);
        
        if ($bolaoId) {
            // Process selected games
            foreach ($jogosSelecionados as $jogoId) {
                // Fetch game data from API
                $jogo = fetchApiFootballData('fixtures', ['id' => $jogoId]);
                
                if ($jogo && !empty($jogo[0])) {
                    $jogoInfo = $jogo[0];
                    
                    // Insert the game into the database
                    $jogoData = [
                        'bolao_id' => $bolaoId,
                        'time_casa' => $jogoInfo['teams']['home']['name'],
                        'time_visitante' => $jogoInfo['teams']['away']['name'],
                        'data_hora' => date('Y-m-d H:i:s', strtotime($jogoInfo['fixture']['date'])),
                        'local' => $jogoInfo['fixture']['venue']['name'] ?? '',
                        'status' => 'agendado',
                        'id_externo' => $jogoInfo['fixture']['id']
                    ];
                    
                    dbInsert('jogos', $jogoData);
                }
            }
            
            // Commit transaction
            dbCommit();
            
            setFlashMessage('success', 'Bolão criado com sucesso!');
            redirect(APP_URL . '/admin/boloes.php');
        } else {
            // Rollback on error
            dbRollback();
            setFlashMessage('danger', 'Erro ao criar o bolão.');
        }
    } catch (Exception $e) {
        // Rollback on exception
        dbRollback();
        setFlashMessage('danger', 'Erro ao criar o bolão: ' . $e->getMessage());
    }
}

/**
 * Função para buscar rodadas do Brasileirão
 * 
 * @param int $campeonatoId ID do campeonato (71 para Série A, 72 para Série B)
 * @param int $temporada Ano da temporada
 * @return array Array com as rodadas disponíveis
 */
function buscarRodasBrasileirao($campeonatoId, $temporada) {
    $rodadas = fetchApiFootballData('fixtures/rounds', [
        'league' => $campeonatoId,
        'season' => $temporada
    ]) ?? [];
    
    // Ordenar rodadas numericamente
    if (!empty($rodadas)) {
        usort($rodadas, function($a, $b) {
            $numA = (int) preg_replace('/[^0-9]/', '', $a);
            $numB = (int) preg_replace('/[^0-9]/', '', $b);
            return $numA - $numB;
        });
    }
    
    return $rodadas;
}

/**
 * Função para buscar jogos de uma rodada específica
 * 
 * @param int $campeonatoId ID do campeonato
 * @param int $temporada Ano da temporada
 * @param string $rodada Nome da rodada (ex: "Regular Season - 1")
 * @return array Array com os jogos da rodada
 */
function buscarJogosRodada($campeonatoId, $temporada, $rodada) {
    return fetchApiFootballData('fixtures', [
        'league' => $campeonatoId,
        'season' => $temporada,
        'round' => $rodada
    ]) ?? [];
}

// Process form submission if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = $_POST;
    $imagemUrl = '';
    $uploadErro = '';

    // Processar upload da imagem
    if (isset($_FILES['imagem_bolao']) && $_FILES['imagem_bolao']['tmp_name']) {
        $uploadsDir = __DIR__ . '/../uploads/boloes/';
        if (!is_dir($uploadsDir)) {
            if (!mkdir($uploadsDir, 0777, true)) {
                error_log('Erro ao criar diretório: ' . $uploadsDir);
                $uploadErro = 'Erro ao criar diretório de uploads';
            }
        }
        
        if (empty($uploadErro)) {
            $ext = pathinfo($_FILES['imagem_bolao']['name'], PATHINFO_EXTENSION);
            $imagemNomeFinal = 'bolao_' . time() . '_' . uniqid() . '.' . $ext;
            $destino = $uploadsDir . $imagemNomeFinal;
            
            error_log('Tentando mover arquivo para: ' . $destino);
            
            if (move_uploaded_file($_FILES['imagem_bolao']['tmp_name'], $destino)) {
                $imagemUrl = 'uploads/boloes/' . $imagemNomeFinal;
                error_log('Arquivo movido com sucesso para: ' . $imagemUrl);
            } else {
                error_log('Erro ao mover arquivo. PHP error: ' . error_get_last()['message']);
                $uploadErro = 'Erro ao salvar a imagem. Verifique as permissões da pasta.';
            }
        }
    }

    if (!empty($uploadErro)) {
        setFlashMessage('danger', $uploadErro);
        redirect(APP_URL . '/admin/novo-bolao.php');
    }

    // Adicionar URL da imagem aos dados
    if (!empty($imagemUrl)) {
        $dados['imagem_bolao_url'] = $imagemUrl;
    }

    // Verificar os jogos selecionados
    if (isset($dados['jogos_selecionados']) && is_array($dados['jogos_selecionados'])) {
        error_log('Jogos selecionados: ' . print_r($dados['jogos_selecionados'], true));
        // Adicionar também formato JSON
        $dados['jogos_json'] = json_encode($dados['jogos_selecionados']);
    } else {
        error_log('Nenhum jogo selecionado ou não é array');
        $dados['jogos_selecionados'] = [];
    }

    // Redirecionar para a página de confirmação com os dados via POST
    $form = '<form id="redirectForm" method="post" action="confirmar-bolao.php">';
    foreach ($dados as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $v) {
                $form .= '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($v) . '">';
            }
        } else {
            $form .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }
    }
    $form .= '</form>';
    $form .= '<script>document.getElementById("redirectForm").submit();</script>';
    echo $form;
    exit;
}

// Método alternativo para buscar jogos
// Se não temos jogos mas temos o país selecionado, vamos mostrar um método alternativo
if (empty($jogos) && isset($_GET['pais']) && !empty($_GET['pais'])) {
    // Adicionar um card para usar o método alternativo
    $paisSelecionado = $_GET['pais'];
    
    // TESTE DIRETO PARA O BRASILEIRÃO
    if ($paisSelecionado == 'Brazil') {
        echo '<div class="alert alert-info mt-3">
                <h5><i class="fas fa-info-circle"></i> Testando busca direta para o Campeonato Brasileiro</h5>
                <p>Tentando buscar jogos diretamente do Campeonato Brasileiro...</p>
              </div>';
        
        // Tenta obter jogos do Brasileirão (ID 71) para a temporada atual
        $testeDireto = fetchApiFootballData('fixtures', [
            'league' => 71,
            'season' => date('Y'),
            'from' => date('Y-m-d'),
            'to' => date('Y-m-d', strtotime('+90 days'))
        ]);
        
        if ($testeDireto && isset($testeDireto['response']) && !empty($testeDireto['response'])) {
            $jogos = $testeDireto['response'];
            echo '<div class="alert alert-success mt-3">
                    <h5><i class="fas fa-check-circle"></i> Sucesso!</h5>
                    <p>Encontramos ' . count($jogos) . ' jogos do Campeonato Brasileiro.</p>
                  </div>';
        } else {
            echo '<div class="alert alert-danger mt-3">
                    <h5><i class="fas fa-times-circle"></i> Falha no teste direto</h5>
                    <p>Não foi possível encontrar jogos do Campeonato Brasileiro. Testando outros campeonatos...</p>
                  </div>';
            
            // Tenta buscar jogos de outras ligas populares do Brasil
            $testeLigas = fetchApiFootballData('fixtures', [
                'country' => 'Brazil',
                'season' => date('Y'),
                'from' => date('Y-m-d'),
                'to' => date('Y-m-d', strtotime('+90 days'))
            ]);
            
            if ($testeLigas && isset($testeLigas['response']) && !empty($testeLigas['response'])) {
                $jogos = $testeLigas['response'];
                echo '<div class="alert alert-success mt-3">
                        <h5><i class="fas fa-check-circle"></i> Sucesso!</h5>
                        <p>Encontramos ' . count($jogos) . ' jogos de campeonatos brasileiros.</p>
                      </div>';
            }
        }
        
        // Salvar informações detalhadas de depuração
        $debugInfo = [
            'teste_brasileirao' => $testeDireto ?? null,
            'teste_ligas_brasil' => $testeLigas ?? null,
            'data_atual' => date('Y-m-d H:i:s'),
            'api_key_valida' => !empty($apiConfig['api_key']),
            'parametros' => $_GET
        ];
        saveConfig('debug_brasil', $debugInfo);
        
        // Mostrar informações detalhadas para o administrador
        if (isAdmin()) {
            echo '<div class="card mb-4 mt-3 border-primary">
                    <div class="card-header bg-green text-white">
                        <i class="fas fa-bug me-1"></i>
                        Informações de Depuração (apenas para administradores)
                    </div>
                    <div class="card-body">
                        <h5>Status da API</h5>
                        <ul>
                            <li>API Key configurada: ' . (!empty($apiConfig['api_key']) ? 'Sim' : 'Não') . '</li>
                            <li>URL Base: ' . ($apiConfig['base_url'] ?? 'Não definida') . '</li>
                            <li>Data atual: ' . date('Y-m-d H:i:s') . '</li>
                        </ul>';
            
            // Mostrar erros da API, se houver
            if (isset($testeDireto['errors']) && !empty($testeDireto['errors'])) {
                echo '<h5>Erros retornados pela API:</h5>
                      <pre>' . print_r($testeDireto['errors'], true) . '</pre>';
            }
            
            // Mostrar cabeçalhos da resposta
            if (isset($testeDireto)) {
                echo '<h5>Cabeçalhos da resposta:</h5>
                      <pre>';
                if (isset($testeDireto['paging'])) {
                    echo "Paginação: " . print_r($testeDireto['paging'], true) . "\n";
                }
                if (isset($testeDireto['parameters'])) {
                    echo "Parâmetros enviados: " . print_r($testeDireto['parameters'], true) . "\n";
                }
                echo '</pre>';
            }
            
            echo '<p>Tente uma destas opções:</p>
                  <ol>
                      <li>Verifique se sua chave API está correta em <a href="configuracoes.php?categoria=api_football">Configurações</a></li>
                      <li>A versão gratuita da API pode ter limitações - considere fazer upgrade</li>
                      <li>Use o método alternativo abaixo</li>
                  </ol>
                  </div>
                  </div>';
        }
    }
    
    echo '<div class="card mb-4 mt-3 border-warning">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Nenhum jogo encontrado pelos filtros
            </div>
            <div class="card-body">
                <p>Não foi possível encontrar jogos com os filtros atuais.</p>
                <p>Possíveis razões:</p>
                <ul>
                    <li>Não há jogos agendados para o período ou campeonato selecionado</li>
                    <li>A API pode ter limitações para o plano atual</li>
                    <li>O formato dos parâmetros pode estar incorreto</li>
                </ul>';
                
    // Mostrar detalhes de depuração se estiver logado como admin
    if (isAdmin()) {
        $debug = getConfig('novo_bolao_debug');
        if ($debug) {
            echo '<div class="mt-3 border p-2 bg-light">
                    <h6>Detalhes técnicos (apenas para administradores):</h6>
                    <p>Data da tentativa: ' . ($debug['data_atual'] ?? 'N/A') . '</p>';
            
            if (isset($debug['requisicao']['errors'])) {
                echo '<p>Erros reportados pela API:</p>
                      <pre>' . print_r($debug['requisicao']['errors'], true) . '</pre>';
            }
            
            echo '<p>Parâmetros enviados:</p>
                  <pre>' . print_r($debug['parametros'] ?? [], true) . '</pre>
                  </div>';
        }
    }

    echo '
                <div class="alert alert-info mt-3">
                    <strong>Dica:</strong> Tente usar o método alternativo abaixo para encontrar jogos.
                </div>
                
                <h5 class="mt-3">Método alternativo</h5>
                <p>Selecione uma das principais ligas para buscar jogos diretamente:</p>
                
                <div class="d-grid gap-2">
                    <a href="?metodo=alternativo&liga=128&temporada=' . $anoAtual . '" class="btn btn-outline-primary">Campeonato Argentino</a>
                    <a href="?metodo=alternativo&liga=71&temporada=' . $anoAtual . '" class="btn btn-outline-primary">Brasileirão Série A</a>
                    <a href="?metodo=alternativo&liga=72&temporada=' . $anoAtual . '" class="btn btn-outline-primary">Brasileirão Série B</a>
                    <a href="?metodo=alternativo&liga=281&temporada=' . $anoAtual . '" class="btn btn-outline-primary">Campeonato Chileno</a>
                    <a href="?metodo=alternativo&liga=239&temporada=' . $anoAtual . '" class="btn btn-outline-primary">Liga MX (México)</a>
                    <a href="?metodo=alternativo&liga=265&temporada=' . $anoAtual . '" class="btn btn-outline-primary">Liga Profesional Peruana</a>
                </div>';
}

// Mostrar um método alternativo para criar bolão com jogos manuais

// Include header
$pageTitle = "Novo Bolão";
$currentPage = "boloes";
include '../templates/admin/header.php';
?>

<style>
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
.futebol-checkboxes .form-check {
    margin-bottom: 0.5rem;
}
.futebol-checkboxes .form-check-input:checked {
    background-color: #388e3c;
    border-color: #388e3c;
    box-shadow: 0 0 0 0.2rem #c8e6c9;
}
.futebol-checkboxes .form-check-input:focus {
    box-shadow: 0 0 0 0.2rem #ffd600;
}
.futebol-checkboxes .form-check-label {
    font-weight: 500;
    font-size: 1.08rem;
    color: #222;
}
.futebol-btn {
    background: linear-gradient(90deg, #43a047 60%, #ffd600 100%);
    color: #fff;
    font-size: 1.2rem;
    font-weight: bold;
    border-radius: 30px;
    padding: 0.75rem 2.5rem;
    box-shadow: 0 2px 8px 0 rgba(34,139,34,0.10);
    border: none;
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}
.futebol-btn:hover {
    background: linear-gradient(90deg, #388e3c 60%, #ffd600 100%);
    color: #fff;
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 6px 24px 0 rgba(34,139,34,0.18);
}
.futebol-separator {
    border-top: 2px dashed #43a047;
    margin: 1.5rem 0 1rem 0;
}
.futebol-label {
    font-weight: 600;
    color: #388e3c;
    letter-spacing: 0.5px;
}

/* Estilos para jogos já utilizados */
.jogo-ja-utilizado {
    background-color: #ffebee !important;
    opacity: 0.6;
}

.jogo-ja-utilizado input[type="checkbox"] {
    cursor: not-allowed;
}

.jogo-ja-utilizado td {
    color: #999;
}

/* Destaque para linhas selecionadas */
tbody tr.jogo-selecionado {
    background-color: #c8e6c9 !important;
    border-left: 4px solid #43a047;
}

/* ESTILO DESTACADO PARA O TOGGLE DE JOGOS EM USO */
.toggle-jogos-em-uso-container {
    position: relative;
    background: linear-gradient(135deg, #ff9800, #ff5722);
    border-radius: 20px;
    padding: 12px 20px;
    box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
    border: 2px solid #ff6f00;
}

.toggle-jogos-destacado {
    margin: 0 !important;
}

.toggle-switch-custom {
    width: 50px !important;
    height: 25px !important;
    background-color: #e53935 !important;
    border: 2px solid #fff !important;
    transition: all 0.3s ease !important;
}

.toggle-switch-custom:checked {
    background-color: #4caf50 !important;
    border-color: #fff !important;
}

.toggle-switch-custom:focus {
    box-shadow: 0 0 0 0.25rem rgba(255, 152, 0, 0.5) !important;
}

.toggle-label-custom {
    font-weight: bold !important;
    color: #fff !important;
    font-size: 1rem !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin-left: 10px !important;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3) !important;
}

.toggle-icon {
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.toggle-text {
    font-size: 1rem;
    font-weight: 600;
}

.toggle-badge {
    background: rgba(255,255,255,0.9);
    color: #ff5722;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
    margin-left: 5px;
    transition: all 0.3s ease;
}

/* Animação pulsante para chamar atenção */
.toggle-jogos-em-uso-container::before {
    content: '';
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    background: linear-gradient(45deg, #ff9800, #ff5722, #ff9800);
    border-radius: 23px;
    z-index: -1;
    animation: pulse-border 2s infinite;
}

@keyframes pulse-border {
    0%, 100% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.02); opacity: 1; }
}

/* Hover effect */
.toggle-jogos-em-uso-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(255, 152, 0, 0.4);
}

/* Estado quando marcado */
.toggle-switch-custom:checked + .toggle-label-custom .toggle-icon {
    transform: rotate(180deg);
}

.toggle-switch-custom:checked + .toggle-label-custom .toggle-badge {
    background: rgba(76, 175, 80, 0.9);
    color: #fff;
}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />

<div class="container-fluid px-4" style="padding-top: 4.5rem;">
    <h1 class="mt-4 mb-4" style="font-family: 'Oswald', 'Montserrat', Arial, sans-serif; color: #388e3c; letter-spacing: 2px;">
        <i class="fa-solid fa-futbol"></i> Novo Bolão
    </h1>
    <!-- Card Dados do Bolão -->
    <div class="card mb-4 futebol-card" style="border: 2px solid #ffd600;">
        <div class="card-header" style="background: linear-gradient(90deg, #ffd600 60%, #43a047 100%); color: #222;">
            <i class="fa-solid fa-pen-nib"></i> Dados do Bolão
                </div>
                <div class="card-body">
            <form method="post" action="<?= APP_URL ?>/admin/novo-bolao.php" enctype="multipart/form-data">
                <div class="row g-4 align-items-stretch">
                    <!-- Card Campeonatos -->
                    <div class="col-lg-4 col-md-12 mb-3 mb-lg-0">
                        <div class="futebol-card h-100 p-3" style="background: #f1f8e9; border: 1.5px solid #c8e6c9;">
                            <label class="form-label futebol-label">Campeonatos</label>
                            <div id="campeonatos-checkboxes" class="futebol-checkboxes" style="height:auto;">
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="71" id="checkA">
                                    <label class="form-check-label" for="checkA"><i class="fa-solid fa-shield-halved"></i> Brasileirão Série A</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="72" id="checkB">
                                    <label class="form-check-label" for="checkB"><i class="fa-solid fa-shield-halved"></i> Brasileirão Série B</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="73" id="checkCopa">
                                    <label class="form-check-label" for="checkCopa"><i class="fa-solid fa-trophy"></i> Copa do Brasil</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input campeonato-checkbox" type="checkbox" name="campeonatos[]" value="13" id="checkLib">
                                    <label class="form-check-label" for="checkLib"><i class="fa-solid fa-globe"></i> Libertadores</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Card Datas -->
                    <div class="col-lg-8 col-md-12 mb-3 mb-lg-0">
                        <div class="futebol-card h-100 p-3" style="background: #f9fbe7; border: 1.5px solid #c8e6c9;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="quantidade-jogos" class="form-label futebol-label">Qtd. Jogos</label>
                                    <div class="input-group align-items-center">
                                        <input type="number" class="form-control" id="quantidade-jogos" name="quantidade_jogos" min="1" max="50" value="11" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="data-inicio" class="form-label futebol-label">Data Início</label>
                                    <input type="date" class="form-control" id="data-inicio" name="data_inicio" value="" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="data-fim" class="form-label futebol-label">Data Fim</label>
                                    <input type="date" class="form-control" id="data-fim" name="data_fim" value="" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados do Bolão - Adicionado antes da tabela de jogos -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label for="nome-bolao" class="form-label futebol-label">Nome do Bolão</label>
                        <input type="text" class="form-control" id="nome-bolao" name="nome" value="<?= htmlspecialchars($formData['nome'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="valor-participacao" class="form-label futebol-label">Valor Participação</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control" id="valor-participacao" name="valor_participacao" value="<?= htmlspecialchars($formData['valor_participacao'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="premio-bolao" class="form-label futebol-label">Prêmio Total</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control" id="premio-bolao" name="premio_total" value="<?= htmlspecialchars($formData['premio_total'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="premio-rodada" class="form-label futebol-label">Prêmio da Rodada</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control" id="premio-rodada" name="premio_rodada" value="<?= htmlspecialchars($formData['premio_rodada'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label for="imagem-bolao" class="form-label futebol-label">Imagem (arte do bolão)</label>
                        <input type="file" class="form-control" id="imagem-bolao" name="imagem_bolao" accept="image/*">
                        <div id="preview-imagem-bolao" class="mt-2"></div>
                    </div>
                </div>
                
                <!-- Opções adicionais -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   id="incluir_sem_horario" name="incluir_sem_horario" value="1">
                            <label class="form-check-label futebol-label" for="incluir_sem_horario">
                                <i class="fa-solid fa-clock"></i> Incluir jogos sem horário definido
                                <small class="text-muted d-block">Marque esta opção para incluir jogos com status TBD (To Be Determined) ou TBA (To Be Announced)</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   id="publico" name="publico" value="1" checked>
                            <label class="form-check-label futebol-label" for="publico">
                                <i class="fa-solid fa-globe"></i> Bolão Público
                                <small class="text-muted d-block">Bolões públicos aparecem na lista geral e podem ser acessados por qualquer usuário</small>
                            </label>
                        </div>
                    </div>
                </div>
                            
                <!-- Botões de ação -->
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <button type="button" id="buscar-jogos-btn" class="btn futebol-btn" style="flex: 1; max-width: 400px;">
                        <i class="fa-solid fa-search"></i> Buscar Jogos Disponíveis
                    </button>
                    <button type="submit" class="btn futebol-btn" style="flex: 1; max-width: 400px;">
                        <i class="fa-solid fa-futbol"></i> Criar Bolão com Jogos Selecionados
                    </button>
                </div>

                <div class="table-responsive mt-4" id="jogos-table-container" style="display:none;">
                    <!-- Toggle para mostrar/ocultar jogos em uso - COM DESTAQUE -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Jogos Disponíveis</h5>
                        <div class="toggle-jogos-em-uso-container">
                            <div class="form-check form-switch toggle-jogos-destacado">
                                <input class="form-check-input toggle-switch-custom" type="checkbox" id="toggle-jogos-em-uso">
                                <label class="form-check-label toggle-label-custom" for="toggle-jogos-em-uso">
                                    <i class="fa-solid fa-eye-slash toggle-icon"></i>
                                    <span class="toggle-text">Mostrar jogos em uso em outros bolões</span>
                                    <span class="toggle-badge">Ocultos</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <table class="table table-striped align-middle" id="jogos-table">
                        <thead style="background: #388e3c; color: #fff;">
                            <tr>
                                <th style="width: 50px;"><i class="fa-solid fa-check-double"></i></th>
                                <th><i class="fa-regular fa-calendar-days"></i> Data/Hora</th>
                                <th><i class="fa-solid fa-shield-halved"></i> Campeonato</th>
                                <th><i class="fa-solid fa-people-group"></i> Time Casa</th>
                                <th><i class="fa-solid fa-people-group"></i> Time Visitante</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            <!-- Preenchido via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts específicos -->
<script>
    const APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/public/js/bolao-creator.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa o criador de bolão
    initBolaoCreator();

    // Configuração do loader
    const buscarJogosBtn = document.getElementById('buscar-jogos-btn');
    const loaderOverlay = document.getElementById('loader-overlay');

    if (buscarJogosBtn && loaderOverlay) {
        const originalClick = buscarJogosBtn.onclick;
        buscarJogosBtn.onclick = async function(e) {
            // Mostra o loader com animação
            loaderOverlay.style.display = 'flex';
            loaderOverlay.classList.add('fade-in');

            // Se houver um manipulador de clique original, execute-o
            if (originalClick) {
                try {
                    await originalClick.call(this, e);
                } catch (error) {
                    console.error('Erro ao buscar jogos:', error);
                }
            }

            // Esconde o loader após um pequeno delay para garantir que os dados foram carregados
            setTimeout(() => {
                loaderOverlay.classList.add('fade-out');
                setTimeout(() => {
                    loaderOverlay.style.display = 'none';
                    loaderOverlay.classList.remove('fade-in', 'fade-out');
                }, 300);
            }, 500);
        };
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
// Preview da imagem do bolão
const inputImagem = document.getElementById('imagem-bolao');
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
// Sugestão automática de nome do bolão
const nomeBolaoInput = document.getElementById('nome-bolao');
const dataInicioInput = document.getElementById('data-inicio');
const dataFimInput = document.getElementById('data-fim');
function sugerirNomeBolao() {
    if (dataInicioInput.value && dataFimInput.value && nomeBolaoInput.value === '') {
        const [ano1, mes1, dia1] = dataInicioInput.value.split('-');
        const [ano2, mes2, dia2] = dataFimInput.value.split('-');
        nomeBolaoInput.value = `Bolão ${dia1}/${mes1}/${ano1} a ${dia2}/${mes2}/${ano2}`;
    }
}
dataInicioInput && dataInicioInput.addEventListener('change', sugerirNomeBolao);
dataFimInput && dataFimInput.addEventListener('change', sugerirNomeBolao);
</script>
<?php include '../templates/admin/footer.php'; ?>