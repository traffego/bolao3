<?php
/**
 * Migration Script - Fix Webhook Configuration
 * 
 * This script fixes the webhook URL configuration in the database by:
 * - Updating the efi_pix_config entry to use the correct production URL from APP_URL
 * - Validating that all EFI Pay configuration parameters are properly set
 * - Logging changes made for audit purposes
 * - Providing rollback capability if needed
 * - Checking for localhost references in production
 */

// Include necessary configuration files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/classes/Logger.php';

// Ensure script is run via CLI only
if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado apenas via CLI');
}

echo "=== Fix Webhook Configuration Script ===\n";
echo "Executando em: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Initialize logger
    $logger = Logger::getInstance();
    $logger->info('Iniciando script de correção de configuração de webhook');
    
    // Initialize database connection
    global $pdo;
    if (!$pdo) {
        throw new Exception('Erro ao conectar com o banco de dados');
    }
    
    echo "1. Lendo configuração atual do banco de dados...\n";
    
    // Read current EFI Pix configuration
    $stmt = $pdo->prepare("SELECT * FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
    $stmt->execute();
    $configRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$configRow) {
        echo "   ERRO: Configuração EFI Pix não encontrada no banco de dados!\n";
        $logger->error('Configuração EFI Pix não encontrada no banco de dados');
        exit(1);
    }
    
    $currentConfig = json_decode($configRow['valor'], true);
    if (!$currentConfig) {
        echo "   ERRO: Configuração EFI Pix inválida no banco de dados!\n";
        $logger->error('Configuração EFI Pix inválida', ['valor' => $configRow['valor']]);
        exit(1);
    }
    
    echo "   Configuração atual encontrada:\n";
    echo "   - Client ID: " . (isset($currentConfig['client_id']) ? 'Definido' : 'NÃO DEFINIDO') . "\n";
    echo "   - Client Secret: " . (isset($currentConfig['client_secret']) ? 'Definido' : 'NÃO DEFINIDO') . "\n";
    echo "   - Pix Key: " . (isset($currentConfig['pix_key']) ? 'Definido' : 'NÃO DEFINIDO') . "\n";
    echo "   - Ambiente: " . ($currentConfig['ambiente'] ?? 'NÃO DEFINIDO') . "\n";
    echo "   - Webhook URL Atual: " . ($currentConfig['webhook_url'] ?? 'NÃO DEFINIDO') . "\n";
    
    // Validate current configuration
    $hasProblems = false;
    $problems = [];
    
    echo "\n2. Validando configuração atual...\n";
    
    // Check if webhook_url exists and is not localhost
    $currentWebhookUrl = $currentConfig['webhook_url'] ?? '';
    if (empty($currentWebhookUrl)) {
        $problems[] = 'webhook_url não está definida';
        $hasProblems = true;
        echo "   PROBLEMA: webhook_url não está definida\n";
    } elseif (strpos($currentWebhookUrl, 'localhost') !== false || strpos($currentWebhookUrl, '127.0.0.1') !== false) {
        $problems[] = 'webhook_url aponta para localhost (inválida para produção)';
        $hasProblems = true;
        echo "   PROBLEMA: webhook_url aponta para localhost: $currentWebhookUrl\n";
    } else {
        echo "   OK: Webhook URL válida\n";
    }
    
    // Check required fields
    $requiredFields = ['client_id', 'client_secret', 'pix_key', 'ambiente'];
    foreach ($requiredFields as $field) {
        if (empty($currentConfig[$field])) {
            $problems[] = "$field não está definido";
            $hasProblems = true;
            echo "   PROBLEMA: $field não está definido\n";
        } else {
            echo "   OK: $field está definido\n";
        }
    }
    
    // Check if we're in production environment
    $isProduction = strpos(APP_URL, 'localhost') === false && strpos(APP_URL, '127.0.0.1') === false;
    $correctWebhookUrl = WEBHOOK_URL;
    
    echo "\n3. Determinando correções necessárias...\n";
    echo "   Ambiente detectado: " . ($isProduction ? 'PRODUÇÃO' : 'DESENVOLVIMENTO') . "\n";
    echo "   APP_URL: " . APP_URL . "\n";
    echo "   Webhook URL correta: " . $correctWebhookUrl . "\n";
    
    // Create backup of current configuration
    $backupFile = __DIR__ . '/../logs/webhook_config_backup_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($backupFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
    echo "   Backup da configuração salvo em: $backupFile\n";
    
    // Determine if update is needed
    $needsUpdate = false;
    $newConfig = $currentConfig;
    
    if ($currentWebhookUrl !== $correctWebhookUrl) {
        $needsUpdate = true;
        $newConfig['webhook_url'] = $correctWebhookUrl;
        echo "   Webhook URL será atualizada de '$currentWebhookUrl' para '$correctWebhookUrl'\n";
    }
    
    // Add webhook_fatal_failure setting if not present
    if (!isset($newConfig['webhook_fatal_failure'])) {
        $needsUpdate = true;
        $newConfig['webhook_fatal_failure'] = false; // Default to non-fatal for stability
        echo "   Adicionando configuração webhook_fatal_failure: false\n";
    }
    
    if (!$needsUpdate) {
        echo "\n4. RESULTADO: Nenhuma atualização necessária!\n";
        echo "   A configuração já está correta.\n";
        
        $logger->info('Script executado - nenhuma correção necessária', [
            'webhook_url_atual' => $currentWebhookUrl,
            'webhook_url_correta' => $correctWebhookUrl
        ]);
        
        exit(0);
    }
    
    echo "\n4. Aplicando correções...\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update configuration in database
        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ?, data_atualizacao = NOW() WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
        $result = $stmt->execute([json_encode($newConfig)]);
        
        if (!$result) {
            throw new Exception('Falha ao atualizar configuração no banco de dados');
        }
        
        echo "   Configuração atualizada no banco de dados\n";
        
        // Log the changes
        $changes = [];
        foreach ($newConfig as $key => $value) {
            if (!isset($currentConfig[$key]) || $currentConfig[$key] !== $value) {
                $changes[$key] = [
                    'old' => $currentConfig[$key] ?? null,
                    'new' => $value
                ];
            }
        }
        
        $logger->info('Configuração de webhook corrigida', [
            'changes' => $changes,
            'backup_file' => $backupFile,
            'app_url' => APP_URL,
            'webhook_url' => $correctWebhookUrl
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo "\n5. SUCESSO: Configuração corrigida com sucesso!\n";
        echo "   Mudanças aplicadas:\n";
        foreach ($changes as $key => $change) {
            echo "   - $key: '" . ($change['old'] ?? 'NÃO DEFINIDO') . "' -> '" . $change['new'] . "'\n";
        }
        
        // Test webhook registration if EfiPixManager is available
        if (class_exists('EfiPixManager') && $isProduction) {
            echo "\n6. Testando registro de webhook...\n";
            try {
                require_once __DIR__ . '/../includes/EfiPixManager.php';
                $efiManager = new EfiPixManager(false); // Non-fatal mode for testing
                $webhookResult = $efiManager->registerWebhook();
                
                if ($webhookResult['status'] === 'success') {
                    echo "   SUCESSO: Webhook registrado com sucesso na EFI Pay!\n";
                    $logger->info('Webhook registrado com sucesso após correção');
                } else {
                    echo "   AVISO: Falha ao registrar webhook: " . $webhookResult['message'] . "\n";
                    echo "   Você pode tentar registrar manualmente mais tarde.\n";
                    $logger->warn('Falha ao registrar webhook após correção', ['error' => $webhookResult['message']]);
                }
            } catch (Exception $e) {
                echo "   AVISO: Erro ao testar webhook: " . $e->getMessage() . "\n";
                echo "   A configuração foi salva, mas o webhook precisa ser registrado manualmente.\n";
                $logger->warn('Erro ao testar webhook após correção', ['error' => $e->getMessage()]);
            }
        }
        
        echo "\n=== Script concluído com sucesso ===\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\nERRO FATAL: " . $e->getMessage() . "\n";
    echo "Para restaurar a configuração anterior, use o arquivo de backup.\n";
    
    $logger->error('Erro fatal no script de correção de webhook', [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
    
    exit(1);
}

echo "\nPara monitorar o status do webhook, use:\n";
echo "- Admin panel: /admin/webhook-diagnostics.php\n";
echo "- CLI: php scripts/webhook_monitor.php\n";
echo "\n";