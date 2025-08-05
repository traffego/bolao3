<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/classes/Logger.php';
// database_functions.php não é mais necessário pois está incluído em database.php

// Initialize logger for configuration issues
$efiConfigLogger = Logger::getInstance();

// Buscar configurações do Pix no banco
$pixConfig = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
$pixConfig = $pixConfig ? json_decode($pixConfig['valor'], true) : [];

// Validate and process webhook URL with fallback mechanisms
$webhookUrl = null;
$configIssues = [];

// Check database webhook URL
if (isset($pixConfig['webhook_url']) && !empty($pixConfig['webhook_url'])) {
    $dbWebhookUrl = $pixConfig['webhook_url'];
    
    // Validate that webhook URL is not localhost in production environments
    $isProduction = (defined('APP_URL') && strpos(APP_URL, 'localhost') === false && strpos(APP_URL, '127.0.0.1') === false);
    $isLocalhostWebhook = (strpos($dbWebhookUrl, 'localhost') !== false || strpos($dbWebhookUrl, '127.0.0.1') !== false);
    
    if ($isProduction && $isLocalhostWebhook) {
        $configIssues[] = 'Webhook URL localhost detectada em ambiente de produção';
        $efiConfigLogger->warn('Webhook URL localhost detectada em ambiente de produção', [
            'db_webhook_url' => $dbWebhookUrl,
            'app_url' => defined('APP_URL') ? APP_URL : 'undefined'
        ]);
        
        // Use fallback to WEBHOOK_URL constant
        if (defined('WEBHOOK_URL')) {
            $webhookUrl = WEBHOOK_URL;
            $configIssues[] = 'Usando fallback para WEBHOOK_URL constant';
            $efiConfigLogger->info('Usando fallback WEBHOOK_URL constant', [
                'fallback_url' => $webhookUrl
            ]);
        }
    } else {
        $webhookUrl = $dbWebhookUrl;
    }
} else {
    // No webhook URL in database, use constant as fallback
    if (defined('WEBHOOK_URL')) {
        $webhookUrl = WEBHOOK_URL;
        $configIssues[] = 'Webhook URL não encontrada no banco, usando constante WEBHOOK_URL';
        $efiConfigLogger->info('Webhook URL não encontrada no banco, usando constante', [
            'webhook_url' => $webhookUrl
        ]);
    } else {
        $configIssues[] = 'Webhook URL não configurada nem no banco nem nas constantes';
        $efiConfigLogger->error('Webhook URL não configurada', [
            'database_config' => $pixConfig,
            'webhook_url_constant_defined' => defined('WEBHOOK_URL')
        ]);
    }
}

// Log configuration issues if any
if (!empty($configIssues)) {
    $efiConfigLogger->warn('Problemas na configuração de webhook detectados', [
        'issues' => $configIssues,
        'database_webhook_url' => $pixConfig['webhook_url'] ?? null,
        'constant_webhook_url' => defined('WEBHOOK_URL') ? WEBHOOK_URL : null,
        'final_webhook_url' => $webhookUrl
    ]);
}

// Definir constantes com base nas configurações do banco
define('EFI_CLIENT_ID', $pixConfig['client_id'] ?? '');
define('EFI_CLIENT_SECRET', $pixConfig['client_secret'] ?? '');
define('EFI_CERTIFICATE_PATH', __DIR__ . '/certificates/certificate.p12');
define('EFI_API_URL', $pixConfig['ambiente'] === 'homologacao' ? 'https://pix-h.api.efipay.com.br' : 'https://pix.api.efipay.com.br');
define('EFI_PIX_KEY', $pixConfig['pix_key'] ?? '');

// Define webhook URL with validation and fallback
if ($webhookUrl) {
    define('EFI_WEBHOOK_URL', $webhookUrl);
} else {
    // Final fallback - generate from APP_URL if available
    if (defined('APP_URL')) {
        $fallbackWebhookUrl = rtrim(APP_URL, '/') . '/api/webhook_pix.php';
        define('EFI_WEBHOOK_URL', $fallbackWebhookUrl);
        $efiConfigLogger->warn('Usando webhook URL gerada automaticamente', [
            'generated_url' => $fallbackWebhookUrl
        ]);
    } else {
        define('EFI_WEBHOOK_URL', '');
        $efiConfigLogger->error('Não foi possível determinar webhook URL');
    }
}

// Define se falhas no registro de webhook devem ser fatais (interromper a execução)
define('EFI_WEBHOOK_FATAL_FAILURE', isset($pixConfig['webhook_fatal_failure']) && ($pixConfig['webhook_fatal_failure'] === true || $pixConfig['webhook_fatal_failure'] === 'true'));

// Better error handling for missing or invalid configurations
if (empty(EFI_CLIENT_ID) || empty(EFI_CLIENT_SECRET) || empty(EFI_PIX_KEY)) {
    $missingFields = [];
    if (empty(EFI_CLIENT_ID)) $missingFields[] = 'client_id';
    if (empty(EFI_CLIENT_SECRET)) $missingFields[] = 'client_secret';
    if (empty(EFI_PIX_KEY)) $missingFields[] = 'pix_key';
    
    $efiConfigLogger->error('Configurações EFI obrigatórias ausentes', [
        'missing_fields' => $missingFields,
        'database_config_found' => !empty($pixConfig)
    ]);
}

// Validate certificate path
if (!file_exists(EFI_CERTIFICATE_PATH)) {
    $efiConfigLogger->warn('Certificado EFI não encontrado', [
        'certificate_path' => EFI_CERTIFICATE_PATH
    ]);
}

// Support for webhook_fatal_failure configuration from database
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $efiConfigLogger->debug('Configuração EFI carregada', [
        'client_id_set' => !empty(EFI_CLIENT_ID),
        'client_secret_set' => !empty(EFI_CLIENT_SECRET),
        'pix_key_set' => !empty(EFI_PIX_KEY),
        'webhook_url' => EFI_WEBHOOK_URL,
        'api_url' => EFI_API_URL,
        'certificate_exists' => file_exists(EFI_CERTIFICATE_PATH),
        'webhook_fatal_failure' => EFI_WEBHOOK_FATAL_FAILURE,
        'config_issues_count' => count($configIssues)
    ]);
} 