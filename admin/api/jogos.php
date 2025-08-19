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
$incluirSemHorario = isset($_GET['incluir_sem_horario']) && $_GET['incluir_sem_horario'] === '1';

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
        // Se incluir jogos sem horário, buscar também status TBD e TBA
        $statusParams = ['status' => 'NS']; // Not Started
        if ($incluirSemHorario) {
            $statusParams = ['status' => 'NS-TBD-TBA']; // Incluir também TBD e TBA
        }
        
        $response = apiFootballRequest('fixtures', array_merge([
            'league' => $id,
            'season' => date('Y'),
            'from' => $dataInicio,
            'to' => $dataFim
        ], $statusParams));

        if ($response && isset($response['response'])) {
            foreach ($response['response'] as $jogo) {
                // Verificar se deve incluir jogos sem horário definido
                $temHorarioDefinido = !empty($jogo['fixture']['date']) && 
                                    $jogo['fixture']['date'] !== '1970-01-01T00:00:00+00:00' &&
                                    !in_array($jogo['fixture']['status']['short'], ['TBD', 'TBA']);
                
                // Incluir o jogo se:
                // 1. Tem horário definido, OU
                // 2. Não tem horário definido mas a opção incluir_sem_horario está marcada
                if ($temHorarioDefinido || $incluirSemHorario) {
                    $dataBr = date('d/m/Y H:i', strtotime($jogo['fixture']['date']));
                    $jogos[] = [
                        'id' => $jogo['fixture']['id'],
                        'data' => $dataBr,
                        'campeonato' => $nome,
                        'time_casa' => $jogo['teams']['home']['name'],
                        'time_visitante' => $jogo['teams']['away']['name'],
                        'status' => $jogo['fixture']['status']['short'],
                        'sem_horario_definido' => !$temHorarioDefinido
                    ];
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
    
    // VERIFICAR JOGOS JÁ UTILIZADOS EM OUTROS BOLÕES
    $jogosUtilizados = [];
    
    // Buscar todos os bolões ativos
    $boloesAtivos = dbFetchAll("SELECT id, nome, jogos FROM dados_boloes WHERE status = 1");
    
    foreach ($boloesAtivos as $bolao) {
        if (!empty($bolao['jogos'])) {
            $jogosJSON = json_decode($bolao['jogos'], true);
            if (is_array($jogosJSON)) {
                foreach ($jogosJSON as $jogo) {
                    if (isset($jogo['id'])) {
                        $jogosUtilizados[$jogo['id']] = [
                            'bolao_id' => $bolao['id'],
                            'bolao_nome' => $bolao['nome']
                        ];
                    }
                }
            }
        }
    }
    
    // Marcar jogos como já utilizados
    foreach ($jogos as &$jogo) {
        if (isset($jogosUtilizados[$jogo['id']])) {
            $jogo['ja_utilizado'] = true;
            $jogo['bolao_nome'] = $jogosUtilizados[$jogo['id']]['bolao_nome'];
            $jogo['bolao_id'] = $jogosUtilizados[$jogo['id']]['bolao_id'];
        } else {
            $jogo['ja_utilizado'] = false;
        }
    }
    unset($jogo); // Quebrar a referência
    
    // Limitar jogos com base no parâmetro limit (padrão 20, máximo 50)
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
    $jogos = array_slice($jogos, 0, $limit);
    
    // Detectar horários suspeitos APENAS nos jogos que serão exibidos (máximo 20)
    $horariosCount = [];
    $alertasHorario = [];
    
    foreach ($jogos as $jogo) {
        // Extrair apenas o horário (sem a data)
        $horario = substr($jogo['data'], -5); // Pega apenas "HH:MM"
        $data = substr($jogo['data'], 0, 10); // Pega apenas "dd/mm/yyyy"
        $chaveHorario = $data . ' ' . $horario;
        
        if (!isset($horariosCount[$chaveHorario])) {
            $horariosCount[$chaveHorario] = [];
        }
        $horariosCount[$chaveHorario][] = $jogo;
    }
    
    // Verificar se há 3 ou mais jogos no mesmo horário (apenas nos 20 jogos da lista)
    foreach ($horariosCount as $horario => $jogosNoHorario) {
        if (count($jogosNoHorario) >= 3) {
            $alertasHorario[] = [
                'horario' => $horario,
                'quantidade' => count($jogosNoHorario),
                'jogos' => $jogosNoHorario
            ];
        }
    }

    // Contar jogos já utilizados para estatísticas
    $totalJogosUtilizados = count(array_filter($jogos, function($jogo) {
        return $jogo['ja_utilizado'];
    }));
    
    // Retornar resposta
    echo json_encode([
        'success' => true,
        'jogos' => $jogos,
        'alertas_horario' => $alertasHorario,
        'estatisticas' => [
            'total_jogos' => count($jogos),
            'jogos_ja_utilizados' => $totalJogosUtilizados,
            'jogos_disponiveis' => count($jogos) - $totalJogosUtilizados
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar jogos: ' . $e->getMessage()
    ]);
}