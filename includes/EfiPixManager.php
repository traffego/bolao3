<?php
require_once __DIR__ . '/../config/efi_config_db.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/classes/Logger.php';

class EfiPixManager {
    private $access_token;
    private $client;
    private $pdo;

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

    public function registerWebhook() {
        // Verificar se é localhost
        if (strpos(WEBHOOK_URL, 'localhost') !== false || strpos(WEBHOOK_URL, '127.0.0.1') !== false) {
            log_info("Ignorando registro de webhook em ambiente local");
            return;
        }

        $curl = curl_init();
        
        $data = [
            'webhookUrl' => WEBHOOK_URL
        ];

        log_debug("Registrando webhook", ['data' => $data]);
        
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

        log_debug("Resposta do registro de webhook", [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $err
        ]);

        if ($err) {
            log_error("Erro ao registrar webhook", ['error' => $err]);
            throw new Exception('Erro ao registrar webhook: ' . $err);
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            log_error("Erro ao registrar webhook", [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            throw new Exception('Erro ao registrar webhook. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
        }

        return json_decode($response, true);
    }

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        // Verificar ambiente
        $isLocalhost = strpos(WEBHOOK_URL, 'localhost') !== false || strpos(WEBHOOK_URL, '127.0.0.1') !== false;
        $isProduction = strpos(EFI_API_URL, 'pix.api.efipay.com.br') !== false;
        
        if ($isLocalhost) {
            log_info("Ambiente local detectado - Webhook será ignorado");
            // Em ambiente local, vamos apenas autenticar
            $this->validateCertificate();
            $this->authenticate();
        } else {
            log_info("Ambiente de produção detectado - Webhook será registrado");
            $this->validateCertificate();
            $this->authenticate();
            
            // Registrar webhook apenas em produção
            try {
                $this->registerWebhook();
            } catch (Exception $e) {
                log_error("Erro ao registrar webhook", ['error' => $e->getMessage()]);
                // Não vamos interromper o fluxo se falhar o registro do webhook
            }
        }
    }

    private function authenticate($retryCount = 0) {
        log_info("Iniciando autenticação EFIBANK", [
            'tentativa' => $retryCount + 1,
            'api_url' => EFI_API_URL,
            'client_id_configured' => !empty(EFI_CLIENT_ID),
            'client_secret_configured' => !empty(EFI_CLIENT_SECRET),
            'certificate_path' => EFI_CERTIFICATE_PATH,
            'certificate_exists' => file_exists(EFI_CERTIFICATE_PATH)
        ]);

        if (!file_exists(EFI_CERTIFICATE_PATH)) {
            log_error("Certificado não encontrado", ['path' => EFI_CERTIFICATE_PATH]);
            throw new Exception('Certificado não encontrado. Por favor, faça o upload do certificado nas configurações.');
        }

        if (empty(EFI_CLIENT_ID) || empty(EFI_CLIENT_SECRET)) {
            log_error("Credenciais não configuradas");
            throw new Exception('Credenciais da API não configuradas.');
        }
        
        $curl = curl_init();
        
        $authString = base64_encode(EFI_CLIENT_ID . ':' . EFI_CLIENT_SECRET);
        log_debug("String de autenticação gerada (base64)");

        $curlOptions = [
            CURLOPT_URL => EFI_API_URL . '/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials']),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $authString
            ],
            CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => true
        ];

        log_debug("Configurando opções do CURL para autenticação", ['curl_options' => $curlOptions]);

        curl_setopt_array($curl, $curlOptions);

