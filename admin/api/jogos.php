<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Validar parâmetros
$dataInicio = $_GET['inicio'] ?? null;
$dataFim = $_GET['fim'] ?? null;
$campeonatosParam = $_GET['campeonatos'] ?? null;

if (!$dataInicio || !$dataFim || !$campeonatosParam) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros de data ou campeonatos inválidos']);
    exit;
}

// IDs dos campeonatos
$campeonatos = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    75 => 'Brasileirão Série C',
    76 => 'Brasileirão Série D',
    73 => 'Copa do Brasil',
    13 => 'Libertadores'
];

// Determinar quais campeonatos buscar
$idsParaBuscar = array_filter(array_map('intval', explode(',', $campeonatosParam)), function($id) use ($campeonatos) {
    return array_key_exists($id, $campeonatos);
});
if (empty($idsParaBuscar)) {
    echo json_encode(['success' => true, 'jogos' => []]);
    exit;
}

try {
    $jogos = [];
    $apiConfig = getConfig('api_football');
    
    if (!$apiConfig || empty($apiConfig['api_key'])) {
        throw new Exception('Configuração da API não encontrada');
    }

    // Buscar jogos para cada campeonato
    foreach ($idsParaBuscar as $id) {
        $nome = $campeonatos[$id] ?? 'Desconhecido';
        $response = apiFootballRequest('fixtures', [
            'league' => $id,
            'season' => date('Y'),
            'from' => $dataInicio,
            'to' => $dataFim,
            'status' => 'NS' // Not Started
        ]);

        if ($response && isset($response['response'])) {
            foreach ($response['response'] as $jogo) {
                // Verificar se a data é válida antes de processar
                $fixtureDate = $jogo['fixture']['date'] ?? '';
                $timestamp = strtotime($fixtureDate);
                
                // Só adicionar jogos com horários válidos (não indefinidos)
                if ($timestamp && $timestamp > 0) {
                    $dataBr = date('d/m/Y H:i', $timestamp);
                    $horario = date('H:i', $timestamp);
                    
                    // Filtrar jogos com horário exatamente 12:00 (horário padrão para indefinidos)
                    if ($horario !== '12:00') {
                        $jogos[] = [
                            'id' => $jogo['fixture']['id'],
                            'data' => $dataBr,
                            'campeonato' => $nome,
                            'time_casa' => $jogo['teams']['home']['name'],
                            'time_visitante' => $jogo['teams']['away']['name'],
                            'status' => $jogo['fixture']['status']['short']
                        ];
                    }
                }
            }
        }
    }

    // Ordenar jogos por série e data
    $ordemSeries = [71, 72, 75, 76, 73, 13];
    usort($jogos, function($a, $b) use ($ordemSeries) {
        $ordA = array_search($a['campeonato'], [
            'Brasileirão Série A',
            'Brasileirão Série B',
            'Brasileirão Série C',
            'Brasileirão Série D',
            'Copa do Brasil',
            'Libertadores'
        ]);
        $ordB = array_search($b['campeonato'], [
            'Brasileirão Série A',
            'Brasileirão Série B',
            'Brasileirão Série C',
            'Brasileirão Série D',
            'Copa do Brasil',
            'Libertadores'
        ]);
        if ($ordA === $ordB) {
            return strtotime($a['data']) - strtotime($b['data']);
        }
        return $ordA - $ordB;
    });
    // Limitar a 20 jogos
    $jogos = array_slice($jogos, 0, 20);

    // Retornar resposta
    echo json_encode([
        'success' => true,
        'jogos' => $jogos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar jogos: ' . $e->getMessage()
    ]);
} 