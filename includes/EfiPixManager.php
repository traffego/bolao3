<?php
require_once __DIR__ . '/../config/efi_config_db.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/classes/Logger.php';

class EfiPixManager {
    private $access_token;
    private $client;
    private $pdo;
    private $logger;
    private $token_cache_file;
    private $token_cache_duration = 3600; // 1 hora
    private $webhook_failure_fatal; // Flag para controlar se falhas de webhook devem ser fatais

    /**
     * Define se falhas no registro de webhook devem ser fatais
     * @param bool $fatal True se falhas devem interromper a execução, false para apenas logar
     */
    public function setWebhookFailureFatal($fatal = false) {
        $this->webhook_failure_fatal = (bool) $fatal;
        $this->logger->info("Configuração de webhook failure fatal alterada", [
            'webhook_failure_fatal' => $this->webhook_failure_fatal
        ]);
    }

    /**
     * Retorna se falhas no registro de webhook são fatais
     * @return bool
     */
    public function isWebhookFailureFatal() {
        return $this->webhook_failure_fatal;
    }

    private function validateCertificate() {
        if (!file_exists(EFI_CERTIFICATE_PATH)) {
            log_error("Certificado não encontrado", ['path' => EFI_CERTIFICATE_PATH]);
            throw new Exception('Certificado não encontrado. Por favor, faça o upload do certificado nas configurações.');
        }

        // Tentar ler o certificado
        $cert_content = @file_get_contents(EFI_CERTIFICATE_PATH);
        if ($cert_content === false) {
            log_error("Não foi possível ler o certificado", ['path' => EFI_CERTIFICATE_PATH]);
            throw new Exception('Erro ao ler o certificado. Verifique as permissões do arquivo.');
        }

        // Verificar se é um certificado P12 válido
        if (@openssl_pkcs12_read($cert_content, $cert_info, '') === false) {
            log_error("Certificado P12 inválido", ['path' => EFI_CERTIFICATE_PATH]);
            throw new Exception('Certificado P12 inválido. Por favor, gere um novo certificado no painel da Efí.');
        }

        log_debug("Certificado validado com sucesso");
        return true;
    }

    /**
     * Testa conectividade com a API EFI
     * @return array Status da conectividade
     */
    public function testConnectivity() {
        try {
            $this->logger->info('Iniciando teste de conectividade EFI');
            
            // Verificar certificado
            $this->validateCertificate();
            
            // Tentar autenticar
            $this->authenticate();
            
            // Verificar se conseguimos fazer uma requisição básica
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => EFI_API_URL . '/v2/gn/infracoes',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
                CURLOPT_SSLCERTTYPE => 'P12'
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                throw new Exception('Erro de conectividade: ' . $err);
            }
            
            $this->logger->info('Teste de conectividade concluído', [
                'http_code' => $httpCode,
                'api_url' => EFI_API_URL
            ]);
            
            return [
                'status' => 'success',
                'message' => 'Conectividade OK',
                'details' => [
                    'api_url' => EFI_API_URL,
                    'certificate_path' => EFI_CERTIFICATE_PATH,
                    'http_code' => $httpCode,
                    'authenticated' => !empty($this->access_token)
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erro no teste de conectividade', [
                'erro' => $e->getMessage(),
                'linha' => $e->getLine()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'details' => [
                    'api_url' => EFI_API_URL,
                    'certificate_exists' => file_exists(EFI_CERTIFICATE_PATH)
                ]
            ];
        }
    }
    
