<?php
/**
 * CLI Webhook Monitor Script
 * 
 * This script monitors webhook configuration and registration status,
 * provides automated monitoring for cron jobs, and can send alerts
 * when webhook issues are detected.
 */

// Ensure script is run via CLI only
if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado apenas via CLI');
}

// Include necessary files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/classes/Logger.php';
require_once __DIR__ . '/../includes/classes/WebhookValidator.php';

// Parse command line arguments
$options = getopt('h', [
    'help',
    'check::',
    'fix',
    'alert::',
    'verbose',
    'format::',
    'output::'
]);

// Help function
function showHelp() {
    echo "Webhook Monitor Script\n";
    echo "=====================\n\n";
    echo "Uso: php webhook_monitor.php [opções]\n\n";
    echo "Opções:\n";
    echo "  -h, --help              Mostra esta ajuda\n";
    echo "  --check[=TIPO]          Executa verificações (all, basic, config, efi)\n";
    echo "  --fix                   Tenta corrigir problemas automaticamente\n";
    echo "  --alert[=EMAIL]         Envia alertas por email se problemas forem encontrados\n";
    echo "  --verbose               Saída detalhada\n";
    echo "  --format[=FORMATO]      Formato de saída (text, json)\n";
    echo "  --output[=ARQUIVO]      Salva resultado em arquivo\n\n";
    echo "Exemplos:\n";
    echo "  php webhook_monitor.php --check=all --verbose\n";
    echo "  php webhook_monitor.php --check=basic --fix\n";
    echo "  php webhook_monitor.php --check=efi --format=json\n";
    echo "  php webhook_monitor.php --alert=admin@example.com\n\n";
}

// Show help if requested
if (isset($options['h']) || isset($options['help'])) {
    showHelp();
    exit(0);
}

// Configuration
$checkType = $options['check'] ?? 'basic';
$shouldFix = isset($options['fix']);
$alertEmail = $options['alert'] ?? null;
$verbose = isset($options['verbose']);
$format = $options['format'] ?? 'text';
$outputFile = $options['output'] ?? null;

// Initialize logger and validator
$logger = Logger::getInstance();
$validator = new WebhookValidator();

$logger->info('Webhook monitor script iniciado', [
    'check_type' => $checkType,
    'should_fix' => $shouldFix,
    'alert_email' => $alertEmail,
    'verbose' => $verbose,
    'format' => $format
]);

// Results array
$results = [
    'timestamp' => date('c'),
    'status' => 'success',
    'checks' => [],
    'issues' => [],
    'fixes_applied' => [],
    'recommendations' => []
];

