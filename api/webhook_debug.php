<?php
// Webhook de debug para capturar o payload exato enviado pelo provedor EFI
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Capturar todos os dados da requisição
$method = $_SERVER['REQUEST_METHOD'];
$headers = getallheaders();
$payload = file_get_contents('php://input');
$get_params = $_GET;
$post_params = $_POST;

// Criar log detalhado
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $method,
    'headers' => $headers,
    'payload_raw' => $payload,
    'payload_decoded' => json_decode($payload, true),
    'get_params' => $get_params,
    'post_params' => $post_params,
    'server_vars' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null,
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
        'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? null,
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]
];

// Salvar no arquivo de log
$logFile = '../logs/webhook_debug.log';
$logEntry = json_encode($logData, JSON_PRETTY_PRINT) . "\n" . str_repeat('=', 80) . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Responder com sucesso para não quebrar o fluxo
http_response_code(200);
echo json_encode(['status' => 'debug_logged', 'timestamp' => date('Y-m-d H:i:s')]);
?>
