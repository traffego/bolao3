<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Inicializar variáveis de controle
$jogosAtualizados = 0;
$boloesAtualizados = 0;
$erros = [];

// Obter configuração da API
$apiConfig = getConfig('api_football');

if (!$apiConfig || empty($apiConfig['api_key'])) {
    setFlashMessage('danger', 'Chave da API não configurada. Configure a API em Configurações.');
    redirect(APP_URL . '/admin/configuracoes.php');
}

// Buscar todos os bolões ativos
$boloes = dbFetchAll("SELECT id, nome, jogos FROM dados_boloes WHERE status = 1");

if (empty($boloes)) {
    setFlashMessage('warning', 'Nenhum bolão ativo encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Registrar início da operação
$logMessage = "Iniciando atualização de jogos: " . date('Y-m-d H:i:s') . "\n";
$logFile = fopen("../logs/atualizacao_jogos.log", "a");
fwrite($logFile, $logMessage);

// Função para fazer requisição à API Football
function requestApiFootball($jogoId) {
    global $apiConfig, $erros;
    
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
    
    if ($httpCode !== 200) {
        $erros[] = "Erro ao consultar API para o jogo ID $jogoId. HTTP Code: $httpCode";
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['response']) || empty($data['response'])) {
        $erros[] = "Resposta vazia da API para o jogo ID $jogoId";
        return null;
    }
    
    return $data['response'][0];
}

// Processar cada bolão
foreach ($boloes as $bolao) {
    $jogosDecode = json_decode($bolao['jogos'], true) ?: [];
    $jogosAtualizados = 0;
    $temAtualizacao = false;
    
    if (empty($jogosDecode)) {
        $erros[] = "Bolão ID {$bolao['id']} ({$bolao['nome']}) não possui jogos.";
        continue;
    }
    
    // Percorrer todos os jogos do bolão
    foreach ($jogosDecode as &$jogo) {
        // Verificar se o jogo já tem resultado ou se precisa ser atualizado
        if (isset($jogo['id']) && ($jogo['resultado_casa'] === null || $jogo['status'] === 'NS')) {
            // Verificar se o jogo já ocorreu (só atualiza jogos passados ou em andamento)
            $dataJogo = isset($jogo['data_iso']) ? new DateTime($jogo['data_iso']) : null;
            $agora = new DateTime();
            
            // Se não tem data ISO ou a data é no futuro, pular para o próximo
            if (!$dataJogo || $dataJogo > $agora) {
                continue;
            }
            
            // Buscar dados do jogo na API
            $jogoApi = requestApiFootball($jogo['id']);
            
            if ($jogoApi) {
                // Verificar status do jogo
                $statusAnterior = $jogo['status'];
                $jogo['status'] = $jogoApi['fixture']['status']['short'];
                
                // Se o jogo já terminou ou está em andamento, atualizar resultados
                if (in_array($jogo['status'], ['FT', 'AET', 'PEN', '1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'])) {
                    $jogo['resultado_casa'] = $jogoApi['goals']['home'];
                    $jogo['resultado_visitante'] = $jogoApi['goals']['away'];
                    $temAtualizacao = true;
                    $jogosAtualizados++;
                    
                    $logMessage = "Jogo ID {$jogo['id']} atualizado - {$jogo['time_casa']} {$jogo['resultado_casa']} x {$jogo['resultado_visitante']} {$jogo['time_visitante']} (Status: $statusAnterior -> {$jogo['status']})\n";
                    fwrite($logFile, $logMessage);
                }
            }
            
            // Aguardar um pequeno intervalo para evitar limitações de API
            usleep(300000); // 0.3 segundos
        }
    }
    
    // Se houve atualização, salvar de volta no banco
    if ($temAtualizacao) {
        $jogosJson = json_encode($jogosDecode);
        dbUpdate('dados_boloes', ['jogos' => $jogosJson], 'id = ?', [$bolao['id']]);
        $boloesAtualizados++;
        
        $logMessage = "Bolão ID {$bolao['id']} ({$bolao['nome']}) atualizado com $jogosAtualizados jogos.\n";
        fwrite($logFile, $logMessage);
    }
}

// Registrar fim da operação
$logMessage = "Atualização concluída: " . date('Y-m-d H:i:s') . " - $boloesAtualizados bolões atualizados.\n";
if (!empty($erros)) {
    $logMessage .= "ERROS:\n" . implode("\n", $erros) . "\n";
}
$logMessage .= "----------------------------------------\n";
fwrite($logFile, $logMessage);
fclose($logFile);

// Redirecionar de volta com mensagem de conclusão
if ($boloesAtualizados > 0) {
    setFlashMessage('success', "Atualização concluída! $boloesAtualizados bolões atualizados com resultados de jogos.");
} elseif (!empty($erros)) {
    setFlashMessage('warning', "Atualização concluída, mas houve erros. Verifique o log para mais detalhes.");
} else {
    setFlashMessage('info', "Nenhum jogo precisava ser atualizado.");
}

redirect(APP_URL . '/admin/boloes.php');
?> 