<?php
// Habilitar exibição de erros para debug em ambiente local
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log do ambiente
error_log("Ambiente: " . php_uname());
error_log("PHP Version: " . phpversion());
error_log("Server Software: " . $_SERVER['SERVER_SOFTWARE']);

// Iniciar buffer de saída
ob_start();

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers para CORS e cache
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');    // cache por 1 dia
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder imediatamente para requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log da requisição
error_log("Método da requisição: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers da requisição: " . print_r(getallheaders(), true));
error_log("Corpo da requisição: " . file_get_contents('php://input'));
error_log("Cookies: " . print_r($_COOKIE, true));
error_log("Sessão: " . print_r($_SESSION, true));

try {
    // Carregar configurações e funções
    $configFiles = [
        __DIR__ . '/../config/config.php' => 'Configurações',
        __DIR__ . '/../config/database.php' => 'Database',
        __DIR__ . '/../includes/database_functions.php' => 'Funções do Database',
        __DIR__ . '/../includes/functions.php' => 'Funções Gerais',
        __DIR__ . '/../includes/EfiPixManager.php' => 'EfiPix Manager'
    ];

    foreach ($configFiles as $file => $description) {
        if (!file_exists($file)) {
            throw new Exception("Arquivo $description não encontrado: $file");
        }
        require_once $file;
    }

    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido: ' . $_SERVER['REQUEST_METHOD']);
    }

    // Obter e validar dados JSON
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    // Validar dados necessários
    if (!isset($jsonData['bolao_id']) || !isset($jsonData['user_id'])) {
        throw new Exception('Dados obrigatórios não fornecidos');
    }

    $bolaoId = (int)$jsonData['bolao_id'];
    $userId = (int)$jsonData['user_id'];

    // Log dos dados processados
    error_log("Dados processados - Bolão ID: $bolaoId, User ID: $userId");

    // Verificar sessão
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sessão não encontrada');
    }

    if ($_SESSION['user_id'] != $userId) {
        throw new Exception('ID do usuário não corresponde à sessão');
    }

    // Buscar dados do usuário
    try {
        $user = dbFetchOne("SELECT txid_pagamento, pagamento_confirmado FROM jogador WHERE id = ?", [$userId]);
        error_log("Dados do usuário: " . print_r($user, true));
    } catch (Exception $e) {
        throw new Exception('Erro ao buscar usuário: ' . $e->getMessage());
    }

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
    try {
        $efiPix = new EfiPixManager();
        $paymentStatus = $efiPix->checkPayment($user['txid_pagamento']);
        error_log("Status do pagamento: " . print_r($paymentStatus, true));

        if ($paymentStatus['status'] === 'CONCLUIDA') {
            // Atualizar status no banco
            dbExecute("UPDATE jogador SET pagamento_confirmado = 1 WHERE id = ?", [$userId]);
            echo json_encode(['status' => 'paid']);
        } else {
            echo json_encode(['status' => 'pending']);
        }
    } catch (Exception $e) {
        throw new Exception('Erro ao verificar pagamento: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Erro na verificação de pagamento: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
}

// Garantir que nenhuma outra saída seja enviada
exit; 