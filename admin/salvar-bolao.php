<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('danger', 'Método inválido.');
    redirect(APP_URL . '/admin/novo-bolao.php');
}

// Obter dados do formulário
$nome = $_POST['nome'] ?? '';
$dataInicio = $_POST['data_inicio'] ?? '';
$dataFim = $_POST['data_fim'] ?? '';
$dataLimitePalpitar = $_POST['data_limite_palpitar'] ?? null;
$horaLimitePalpitar = $_POST['hora_limite_palpitar'] ?? '23:59';

// Combinar data e hora limite para palpites
if (!empty($dataLimitePalpitar)) {
    $dataLimitePalpitar = $dataLimitePalpitar . ' ' . $horaLimitePalpitar . ':00';
}

$valorParticipacao = isset($_POST['valor_participacao']) ? (float)str_replace(',', '.', $_POST['valor_participacao']) : 0;
$premioTotal = isset($_POST['premio_total']) ? (float)str_replace(',', '.', $_POST['premio_total']) : 0;
$premioRodada = isset($_POST['premio_rodada']) ? (float)str_replace(',', '.', $_POST['premio_rodada']) : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 1; // Default to active (1) if not specified
$publico = isset($_POST['publico']) ? 1 : 1; // Default to public (1) if not specified
$maxParticipantes = $_POST['max_participantes'] ?? null;
$quantidadeJogos = $_POST['quantidade_jogos'] ?? 0;
$imagemBolaoUrl = $_POST['imagem_bolao_url'] ?? '';

// Gerar slug a partir do nome
$slug = slugify($nome);

// Verificar se o slug já existe
$existingSlug = dbFetchOne("SELECT id FROM dados_boloes WHERE slug = ?", [$slug]);
if ($existingSlug) {
    $slug = $slug . '-' . time();
}

// Obter jogos selecionados
$jogosIds = [];
if (isset($_POST['jogos_json']) && !empty($_POST['jogos_json'])) {
    $jogosIds = json_decode($_POST['jogos_json'], true) ?? [];
} elseif (isset($_POST['jogos_selecionados']) && is_array($_POST['jogos_selecionados'])) {
    $jogosIds = $_POST['jogos_selecionados'];
} elseif (isset($_POST['jogos_string']) && !empty($_POST['jogos_string'])) {
    $jogosIds = explode(',', $_POST['jogos_string']);
}

if (empty($jogosIds)) {
    setFlashMessage('danger', 'Nenhum jogo foi selecionado. Por favor, selecione pelo menos um jogo.');
    redirect(APP_URL . '/admin/novo-bolao.php');
}

// Obter campeonatos
$campeonatosIds = isset($_POST['campeonatos']) ? (array)$_POST['campeonatos'] : [];

// Buscar detalhes dos jogos na API
$jogosFull = [];
$apiConfig = getConfig('api_football');

