<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se a atualização automática está ativa
$config = dbFetchRow("SELECT valor FROM configuracoes WHERE nome_configuracao = 'atualizacao_automatica' AND categoria = 'resultados'");
if (!$config || $config['valor'] != '1') {
    exit("Atualização automática desativada\n");
}

// Verificar horário permitido
$horaAtual = date('H:i');
$config = dbFetchRow("SELECT valor FROM configuracoes WHERE nome_configuracao = 'horario_inicio' AND categoria = 'resultados'");
$horaInicio = $config['valor'] ?? '08:00';
$config = dbFetchRow("SELECT valor FROM configuracoes WHERE nome_configuracao = 'horario_fim' AND categoria = 'resultados'");
$horaFim = $config['valor'] ?? '23:00';

if ($horaAtual < $horaInicio || $horaAtual > $horaFim) {
    exit("Fora do horário permitido para atualizações ({$horaInicio} - {$horaFim})\n");
}

// Verificar dia da semana permitido
$diaSemanaAtual = date('N'); // 1 (Segunda) até 7 (Domingo)
$config = dbFetchRow("SELECT valor FROM configuracoes WHERE nome_configuracao = 'dias_semana' AND categoria = 'resultados'");
$diasPermitidos = explode(',', $config['valor'] ?? '1,2,3,4,5,6,7');

if (!in_array($diaSemanaAtual, $diasPermitidos)) {
    exit("Dia da semana não permitido para atualizações\n");
}

// Função para registrar log
function logMessage($message) {
    $logFile = fopen(__DIR__ . "/../logs/atualizacao_automatica.log", "a");
    fwrite($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n");
    fclose($logFile);
}

// Buscar bolões ativos
$boloes = dbFetchAll("SELECT id, nome, jogos FROM dados_boloes WHERE status = 1");
$totalBoloesAtualizados = 0;
$totalJogosAtualizados = 0;

foreach ($boloes as $bolao) {
    $jogos = json_decode($bolao['jogos'], true);
    $resultadosJson = ['jogos' => []];
    $temAtualizacao = false;
    $jogosAtualizados = 0;

    if (empty($jogos)) {
        logMessage("Bolão ID {$bolao['id']} ({$bolao['nome']}) não possui jogos.");
        continue;
    }

    foreach ($jogos as &$jogo) {
        if (!isset($jogo['id'])) {
            continue;
        }

        // Verificar se o jogo precisa ser atualizado
        // Priorizar data_iso, mas usar data como fallback
        $dataParaVerificar = isset($jogo['data_iso']) ? $jogo['data_iso'] : (isset($jogo['data']) ? $jogo['data'] : null);
        if (!$dataParaVerificar) {
            continue;
        }
        
        $dataJogo = new DateTime($dataParaVerificar);
        $agora = new DateTime();
        
        if ($dataJogo > $agora) {
            continue;
        }

        // Buscar resultado na API
        $jogoApi = requestApiFootball($jogo['id']);
        
        if ($jogoApi) {
            $statusAnterior = $jogo['status'];
            $jogo['status'] = $jogoApi['fixture']['status']['short'];

            if (in_array($jogo['status'], ['FT', 'AET', 'PEN', '1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'])) {
                $jogo['resultado_casa'] = $jogoApi['goals']['home'];
                $jogo['resultado_visitante'] = $jogoApi['goals']['away'];
                
                // Determinar resultado para o JSON (0=empate, 1=casa vence, 2=visitante vence)
                if ($jogo['resultado_casa'] > $jogo['resultado_visitante']) {
                    $resultadosJson['jogos'][$jogo['id']] = "1";
                } elseif ($jogo['resultado_casa'] < $jogo['resultado_visitante']) {
                    $resultadosJson['jogos'][$jogo['id']] = "2";
                } else {
                    $resultadosJson['jogos'][$jogo['id']] = "0";
                }

                $temAtualizacao = true;
                $jogosAtualizados++;
                
                logMessage("Jogo ID {$jogo['id']} atualizado - {$jogo['time_casa']} {$jogo['resultado_casa']} x {$jogo['resultado_visitante']} {$jogo['time_visitante']} (Status: $statusAnterior -> {$jogo['status']})");
            }
        }

        usleep(300000); // 0.3 segundos entre requisições
    }

    if ($temAtualizacao) {
        // Atualizar jogos na tabela dados_boloes
        $jogosJson = json_encode($jogos);
        dbUpdate('dados_boloes', ['jogos' => $jogosJson], 'id = ?', [$bolao['id']]);

        // Atualizar ou inserir na tabela resultados
        $resultado = dbFetchRow("SELECT id FROM resultados WHERE bolao_id = ?", [$bolao['id']]);
        if ($resultado) {
            dbUpdate('resultados', [
                'resultado' => json_encode($resultadosJson),
                'data_resultado' => date('Y-m-d H:i:s')
            ], 'bolao_id = ?', [$bolao['id']]);
        } else {
            dbInsert('resultados', [
                'bolao_id' => $bolao['id'],
                'resultado' => json_encode($resultadosJson),
                'data_resultado' => date('Y-m-d H:i:s')
            ]);
        }

        $totalBoloesAtualizados++;
        $totalJogosAtualizados += $jogosAtualizados;
        
        logMessage("Bolão ID {$bolao['id']} ({$bolao['nome']}) atualizado com $jogosAtualizados jogos modificados.");
    }
}

// Atualizar última execução
dbUpdate(
    'configuracoes',
    ['valor' => date('Y-m-d H:i:s')],
    'nome_configuracao = ? AND categoria = ?',
    ['ultima_atualizacao', 'resultados']
);

logMessage("Atualização automática concluída: $totalJogosAtualizados jogos foram atualizados em $totalBoloesAtualizados bolões.");

exit(0);