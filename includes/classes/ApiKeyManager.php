<?php
/**
 * API Key Manager Class
 * 
 * Handles all API key operations including validation, generation, 
 * management, and security features for webhook and API authentication.
 */

class ApiKeyManager 
{
    private $db;
    private $logger;
    
    public function __construct($database = null, $logger = null) 
    {
        $this->db = $database ?: getPDO();
        $this->logger = $logger ?: Logger::getInstance();
    }
    
    /**
     * Validate an API key and check permissions
     * 
     * @param string $apiKey The API key to validate
     * @param string $requiredPermission Optional permission to check
     * @param string $endpoint The endpoint being accessed
     * @return array Validation result with key details
     */
    public function validateApiKey($apiKey, $requiredPermission = null, $endpoint = '') 
    {
        if (empty($apiKey)) {
            return [
                'valid' => false,
                'error' => 'API key is required',
                'key_id' => null
            ];
        }
        
        try {
            // Hash the provided key to compare with stored hash
            $hashedKey = hash('sha256', $apiKey);
            
            // Query for the API key
            $query = "
                SELECT id, key_name, key_prefix, permissions, is_active, 
                       expires_at, rate_limit, usage_count, last_used_at
                FROM api_keys 
                WHERE api_key = ? 
                AND is_active = 1 
                AND (expires_at IS NULL OR expires_at > NOW())
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$hashedKey]);
            $keyData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$keyData) {
                $this->logUsage(null, $endpoint, false, 'Invalid API key', $apiKey);
                return [
                    'valid' => false,
                    'error' => 'Invalid or expired API key',
                    'key_id' => null
                ];
            }
            
            // Check rate limiting
            if ($keyData['rate_limit'] && $this->isRateLimited($keyData['id'], $keyData['rate_limit'])) {
                $this->logUsage($keyData['id'], $endpoint, false, 'Rate limit exceeded');
                return [
                    'valid' => false,
                    'error' => 'Rate limit exceeded',
                    'key_id' => $keyData['id']
                ];
            }
            
            // Check permissions if required
            if ($requiredPermission) {
                $permissions = json_decode($keyData['permissions'], true) ?: [];
                
                if (!in_array($requiredPermission, $permissions) && !in_array('*', $permissions)) {
                    $this->logUsage($keyData['id'], $endpoint, false, 'Insufficient permissions');
                    return [
                        'valid' => false,
                        'error' => 'Insufficient permissions for this action',
                        'key_id' => $keyData['id']
                    ];
                }
            }
            
            // Update usage statistics
            $this->updateUsageStats($keyData['id']);
            
            // Log successful usage
            $this->logUsage($keyData['id'], $endpoint, true);
            
            return [
                'valid' => true,
                'key_id' => $keyData['id'],
                'key_name' => $keyData['key_name'],
                'key_prefix' => $keyData['key_prefix'],
                'permissions' => json_decode($keyData['permissions'], true) ?: []
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error validating API key', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);
            
            return [
                'valid' => false,
                'error' => 'Internal validation error',
                'key_id' => null
            ];
        }
    }
    
    /**
     * Generate a new API key
     * 
     * @param string $keyName Human-readable name for the key
     * @param array $permissions Array of permissions for the key
     * @param int $createdBy User ID who created the key
     * @param string $expiresAt Optional expiration date (Y-m-d H:i:s format)
     * @param int $rateLimit Optional rate limit (requests per hour)
     * @return array Generated key information
     */
    public function generateApiKey($keyName, $permissions = [], $createdBy = null, $expiresAt = null, $rateLimit = null) 
    {
        try {
            // Generate a secure random API key
            $rawKey = $this->generateSecureKey();
            $hashedKey = hash('sha256', $rawKey);
            
            // Generate a readable prefix
            $prefix = $this->generateKeyPrefix($keyName);
            
            // Validate permissions
            $validPermissions = $this->validatePermissions($permissions);
            
            // Insert into database
            $insertData = [
                'key_name' => $keyName,
                'api_key' => $hashedKey,
                'key_prefix' => $prefix,
                'permissions' => json_encode($validPermissions),
                'is_active' => 1,
                'created_by' => $createdBy,
                'expires_at' => $expiresAt,
                'rate_limit' => $rateLimit
            ];
            
            $keyId = dbInsert('api_keys', $insertData);
            
            if (!$keyId) {
                throw new Exception('Failed to insert API key into database');
            }
            
            $this->logger->info('New API key generated', [
                'key_id' => $keyId,
                'key_name' => $keyName,
                'key_prefix' => $prefix,
                'created_by' => $createdBy
            ]);
            
            return [
                'success' => true,
                'key_id' => $keyId,
                'api_key' => $rawKey, // Only returned once!
                'key_name' => $keyName,
                'key_prefix' => $prefix,
                'permissions' => $validPermissions
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error generating API key', [
                'error' => $e->getMessage(),
                'key_name' => $keyName
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Revoke an API key
     * 
     * @param int $keyId The ID of the key to revoke
     * @return bool Success status
     */
    public function revokeApiKey($keyId) 
    {
        try {
            $result = dbUpdate('api_keys', ['is_active' => 0], 'id = ?', [$keyId]);
            
            if ($result) {
                $this->logger->info('API key revoked', ['key_id' => $keyId]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Error revoking API key', [
                'error' => $e->getMessage(),
                'key_id' => $keyId
            ]);
            
            return false;
        }
    }
    
    /**
     * Get all API keys with optional filtering
     * 
     * @param bool $activeOnly Whether to return only active keys
     * @return array List of API keys (without actual key values)
     */
    public function getApiKeys($activeOnly = true) 
    {
        try {
            $whereClause = $activeOnly ? 'is_active = 1' : '1';
            
            $query = "
                SELECT id, key_name, key_prefix, permissions, is_active, 
                       created_by, last_used_at, expires_at, usage_count, 
                       rate_limit, created_at, updated_at
                FROM api_keys 
                WHERE {$whereClause}
                ORDER BY created_at DESC
            ";
            
            return dbFetchAll($query);
            
        } catch (Exception $e) {
            $this->logger->error('Error fetching API keys', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Get API key usage statistics
     * 
     * @param int $keyId Optional specific key ID
     * @param int $days Number of days to look back
     * @return array Usage statistics
     */
    public function getUsageStats($keyId = null, $days = 30) 
    {
        try {
            $whereClause = 'created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params = [$days];
            
            if ($keyId) {
                $whereClause .= ' AND api_key_id = ?';
                $params[] = $keyId;
            }
            
            $query = "
                SELECT 
                    api_key_id,
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN success = 1 THEN 1 END) as successful_requests,
                    COUNT(CASE WHEN success = 0 THEN 1 END) as failed_requests,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    MIN(created_at) as first_request,
                    MAX(created_at) as last_request
                FROM api_key_usage_log 
                WHERE {$whereClause}
                GROUP BY api_key_id
            ";
            
            return dbFetchAll($query, $params);
            
        } catch (Exception $e) {
            $this->logger->error('Error fetching usage stats', [
                'error' => $e->getMessage(),
                'key_id' => $keyId
            ]);
            
            return [];
        }
    }
    
    /**
     * Clean up old usage logs
     * 
     * @param int $daysToKeep Number of days to retain logs
     * @return bool Success status
     */
    public function cleanupUsageLogs($daysToKeep = 90) 
    {
        try {
            $query = "DELETE FROM api_key_usage_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$daysToKeep]);
            
            $deletedRows = $stmt->rowCount();
            
            $this->logger->info('Usage logs cleaned up', [
                'days_kept' => $daysToKeep,
                'deleted_rows' => $deletedRows
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Error cleaning up usage logs', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    // Private helper methods
    
    private function generateSecureKey($length = 32) 
    {
        return bin2hex(random_bytes($length));
    }
    
    private function generateKeyPrefix($keyName) 
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $keyName);
        $prefix = strtolower(substr($cleaned, 0, 8));
        $random = substr(md5(uniqid()), 0, 4);
        return $prefix . '_' . $random . '_';
    }
    
    private function validatePermissions($permissions) 
    {
        $validPermissions = [
            'webhook_status', 'webhook_monitor', 'webhook_register',
            'system_admin', 'api_manage', '*'
        ];
        
        return array_intersect($permissions, $validPermissions);
    }
    
    private function isRateLimited($keyId, $rateLimit) 
    {
        $query = "
            SELECT COUNT(*) as request_count 
            FROM api_key_usage_log 
            WHERE api_key_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        
        $result = dbFetchOne($query, [$keyId]);
        return ($result['request_count'] ?? 0) >= $rateLimit;
    }
    
    private function updateUsageStats($keyId) 
    {
        $updateData = [
            'usage_count' => new \PDO('usage_count + 1'),
            'last_used_at' => date('Y-m-d H:i:s'),
            'last_used_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Use raw SQL for increment operation
        $query = "
            UPDATE api_keys 
            SET usage_count = usage_count + 1, 
                last_used_at = NOW(), 
                last_used_ip = ? 
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', $keyId]);
    }
    
    private function logUsage($keyId, $endpoint, $success, $errorMessage = null, $rawKey = null) 
    {
        try {
            $logData = [
                'api_key_id' => $keyId,
                'endpoint' => $endpoint,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'success' => $success ? 1 : 0,
                'error_message' => $errorMessage,
                'request_data' => json_encode([
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    'raw_key_prefix' => $rawKey ? substr($rawKey, 0, 8) . '...' : null
                ])
            ];
            
            dbInsert('api_key_usage_log', $logData);
            
        } catch (Exception $e) {
            // Log silently - don't break the main flow
            error_log('Failed to log API key usage: ' . $e->getMessage());
        }
    }
}