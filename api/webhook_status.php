<?php
/**
 * Webhook Status API Endpoint
 * 
 * Provides JSON API for webhook monitoring and debugging.
 * Returns webhook configuration status, registration status, 
 * and connectivity information.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/classes/Logger.php';
require_once __DIR__ . '/../includes/classes/WebhookValidator.php';

// Initialize logger
$logger = Logger::getInstance();

/**
 * Simple API key validation using direct database lookup
 * @param string $apiKey The API key to validate
 * @param string $requiredPermission Required permission for this endpoint
 * @param string $endpoint Current endpoint name for logging
 * @return array Validation result with status and details
 */
function validateApiKeySimple($apiKey, $requiredPermission = null, $endpoint = null) {
    if (empty($apiKey)) {
        return ['valid' => false, 'error' => 'No API key provided'];
    }
    
    try {
        // Hash the provided key to match stored format
        $hashedKey = hash('sha256', $apiKey);
        
        // Query database for the API key
        $sql = "SELECT id, key_name, key_prefix, permissions, is_active, expires_at 
                FROM api_keys 
                WHERE api_key = ? AND is_active = 1";
        
        $result = dbFetchOne($sql, [$hashedKey]);
        
        if (!$result) {
            return ['valid' => false, 'error' => 'Invalid API key'];
        }
        
        // Check expiration
        if ($result['expires_at'] && strtotime($result['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'API key expired'];
        }
        
        // Parse permissions
        $permissions = $result['permissions'] ? json_decode($result['permissions'], true) : [];
        
        // Check required permission
        if ($requiredPermission && !in_array($requiredPermission, $permissions) && !in_array('*', $permissions)) {
            return [
                'valid' => false, 
                'error' => 'Insufficient permissions',
                'required' => $requiredPermission,
                'available' => $permissions
            ];
        }
        
        // Update last used info (optional - don't fail if this fails)
        try {
            $updateSql = "UPDATE api_keys SET last_used_at = NOW(), last_used_ip = ?, usage_count = usage_count + 1 WHERE id = ?";
            dbExecute($updateSql, [$_SERVER['REMOTE_ADDR'] ?? 'unknown', $result['id']]);
        } catch (Exception $e) {
            // Log but don't fail validation
            error_log("Failed to update API key usage: " . $e->getMessage());
        }
        
        return [
            'valid' => true,
            'key_id' => $result['id'],
            'key_name' => $result['key_name'],
            'key_prefix' => $result['key_prefix'],
            'permissions' => $permissions
        ];
        
    } catch (Exception $e) {
        return ['valid' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

$logger->info('Webhook status API accessed', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Simple authentication - check for admin session or API key
$authenticated = false;
$authMethod = 'none';

// Check admin session
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $authenticated = true;
    $authMethod = 'session';
}

// Check API key (if provided in header or query)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
$apiKeyValidation = null;

if (!$authenticated && $apiKey) {
    // Simple API key validation using direct database lookup
    $apiKeyValidation = validateApiKeySimple(
        $apiKey, 
        'webhook_status', // Required permission
        'webhook_status.php' // Current endpoint
    );
    
    if ($apiKeyValidation['valid']) {
        $authenticated = true;
        $authMethod = 'api_key';
        
        $logger->info('API key authentication successful', [
            'key_id' => $apiKeyValidation['key_id'],
            'key_name' => $apiKeyValidation['key_name'],
            'key_prefix' => $apiKeyValidation['key_prefix']
        ]);
    } else {
        $logger->warning('API key authentication failed', [
            'error' => $apiKeyValidation['error'],
            'key_prefix' => substr($apiKey, 0, 8) . '...'
        ]);
    }
}

// For GET requests, allow basic status without full authentication
$allowBasicStatus = ($_SERVER['REQUEST_METHOD'] === 'GET');

if (!$authenticated && !$allowBasicStatus) {
    http_response_code(401);
    
    $errorResponse = [
        'error' => 'Authentication required',
        'message' => 'Access denied. Admin login or valid API key required.',
        'auth_methods' => ['session', 'api_key'],
        'timestamp' => date('c')
    ];
    
    // Add specific API key error details if validation failed
    if ($apiKeyValidation && !$apiKeyValidation['valid']) {
        $errorResponse['api_key_error'] = $apiKeyValidation['error'];
        $errorResponse['required_permissions'] = ['webhook_status'];
    }
    
    echo json_encode($errorResponse);
    exit;
}

try {
    $response = [
        'status' => 'success',
        'timestamp' => date('c'),
        'auth_method' => $authMethod,
        'data' => []
    ];
    
    // Add API key information if authenticated via API key
    if ($authMethod === 'api_key' && $apiKeyValidation) {
        $response['api_key_info'] = [
            'key_name' => $apiKeyValidation['key_name'],
            'key_prefix' => $apiKeyValidation['key_prefix'],
            'permissions' => $apiKeyValidation['permissions']
        ];
    }
    
    // Get requested checks from query parameters
    $checks = $_GET['checks'] ?? 'basic';
    $includeDetails = ($_GET['details'] ?? 'false') === 'true';
    $includeConfig = $authenticated && (($_GET['config'] ?? 'false') === 'true');
    
    // Basic system information
    $response['data']['system'] = [
        'app_url' => APP_URL,
        'environment' => defined('DEBUG_MODE') && DEBUG_MODE ? 'development' : 'production',
        'php_version' => PHP_VERSION,
        'timestamp' => time()
    ];
    
    // Initialize validator
    $validator = new WebhookValidator();
    
    // Get current configuration
    $dbConfig = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
    $pixConfig = $dbConfig ? json_decode($dbConfig['valor'], true) : [];
    $webhookUrl = $pixConfig['webhook_url'] ?? (defined('WEBHOOK_URL') ? WEBHOOK_URL : '');
    
    // Basic webhook status
    if ($checks === 'basic' || $checks === 'all') {
        $webhookValidation = $validator->validateWebhookUrl($webhookUrl);
        $response['data']['webhook'] = [
            'url' => $includeConfig ? $webhookUrl : (empty($webhookUrl) ? null : '[CONFIGURED]'),
            'valid' => $webhookValidation['valid'],
            'error_count' => count($webhookValidation['errors']),
            'warning_count' => count($webhookValidation['warnings'])
        ];
        
        if ($includeDetails) {
            $response['data']['webhook']['validation_details'] = [
                'errors' => $webhookValidation['errors'],
                'warnings' => $webhookValidation['warnings']
            ];
        }
    }
    
    // Configuration consistency
    if ($checks === 'config' || $checks === 'all') {
        if ($authenticated) {
            $consistency = $validator->validateEfiConfigConsistency();
            $response['data']['configuration'] = [
                'consistent' => $consistency['consistent'],
                'issue_count' => count($consistency['issues'])
            ];
            
            if ($includeDetails) {
                $response['data']['configuration']['issues'] = $consistency['issues'];
            }
            
            if ($includeConfig) {
                $response['data']['configuration']['sources'] = $consistency['config_sources'];
            }
        } else {
            $response['data']['configuration'] = [
                'message' => 'Authentication required for configuration details'
            ];
        }
    }
    
    // EFI Pay connectivity and webhook registration
    if ($checks === 'efi' || $checks === 'all') {
        if ($authenticated) {
            try {
                require_once __DIR__ . '/../includes/EfiPixManager.php';
                $efiManager = new EfiPixManager(false);
                
                // Test basic connectivity
                $connectivity = $efiManager->testConnectivity();
                $response['data']['efi_connectivity'] = [
                    'status' => $connectivity['status'],
                    'api_url' => $includeConfig ? $connectivity['details']['api_url'] ?? null : '[CONFIGURED]'
                ];
                
                if ($includeDetails) {
                    $response['data']['efi_connectivity']['details'] = $connectivity['details'];
                }
                
                // Check webhook registration
                $registration = $efiManager->getWebhookRegistrationStatus();
                $response['data']['webhook_registration'] = [
                    'registered' => $registration['registered'],
                    'http_code' => $registration['http_code']
                ];
                
                if ($includeDetails && isset($registration['response'])) {
                    $response['data']['webhook_registration']['response'] = $registration['response'];
                }
                
                if (isset($registration['error'])) {
                    $response['data']['webhook_registration']['error'] = $registration['error'];
                }
                
            } catch (Exception $e) {
                $response['data']['efi_error'] = [
                    'message' => $e->getMessage(),
                    'type' => get_class($e)
                ];
                
                $logger->error('Erro ao verificar status EFI via API', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
            }
        } else {
            $response['data']['efi_status'] = [
                'message' => 'Authentication required for EFI status'
            ];
        }
    }
    
    // URL connectivity test
    if ($checks === 'connectivity' || $checks === 'all') {
        if (!empty($webhookUrl)) {
            $connectivityTest = $validator->testWebhookConnectivity($webhookUrl);
            $response['data']['connectivity'] = [
                'accessible' => $connectivityTest['accessible'],
                'response_code' => $connectivityTest['response_code'],
                'response_time' => $connectivityTest['response_time'],
                'ssl_valid' => $connectivityTest['ssl_valid']
            ];
            
            if ($connectivityTest['error']) {
                $response['data']['connectivity']['error'] = $connectivityTest['error'];
            }
        } else {
            $response['data']['connectivity'] = [
                'error' => 'No webhook URL configured'
            ];
        }
    }
    
    // Health summary
    $response['data']['health'] = [
        'overall_status' => 'healthy',
        'issues' => []
    ];
    
    // Determine overall health
    $healthIssues = [];
    
    if (isset($response['data']['webhook']) && !$response['data']['webhook']['valid']) {
        $healthIssues[] = 'webhook_invalid';
        $response['data']['health']['overall_status'] = 'warning';
    }
    
    if (isset($response['data']['configuration']) && !$response['data']['configuration']['consistent']) {
        $healthIssues[] = 'config_inconsistent';
        $response['data']['health']['overall_status'] = 'warning';
    }
    
    if (isset($response['data']['efi_connectivity']) && $response['data']['efi_connectivity']['status'] !== 'success') {
        $healthIssues[] = 'efi_connectivity';
        $response['data']['health']['overall_status'] = 'critical';
    }
    
    if (isset($response['data']['webhook_registration']) && !$response['data']['webhook_registration']['registered']) {
        $healthIssues[] = 'webhook_not_registered';
        if ($response['data']['health']['overall_status'] === 'healthy') {
            $response['data']['health']['overall_status'] = 'warning';
        }
    }
    
    if (isset($response['data']['connectivity']) && !$response['data']['connectivity']['accessible']) {
        $healthIssues[] = 'webhook_not_accessible';
        $response['data']['health']['overall_status'] = 'critical';
    }
    
    $response['data']['health']['issues'] = $healthIssues;
    
    // Log successful access
    $logger->info('Webhook status API response generated', [
        'auth_method' => $authMethod,
        'checks' => $checks,
        'overall_status' => $response['data']['health']['overall_status'],
        'issue_count' => count($healthIssues)
    ]);
    
    // Set appropriate HTTP status code
    $httpStatus = 200;
    if ($response['data']['health']['overall_status'] === 'critical') {
        $httpStatus = 503; // Service Unavailable
    } elseif ($response['data']['health']['overall_status'] === 'warning') {
        $httpStatus = 200; // OK but with warnings
    }
    
    http_response_code($httpStatus);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $logger->error('Erro na API de status do webhook', [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}

// Handle POST requests for actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authenticated) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';
    
    // Check specific permissions for POST actions
    $actionPermissions = [
        'register_webhook' => 'webhook_register',
        'test_connectivity' => 'webhook_status'
    ];
    
    $requiredPermission = $actionPermissions[$action] ?? 'system_admin';
    
    // Validate permission if using API key authentication
    if ($authMethod === 'api_key' && $apiKeyValidation) {
        $permissions = $apiKeyValidation['permissions'];
        if (!in_array($requiredPermission, $permissions) && !in_array('*', $permissions)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Insufficient permissions for action: ' . $action,
                'required_permission' => $requiredPermission,
                'available_permissions' => $permissions,
                'timestamp' => date('c')
            ]);
            exit;
        }
    }
    
    try {
        require_once __DIR__ . '/../includes/EfiPixManager.php';
        $efiManager = new EfiPixManager(false);
        
        switch ($action) {
            case 'register_webhook':
                $webhookUrl = $input['webhook_url'] ?? $_POST['webhook_url'] ?? null;
                $result = $efiManager->forceWebhookReRegistration($webhookUrl);
                
                echo json_encode([
                    'status' => 'success',
                    'action' => 'register_webhook',
                    'result' => $result,
                    'timestamp' => date('c')
                ]);
                break;
                
            case 'test_connectivity':
                $webhookUrl = $input['webhook_url'] ?? $_POST['webhook_url'] ?? '';
                if (empty($webhookUrl)) {
                    throw new Exception('Webhook URL is required for connectivity test');
                }
                
                $result = $validator->testWebhookConnectivity($webhookUrl);
                
                echo json_encode([
                    'status' => 'success',
                    'action' => 'test_connectivity',
                    'result' => $result,
                    'timestamp' => date('c')
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unknown action: ' . $action,
                    'available_actions' => ['register_webhook', 'test_connectivity'],
                    'timestamp' => date('c')
                ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'action' => $action,
            'message' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
    }
}
?>