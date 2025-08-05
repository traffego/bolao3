<?php
/**
 * Admin Webhook Diagnostics - Bolão Vitimba
 * 
 * Comprehensive webhook diagnostics page for administrators to monitor,
 * test, and troubleshoot webhook configurations and connectivity.
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/classes/WebhookValidator.php';

// Enhanced admin authentication with proper error handling
$authResult = checkAdminAuthentication();
if (!$authResult['authenticated']) {
    setFlashMessage('danger', $authResult['message']);
    redirect(APP_URL . '/admin/login.php');
}

/**
 * Enhanced admin authentication function with error handling
 * 
 * @return array Authentication result with status and message
 */
function checkAdminAuthentication() {
    $result = [
        'authenticated' => false,
        'message' => 'Erro desconhecido de autenticação.',
        'method' => 'unknown'
    ];
    
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Method 1: Check if isAdmin function exists and use it
        if (function_exists('isAdmin')) {
            try {
                if (isAdmin()) {
                    $result['authenticated'] = true;
                    $result['message'] = 'Autenticado via função isAdmin()';
                    $result['method'] = 'isAdmin_function';
                    return $result;
                }
            } catch (Exception $e) {
                error_log("Error calling isAdmin(): " . $e->getMessage());
                // Continue to fallback methods
            }
        }
        
        // Method 2: Direct session check (fallback)
        if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
            try {
                // Validate admin exists and is active in database
                $adminId = $_SESSION['admin_id'];
                $sql = "SELECT id, nome, status FROM administradores WHERE id = ? AND status = 'ativo'";
                $admin = dbFetchOne($sql, [$adminId]);
                
                if ($admin) {
                    $result['authenticated'] = true;
                    $result['message'] = 'Autenticado via sessão (admin ID: ' . $adminId . ')';
                    $result['method'] = 'session_with_db_validation';
                    return $result;
                } else {
                    // Admin not found or inactive - destroy session
                    session_destroy();
                    $result['message'] = 'Sessão expirou ou conta foi desativada. Faça login novamente.';
                    $result['method'] = 'session_invalid_admin';
                    return $result;
                }
            } catch (Exception $e) {
                error_log("Database error during admin validation: " . $e->getMessage());
                $result['message'] = 'Erro ao validar credenciais no banco de dados. Tente novamente.';
                $result['method'] = 'database_error';
                return $result;
            }
        }
        
        // Method 3: Check for any admin-related session indicators
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
            $result['authenticated'] = true;
            $result['message'] = 'Autenticado via tipo de usuário na sessão';
            $result['method'] = 'session_user_type';
            return $result;
        }
        
        // No valid authentication found
        $result['message'] = 'Acesso negado. Faça login como administrador.';
        $result['method'] = 'no_authentication';
        return $result;
        
    } catch (Exception $e) {
        error_log("Critical error in checkAdminAuthentication(): " . $e->getMessage());
        $result['message'] = 'Erro crítico de autenticação. Contate o suporte técnico.';
        $result['method'] = 'critical_error';
        return $result;
    }
}

$pageTitle = 'Diagnóstico de Webhook';
$currentPage = 'webhook-diagnostics';

// Initialize classes with error handling
$diagnosticResults = [];
$actionResult = null;
$webhookValidator = null;

try {
    $webhookValidator = new WebhookValidator();
} catch (Exception $e) {
    error_log("Failed to initialize WebhookValidator: " . $e->getMessage());
    $diagnosticResults['webhook_validator_error'] = [
        'error' => 'Falha ao inicializar o validador de webhook: ' . $e->getMessage(),
        'line' => $e->getLine()
    ];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-validate authentication for POST actions for additional security
    $postAuthResult = checkAdminAuthentication();
    if (!$postAuthResult['authenticated']) {
        $actionResult = [
            'type' => 'auth_error',
            'result' => [
                'status' => 'error',
                'message' => 'Falha na autenticação durante processamento da ação: ' . $postAuthResult['message']
            ]
        ];
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            require_once '../includes/EfiPixManager.php';
            $efiManager = new EfiPixManager(false); // Non-fatal mode for diagnostics
        
        switch ($action) {
            case 'test_webhook_connectivity':
                $webhookUrl = $_POST['webhook_url'] ?? '';
                if (!empty($webhookUrl)) {
                    if ($webhookValidator !== null) {
                        $actionResult = [
                            'type' => 'test_connectivity',
                            'result' => $webhookValidator->testWebhookConnectivity($webhookUrl)
                        ];
                    } else {
                        $actionResult = [
                            'type' => 'test_connectivity',
                            'result' => [
                                'status' => 'error',
                                'message' => 'Validador de webhook não está disponível'
                            ]
                        ];
                    }
                }
                break;
                
            case 'register_webhook':
                $webhookUrl = $_POST['webhook_url'] ?? null;
                $result = $efiManager->forceWebhookReRegistration($webhookUrl);
                $actionResult = [
                    'type' => 'register_webhook',
                    'result' => $result
                ];
                break;
                
            case 'check_webhook_status':
                $result = $efiManager->getWebhookRegistrationStatus();
                $actionResult = [
                    'type' => 'webhook_status',
                    'result' => $result
                ];
                break;
                
            case 'test_efi_connectivity':
                $result = $efiManager->testConnectivity();
                $actionResult = [
                    'type' => 'efi_connectivity',
                    'result' => $result
                ];
                break;
            }
        } catch (Exception $e) {
            $actionResult = [
                'type' => 'error',
                'result' => [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]
            ];
        }
    } // End of authentication check for POST actions
}

