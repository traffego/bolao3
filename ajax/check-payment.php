<?php
// Habilitar exibição de erros para debug em ambiente local
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar exibição de erros para evitar HTML na saída
ini_set('display_startup_errors', 0);

// Headers para CORS e cache (definir antes de qualquer saída)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');    // cache por 1 dia
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Responder imediatamente para requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['status' => 'ok']);
    exit;
}

try {
    // Configurar cookie SameSite
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path' => '/',
        'domain' => '',  // Deixar vazio para usar o domínio atual
        'secure' => false,  // Desabilitar temporariamente para debug
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Obter dados da requisição antes de iniciar a sessão
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    // Se tiver session_id na requisição, usar ele
    if (isset($jsonData['session_id'])) {
        session_id($jsonData['session_id']);
    }

    // Iniciar sessão
    session_start();

    // Log detalhado do ambiente e sessão
    error_log("=== Debug de Sessão ===");
    error_log("Session ID Recebido: " . ($jsonData['session_id'] ?? 'não informado'));
    error_log("Session ID Atual: " . session_id());
    error_log("Session Status: " . session_status());
    error_log("Session Data: " . print_r($_SESSION, true));
    error_log("Request Data: " . print_r($jsonData, true));
    error_log("=== Fim Debug de Sessão ===");

    // Verificar se a sessão está ativa e tem usuário
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sessão não encontrada. Session ID: ' . session_id());
    }

    // Validar dados necessários
    if (!isset($jsonData['bolao_id']) || !isset($jsonData['user_id'])) {
        throw new Exception('Dados obrigatórios não fornecidos (bolao_id e user_id)');
    }

    $bolaoId = (int)$jsonData['bolao_id'];
    $userId = (int)$jsonData['user_id'];

    // Verificar se o usuário da sessão corresponde
    if ($_SESSION['user_id'] != $userId) {
        throw new Exception('ID do usuário não corresponde à sessão');
    }

    // Carregar configurações e funções
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/EfiPixManager.php';

    // Buscar dados do usuário
    $user = dbFetchOne("
        SELECT j.id, j.txid_pagamento, j.pagamento_confirmado, p.id as palpite_id, p.status as palpite_status
        FROM jogador j
        LEFT JOIN palpites p ON p.jogador_id = j.id 
            AND p.bolao_id = ? 
            AND p.status = 'pendente'
        WHERE j.id = ?
        ORDER BY p.data_palpite DESC
        LIMIT 1
    ", [$bolaoId, $userId]);

    $bolao = dbFetchOne("SELECT valor_participacao FROM dados_boloes WHERE id = ?", [$bolaoId]);

    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }
    if (!$bolao) {
        throw new Exception('Bolão não encontrado');
    }

    error_log("Dados do usuário: " . print_r($user, true));
    error_log("Dados do bolão: " . print_r($bolao, true));

    // Se não tem palpite pendente
    if (empty($user['palpite_id'])) {
        error_log("Nenhum palpite pendente encontrado");
        echo json_encode(['status' => 'error', 'message' => 'Nenhum palpite pendente encontrado']);
        exit;
    }

    // Se não tem TXID
    if (empty($user['txid_pagamento'])) {
        error_log("TXID não encontrado");
        echo json_encode(['status' => 'pending', 'message' => 'Aguardando início do pagamento']);
        exit;
    }

    // Verificar status na API do EFIBANK
    $efiPix = new EfiPixManager();
    $paymentStatus = $efiPix->checkPayment($user['txid_pagamento']);
    error_log("Status do pagamento EFIBANK: " . print_r($paymentStatus, true));

    // Verificar status do pagamento
    if ($paymentStatus['status'] === 'CONCLUIDA') {
        // Verificar valor pago
        $valorPago = $paymentStatus['valor']['pago'];
        $valorBolao = (float)$bolao['valor_participacao'];
        
        if ($valorPago < $valorBolao) {
            error_log("Valor pago insuficiente - Esperado: {$valorBolao}, Recebido: {$valorPago}");
            echo json_encode([
                'status' => 'pending',
                'message' => 'Valor pago inferior ao valor do bolão'
            ]);
            exit;
        }

        // Iniciar transação
        error_log("Iniciando transação para confirmar pagamento");
        $pdo->beginTransaction();
        
        try {
            // Atualizar status do palpite
            dbExecute("UPDATE palpites SET status = 'pago' WHERE id = ?", [$user['palpite_id']]);
            error_log("Palpite {$user['palpite_id']} atualizado para pago");

            // Registrar pagamento na tabela pagamentos
            dbExecute("
                INSERT INTO pagamentos (
                    jogador_id, 
                    bolao_id, 
                    valor, 
                    tipo,
                    status, 
                    metodo_pagamento,
                    data_pagamento
                ) VALUES (?, ?, ?, 'entrada', 'confirmado', 'pix', NOW())",
                [$userId, $bolaoId, $valorPago]
            );
            error_log("Pagamento registrado na tabela pagamentos");

            // Commit da transação
            $pdo->commit();
            error_log("Transação concluída com sucesso");

            echo json_encode([
                'status' => 'paid',
                'palpite_id' => $user['palpite_id']
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro na transação: " . $e->getMessage());
            throw new Exception('Erro ao processar pagamento: ' . $e->getMessage());
        }
    } else if ($paymentStatus['status'] === 'REMOVIDA') {
        error_log("Pagamento removido pelo PSP");
        echo json_encode([
            'status' => 'cancelled',
            'message' => 'Pagamento foi cancelado ou removido'
        ]);
    } else {
        error_log("Pagamento ainda pendente: " . $paymentStatus['status']);
        echo json_encode([
            'status' => 'pending',
            'message' => 'Aguardando confirmação do pagamento'
        ]);
    }

    error_log("=== Fim da Verificação de Pagamento ===\n");

} catch (Exception $e) {
    error_log("Erro na verificação de pagamento: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
} 