    /**
     * Verifica se o webhook está registrado na EFI
     * @return array Status do webhook
     */
    public function checkWebhookRegistration() {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => EFI_API_URL . '/v2/webhook/' . EFI_PIX_KEY,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
                CURLOPT_SSLCERTTYPE => 'P12'
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            return [
                'registered' => $httpCode === 200,
                'http_code' => $httpCode,
                'response' => json_decode($response, true)
            ];
            
        } catch (Exception $e) {
            return [
                'registered' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate webhook URL before registration
     * 
     * @param string $webhookUrl Optional webhook URL to validate, uses EFI_WEBHOOK_URL if not provided
     * @return array Validation result
     */
    public function validateWebhookUrl($webhookUrl = null) {
        if (!$webhookUrl) {
            $webhookUrl = defined('EFI_WEBHOOK_URL') ? EFI_WEBHOOK_URL : WEBHOOK_URL;
        }
        
        $this->logger->info('Validando webhook URL antes do registro', ['webhook_url' => $webhookUrl]);
        
        $result = [
            'valid' => false,
            'webhook_url' => $webhookUrl,
            'errors' => []
        ];
        
        // Check if URL is provided
        if (empty($webhookUrl)) {
            $result['errors'][] = 'Webhook URL não foi fornecida';
            return $result;
        }
        
        // Check if localhost in production
        $isProduction = strpos(EFI_API_URL, 'pix.api.efipay.com.br') !== false;
        $isLocalhost = strpos($webhookUrl, 'localhost') !== false || strpos($webhookUrl, '127.0.0.1') !== false;
        
        if ($isProduction && $isLocalhost) {
            $result['errors'][] = 'URL localhost não é válida em ambiente de produção';
            return $result;
        }
        
        // Check HTTPS requirement
        if (strpos($webhookUrl, 'https://') !== 0) {
            $result['errors'][] = 'Webhook deve usar protocolo HTTPS';
            return $result;
        }
        
        // Check URL format
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $result['errors'][] = 'Formato de URL inválido';
            return $result;
        }
        
        $result['valid'] = true;
        $this->logger->info('Webhook URL validada com sucesso', ['webhook_url' => $webhookUrl]);
        
        return $result;
    }
    
    /**
     * Check current webhook registration status with EFI Pay
     * 
     * @return array Registration status
     */
    public function getWebhookRegistrationStatus() {
        $this->logger->info('Verificando status de registro do webhook');
        
        try {
            $webhookCheck = $this->checkWebhookRegistration();
            
            $result = [
                'registered' => $webhookCheck['registered'],
                'http_code' => $webhookCheck['http_code'],
                'webhook_url' => defined('EFI_WEBHOOK_URL') ? EFI_WEBHOOK_URL : WEBHOOK_URL,
                'pix_key' => EFI_PIX_KEY,
                'api_url' => EFI_API_URL
            ];
            
            if (isset($webhookCheck['response'])) {
                $result['response'] = $webhookCheck['response'];
            }
            
            if (isset($webhookCheck['error'])) {
                $result['error'] = $webhookCheck['error'];
            }
            
            $this->logger->info('Status de webhook verificado', [
                'registered' => $result['registered'],
                'http_code' => $result['http_code']
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao verificar status do webhook', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'registered' => false,
                'error' => $e->getMessage(),
                'webhook_url' => defined('EFI_WEBHOOK_URL') ? EFI_WEBHOOK_URL : WEBHOOK_URL
            ];
        }
    }
    
    /**
     * Force webhook re-registration
     * 
     * @param string $webhookUrlOverride Optional webhook URL override
     * @return array Registration result
     */
    public function forceWebhookReRegistration($webhookUrlOverride = null) {
        $webhookUrl = $webhookUrlOverride ?: (defined('EFI_WEBHOOK_URL') ? EFI_WEBHOOK_URL : WEBHOOK_URL);
        
        $this->logger->info('Forçando re-registro de webhook', [
            'webhook_url' => $webhookUrl,
            'override_provided' => !empty($webhookUrlOverride)
        ]);
        
        // Validate URL first
        $validation = $this->validateWebhookUrl($webhookUrl);
        if (!$validation['valid']) {
            return [
                'status' => 'error',
                'message' => 'Webhook URL inválida: ' . implode(', ', $validation['errors']),
                'validation_errors' => $validation['errors']
            ];
        }
        
        // Attempt registration with higher retry count for forced registration
        return $this->registerWebhook(5, $webhookUrl);
    }

    public function registerWebhook($maxRetries = 3, $webhookUrlOverride = null) {
        $webhookUrl = $webhookUrlOverride ?: (defined('EFI_WEBHOOK_URL') ? EFI_WEBHOOK_URL : WEBHOOK_URL);
        
        // Validate webhook URL first
        $validation = $this->validateWebhookUrl($webhookUrl);
        if (!$validation['valid']) {
            $this->logger->error("Webhook URL inválida, cancelando registro", [
                'webhook_url' => $webhookUrl,
                'validation_errors' => $validation['errors']
            ]);
            return [
                'status' => 'error',
                'message' => 'Webhook URL inválida: ' . implode(', ', $validation['errors']),
                'validation_errors' => $validation['errors']
            ];
        }
        
        // Verificar se é localhost
        if (strpos($webhookUrl, 'localhost') !== false || strpos($webhookUrl, '127.0.0.1') !== false) {
            $this->logger->info("Ignorando registro de webhook em ambiente local");
            return ['status' => 'skipped', 'message' => 'Ambiente local - webhook ignorado'];
        }

        $data = [
            'webhookUrl' => $webhookUrl
        ];

        $this->logger->info("Registrando webhook", [
            'webhook_url' => $webhookUrl,
            'pix_key' => EFI_PIX_KEY,
            'max_retries' => $maxRetries,
            'override_provided' => !empty($webhookUrlOverride)
        ]);
        
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $curl = curl_init();
                
                // Endpoint correto conforme documentação da Efí
                $url = EFI_API_URL . '/v2/webhook/' . EFI_PIX_KEY;
                
                curl_setopt_array($curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $this->access_token,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
                    CURLOPT_SSLCERTTYPE => 'P12'
                ]);
        
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $err = curl_error($curl);
                curl_close($curl);
        
                $this->logger->info("Tentativa de registro de webhook", [
                    'attempt' => $attempt,
                    'http_code' => $httpCode,
                    'response' => $response,
                    'curl_error' => $err
                ]);
        
                if ($err) {
                    $lastError = 'Erro cURL: ' . $err;
                    if ($attempt < $maxRetries) {
                        $this->logger->warn("Erro cURL na tentativa $attempt, tentando novamente", ['error' => $err]);
                        sleep(2); // Aguardar 2 segundos antes da próxima tentativa
                        continue;
                    }
                    throw new Exception($lastError);
                }
        
                if ($httpCode === 200 || $httpCode === 201) {
                    $this->logger->info("Webhook registrado com sucesso", [
                        'attempt' => $attempt,
                        'http_code' => $httpCode
                    ]);
                    return [
                        'status' => 'success',
                        'message' => 'Webhook registrado com sucesso',
                        'data' => json_decode($response, true),
                        'attempts' => $attempt
                    ];
                }
                
                $lastError = "HTTP $httpCode: $response";
                if ($attempt < $maxRetries) {
                    $this->logger->warn("Erro HTTP na tentativa $attempt, tentando novamente", [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]);
                    sleep(2);
                    continue;
                }
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                if ($attempt < $maxRetries) {
                    $this->logger->warn("Exceção na tentativa $attempt, tentando novamente", ['error' => $e->getMessage()]);
                    sleep(2);
                    continue;
                }
            }
        }

