<?php
/**
 * Script de atualização automática dos resultados de jogos
 * Este script é projetado para ser executado como um cron job
 * Exemplo de cron: 0 /2 * * * php /caminho/para/atualizar-resultados.php >> /caminho/para/logs/atualizacao.log 2>&1
 */

// Definir o caminho base do projeto
$baseDir = dirname(__DIR__);

// Carregar dependências
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/includes/database.php';
require_once $baseDir . '/includes/functions.php';

// Função para registrar log
function logMessage($message) {
    echo date('Y-m-d H:i:s') . " - " . $message . "\n";
}

// Iniciar processo
logMessage("Iniciando atualização automática de resultados");

// Obter configuração da API
$apiConfig = getConfig('api_football');

if (!$apiConfig || empty($apiConfig['api_key'])) {
    logMessage("ERRO: Chave da API não configurada.");
    exit(1);
}

// Buscar bolões ativos com jogos em andamento ou agendados para hoje
$hoje = date('Y-m-d');
$boloes = dbFetchAll("SELECT id, nome, jogos FROM dados_boloes WHERE status = 1");

if (empty($boloes)) {
    logMessage("Nenhum bolão ativo encontrado.");
    exit(0);
}

logMessage("Encontrados " . count($boloes) . " bolões ativos para verificar");

// Função para fazer requisição à API Football
function requestApiFootball($jogoId, $apiConfig) {
    $apiUrl = "https://v3.football.api-sports.io/fixtures?id=" . $jogoId;
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
    
    if ($httpCode !== 200) {
        logMessage("ERRO ao consultar API para o jogo ID $jogoId. HTTP Code: $httpCode");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['response']) || empty($data['response'])) {
        logMessage("Resposta vazia da API para o jogo ID $jogoId");
        return null;
    }
    
    return $data['response'][0];
}

// Contador de jogos atualizados
$totalJogosAtualizados = 0;
$totalBoloesAtualizados = 0;

// Verificar cada bolão
foreach ($boloes as $bolao) {
    $jogosAtualizados = 0;
    $jogosDecode = json_decode($bolao['jogos'], true) ?: [];
    $temAtualizacao = false;
    
    if (empty($jogosDecode)) {
        logMessage("Bolão ID {$bolao['id']} ({$bolao['nome']}) não possui jogos.");
        continue;
    }
    
    logMessage("Verificando {$bolao['nome']} (ID: {$bolao['id']}) - " . count($jogosDecode) . " jogos");
    
    // Percorrer todos os jogos do bolão
    foreach ($jogosDecode as &$jogo) {
        // Verificar se o jogo precisa ser atualizado (sem resultado ou em andamento)
        if (isset($jogo['id']) && ($jogo['resultado_casa'] === null || $jogo['status'] === 'NS' || in_array($jogo['status'], ['LIVE', '1H', 'HT', '2H']))) {
            // Verificar se o jogo já começou ou terminou
            $dataJogo = isset($jogo['data_iso']) ? new DateTime($jogo['data_iso']) : null;
            $agora = new DateTime();
            
            // Se não tem data ISO ou a data é no futuro (mais de 90 minutos), pular
            if (!$dataJogo || $dataJogo > $agora->modify('+90 minutes')) {
                continue;
            }
            
            logMessage("Verificando jogo ID {$jogo['id']} - {$jogo['time_casa']} x {$jogo['time_visitante']}");
            
            // Buscar dados do jogo na API
            $jogoApi = requestApiFootball($jogo['id'], $apiConfig);
            
            if ($jogoApi) {
                // Obter status anterior para log
                $statusAnterior = $jogo['status'];
                $resultadoCasaAnterior = $jogo['resultado_casa'];
                $resultadoVisitanteAnterior = $jogo['resultado_visitante'];
                
                // Atualizar status do jogo
                $jogo['status'] = $jogoApi['fixture']['status']['short'];
                
                // Se o jogo já começou, atualizar resultados (mesmo que seja 0x0)
                if ($jogoApi['fixture']['status']['short'] != 'NS') {
                    $jogo['resultado_casa'] = $jogoApi['goals']['home'];
                    $jogo['resultado_visitante'] = $jogoApi['goals']['away'];
                    $temAtualizacao = true;
                    $jogosAtualizados++;
                    
                    // Registrar mudança apenas se houver alteração real
                    if ($statusAnterior != $jogo['status'] || 
                        $resultadoCasaAnterior !== $jogo['resultado_casa'] || 
                        $resultadoVisitanteAnterior !== $jogo['resultado_visitante']) {
                        
                        logMessage("ATUALIZADO: Jogo ID {$jogo['id']} - {$jogo['time_casa']} {$jogo['resultado_casa']} x {$jogo['resultado_visitante']} {$jogo['time_visitante']} (Status: $statusAnterior -> {$jogo['status']})");
                    }
                }
            }
            
            // Aguardar para evitar limitações de API
            sleep(1);
        }
    }
    
    // Se houver atualizações, salvar de volta no banco
    if ($temAtualizacao) {
        $jogosJson = json_encode($jogosDecode);
        dbUpdate('dados_boloes', ['jogos' => $jogosJson], 'id = ?', [$bolao['id']]);
        $totalBoloesAtualizados++;
        $totalJogosAtualizados += $jogosAtualizados;
        
        logMessage("Bolão ID {$bolao['id']} ({$bolao['nome']}) atualizado com $jogosAtualizados jogos modificados.");
    } else {
        logMessage("Bolão ID {$bolao['id']} ({$bolao['nome']}) verificado, sem atualizações necessárias.");
    }
}

// Registrar conclusão
logMessage("Atualização concluída: $totalJogosAtualizados jogos foram atualizados em $totalBoloesAtualizados bolões.");

// Retornar status de sucesso
exit(0); 