// Run comprehensive diagnostics
try {
    // Get current configuration
    $dbConfig = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
    $pixConfig = $dbConfig ? json_decode($dbConfig['valor'], true) : [];
    
    // Webhook URL validation (only if validator was successfully initialized)
    $webhookUrl = $pixConfig['webhook_url'] ?? (defined('WEBHOOK_URL') ? WEBHOOK_URL : '');
    if ($webhookValidator !== null) {
        try {
            $diagnosticResults['webhook_validation'] = $webhookValidator->validateWebhookUrl($webhookUrl);
        } catch (Exception $e) {
            error_log("Error validating webhook URL: " . $e->getMessage());
            $diagnosticResults['webhook_validation_error'] = [
                'error' => 'Erro ao validar URL do webhook: ' . $e->getMessage(),
                'line' => $e->getLine()
            ];
        }
        
        // Configuration consistency check
        try {
            $diagnosticResults['config_consistency'] = $webhookValidator->validateEfiConfigConsistency();
        } catch (Exception $e) {
            error_log("Error checking config consistency: " . $e->getMessage());
            $diagnosticResults['config_consistency_error'] = [
                'error' => 'Erro ao verificar consistência da configuração: ' . $e->getMessage(),
                'line' => $e->getLine()
            ];
        }
        
        // SSL validation if HTTPS
        if (!empty($webhookUrl) && strpos($webhookUrl, 'https://') === 0) {
            try {
                $diagnosticResults['ssl_validation'] = $webhookValidator->validateSslCertificate($webhookUrl);
            } catch (Exception $e) {
                error_log("Error validating SSL certificate: " . $e->getMessage());
                $diagnosticResults['ssl_validation_error'] = [
                    'error' => 'Erro ao validar certificado SSL: ' . $e->getMessage(),
                    'line' => $e->getLine()
                ];
            }
        }
    } else {
        $diagnosticResults['webhook_validation'] = [
            'valid' => false,
            'errors' => ['Validador de webhook não pôde ser inicializado'],
            'warnings' => [],
            'webhook_url' => $webhookUrl
        ];
    }
    
    // EFI Manager status (if possible to initialize)
    try {
        require_once '../includes/EfiPixManager.php';
        $efiManager = new EfiPixManager(false);
        $diagnosticResults['efi_connectivity'] = $efiManager->testConnectivity();
        $diagnosticResults['webhook_registration'] = $efiManager->getWebhookRegistrationStatus();
    } catch (Exception $e) {
        $diagnosticResults['efi_manager_error'] = [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ];
    }
    
} catch (Exception $e) {
    $diagnosticResults['general_error'] = [
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ];
}