if (!empty($jogosIds) && $apiConfig && !empty($apiConfig['api_key'])) {
    foreach ($jogosIds as $jogoId) {
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
                
                $fixtureDate = $jogo['fixture']['date'] ?? '';
                error_log('Fixture date para jogo ' . $jogo['fixture']['id'] . ': ' . $fixtureDate);
                $timestamp = strtotime($fixtureDate);
                if (!$timestamp) {
                    // Se a data vier vazia ou inválida, salva como null
                    $dataPadrao = null;
                    $dataFormatadaPadrao = 'Data inválida';
                } else {
                    $dataPadrao = date('Y-m-d H:i:s', $timestamp);
                    $dataFormatadaPadrao = date('d/m/Y H:i', $timestamp);
                }
                // Obter IDs dos times
                $timeCasaId = $jogo['teams']['home']['id'] ?? null;
                $timeVisitanteId = $jogo['teams']['away']['id'] ?? null;
                
                // Construir URLs das logos usando os IDs dos times
                $logoTimeCasa = $timeCasaId ? 'https://media.api-sports.io/football/teams/' . $timeCasaId . '.png' : '';
                $logoTimeVisitante = $timeVisitanteId ? 'https://media.api-sports.io/football/teams/' . $timeVisitanteId . '.png' : '';
                
                $jogosFull[] = [
                    'id' => $jogo['fixture']['id'],
                    'campeonato' => $jogo['league']['name'],
                    'campeonato_id' => $jogo['league']['id'],
                    'time_casa' => $jogo['teams']['home']['name'],
                    'time_visitante' => $jogo['teams']['away']['name'],
                    'nome_time_casa' => $jogo['teams']['home']['name'],
                    'nome_time_visitante' => $jogo['teams']['away']['name'],
                    'logo_time_casa' => $logoTimeCasa,
                    'logo_time_visitante' => $logoTimeVisitante,
                    'data' => $dataPadrao,
                    'data_formatada' => $dataFormatadaPadrao,
                    'status' => $jogo['fixture']['status']['short'],
                    'resultado_casa' => null,
                    'resultado_visitante' => null,
                    'local' => $jogo['fixture']['venue']['name'] ?? ''
                ];
            }
        }
    }
}

// Mapear IDs para nomes dos campeonatos
$nomesCampeonatos = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    75 => 'Brasileirão Série C',
    76 => 'Brasileirão Série D',
    73 => 'Copa do Brasil',
    13 => 'Libertadores'
];

$campeonatosFull = [];
foreach ($campeonatosIds as $id) {
    $campeonatosFull[] = [
        'id' => $id,
        'nome' => $nomesCampeonatos[$id] ?? "Campeonato ID $id"
    ];
}

// Preparar dados para inserção
$dados = [
    'nome' => $nome,
    'slug' => $slug,
    'data_inicio' => $dataInicio,
    'data_fim' => $dataFim,
    'data_limite_palpitar' => $dataLimitePalpitar,
    'valor_participacao' => $valorParticipacao,
    'premio_total' => $premioTotal,
    'premio_rodada' => $premioRodada,
    'status' => $status,
    'publico' => $publico,
    'max_participantes' => $maxParticipantes,
    'quantidade_jogos' => $quantidadeJogos,
    'imagem_bolao_url' => $imagemBolaoUrl,
    'admin_id' => getCurrentAdminId(),
    'jogos' => json_encode($jogosFull),
    'campeonatos' => json_encode($campeonatosFull),
    'data_criacao' => date('Y-m-d H:i:s')
];

// Validar dados
$errors = [];
if (empty($nome)) {
    $errors[] = "O nome do bolão é obrigatório";
}
if (empty($dataInicio)) {
    $errors[] = "A data de início é obrigatória";
}
if (empty($dataFim)) {
    $errors[] = "A data de término é obrigatória";
}
if (empty($jogosFull)) {
    $errors[] = "Não foi possível obter detalhes dos jogos selecionados";
}

// Se houver erros, redirecionar de volta
if (!empty($errors)) {
    setFlashMessage('danger', 'Erro ao salvar bolão: ' . implode(', ', $errors));
    redirect(APP_URL . '/admin/novo-bolao.php');
}

try {
    // Inserir no banco de dados
    $result = dbInsert('dados_boloes', $dados);
    
    if ($result) {
        setFlashMessage('success', 'Bolão cadastrado com sucesso!');
        redirect(APP_URL . '/admin/boloes.php');
    } else {
        setFlashMessage('danger', 'Erro ao salvar o bolão no banco de dados.');
        redirect(APP_URL . '/admin/novo-bolao.php');
    }
} catch (Exception $e) {
    error_log("Erro ao salvar bolão: " . $e->getMessage());
    setFlashMessage('danger', 'Ocorreu um erro ao salvar o bolão: ' . $e->getMessage());
    redirect(APP_URL . '/admin/novo-bolao.php');
}
?>