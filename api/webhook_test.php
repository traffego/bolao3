<?php
/**
 * Endpoint para testar webhooks da EFI - Apenas para administradores
 * 
 * Este endpoint oferece duas formas de teste:
 * 1. Teste via função (padrão) - chama diretamente a função processPixWebhook
 * 2. Teste via HTTP - faz uma requisição HTTP real para o endpoint do webhook
 */

// Incluir configurações e funções necessárias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/classes/Logger.php';

// Verificar se é admin
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Acesso negado. Apenas administradores podem acessar este endpoint.');
}

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido. Use POST.');
}

// Obter payload da requisição
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

// Validar payload
if (!$payload) {
    http_response_code(400);
    die('Payload inválido. Envie um JSON válido.');
}

// Verificar tipo de teste (função ou HTTP)
$testType = $_GET['type'] ?? 'function';

// Criar logger para este teste
$logger = new Logger('webhook_test');
$logger->info('Teste de webhook recebido', [
    'test_type' => $testType,
    'payload' => $payload,
    'ip' => $_SERVER['REMOTE_ADDR']
]);

try {
    if ($testType === 'http') {
        // Opção 1: Fazer requisição HTTP real para o webhook
        $result = testWebhookViaHTTP($payload, $logger);
    } else {
        // Opção 2: Chamar a função diretamente (padrão)
        $result = testWebhookViaFunction($payload, $logger);
    }
    
    $logger->info('Teste de webhook concluído', [
        'test_type' => $testType,
        'result' => $result
    ]);
    
    echo json_encode([
        'status' => 'success',
        'test_type' => $testType,
        'message' => 'Webhook testado com sucesso',
        'result' => $result,
        'received_payload' => $payload
    ]);

} catch (Exception $e) {
    $logger->error('Erro no teste de webhook', [
        'test_type' => $testType,
        'error' => $e->getMessage(),
        'payload' => $payload
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'test_type' => $testType,
        'message' => $e->getMessage(),
        'received_payload' => $payload
    ]);
}

/**
 * Test webhook by calling the function directly
 */
function testWebhookViaFunction($payload, $logger) {
    global $pdo;
    
    // Set global flag to indicate this is a test
    $GLOBALS['is_webhook_test'] = true;
    
    // Include the webhook processor function
    require_once __DIR__ . '/webhook_pix.php';
    
    // Call the function directly
    return processPixWebhook($payload, $logger, $pdo);
}

/**
 * Test webhook by making HTTP request to the actual endpoint
 */
function testWebhookViaHTTP($payload, $logger) {
    // Determine the webhook URL
    $webhookUrl = getWebhookUrl();
    
    $logger->info('Fazendo requisição HTTP para webhook', [
        'url' => $webhookUrl,
        'payload' => $payload
    ]);
    
    // Prepare cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: WebhookTester/1.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('Erro cURL: ' . $error);
    }
    
    $responseData = json_decode($response, true);
    
    return [
        'http_code' => $httpCode,
        'response' => $responseData,
        'raw_response' => $response
    ];
}

/**
 * Get webhook URL based on current environment
 */
function getWebhookUrl() {
    // Try to get from config first
    if (defined('WEBHOOK_URL') && WEBHOOK_URL) {
        return WEBHOOK_URL;
    }
    
    // Build URL based on current request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = dirname($_SERVER['REQUEST_URI']);
    
    // Remove '/api' from the path if present and add webhook_pix.php
    $webhookPath = str_replace('/api', '/api', $basePath) . '/webhook_pix.php';
    
    return $protocol . $host . $webhookPath;
}
?>