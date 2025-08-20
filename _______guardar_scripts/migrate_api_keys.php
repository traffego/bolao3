<?php
/**
 * API Keys Migration Script
 * 
 * This script handles the migration from hardcoded API keys to database-stored keys.
 * It creates the necessary tables and populates them with default keys for backward compatibility.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/classes/Logger.php';
require_once __DIR__ . '/../includes/classes/ApiKeyManager.php';

$logger = Logger::getInstance();
$isCliMode = php_sapi_name() === 'cli';

if (!$isCliMode) {
    // If running via web, ensure admin authentication
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        die('Access denied. Admin authentication required.');
    }
    
    header('Content-Type: text/plain; charset=utf-8');
}

function outputMessage($message, $isError = false) {
    global $isCliMode;
    
    if ($isCliMode) {
        echo ($isError ? '[ERROR] ' : '[INFO] ') . $message . PHP_EOL;
    } else {
        echo ($isError ? 'ERROR: ' : 'INFO: ') . $message . "\n";
    }
}

function runMigration() {
    global $logger, $argv;
    
    try {
        $pdo = getPDO();
        $apiKeyManager = new ApiKeyManager();
        
        outputMessage('Starting API Keys migration...');
        
        // Step 1: Check if tables exist
        outputMessage('Checking database tables...');
        
        $tableExists = $pdo->query("SHOW TABLES LIKE 'api_keys'")->fetch();
        
        if (!$tableExists) {
            outputMessage('Creating api_keys tables...');
            
            // Read and execute the SQL file
            $sqlFile = __DIR__ . '/create_api_keys_table.sql';
            
            if (!file_exists($sqlFile)) {
                throw new Exception('SQL file not found: ' . $sqlFile);
            }
            
            $sql = file_get_contents($sqlFile);
            
            // Execute table creation directly with individual statements
            try {
                // Create api_keys table
                outputMessage('Creating api_keys table...');
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `api_keys` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `key_name` varchar(100) NOT NULL COMMENT 'Human-readable name for the API key',
                      `api_key` varchar(64) NOT NULL COMMENT 'The actual API key (hashed)',
                      `key_prefix` varchar(16) NOT NULL COMMENT 'Visible prefix for identification',
                      `permissions` JSON DEFAULT NULL COMMENT 'JSON array of allowed permissions/scopes',
                      `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether the key is active',
                      `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the key',
                      `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last time the key was used',
                      `last_used_ip` varchar(45) DEFAULT NULL COMMENT 'IP address of last usage',
                      `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Optional expiration date',
                      `usage_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times the key has been used',
                      `rate_limit` int(11) DEFAULT NULL COMMENT 'Max requests per hour (NULL = no limit)',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `unique_api_key` (`api_key`),
                      UNIQUE KEY `unique_key_name` (`key_name`),
                      KEY `idx_active_keys` (`is_active`, `expires_at`),
                      KEY `idx_key_prefix` (`key_prefix`),
                      KEY `idx_created_by` (`created_by`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Keys for webhook and system access'
                ");
                
                // Create usage log table
                outputMessage('Creating api_key_usage_log table...');
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `api_key_usage_log` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `api_key_id` int(11) NOT NULL,
                      `endpoint` varchar(255) NOT NULL COMMENT 'Which endpoint was accessed',
                      `ip_address` varchar(45) NOT NULL,
                      `user_agent` text DEFAULT NULL,
                      `success` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether the request was successful',
                      `error_message` varchar(500) DEFAULT NULL COMMENT 'Error message if request failed',
                      `request_data` JSON DEFAULT NULL COMMENT 'Additional request information',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `idx_api_key_id` (`api_key_id`),
                      KEY `idx_created_at` (`created_at`),
                      KEY `idx_endpoint` (`endpoint`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Key usage audit log'
                ");
                
                // Add foreign key constraint
                outputMessage('Adding foreign key constraints...');
                $pdo->exec("
                    ALTER TABLE `api_key_usage_log` 
                    ADD CONSTRAINT `fk_api_key_usage_log_api_key` 
                    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
                
                outputMessage('Tables created successfully.');
                
            } catch (Exception $e) {
                throw new Exception('Failed to create tables: ' . $e->getMessage());
            }
            
        } else {
            outputMessage('Tables already exist, checking structure...');
            
            // Check if all required columns exist
            $columns = $pdo->query("DESCRIBE api_keys")->fetchAll(PDO::FETCH_COLUMN);
            $requiredColumns = [
                'id', 'key_name', 'api_key', 'key_prefix', 'permissions', 
                'is_active', 'created_by', 'last_used_at', 'last_used_ip', 
                'expires_at', 'usage_count', 'rate_limit', 'created_at', 'updated_at'
            ];
            
            $missingColumns = array_diff($requiredColumns, $columns);
            
            if (!empty($missingColumns)) {
                throw new Exception('Missing required columns: ' . implode(', ', $missingColumns));
            }
            
            outputMessage('Table structure is up to date.');
        }
        
        // Step 2: Check for existing default keys
        outputMessage('Checking for existing default API keys...');
        
        $existingKeys = $pdo->query("
            SELECT key_name FROM api_keys 
            WHERE key_name IN ('webhook_monitor_default', 'system_admin_default')
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        // Step 3: Create backward-compatible API keys
        $defaultKeys = [
            [
                'name' => 'webhook_monitor_default',
                'permissions' => ['webhook_status', 'webhook_monitor'],
                'description' => 'Default webhook monitoring key for backward compatibility'
            ],
            [
                'name' => 'system_admin_default', 
                'permissions' => ['webhook_status', 'webhook_monitor', 'webhook_register', 'system_admin'],
                'description' => 'Default system admin key for backward compatibility'
            ]
        ];
        
        foreach ($defaultKeys as $keyConfig) {
            if (!in_array($keyConfig['name'], $existingKeys)) {
                outputMessage('Creating default API key: ' . $keyConfig['name']);
                
                $result = $apiKeyManager->generateApiKey(
                    $keyConfig['name'],
                    $keyConfig['permissions'],
                    null, // No specific creator
                    null, // No expiration
                    null  // No rate limit
                );
                
                if ($result['success']) {
                    outputMessage('Created API key: ' . $keyConfig['name']);
                    outputMessage('Key value: ' . $result['api_key']);
                    outputMessage('Key prefix: ' . $result['key_prefix']);
                    
                    $logger->info('Default API key created during migration', [
                        'key_id' => $result['key_id'],
                        'key_name' => $keyConfig['name'],
                        'permissions' => $keyConfig['permissions']
                    ]);
                } else {
                    throw new Exception('Failed to create default key ' . $keyConfig['name'] . ': ' . $result['error']);
                }
            } else {
                outputMessage('Default API key already exists: ' . $keyConfig['name']);
            }
        }
        
        // Step 4: Create a configuration entry to track migration
        outputMessage('Updating configuration...');
        
        $configExists = dbFetchOne("
            SELECT id FROM configuracoes 
            WHERE nome_configuracao = 'api_keys_migration' 
            AND categoria = 'system'
        ");
        
        if (!$configExists) {
            dbInsert('configuracoes', [
                'nome_configuracao' => 'api_keys_migration',
                'valor' => json_encode([
                    'migrated_at' => date('Y-m-d H:i:s'),
                    'version' => '1.0',
                    'migration_script' => basename(__FILE__)
                ]),
                'categoria' => 'system',
                'descricao' => 'API Keys migration tracking'
            ]);
            
            outputMessage('Migration configuration saved.');
        }
        
        // Step 5: Generate a new admin API key if requested
        $generateAdminKey = isset($_GET['generate_admin_key']) || (isset($argv[1]) && $argv[1] === '--generate-admin-key');
        
        // Check for admin key generation request
        
        if ($generateAdminKey) {
            outputMessage('Generating new admin API key...');
            
            $result = $apiKeyManager->generateApiKey(
                'admin_generated_' . date('Y_m_d_H_i'),
                ['*'], // All permissions
                null,
                null,
                1000 // 1000 requests per hour rate limit
            );
            
            if ($result['success']) {
                outputMessage('NEW ADMIN API KEY GENERATED:');
                outputMessage('Key Name: ' . $result['key_name']);
                outputMessage('API Key: ' . $result['api_key']);
                outputMessage('Key Prefix: ' . $result['key_prefix']);
                outputMessage('');
                outputMessage('IMPORTANT: Save this key securely. It will not be shown again.');
                
                $logger->info('New admin API key generated during migration', [
                    'key_id' => $result['key_id'],
                    'key_name' => $result['key_name']
                ]);
            } else {
                outputMessage('Failed to generate new admin key: ' . $result['error'], true);
            }
        }
        
        // Step 6: Provide usage examples
        outputMessage('');
        outputMessage('=== MIGRATION COMPLETED SUCCESSFULLY ===');
        outputMessage('');
        outputMessage('Usage Examples:');
        outputMessage('');
        outputMessage('1. Test API access with curl:');
        outputMessage('   curl -H "X-API-KEY: your_key_here" "' . APP_URL . '/api/webhook_status.php"');
        outputMessage('');
        outputMessage('2. Access admin interface:');
        outputMessage('   ' . APP_URL . '/admin/api-keys.php');
        outputMessage('');
        outputMessage('3. Re-run migration with new admin key:');
        outputMessage('   php ' . basename(__FILE__) . ' --generate-admin-key');
        outputMessage('');
        
        $logger->info('API Keys migration completed successfully');
        
        return true;
        
    } catch (Exception $e) {
        outputMessage('Migration failed: ' . $e->getMessage(), true);
        
        $logger->error('API Keys migration failed', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        
        return false;
    }
}

// Run the migration
try {
    $success = runMigration();
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    outputMessage('Fatal error during migration: ' . $e->getMessage(), true);
    exit(1);
}
?>