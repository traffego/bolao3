<?php
/**
 * WebhookValidator Class
 * 
 * Provides comprehensive webhook validation functionality to ensure webhook
 * configurations are correct and working properly.
 */

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../../config/config.php';

class WebhookValidator {
    private $logger;
    private $pdo;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Validate webhook URL format and accessibility
     * 
     * @param string $webhookUrl The webhook URL to validate
     * @return array Validation result with status and details
     */
    public function validateWebhookUrl($webhookUrl) {
        $this->logger->info('Validando webhook URL', ['webhook_url' => $webhookUrl]);
        
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'details' => []
        ];
        
        // Check if URL is provided
        if (empty($webhookUrl)) {
            $result['errors'][] = 'Webhook URL não foi fornecida';
            return $result;
        }
        
        // Check URL format
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $result['errors'][] = 'Formato de URL inválido';
            return $result;
        }
        
        $parsed = parse_url($webhookUrl);
        if (!$parsed) {
            $result['errors'][] = 'Não foi possível analisar a URL';
            return $result;
        }
        
        // Check for HTTPS requirement
        if ($parsed['scheme'] !== 'https') {
            $result['errors'][] = 'Webhook deve usar HTTPS (protocolo seguro)';
        }
        
        // Check for localhost in production
        if ($this->isProductionEnvironment() && $this->isLocalhostUrl($webhookUrl)) {
            $result['errors'][] = 'URL localhost não é válida em ambiente de produção';
        }
        
        // Check if domain is reachable
        $reachabilityCheck = $this->checkUrlReachability($webhookUrl);
        $result['details']['reachability'] = $reachabilityCheck;
        
        if (!$reachabilityCheck['reachable']) {
            $result['warnings'][] = 'URL pode não estar acessível: ' . $reachabilityCheck['error'];
        }
        
        // Check webhook endpoint specific requirements
        $endpointCheck = $this->checkWebhookEndpoint($webhookUrl);
        $result['details']['endpoint'] = $endpointCheck;
        
        if (!$endpointCheck['valid']) {
            $result['warnings'][] = 'Endpoint do webhook pode não estar funcionando corretamente';
        }
        
        // Determine if valid
        $result['valid'] = empty($result['errors']);
        
        $this->logger->info('Validação de webhook URL concluída', [
            'webhook_url' => $webhookUrl,
            'valid' => $result['valid'],
            'errors_count' => count($result['errors']),
            'warnings_count' => count($result['warnings'])
        ]);
        
        return $result;
    }
    
    /**
     * Check for localhost/development URLs in production
     * 
     * @param string $url URL to check
     * @return bool True if URL is localhost
     */
    public function isLocalhostUrl($url) {
        return strpos($url, 'localhost') !== false || 
               strpos($url, '127.0.0.1') !== false ||
               strpos($url, '192.168.') !== false ||
               strpos($url, '10.0.') !== false;
    }
    
    /**
     * Verify EFI Pay configuration consistency between files and database
     * 
     * @return array Consistency check result
     */
    public function validateEfiConfigConsistency() {
        $this->logger->info('Verificando consistência de configuração EFI');
        
        $result = [
            'consistent' => false,
            'issues' => [],
            'config_sources' => []
        ];
        
        try {
            // Load configuration from database
            $dbConfig = $this->loadDatabaseConfig();
            $result['config_sources']['database'] = $dbConfig;
            
            // Load configuration from constants (after efi_config_db.php)
            $constantsConfig = $this->loadConstantsConfig();
            $result['config_sources']['constants'] = $constantsConfig;
            
            // Check APP_URL vs webhook_url consistency
            $appUrl = defined('APP_URL') ? APP_URL : null;
            $webhookUrl = defined('WEBHOOK_URL') ? WEBHOOK_URL : null;
            $dbWebhookUrl = $dbConfig['webhook_url'] ?? null;
            
            $result['config_sources']['app_url'] = $appUrl;
            $result['config_sources']['webhook_url_constant'] = $webhookUrl;
            $result['config_sources']['webhook_url_database'] = $dbWebhookUrl;
            
            // Validate consistency
            if ($appUrl && $webhookUrl) {
                $expectedWebhookUrl = rtrim($appUrl, '/') . '/api/webhook_pix.php';
                if ($webhookUrl !== $expectedWebhookUrl) {
                    $result['issues'][] = "WEBHOOK_URL constant ($webhookUrl) não corresponde ao esperado ($expectedWebhookUrl)";
                }
            }
            
            if ($dbWebhookUrl && $webhookUrl && $dbWebhookUrl !== $webhookUrl) {
                $result['issues'][] = "Webhook URL no banco ($dbWebhookUrl) difere da constante ($webhookUrl)";
            }
            
            // Check environment consistency
            $isProductionEnv = $this->isProductionEnvironment();
            $configEnvironment = $dbConfig['ambiente'] ?? 'desconhecido';
            
            if ($isProductionEnv && $configEnvironment === 'homologacao') {
                $result['issues'][] = 'Ambiente de produção detectado mas configuração está em homologação';
            } elseif (!$isProductionEnv && $configEnvironment === 'producao') {
                $result['issues'][] = 'Ambiente de desenvolvimento detectado mas configuração está em produção';
            }
            
            // Check required fields
            $requiredFields = ['client_id', 'client_secret', 'pix_key'];
            foreach ($requiredFields as $field) {
                if (empty($dbConfig[$field])) {
                    $result['issues'][] = "Campo obrigatório '$field' não está definido no banco";
                }
                if (empty($constantsConfig[$field])) {
                    $result['issues'][] = "Constante '$field' não está definida";
                }
            }
            
            $result['consistent'] = empty($result['issues']);
            
        } catch (Exception $e) {
            $result['issues'][] = 'Erro ao verificar consistência: ' . $e->getMessage();
            $this->logger->error('Erro ao verificar consistência de configuração EFI', [
                'error' => $e->getMessage()
            ]);
        }
        
        $this->logger->info('Verificação de consistência concluída', [
            'consistent' => $result['consistent'],
            'issues_count' => count($result['issues'])
        ]);
        
        return $result;
    }
    
    /**
     * Test webhook endpoint connectivity
     * 
     * @param string $webhookUrl Webhook URL to test
     * @return array Test result
     */
    public function testWebhookConnectivity($webhookUrl) {
        $this->logger->info('Testando conectividade do webhook', ['webhook_url' => $webhookUrl]);
        
        $result = [
            'accessible' => false,
            'response_code' => null,
            'response_time' => null,
            'ssl_valid' => false,
            'error' => null
        ];
        
        $startTime = microtime(true);
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhookUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'WebhookValidator/1.0',
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode(['test' => true]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: WebhookValidator/1.0'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $sslResult = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
            $result['response_code'] = $httpCode;
            $result['ssl_valid'] = ($sslResult === 0);
            
            if ($error) {
                $result['error'] = $error;
            } else {
                $result['accessible'] = ($httpCode > 0);
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        $this->logger->info('Teste de conectividade concluído', [
            'webhook_url' => $webhookUrl,
            'accessible' => $result['accessible'],
            'response_code' => $result['response_code'],
            'response_time' => $result['response_time']
        ]);
        
        return $result;
    }
    
    /**
     * Validate SSL certificates and HTTPS requirements
     * 
     * @param string $webhookUrl Webhook URL to validate
     * @return array SSL validation result
     */
    public function validateSslCertificate($webhookUrl) {
        $this->logger->info('Validando certificado SSL', ['webhook_url' => $webhookUrl]);
        
        $result = [
            'valid' => false,
            'certificate_info' => null,
            'errors' => [],
            'warnings' => []
        ];
        
        $parsed = parse_url($webhookUrl);
        if ($parsed['scheme'] !== 'https') {
            $result['errors'][] = 'URL não usa HTTPS';
            return $result;
        }
        
        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            
            $stream = @stream_socket_client(
                'ssl://' . $parsed['host'] . ':443',
                $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context
            );
            
            if ($stream) {
                $params = stream_context_get_params($stream);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                    $result['certificate_info'] = [
                        'subject' => $cert['subject'] ?? null,
                        'issuer' => $cert['issuer'] ?? null,
                        'valid_from' => date('Y-m-d H:i:s', $cert['validFrom_time_t'] ?? 0),
                        'valid_to' => date('Y-m-d H:i:s', $cert['validTo_time_t'] ?? 0),
                        'expires_in_days' => round(($cert['validTo_time_t'] - time()) / 86400)
                    ];
                    
                    // Check if certificate is expiring soon
                    if ($result['certificate_info']['expires_in_days'] < 30) {
                        $result['warnings'][] = 'Certificado SSL expira em menos de 30 dias';
                    }
                    
                    $result['valid'] = true;
                }
                fclose($stream);
            } else {
                $result['errors'][] = "Não foi possível conectar via SSL: $errstr";
            }
            
        } catch (Exception $e) {
            $result['errors'][] = 'Erro ao validar SSL: ' . $e->getMessage();
        }
        
        $this->logger->info('Validação SSL concluída', [
            'webhook_url' => $webhookUrl,
            'valid' => $result['valid'],
            'errors_count' => count($result['errors'])
        ]);
        
        return $result;
    }
    
    /**
     * Provide detailed error messages for configuration issues
     * 
     * @param array $validationResults Combined validation results
     * @return array Detailed error messages with solutions
     */
    public function generateDetailedErrorMessages($validationResults) {
        $messages = [];
        
        foreach ($validationResults as $type => $result) {
            if (isset($result['errors']) && !empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $messages[] = [
                        'type' => 'error',
                        'category' => $type,
                        'message' => $error,
                        'solution' => $this->getSolutionForError($error)
                    ];
                }
            }
            
            if (isset($result['warnings']) && !empty($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    $messages[] = [
                        'type' => 'warning',
                        'category' => $type,
                        'message' => $warning,
                        'solution' => $this->getSolutionForError($warning)
                    ];
                }
            }
        }
        
        return $messages;
    }
    
    /**
     * Check if running in production environment
     * 
     * @return bool True if production
     */
    private function isProductionEnvironment() {
        if (defined('APP_URL')) {
            return !$this->isLocalhostUrl(APP_URL);
        }
        
        // Fallback to server detection
        $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        return !$this->isLocalhostUrl('http://' . $serverName);
    }
    
    /**
     * Check URL reachability
     * 
     * @param string $url URL to check
     * @return array Reachability result
     */
    private function checkUrlReachability($url) {
        $result = ['reachable' => false, 'error' => null];
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false, // For testing only
                CURLOPT_USERAGENT => 'WebhookValidator/1.0'
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $result['error'] = $error;
            } else {
                $result['reachable'] = ($httpCode > 0 && $httpCode < 500);
                if (!$result['reachable']) {
                    $result['error'] = "HTTP $httpCode";
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check webhook endpoint specific functionality
     * 
     * @param string $webhookUrl Webhook URL to check
     * @return array Endpoint check result
     */
    private function checkWebhookEndpoint($webhookUrl) {
        // Check if URL ends with expected webhook endpoint
        $expectedEndpoint = '/api/webhook_pix.php';
        $hasCorrectEndpoint = (strpos($webhookUrl, $expectedEndpoint) !== false);
        
        return [
            'valid' => $hasCorrectEndpoint,
            'has_correct_endpoint' => $hasCorrectEndpoint,
            'expected_endpoint' => $expectedEndpoint
        ];
    }
    
    /**
     * Load configuration from database
     * 
     * @return array Database configuration
     */
    private function loadDatabaseConfig() {
        $stmt = $this->pdo->prepare("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? json_decode($result['valor'], true) : [];
    }
    
    /**
     * Load configuration from constants
     * 
     * @return array Constants configuration
     */
    private function loadConstantsConfig() {
        return [
            'client_id' => defined('EFI_CLIENT_ID') ? EFI_CLIENT_ID : null,
            'client_secret' => defined('EFI_CLIENT_SECRET') ? EFI_CLIENT_SECRET : null,
            'pix_key' => defined('EFI_PIX_KEY') ? EFI_PIX_KEY : null,
            'api_url' => defined('EFI_API_URL') ? EFI_API_URL : null,
            'certificate_path' => defined('EFI_CERTIFICATE_PATH') ? EFI_CERTIFICATE_PATH : null
        ];
    }
    
    /**
     * Get solution suggestion for error
     * 
     * @param string $error Error message
     * @return string Solution suggestion
     */
    private function getSolutionForError($error) {
        $solutions = [
            'Webhook URL não foi fornecida' => 'Configure o webhook URL nas configurações de pagamento',
            'Formato de URL inválido' => 'Verifique se a URL está no formato correto (https://dominio.com/api/webhook_pix.php)',
            'Webhook deve usar HTTPS' => 'Certifique-se de que o webhook URL usa protocolo HTTPS',
            'URL localhost não é válida em ambiente de produção' => 'Configure uma URL pública acessível para o webhook em produção',
            'Ambiente de produção detectado mas configuração está em homologação' => 'Altere o ambiente para "producao" nas configurações',
            'Certificado SSL expira em menos de 30 dias' => 'Renove o certificado SSL do servidor'
        ];
        
        foreach ($solutions as $pattern => $solution) {
            if (strpos($error, $pattern) !== false) {
                return $solution;
            }
        }
        
        return 'Verifique a configuração e documentação para mais detalhes';
    }
}