include '../templates/admin/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?php echo $pageTitle; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active"><?php echo $pageTitle; ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php if ($actionResult): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert <?php echo $actionResult['result']['status'] === 'success' ? 'alert-success' : 'alert-warning'; ?>">
                            <h5><i class="icon fas fa-info"></i> Resultado da Ação</h5>
                            <strong>Tipo:</strong> <?php echo ucfirst($actionResult['type']); ?><br>
                            <strong>Status:</strong> <?php echo $actionResult['result']['status']; ?><br>
                            <strong>Mensagem:</strong> <?php echo htmlspecialchars($actionResult['result']['message'] ?? 'Concluído'); ?>
                            
                            <?php if (isset($actionResult['result']['details'])): ?>
                                <hr>
                                <strong>Detalhes:</strong>
                                <pre><?php echo json_encode($actionResult['result']['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Authentication Status -->
            <?php if (isset($authResult)): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-user-shield"></i> Status de Autenticação</h5>
                            <strong>Método:</strong> <?php echo htmlspecialchars($authResult['method']); ?><br>
                            <strong>Status:</strong> <?php echo $authResult['authenticated'] ? 'Autenticado' : 'Não autenticado'; ?><br>
                            <strong>Detalhes:</strong> <?php echo htmlspecialchars($authResult['message']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configuration Overview -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Configuração Atual</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>APP_URL:</strong></td>
                                    <td><?php echo defined('APP_URL') ? APP_URL : '<em>Não definido</em>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>WEBHOOK_URL (Constante):</strong></td>
                                    <td><?php echo defined('WEBHOOK_URL') ? WEBHOOK_URL : '<em>Não definido</em>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Webhook URL (Banco):</strong></td>
                                    <td><?php echo htmlspecialchars($pixConfig['webhook_url'] ?? '<em>Não definido</em>'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>EFI API URL:</strong></td>
                                    <td><?php echo defined('EFI_API_URL') ? EFI_API_URL : '<em>Não definido</em>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Ambiente:</strong></td>
                                    <td><?php echo $pixConfig['ambiente'] ?? '<em>Não definido</em>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>PIX Key:</strong></td>
                                    <td><?php echo !empty($pixConfig['pix_key']) ? 'Definida' : '<em>Não definida</em>'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Status Geral</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $overallStatus = 'success';
                            $statusChecks = [];
                            
                            // Check webhook validation
                            if (isset($diagnosticResults['webhook_validation'])) {
                                $webhookValid = $diagnosticResults['webhook_validation']['valid'];
                                $statusChecks['Webhook URL'] = $webhookValid;
                                if (!$webhookValid) $overallStatus = 'warning';
                            }
                            
                            // Check config consistency
                            if (isset($diagnosticResults['config_consistency'])) {
                                $configConsistent = $diagnosticResults['config_consistency']['consistent'];
                                $statusChecks['Configuração Consistente'] = $configConsistent;
                                if (!$configConsistent) $overallStatus = 'warning';
                            }
                            
                            // Check EFI connectivity
                            if (isset($diagnosticResults['efi_connectivity'])) {
                                $efiConnected = $diagnosticResults['efi_connectivity']['status'] === 'success';
                                $statusChecks['Conectividade EFI'] = $efiConnected;
                                if (!$efiConnected) $overallStatus = 'danger';
                            }
                            
                            // Check webhook registration
                            if (isset($diagnosticResults['webhook_registration'])) {
                                $webhookRegistered = $diagnosticResults['webhook_registration']['registered'];
                                $statusChecks['Webhook Registrado'] = $webhookRegistered;
                                if (!$webhookRegistered) $overallStatus = 'warning';
                            }
                            ?>
                            
                            <div class="alert alert-<?php echo $overallStatus; ?>">
                                <h5><i class="icon fas fa-<?php echo $overallStatus === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i> 
                                Status: <?php echo $overallStatus === 'success' ? 'OK' : 'Problemas Detectados'; ?></h5>
                            </div>
                            
                            <?php foreach ($statusChecks as $check => $status): ?>
                                <div class="form-group">
                                    <i class="fas fa-<?php echo $status ? 'check text-success' : 'times text-danger'; ?>"></i>
                                    <?php echo $check; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Validation Results -->
            <?php if (isset($diagnosticResults['webhook_validation'])): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Validação da Webhook URL</h3>
                            </div>
                            <div class="card-body">
                                <?php $validation = $diagnosticResults['webhook_validation']; ?>
                                
                                <div class="alert alert-<?php echo $validation['valid'] ? 'success' : 'danger'; ?>">
                                    <strong>URL:</strong> <?php echo htmlspecialchars($validation['webhook_url'] ?? 'N/A'); ?><br>
                                    <strong>Status:</strong> <?php echo $validation['valid'] ? 'Válida' : 'Inválida'; ?>
                                </div>
                                
                                <?php if (!empty($validation['errors'])): ?>
                                    <h5>Erros:</h5>
                                    <ul class="list-unstyled">
                                        <?php foreach ($validation['errors'] as $error): ?>
                                            <li><i class="fas fa-times text-danger"></i> <?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <?php if (!empty($validation['warnings'])): ?>
                                    <h5>Avisos:</h5>
                                    <ul class="list-unstyled">
                                        <?php foreach ($validation['warnings'] as $warning): ?>
                                            <li><i class="fas fa-exclamation-triangle text-warning"></i> <?php echo htmlspecialchars($warning); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configuration Consistency -->
            <?php if (isset($diagnosticResults['config_consistency'])): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Consistência da Configuração</h3>
                            </div>
                            <div class="card-body">
                                <?php $consistency = $diagnosticResults['config_consistency']; ?>
                                
                                <div class="alert alert-<?php echo $consistency['consistent'] ? 'success' : 'warning'; ?>">
                                    <strong>Status:</strong> <?php echo $consistency['consistent'] ? 'Consistente' : 'Inconsistente'; ?>
                                </div>
                                
                                <?php if (!empty($consistency['issues'])): ?>
                                    <h5>Problemas Detectados:</h5>
                                    <ul>
                                        <?php foreach ($consistency['issues'] as $issue): ?>
                                            <li><?php echo htmlspecialchars($issue); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <?php if (!empty($consistency['config_sources'])): ?>
                                    <button class="btn btn-info btn-sm" type="button" data-toggle="collapse" data-target="#configSources">
                                        Ver Fontes de Configuração
                                    </button>
                                    <div class="collapse mt-2" id="configSources">
                                        <pre><?php echo json_encode($consistency['config_sources'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Ações de Diagnóstico</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="check_webhook_status">
                                <button type="submit" class="btn btn-info mr-2">
                                    <i class="fas fa-search"></i> Verificar Status do Webhook
                                </button>
                            </form>
                            
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="test_efi_connectivity">
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="fas fa-plug"></i> Testar Conectividade EFI
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-warning mr-2" data-toggle="modal" data-target="#registerWebhookModal">
                                <i class="fas fa-sync"></i> Re-registrar Webhook
                            </button>
                            
                            <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#testConnectivityModal">
                                <i class="fas fa-wifi"></i> Testar Conectividade URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Logs -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Logs Recentes</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $logFile = ROOT_DIR . '/logs/app.log';
                            if (file_exists($logFile)) {
                                $logLines = array_slice(file($logFile), -20, 20);
                                echo '<pre style="height: 300px; overflow-y: scroll; font-size: 0.8em;">';
                                foreach (array_reverse($logLines) as $line) {
                                    if (strpos($line, 'webhook') !== false || strpos($line, 'EFI') !== false) {
                                        echo '<span class="text-info">' . htmlspecialchars($line) . '</span>';
                                    } else {
                                        echo htmlspecialchars($line);
                                    }
                                }
                                echo '</pre>';
                            } else {
                                echo '<p class="text-muted">Arquivo de log não encontrado.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Register Webhook Modal -->
<div class="modal fade" id="registerWebhookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Re-registrar Webhook</h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="register_webhook">
                    <div class="form-group">
                        <label for="webhook_url">Webhook URL (deixe vazio para usar a configuração atual):</label>
                        <input type="url" class="form-control" id="webhook_url" name="webhook_url" 
                               value="<?php echo htmlspecialchars($webhookUrl); ?>">
                        <small class="form-text text-muted">
                            URL deve usar HTTPS e estar acessível publicamente
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Re-registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Test Connectivity Modal -->
<div class="modal fade" id="testConnectivityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Testar Conectividade URL</h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="test_webhook_connectivity">
                    <div class="form-group">
                        <label for="test_webhook_url">URL para testar:</label>
                        <input type="url" class="form-control" id="test_webhook_url" name="webhook_url" 
                               value="<?php echo htmlspecialchars($webhookUrl); ?>" required>
                        <small class="form-text text-muted">
                            Esta URL será testada para conectividade e tempo de resposta
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-secondary">Testar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-refresh functionality
    let autoRefresh = false;
    
    $('.btn-auto-refresh').on('click', function() {
        autoRefresh = !autoRefresh;
        if (autoRefresh) {
            $(this).addClass('btn-success').removeClass('btn-secondary');
            $(this).html('<i class="fas fa-sync fa-spin"></i> Auto-refresh ON');
            setTimeout(function() {
                if (autoRefresh) {
                    location.reload();
                }
            }, 30000); // Refresh every 30 seconds
        } else {
            $(this).addClass('btn-secondary').removeClass('btn-success');
            $(this).html('<i class="fas fa-sync"></i> Auto-refresh OFF');
        }
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?>