try {
    if ($verbose) {
        echo "=== Webhook Monitor ===\n";
        echo "Horário: " . date('Y-m-d H:i:s') . "\n";
        echo "Tipo de verificação: $checkType\n";
        echo "Aplicar correções: " . ($shouldFix ? 'Sim' : 'Não') . "\n\n";
    }
    
    // Get current configuration
    $dbConfig = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
    $pixConfig = $dbConfig ? json_decode($dbConfig['valor'], true) : [];
    $webhookUrl = $pixConfig['webhook_url'] ?? (defined('WEBHOOK_URL') ? WEBHOOK_URL : '');
    
    // Basic checks
    if ($checkType === 'basic' || $checkType === 'all') {
        if ($verbose) echo "Executando verificações básicas...\n";
        
        // Webhook URL validation
        $webhookValidation = $validator->validateWebhookUrl($webhookUrl);
        $results['checks']['webhook_validation'] = $webhookValidation;
        
        if (!$webhookValidation['valid']) {
            $results['issues'][] = [
                'type' => 'webhook_invalid',
                'severity' => 'error',
                'message' => 'Webhook URL inválida',
                'details' => $webhookValidation['errors']
            ];
            
            if ($verbose) {
                echo "  ❌ Webhook URL inválida:\n";
                foreach ($webhookValidation['errors'] as $error) {
                    echo "     - $error\n";
                }
            }
            
            // Try to fix webhook URL
            if ($shouldFix) {
                if ($verbose) echo "  🔧 Tentando corrigir webhook URL...\n";
                
                // Check if we can generate a valid URL
                if (defined('APP_URL') && !empty(APP_URL)) {
                    $newWebhookUrl = rtrim(APP_URL, '/') . '/api/webhook_pix.php';
                    $newValidation = $validator->validateWebhookUrl($newWebhookUrl);
                    
                    if ($newValidation['valid']) {
                        // Update database configuration
                        $pixConfig['webhook_url'] = $newWebhookUrl;
                        global $pdo;
                        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
                        if ($stmt->execute([json_encode($pixConfig)])) {
                            $results['fixes_applied'][] = [
                                'type' => 'webhook_url_fixed',
                                'message' => "Webhook URL atualizada para: $newWebhookUrl"
                            ];
                            
                            if ($verbose) echo "  ✅ Webhook URL corrigida: $newWebhookUrl\n";
                            $webhookUrl = $newWebhookUrl; // Update for subsequent checks
                        }
                    }
                }
            }
        } else {
            if ($verbose) echo "  ✅ Webhook URL válida\n";
        }
        
        // SSL validation for HTTPS URLs
        if (!empty($webhookUrl) && strpos($webhookUrl, 'https://') === 0) {
            $sslValidation = $validator->validateSslCertificate($webhookUrl);
            $results['checks']['ssl_validation'] = $sslValidation;
            
            if (!$sslValidation['valid']) {
                $results['issues'][] = [
                    'type' => 'ssl_invalid',
                    'severity' => 'warning',
                    'message' => 'Certificado SSL inválido',
                    'details' => $sslValidation['errors']
                ];
                
                if ($verbose) {
                    echo "  ⚠️  Certificado SSL com problemas:\n";
                    foreach ($sslValidation['errors'] as $error) {
                        echo "     - $error\n";
                    }
                }
            } else {
                if ($verbose) echo "  ✅ Certificado SSL válido\n";
            }
        }
    }
    
    // Configuration checks
    if ($checkType === 'config' || $checkType === 'all') {
        if ($verbose) echo "\nExecutando verificações de configuração...\n";
        
        $consistency = $validator->validateEfiConfigConsistency();
        $results['checks']['config_consistency'] = $consistency;
        
        if (!$consistency['consistent']) {
            $results['issues'][] = [
                'type' => 'config_inconsistent',
                'severity' => 'warning',
                'message' => 'Configuração inconsistente',
                'details' => $consistency['issues']
            ];
            
            if ($verbose) {
                echo "  ⚠️  Configuração inconsistente:\n";
                foreach ($consistency['issues'] as $issue) {
                    echo "     - $issue\n";
                }
            }
        } else {
            if ($verbose) echo "  ✅ Configuração consistente\n";
        }
    }
    
    // EFI Pay checks
    if ($checkType === 'efi' || $checkType === 'all') {
        if ($verbose) echo "\nExecutando verificações EFI Pay...\n";
        
        try {
            require_once __DIR__ . '/../includes/EfiPixManager.php';
            $efiManager = new EfiPixManager(false);
            
            // Test connectivity
            $connectivity = $efiManager->testConnectivity();
            $results['checks']['efi_connectivity'] = $connectivity;
            
            if ($connectivity['status'] !== 'success') {
                $results['issues'][] = [
                    'type' => 'efi_connectivity',
                    'severity' => 'error',
                    'message' => 'Falha na conectividade EFI Pay',
                    'details' => [$connectivity['message']]
                ];
                
                if ($verbose) echo "  ❌ Conectividade EFI Pay falhou: " . $connectivity['message'] . "\n";
            } else {
                if ($verbose) echo "  ✅ Conectividade EFI Pay OK\n";
                
                // Check webhook registration
                $registration = $efiManager->getWebhookRegistrationStatus();
                $results['checks']['webhook_registration'] = $registration;
                
                if (!$registration['registered']) {
                    $results['issues'][] = [
                        'type' => 'webhook_not_registered',
                        'severity' => 'warning',
                        'message' => 'Webhook não está registrado na EFI Pay',
                        'details' => isset($registration['error']) ? [$registration['error']] : []
                    ];
                    
                    if ($verbose) echo "  ⚠️  Webhook não registrado na EFI Pay\n";
                    
                    // Try to register webhook if fix is enabled
                    if ($shouldFix && !empty($webhookUrl)) {
                        if ($verbose) echo "  🔧 Tentando registrar webhook...\n";
                        
                        $registerResult = $efiManager->forceWebhookReRegistration();
                        if ($registerResult['status'] === 'success') {
                            $results['fixes_applied'][] = [
                                'type' => 'webhook_registered',
                                'message' => 'Webhook registrado com sucesso na EFI Pay'
                            ];
                            
                            if ($verbose) echo "  ✅ Webhook registrado com sucesso\n";
                        } else {
                            if ($verbose) echo "  ❌ Falha ao registrar webhook: " . $registerResult['message'] . "\n";
                        }
                    }
                } else {
                    if ($verbose) echo "  ✅ Webhook registrado na EFI Pay\n";
                }
            }
            
        } catch (Exception $e) {
            $results['issues'][] = [
                'type' => 'efi_error',
                'severity' => 'error',
                'message' => 'Erro ao verificar EFI Pay',
                'details' => [$e->getMessage()]
            ];
            
            if ($verbose) echo "  ❌ Erro EFI Pay: " . $e->getMessage() . "\n";
        }
    }
    
    // Connectivity test
    if (!empty($webhookUrl)) {
        if ($verbose) echo "\nTestando conectividade da webhook URL...\n";
        
        $connectivityTest = $validator->testWebhookConnectivity($webhookUrl);
        $results['checks']['webhook_connectivity'] = $connectivityTest;
        
        if (!$connectivityTest['accessible']) {
            $results['issues'][] = [
                'type' => 'webhook_not_accessible',
                'severity' => 'error',
                'message' => 'Webhook URL não está acessível',
                'details' => [$connectivityTest['error'] ?? 'Erro desconhecido']
            ];
            
            if ($verbose) echo "  ❌ Webhook não acessível: " . ($connectivityTest['error'] ?? 'Erro desconhecido') . "\n";
        } else {
            if ($verbose) echo "  ✅ Webhook acessível (tempo: {$connectivityTest['response_time']}ms)\n";
        }
    }
    
    // Generate recommendations
    if (!empty($results['issues'])) {
        foreach ($results['issues'] as $issue) {
            switch ($issue['type']) {
                case 'webhook_invalid':
                    $results['recommendations'][] = 'Execute o script de correção: php scripts/fix_webhook_config.php';
                    break;
                case 'webhook_not_registered':
                    $results['recommendations'][] = 'Registre o webhook manualmente ou use --fix';
                    break;
                case 'config_inconsistent':
                    $results['recommendations'][] = 'Verifique as configurações no painel admin';
                    break;
                case 'efi_connectivity':
                    $results['recommendations'][] = 'Verifique certificado e configurações EFI Pay';
                    break;
                case 'webhook_not_accessible':
                    $results['recommendations'][] = 'Verifique se a URL está acessível publicamente';
                    break;
            }
        }
        $results['recommendations'] = array_unique($results['recommendations']);
    }
    
    // Determine overall status
    $errorCount = count(array_filter($results['issues'], fn($issue) => $issue['severity'] === 'error'));
    $warningCount = count(array_filter($results['issues'], fn($issue) => $issue['severity'] === 'warning'));
    
    if ($errorCount > 0) {
        $results['status'] = 'error';
    } elseif ($warningCount > 0) {
        $results['status'] = 'warning';
    } else {
        $results['status'] = 'ok';
    }
    
    // Summary
    if ($verbose) {
        echo "\n=== Resumo ===\n";
        echo "Status geral: " . strtoupper($results['status']) . "\n";
        echo "Problemas encontrados: " . count($results['issues']) . "\n";
        echo "Correções aplicadas: " . count($results['fixes_applied']) . "\n";
        
        if (!empty($results['recommendations'])) {
            echo "\nRecomendações:\n";
            foreach ($results['recommendations'] as $rec) {
                echo "- $rec\n";
            }
        }
        echo "\n";
    }
    
    // Send alert email if issues found and email provided
    if (!empty($results['issues']) && $alertEmail) {
        $subject = "Webhook Monitor Alert - " . strtoupper($results['status']);
        $message = "Problemas detectados no webhook:\n\n";
        
        foreach ($results['issues'] as $issue) {
            $message .= "- [{$issue['severity']}] {$issue['message']}\n";
            foreach ($issue['details'] as $detail) {
                $message .= "  * $detail\n";
            }
            $message .= "\n";
        }
        
        if (!empty($results['recommendations'])) {
            $message .= "Recomendações:\n";
            foreach ($results['recommendations'] as $rec) {
                $message .= "- $rec\n";
            }
        }
        
        $message .= "\nTimestamp: " . $results['timestamp'] . "\n";
        
        // Simple mail sending (could be enhanced with proper email library)
        $headers = "From: Webhook Monitor <no-reply@" . parse_url(APP_URL, PHP_URL_HOST) . ">";
        if (@mail($alertEmail, $subject, $message, $headers)) {
            if ($verbose) echo "Alert email enviado para: $alertEmail\n";
            $logger->info('Alert email enviado', ['email' => $alertEmail, 'issue_count' => count($results['issues'])]);
        } else {
            if ($verbose) echo "Falha ao enviar alert email\n";
            $logger->error('Falha ao enviar alert email', ['email' => $alertEmail]);
        }
    }
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['error'] = $e->getMessage();
    
    $logger->error('Erro no webhook monitor script', [
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
    
    if ($verbose) {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}

// Output results
$output = '';
if ($format === 'json') {
    $output = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Text format
    if (!$verbose) {
        $output = "Status: " . strtoupper($results['status']) . "\n";
        $output .= "Issues: " . count($results['issues']) . "\n";
        $output .= "Fixes: " . count($results['fixes_applied']) . "\n";
        if (!empty($results['recommendations'])) {
            $output .= "Recommendations: " . implode('; ', $results['recommendations']) . "\n";
        }
    }
}

// Save to file if specified
if ($outputFile && !empty($output)) {
    file_put_contents($outputFile, $output);
    if ($verbose) echo "Resultado salvo em: $outputFile\n";
}

// Output to console if not verbose (verbose already printed)
if (!$verbose && !empty($output)) {
    echo $output;
}

// Exit with appropriate code
$exitCode = 0;
if ($results['status'] === 'error') {
    $exitCode = 2;
} elseif ($results['status'] === 'warning') {
    $exitCode = 1;
}

exit($exitCode);