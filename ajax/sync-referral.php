<?php
/**
 * Endpoint AJAX para sincronizar código de afiliação
 * Mantém localStorage JavaScript sincronizado com sessão PHP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se é requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Acesso negado');
}

// Definir cabeçalhos JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Obter dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }
    
    // Verificar se código foi enviado
    if (!isset($data['referral_code']) || empty(trim($data['referral_code']))) {
        throw new Exception('Código de afiliação não fornecido');
    }
    
    $referralCode = trim($data['referral_code']);
    
    // Validar formato do código (opcional)
    if (strlen($referralCode) < 3 || strlen($referralCode) > 20) {
        throw new Exception('Código de afiliação inválido');
    }
    
    // Verificar se o código existe no banco (opcional - para validação)
    $isValidCode = false;
    try {
        $referral = dbFetchOne(
            "SELECT id, nome FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'",
            [$referralCode]
        );
        $isValidCode = !empty($referral);
    } catch (Exception $e) {
        // Se der erro na validação, continua sem validar
        // Isso permite que códigos sejam salvos mesmo se o banco estiver temporariamente indisponível
    }
    
    // Salvar na sessão
    $_SESSION['referral_code'] = $referralCode;
    
    // Log para debug (opcional)
    if (defined('DEBUG_REFERRAL') && DEBUG_REFERRAL) {
        error_log("[REFERRAL SYNC] Código sincronizado: {$referralCode} | Válido: " . ($isValidCode ? 'Sim' : 'Não') . " | IP: {$_SERVER['REMOTE_ADDR']}");
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Código sincronizado com sucesso',
        'code' => $referralCode,
        'valid' => $isValidCode,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Log do erro
    error_log("[REFERRAL SYNC ERROR] " . $e->getMessage() . " | IP: {$_SERVER['REMOTE_ADDR']}");
    
    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>