        // Capturar output verbose do CURL
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose);

        log_debug("Executando requisição de autenticação...");
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        // Log do resultado
        log_debug("Resposta da autenticação", [
            'http_code' => $httpCode,
            'error' => $err,
            'response' => $response
        ]);

        // Log verbose do CURL
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        log_trace("CURL Verbose Log", ['verbose_log' => $verboseLog]);
        fclose($verbose);

        curl_close($curl);

        if ($err) {
            log_error("Falha na autenticação", ['error' => $err]);
            throw new Exception('Erro na autenticação: ' . $err);
        }

        if ($httpCode !== 200) {
            log_error("Código HTTP inválido na autenticação", [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            throw new Exception('Erro na autenticação. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
        }

        $result = json_decode($response, true);
        if (!isset($result['access_token'])) {
            log_error("Token não recebido na resposta", ['result' => $result]);
            throw new Exception('Erro na autenticação: Token não recebido');
        }
        
        // Verificar escopos necessários
        $required_scopes = ['cob.write', 'cob.read', 'pix.read', 'webhook.write', 'webhook.read'];
        $received_scopes = explode(' ', $result['scope']);
        
        foreach ($required_scopes as $scope) {
            if (!in_array($scope, $received_scopes)) {
                log_error("Escopo necessário não encontrado", ['scope' => $scope]);
                throw new Exception('Erro na autenticação: Escopo ' . $scope . ' não autorizado');
            }
        }
        
        $this->access_token = $result['access_token'];
        log_info("Autenticação concluída com sucesso", [
            'escopos_autorizados' => $result['scope']
        ]);
    }

    public function createCharge($user_id, $valor, $referencia = null, $descricao = null) {
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

    public function getQrCode($location_id) {
        log_debug("Iniciando getQrCode", [
            'location_id' => $location_id,
            'url' => EFI_API_URL . '/v2/loc/' . $location_id . '/qrcode',
            'access_token_configured' => !empty($this->access_token),
            'certificate_path' => EFI_CERTIFICATE_PATH,
            'certificate_exists' => file_exists(EFI_CERTIFICATE_PATH)
        ]);
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => EFI_API_URL . '/v2/loc/' . $location_id . '/qrcode',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token
            ],
            CURLOPT_SSLCERT => EFI_CERTIFICATE_PATH,
            CURLOPT_SSLCERTTYPE => 'P12'
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        log_debug("Resposta getQrCode", [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $err
        ]);

        if ($err) {
            log_error("Erro cURL ao gerar QR Code", ['error' => $err]);
            throw new Exception('Erro ao gerar QR Code: ' . $err);
        }

        if ($httpCode !== 200) {
            log_error("HTTP Code inválido ao gerar QR Code", [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            throw new Exception('Erro ao gerar QR Code. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
        }

        $qrCodeData = json_decode($response, true);
        log_debug("QR Code data decodificado", ['qr_code_data' => $qrCodeData]);
        
        if (!isset($qrCodeData['qrcode'])) {
            log_error("Resposta inválida ao gerar QR Code - campo qrcode não encontrado", ['qr_code_data' => $qrCodeData]);
            throw new Exception('Resposta inválida ao gerar QR Code');
        }

        return $qrCodeData;
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

        // Buscar transação
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.jogador_id 
            FROM transacoes t 
            INNER JOIN contas c ON t.conta_id = c.id 
            WHERE t.txid = ?
        ");
        $stmt->execute([$txid]);
        $transacao = $stmt->fetch();

        if (!$transacao) {
            throw new Exception('Transação não encontrada');
        }

        $curl = curl_init();
        
        $curlOptions = [
            CURLOPT_URL => EFI_API_URL . '/v2/cob/' . $txid,
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
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => true
        ];

        log_debug("Configurando CURL", ['curl_options' => $curlOptions]);
        curl_setopt_array($curl, $curlOptions);

        // Capturar output verbose do CURL
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        // Log do resultado
        log_debug("Resposta da verificação", [
            'http_code' => $httpCode,
            'error' => $err,
            'response' => $response
        ]);

        // Log verbose do CURL
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        log_trace("CURL Verbose Log", ['verbose_log' => $verboseLog]);
        fclose($verbose);

        curl_close($curl);

        if ($err) {
            log_error("Erro na verificação", ['error' => $err]);
            throw new Exception('Erro ao verificar pagamento: ' . $err);
        }

        if ($httpCode !== 200) {
            log_error("Código HTTP inválido", ['http_code' => $httpCode]);
            throw new Exception('Erro ao verificar pagamento. HTTP Code: ' . $httpCode);
        }

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
                    WHERE txid = ?
                ");
                log_debug("Executando update", [
                    'status' => $novoStatus,
                    'txid' => $txid,
                    'afeta_saldo' => 1
                ]);
                $stmt->execute([
                    $novoStatus,
                    $txid
                ]);
                log_debug("Update executado com sucesso");
            } else if ($status === 'REMOVIDA_PELO_PSP') {
                $stmt = $this->pdo->prepare("
                    UPDATE transacoes 
                    SET status = 'cancelado',
                        data_processamento = NOW()
                    WHERE txid = ?
                ");
                $stmt->execute([$txid]);
                $novoStatus = 'cancelado';
            } else {
                $novoStatus = 'pendente';
            }

            // Commit da transação no banco
            $this->pdo->commit();

            log_info("Status final determinado", [
                'status' => $novoStatus,
                'valor_cobrado' => $valorCobrado,
                'valor_pago' => $valorPago
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
        log_debug("Iniciando geração de TXID aleatório");
        
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $txid = '';
        $length = 32; // Usar 32 caracteres (dentro do range 26-35)
        
        for ($i = 0; $i < $length; $i++) {
            $txid .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        log_debug("TXID aleatório gerado", [
            'txid' => $txid,
            'comprimento' => strlen($txid)
        ]);
        
        return $txid;
    }
} 