        // Return error response when all attempts fail
        $errorResult = [
            'status' => 'error', 
            'message' => $lastError, 
            'attempts' => $maxRetries,
            'webhook_url' => $webhookUrl,
            'troubleshooting' => $this->getWebhookTroubleshootingInfo($webhookUrl)
        ];
        
        $this->logger->error('Falha completa no registro de webhook', $errorResult);
        
        return $errorResult;
    }
    
    /**
     * Get troubleshooting information for webhook issues
     * 
     * @param string $webhookUrl Webhook URL that failed
     * @return array Troubleshooting information
     */
    private function getWebhookTroubleshootingInfo($webhookUrl) {
        return [
            'suggestions' => [
                'Verifique se a URL é acessível publicamente',
                'Confirme que o certificado SSL está válido',
                'Verifique se não há firewall bloqueando requisições da EFI Pay',
                'Confirme que o endpoint /api/webhook_pix.php existe e está funcionando'
            ],
            'checks' => [
                'webhook_url_format' => filter_var($webhookUrl, FILTER_VALIDATE_URL) !== false,
                'uses_https' => strpos($webhookUrl, 'https://') === 0,
                'not_localhost' => strpos($webhookUrl, 'localhost') === false && strpos($webhookUrl, '127.0.0.1') === false,
                'certificate_exists' => file_exists(EFI_CERTIFICATE_PATH),
                'api_url' => EFI_API_URL,
                'pix_key_set' => !empty(EFI_PIX_KEY)
            ]
        ];
    }

    public function __construct($webhookFailureFatal = false) {
        global $pdo;
        $this->pdo = $pdo;
        $this->logger = Logger::getInstance();
        $this->token_cache_file = __DIR__ . '/../logs/efi_token_cache.json';
        $this->webhook_failure_fatal = $webhookFailureFatal;
        
        // Verificar ambiente
        $isLocalhost = strpos(WEBHOOK_URL, 'localhost') !== false || strpos(WEBHOOK_URL, '127.0.0.1') !== false;
        $isProduction = strpos(EFI_API_URL, 'pix.api.efipay.com.br') !== false;
        
        if ($isLocalhost) {
            $this->logger->info("Ambiente local detectado - Webhook será ignorado");
            // Em ambiente local, vamos apenas autenticar
            $this->validateCertificate();
            $this->authenticate();
                    } else {
                try {
                $this->validateCertificate();
                $this->authenticate();
                
                // Validate webhook URL before attempting registration
                $webhookValidation = $this->validateWebhookUrl();
                if (!$webhookValidation['valid']) {
                    $this->logger->error("Webhook URL inválida durante inicialização", [
                        'validation_errors' => $webhookValidation['errors'],
                        'webhook_url' => $webhookValidation['webhook_url']
                    ]);
                    
                    if ($this->webhook_failure_fatal) {
                        throw new Exception("Webhook URL inválida: " . implode(', ', $webhookValidation['errors']));
                    }
                } else {
                    // Tentar registrar webhook apenas se URL for válida
                    $webhookResult = $this->registerWebhook();
                    
                    // Verificar se o registro falhou e se deve ser fatal
                    if ($webhookResult['status'] === 'error' && $this->webhook_failure_fatal) {
                        $this->logger->error("Falha fatal no registro de webhook", [
                            'error' => $webhookResult['message'],
                            'webhook_failure_fatal' => $this->webhook_failure_fatal,
                            'troubleshooting' => $webhookResult['troubleshooting'] ?? null
                        ]);
                        throw new Exception("Falha crítica no registro de webhook: " . $webhookResult['message']);
                    } else if ($webhookResult['status'] === 'error') {
                        $this->logger->warn("Falha no registro de webhook (não fatal)", [
                            'error' => $webhookResult['message'],
                            'webhook_failure_fatal' => $this->webhook_failure_fatal,
                            'troubleshooting' => $webhookResult['troubleshooting'] ?? null
                        ]);
                    }
                }
                
            } catch (Exception $e) {
                if ($this->webhook_failure_fatal) {
                    log_error("Erro fatal ao inicializar EfiPixManager", ['error' => $e->getMessage()]);
                    throw $e;
                } else {
                    log_error("Erro ao inicializar EfiPixManager (não fatal)", ['error' => $e->getMessage()]);
                    // Continuar a execução mesmo com erro
                }
            }
        }
    }

    private function loadTokenFromCache() {
        if (!file_exists($this->token_cache_file)) {
            return false;
        }
        
        $cacheData = json_decode(file_get_contents($this->token_cache_file), true);
        if (!$cacheData || !isset($cacheData['token']) || !isset($cacheData['expires_at'])) {
            return false;
        }
        
        if (time() >= $cacheData['expires_at']) {
            $this->logger->info('Token em cache expirado');
            return false;
        }
        
        $this->access_token = $cacheData['token'];
        $this->logger->info('Token carregado do cache', ['expires_in' => $cacheData['expires_at'] - time()]);
        return true;
    }
    
    private function saveTokenToCache($token, $expiresIn = null) {
        $expiresIn = $expiresIn ?: $this->token_cache_duration;
        $cacheData = [
            'token' => $token,
            'expires_at' => time() + $expiresIn - 60, // 1 minuto de margem
            'created_at' => time()
        ];
        
        file_put_contents($this->token_cache_file, json_encode($cacheData));
        $this->logger->info('Token salvo no cache', ['expires_in' => $expiresIn]);
    }

    public function authenticate($retryCount = 0) {
        // Tentar carregar token do cache primeiro
        if ($this->loadTokenFromCache()) {
            return $this->access_token;
        }

        $curl = curl_init();
        
        $params = [
            'grant_type' => 'client_credentials'
        ];
        
        $url = EFI_API_URL . '/v1/authorize';
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(EFI_CLIENT_ID . ':' . EFI_CLIENT_SECRET),
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
            CURLOPT_SSLCERTTYPE => 'P12'
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        $this->logger->info("Resposta da autenticação", [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $err
        ]);

        if ($err) {
            $this->logger->error("Erro cURL na autenticação", ['error' => $err]);
            if ($retryCount < 3) {
                sleep(2);
                return $this->authenticate($retryCount + 1);
            }
            throw new Exception('Erro de conexão: ' . $err);
        }

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Falha ao decodificar JSON da resposta", ['error' => json_last_error_msg()]);
            throw new Exception('Resposta inválida da API');
        }

        if ($httpCode == 200) {
            $this->access_token = $responseData['access_token'];
            
            // Salvar token no cache
            $expiresIn = isset($responseData['expires_in']) ? $responseData['expires_in'] : $this->token_cache_duration;
            $this->saveTokenToCache($this->access_token, $expiresIn);
            
            $this->logger->info("Autenticação realizada com sucesso", [
                'expires_in' => $expiresIn,
                'retry_count' => $retryCount
            ]);
            return $this->access_token;
        }

        $this->logger->error("Erro na autenticação", [
            'http_code' => $httpCode,
            'response' => $responseData
        ]);
        
        if ($retryCount < 3) {
            sleep(2);
            return $this->authenticate($retryCount + 1);
        }
        
        if (isset($responseData['message'])) {
            throw new Exception('Erro na autenticação: ' . $responseData['message']);
        } else {
            throw new Exception('Erro na autenticação. HTTP Code: ' . $httpCode);
        }
    }

    public function createCharge($user_id, $valor, $referencia = null, $descricao = null) {
        // ... (rest of the code remains the same)
        log_debug("Iniciando createCharge", [
            'user_id' => $user_id,
            'valor' => $valor,
            'referencia' => $referencia,
            'descricao' => $descricao
        ]);
        
        // Validar user_id
        if (!$user_id || !is_numeric($user_id) || $user_id <= 0) {
            log_error("user_id inválido", ['user_id' => $user_id]);
            throw new Exception('ID do usuário inválido');
        }
        
        if (!is_numeric($valor) || $valor <= 0) {
            log_error("valor inválido", ['valor' => $valor]);
            throw new Exception('Valor inválido');
        }

        // Buscar conta do usuário
        log_debug("Buscando conta para user_id", ['user_id' => $user_id]);
        $stmt = $this->pdo->prepare("SELECT id FROM contas WHERE jogador_id = ?");
        $stmt->execute([$user_id]);
        $conta = $stmt->fetch();
        log_debug("Conta encontrada", ['conta' => $conta]);

        if (!$conta) {
            // Verificar se o usuário existe antes de criar a conta
            $stmt = $this->pdo->prepare("SELECT id FROM jogador WHERE id = ?");
            $stmt->execute([$user_id]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                log_error("Tentativa de criar conta para usuário inexistente", ['user_id' => $user_id]);
                throw new Exception('Usuário não encontrado');
            }
            
            // Criar conta automaticamente se não existir
            try {
                log_debug("Criando conta para usuário", ['user_id' => $user_id]);
                
                $stmt = $this->pdo->prepare(
                    "INSERT INTO contas (jogador_id, status) VALUES (?, 'ativo')"
                );
                $result = $stmt->execute([$user_id]);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    log_error("Erro SQL ao criar conta", ['error_info' => $errorInfo]);
                    throw new Exception('Falha na execução do SQL');
                }
                
                $contaId = $this->pdo->lastInsertId();
                
                if (!$contaId) {
                    log_error("LastInsertId retornou 0", ['user_id' => $user_id]);
                    throw new Exception('Falha ao obter ID da conta criada');
                }
                
                $conta = ['id' => $contaId];
                
                log_debug("Conta criada com sucesso", [
                    'user_id' => $user_id,
                    'conta_id' => $contaId
                ]);
            } catch (PDOException $e) {
                log_error("Erro PDO ao criar conta", [
                    'user_id' => $user_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
                throw new Exception('Erro na base de dados ao criar conta');
            } catch (Exception $e) {
                log_error("Erro geral ao criar conta", [
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                ]);
                throw new Exception('Erro ao criar conta do usuário: ' . $e->getMessage());
            }
        }

        // Gerar novo TXID se não fornecido
        // Formato: apenas caracteres alfanuméricos aleatórios (26-35 chars)
        // Conforme especificação EFI Pay: ^[a-zA-Z0-9]{26,35}$
        $txid = $this->generateRandomTxid();

        log_debug("Gerando TXID", ['txid' => $txid, 'user_id' => $user_id]);
        
        // Usar descrição fornecida ou padrão
        $descricao = $descricao ?: "Depósito #{$referencia}";

        log_debug("Criando cobrança", ['txid' => $txid, 'valor' => $valor]);
        
        try {
            $curl = curl_init();
            
            $data = [
                'calendario' => [
                    'expiracao' => 3600
                ],
                'valor' => [
                    'original' => number_format($valor, 2, '.', '')
                ],
                'chave' => EFI_PIX_KEY,
                'solicitacaoPagador' => $descricao
            ];

            log_debug("Dados enviados para API", [
                'data' => $data,
                'url' => EFI_API_URL . '/v2/cob/' . $txid,
                'access_token_configured' => !empty($this->access_token),
                'certificate_path' => EFI_CERTIFICATE_PATH,
                'certificate_exists' => file_exists(EFI_CERTIFICATE_PATH)
            ]);

            curl_setopt_array($curl, [
                CURLOPT_URL => EFI_API_URL . '/v2/cob/' . $txid,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
                CURLOPT_SSLCERTTYPE => 'P12'
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            log_debug("Resposta da API", [
                'http_code' => $httpCode,
                'response' => $response,
                'error' => $err
            ]);

            if ($err) {
                log_error("Erro cURL ao criar cobrança", ['error' => $err]);
                throw new Exception('Erro ao criar cobrança: ' . $err);
            }

            if ($httpCode !== 200 && $httpCode !== 201) {
                log_error("HTTP Code inválido ao criar cobrança", [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                throw new Exception('Erro ao criar cobrança. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
            }

            $responseData = json_decode($response, true);
            log_debug("Response data", ['response_data' => $responseData]);
            
            if (isset($responseData['nome']) && $responseData['nome'] === 'txid_duplicado') {
                log_debug("TXID duplicado, tentando novamente");
                // Se o TXID estiver duplicado, tentar novamente
                return $this->createCharge($user_id, $valor, $referencia, $descricao);
            }

            // Verificar se a resposta contém os dados necessários
            if (!isset($responseData['loc']['id'])) {
                log_error("Resposta inválida da API - loc.id não encontrado", ['response_data' => $responseData]);
                throw new Exception('Resposta inválida da API ao criar cobrança');
            }

            // Gerar QR Code
            $locationId = $responseData['loc']['id'];
            log_debug("Gerando QR Code", ['location_id' => $locationId]);
            $qrCode = $this->getQrCode($locationId);
            
            log_debug("QR Code gerado", ['qr_code' => $qrCode]);
            
            return [
                'txid' => $txid,
                'status' => $responseData['status'],
                'valor' => $responseData['valor']['original'],
                'qrcode' => $qrCode['imagemQrcode'] ?? null,
                'qrcode_texto' => $qrCode['qrcode'] ?? null,
                'calendario' => $responseData['calendario']
            ];

        } catch (Exception $e) {
            log_error("Erro ao criar cobrança", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function checkPayment($txid) {
        if (empty($txid)) {
            throw new Exception('TXID não fornecido');
        }

        // Log para debug
        log_info("Iniciando checkPayment", [
            'txid' => $txid,
            'api_url' => EFI_API_URL . '/v2/cob/' . $txid,
            'certificate_path' => EFI_CERTIFICATE_PATH
        ]);

        // Verificar certificado
        if (!file_exists(EFI_CERTIFICATE_PATH)) {
            log_error("Certificado não encontrado", ['path' => EFI_CERTIFICATE_PATH]);
            throw new Exception('Certificado não encontrado');
        }

        // Verificar token
        if (empty($this->access_token)) {
            log_info("Token de acesso não disponível. Tentando reautenticar...");
            $this->authenticate();
        }

        // Normalizar TXID para busca
        $txid = strtoupper(trim($txid));
        $this->logger->info('Verificando pagamento', ['txid' => $txid]);
        
        // Buscar transação pelo TXID (case-insensitive)
        $stmt = $this->pdo->prepare("SELECT * FROM transacoes WHERE UPPER(txid) = UPPER(?)");
        $stmt->execute([$txid]);
        $transacao = $stmt->fetch();
        
        if (!$transacao) {
            $this->logger->error("Transação não encontrada", ['txid' => $txid]);
            throw new Exception('Transação não encontrada para TXID: ' . $txid);
        }
        
        $this->logger->info('Transação encontrada', [
            'transacao_id' => $transacao['id'],
            'status_atual' => $transacao['status'],
            'valor' => $transacao['valor']
        ]);

        // ... restante do código ...
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_error("Falha ao decodificar JSON", ['error' => json_last_error_msg()]);
            throw new Exception('Erro ao decodificar resposta da API');
        }

        log_debug("Resposta decodificada", ['response_data' => $responseData]);

        // Verificar se a resposta contém os campos necessários
        if (!isset($responseData['status'])) {
            log_error("Campo 'status' não encontrado na resposta", ['response_data' => $responseData]);
            throw new Exception('Resposta da API inválida: campo status não encontrado');
        }

        // Verificar se há pagamentos PIX
        $pixPagamentos = isset($responseData['pix']) ? $responseData['pix'] : [];
        $valorPago = 0;

        // Calcular valor total pago
        foreach ($pixPagamentos as $pix) {
            if (isset($pix['valor'])) {
                $valorPago += floatval($pix['valor']);
            }
        }

        // Verificar se o valor cobrado está presente
        if (!isset($responseData['valor']['original'])) {
            log_error("Valor original não encontrado na resposta", ['response_data' => $responseData]);
            throw new Exception('Resposta da API inválida: valor original não encontrado');
        }

        $valorCobrado = floatval($responseData['valor']['original']);
        $status = $responseData['status'];

        try {
            // Iniciar transação no banco
            $this->pdo->beginTransaction();

            // Determinar o status final e atualizar a transação
            if ($status === 'CONCLUIDA' && $valorPago >= $valorCobrado) {
                $novoStatus = 'aprovado';
                
                log_info("Transação será aprovada", ['transacao' => $transacao]);
                
                // Atualizar transação para aprovado - deixar as triggers cuidarem do saldo
                $stmt = $this->pdo->prepare("
                    UPDATE transacoes 
                    SET status = ?, 
                        data_processamento = NOW(),
                        afeta_saldo = 1
                    WHERE UPPER(txid) = UPPER(?)
                ");
                $this->logger->info("Executando update para aprovado", [
                    'status' => $novoStatus,
                    'txid' => $txid,
                    'afeta_saldo' => 1,
                    'transacao_id' => $transacao['id']
                ]);
                $stmt->execute([
                    $novoStatus,
                    $txid
                ]);
                $this->logger->info("Update executado com sucesso");
            } else if ($status === 'REMOVIDA_PELO_PSP') {
                $stmt = $this->pdo->prepare("
                    UPDATE transacoes 
                    SET status = 'cancelado',
                        data_processamento = NOW()
                    WHERE UPPER(txid) = UPPER(?)
                ");
                $stmt->execute([$txid]);
                $this->logger->info("Transação cancelada", ['txid' => $txid]);
                $novoStatus = 'cancelado';
            } else {
                $novoStatus = 'pendente';
            }

            // Commit da transação no banco
            $this->pdo->commit();

            $this->logger->info("Status final determinado", [
                'status' => $novoStatus,
                'valor_cobrado' => $valorCobrado,
                'valor_pago' => $valorPago,
                'txid' => $txid,
                'transacao_id' => $transacao['id']
            ]);

            return [
                'status' => $novoStatus,
                'valor' => [
                    'cobrado' => $valorCobrado,
                    'pago' => $valorPago
                ],
                'pix' => $pixPagamentos,
                'jogador_id' => $transacao['jogador_id']
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Gera um TXID aleatório alfanumérico conforme especificação EFI Pay
     * Formato: ^[a-zA-Z0-9]{26,35}$
     * 
     * @return string TXID com 32 caracteres alfanuméricos aleatórios
     */
    private function generateRandomTxid() {
        $this->logger->info("Iniciando geração de TXID aleatório");
        
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $txid = '';
        $length = 32; // Usar 32 caracteres (dentro do range 26-35)
        
        for ($i = 0; $i < $length; $i++) {
            $txid .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        $this->logger->info("TXID aleatório gerado", [
            'txid' => $txid,
            'comprimento' => strlen($txid)
        ]);
        
        return $txid;
    }

    /**
     * Generate QR Code for a given location ID
     * 
     * @param string $locationId Location ID from charge creation
     * @return array QR Code data
     */
    private function getQrCode($locationId) {
        $this->logger->info('Gerando QR Code', ['location_id' => $locationId]);
        
        try {
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => EFI_API_URL . '/v2/loc/' . $locationId . '/qrcode',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
                CURLOPT_SSLCERTTYPE => 'P12'
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                $this->logger->error('Erro cURL ao gerar QR Code', ['error' => $err]);
                throw new Exception('Erro ao gerar QR Code: ' . $err);
            }

            if ($httpCode !== 200) {
                $this->logger->error('Erro HTTP ao gerar QR Code', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                throw new Exception('Erro ao gerar QR Code. HTTP Code: ' . $httpCode);
            }

            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Erro ao decodificar JSON do QR Code', ['error' => json_last_error_msg()]);
                throw new Exception('Erro ao decodificar resposta do QR Code');
            }

            $this->logger->info('QR Code gerado com sucesso', ['location_id' => $locationId]);
            return $responseData;

        } catch (Exception $e) {
            $this->logger->error('Erro ao gerar QR Code', [
                'location_id' => $locationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
} 