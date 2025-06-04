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
        throw new Exception('Dados obrigatórios não fornecidos');
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
    $user = dbFetchOne("SELECT txid_pagamento, pagamento_confirmado FROM jogador WHERE id = ?", [$userId]);
    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }

    // Se já confirmado, retornar status pago
    if ($user['pagamento_confirmado']) {
        echo json_encode(['status' => 'paid']);
        exit;
    }

    // Se não tem TXID, ainda não iniciou o pagamento
    if (empty($user['txid_pagamento'])) {
        echo json_encode(['status' => 'pending']);
        exit;
    }

    // Verificar status na API do EFIBANK
    $efiPix = new EfiPixManager();
    $paymentStatus = $efiPix->checkPayment($user['txid_pagamento']);

    if ($paymentStatus['status'] === 'CONCLUIDA') {
        // Atualizar status no banco
        dbExecute("UPDATE jogador SET pagamento_confirmado = 1 WHERE id = ?", [$userId]);
        
        // Garantir que a sessão está iniciada
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Definir flag na sessão
        $_SESSION['payment_confirmed'] = true;
        
        echo json_encode(['status' => 'paid']);
    } else {
        echo json_encode(['status' => 'pending']);
    }

} catch (Exception $e) {
    error_log("Erro na verificação de pagamento: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
} 