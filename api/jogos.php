<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Adicionar cabeçalhos para API
header('Content-Type: application/json');

// Parâmetros da requisição
$dataInicio = $_GET['inicio'] ?? date('Y-m-d');
$dataFim = $_GET['fim'] ?? date('Y-m-d', strtotime('+30 days'));
$campeonatosIds = isset($_GET['campeonatos']) ? explode(',', $_GET['campeonatos']) : [];

// Validar parâmetros
if (empty($campeonatosIds)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum campeonato selecionado'
    ]);
    exit;
}

// Obter configuração da API
$apiConfig = getConfig('api_football');
if (!$apiConfig || empty($apiConfig['api_key'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuração da API não encontrada ou chave inválida'
    ]);
    exit;
}

// Lista de jogos
$jogos = [];

// Buscar jogos para cada campeonato selecionado
foreach ($campeonatosIds as $campeonatoId) {
    $apiUrl = api_football_url('fixtures');
    $parametros = [
        'league' => $campeonatoId,
        'season' => date('Y'),
        'from' => $dataInicio,
        'to' => $dataFim,
        'status' => 'NS' // Not Started
    ];
    
    $urlCompleta = $apiUrl . '?' . http_build_query($parametros);
    
    $headers = [
        "x-rapidapi-key: {$apiConfig['api_key']}",
        "x-rapidapi-host: v3.football.api-sports.io"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlCompleta);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['response']) && !empty($data['response'])) {
            foreach ($data['response'] as $jogo) {
                // Verificar se a data é válida antes de processar
                $fixtureDate = $jogo['fixture']['date'] ?? '';
                $timestamp = strtotime($fixtureDate);
                
                // Só adicionar jogos com horários válidos (não indefinidos)
                if ($timestamp && $timestamp > 0) {
                    $jogos[] = [
                        'id' => $jogo['fixture']['id'],
                        'campeonato' => $jogo['league']['name'],
                        'campeonato_id' => $jogo['league']['id'],
                        'time_casa' => $jogo['teams']['home']['name'],
                        'time_visitante' => $jogo['teams']['away']['name'],
                        'data' => date('d/m/Y H:i', $timestamp),
                        'status' => $jogo['fixture']['status']['short']
                    ];
                }
            }
        }
    } else {
        // Registrar erro mas continuar para outros campeonatos
        error_log("Erro ao buscar jogos do campeonato $campeonatoId. HTTP Code: $httpCode");
        error_log("Resposta: $response");
    }
}

// Ordenar jogos por data
usort($jogos, function($a, $b) {
    return strtotime(str_replace('/', '-', $a['data'])) - strtotime(str_replace('/', '-', $b['data']));
});

// Retornar resposta
echo json_encode([
    'success' => !empty($jogos),
    'jogos' => $jogos,
    'filtros' => [
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
        'campeonatos' => $campeonatosIds
    ],
    'total' => count($jogos),
    'message' => empty($jogos) ? 'Nenhum jogo encontrado para os filtros selecionados' : null
]);
?> 