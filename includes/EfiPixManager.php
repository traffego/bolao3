<?php
require_once __DIR__ . '/../config/efi_config_db.php';
require_once __DIR__ . '/../config/database.php';

class EfiPixManager {
    private $access_token;
    private $client;
    private $pdo;

    private function validateCertificate() {
        if (!file_exists(EFI_CERTIFICATE_PATH)) {
            error_log("ERRO: Certificado não encontrado em: " . EFI_CERTIFICATE_PATH);
            throw new Exception('Certificado não encontrado. Por favor, faça o upload do certificado nas configurações.');
        }

        // Tentar ler o certificado
        $cert_content = @file_get_contents(EFI_CERTIFICATE_PATH);
        if ($cert_content === false) {
            error_log("ERRO: Não foi possível ler o certificado. Verifique as permissões.");
            throw new Exception('Erro ao ler o certificado. Verifique as permissões do arquivo.');
        }

        // Verificar se é um certificado P12 válido
        if (@openssl_pkcs12_read($cert_content, $cert_info, '') === false) {
            error_log("ERRO: Certificado P12 inválido");
            throw new Exception('Certificado P12 inválido. Por favor, gere um novo certificado no painel da Efí.');
        }

        error_log("Certificado validado com sucesso");
        return true;
    }

    public function registerWebhook() {
        // Verificar se é localhost
        if (strpos(WEBHOOK_URL, 'localhost') !== false || strpos(WEBHOOK_URL, '127.0.0.1') !== false) {
            error_log("Ignorando registro de webhook em ambiente local");
            return;
        }

        $curl = curl_init();
        
        $data = [
            'webhookUrl' => WEBHOOK_URL
        ];

        error_log("Registrando webhook: " . json_encode($data));
        
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

        error_log("Resposta do registro de webhook (HTTP $httpCode): " . $response);

        if ($err) {
            throw new Exception('Erro ao registrar webhook: ' . $err);
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
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
            error_log("Ambiente local detectado - Webhook será ignorado");
            // Em ambiente local, vamos apenas autenticar
            $this->validateCertificate();
            $this->authenticate();
        } else {
            error_log("Ambiente de produção detectado - Webhook será registrado");
            $this->validateCertificate();
            $this->authenticate();
            
            // Registrar webhook apenas em produção
            try {
                $this->registerWebhook();
            } catch (Exception $e) {
                error_log("Erro ao registrar webhook: " . $e->getMessage());
                // Não vamos interromper o fluxo se falhar o registro do webhook
            }
        }
    }

    private function authenticate($retryCount = 0) {
        error_log("\n=== Iniciando autenticação EFIBANK (Tentativa " . ($retryCount + 1) . ") ===");
        error_log("API URL: " . EFI_API_URL);
        error_log("Client ID: " . (empty(EFI_CLIENT_ID) ? "Vazio" : "Configurado"));
        error_log("Client Secret: " . (empty(EFI_CLIENT_SECRET) ? "Vazio" : "Configurado"));
        error_log("Certificado Path: " . EFI_CERTIFICATE_PATH);
        error_log("Certificado existe: " . (file_exists(EFI_CERTIFICATE_PATH) ? "Sim" : "Não"));

        if (!file_exists(EFI_CERTIFICATE_PATH)) {
            error_log("ERRO: Certificado não encontrado em: " . EFI_CERTIFICATE_PATH);
            throw new Exception('Certificado não encontrado. Por favor, faça o upload do certificado nas configurações.');
        }

        if (empty(EFI_CLIENT_ID) || empty(EFI_CLIENT_SECRET)) {
            error_log("ERRO: Credenciais não configuradas");
            throw new Exception('Credenciais da API não configuradas.');
        }
        
        $curl = curl_init();
        
        $authString = base64_encode(EFI_CLIENT_ID . ':' . EFI_CLIENT_SECRET);
        error_log("String de autenticação gerada (base64)");

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

        error_log("Configurando opções do CURL para autenticação");
        error_log("Opções CURL: " . print_r($curlOptions, true));

        curl_setopt_array($curl, $curlOptions);

        // Capturar output verbose do CURL
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose);

        error_log("Executando requisição de autenticação...");
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        // Log do resultado
        error_log("HTTP Code: " . $httpCode);
        if ($err) {
            error_log("Erro CURL: " . $err);
        }
        error_log("Resposta: " . $response);

        // Log verbose do CURL
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("CURL Verbose Log: " . $verboseLog);
        fclose($verbose);

        curl_close($curl);

        if ($err) {
            error_log("ERRO: Falha na autenticação - " . $err);
            throw new Exception('Erro na autenticação: ' . $err);
        }

        if ($httpCode !== 200) {
            error_log("ERRO: Código HTTP inválido na autenticação - " . $httpCode);
            throw new Exception('Erro na autenticação. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
        }

        $result = json_decode($response, true);
        if (!isset($result['access_token'])) {
            error_log("ERRO: Token não recebido na resposta");
            error_log("Resposta completa: " . print_r($result, true));
            throw new Exception('Erro na autenticação: Token não recebido');
        }
        
        // Verificar escopos necessários
        $required_scopes = ['cob.write', 'cob.read', 'pix.read', 'webhook.write', 'webhook.read'];
        $received_scopes = explode(' ', $result['scope']);
        
        foreach ($required_scopes as $scope) {
            if (!in_array($scope, $received_scopes)) {
                error_log("ERRO: Escopo necessário não encontrado: " . $scope);
                throw new Exception('Erro na autenticação: Escopo ' . $scope . ' não autorizado');
            }
        }
        
        $this->access_token = $result['access_token'];
        error_log("Autenticação concluída com sucesso - Token recebido");
        error_log("Escopos autorizados: " . $result['scope']);
        error_log("=== Fim da autenticação ===\n");
    }

    public function createCharge($user_id, $valor, $referencia = null, $descricao = null) {
        if (!is_numeric($valor) || $valor <= 0) {
            throw new Exception('Valor inválido');
        }

        // Buscar conta do usuário
        $stmt = $this->pdo->prepare("SELECT id FROM contas WHERE jogador_id = ?");
        $stmt->execute([$user_id]);
        $conta = $stmt->fetch();

        if (!$conta) {
            throw new Exception('Conta do usuário não encontrada');
        }

        // Gerar novo TXID se não fornecido
        $timestamp = time();
        $random = bin2hex(random_bytes(8)); // 16 caracteres
        $prefix = 'DEP';
        $userId = str_pad($user_id, 3, '0', STR_PAD_LEFT);
        $txid = $prefix . $userId . $timestamp . $random;
        $txid = substr($txid, 0, 35); // Garantir máximo de 35 caracteres

        // Usar descrição fornecida ou padrão
        $descricao = $descricao ?: "Depósito #{$referencia}";

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

            error_log("Dados enviados para API: " . json_encode($data));

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

            error_log("Resposta da API (HTTP $httpCode): " . $response);

            if ($err) {
                throw new Exception('Erro ao criar cobrança: ' . $err);
            }

            if ($httpCode !== 200 && $httpCode !== 201) {
                throw new Exception('Erro ao criar cobrança. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
            }

            $responseData = json_decode($response, true);
            if (isset($responseData['nome']) && $responseData['nome'] === 'txid_duplicado') {
                // Se o TXID estiver duplicado, tentar novamente
                return $this->createCharge($user_id, $valor, $referencia, $descricao);
            }

            // Gerar QR Code
            $locationId = $responseData['loc']['id'];
            $qrCode = $this->getQrCode($locationId);
            
            error_log("QR Code gerado: " . json_encode($qrCode));
            
            return [
                'txid' => $txid,
                'status' => $responseData['status'],
                'valor' => $responseData['valor']['original'],
                'qrcode' => $qrCode['imagemQrcode'],
                'qrcode_texto' => $qrCode['qrcode'],
                'calendario' => $responseData['calendario']
            ];

        } catch (Exception $e) {
            error_log("Erro ao criar cobrança: " . $e->getMessage());
            throw $e;
        }
    }

    public function getQrCode($location_id) {
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

        error_log("Resposta getQrCode (HTTP $httpCode): " . $response);

        if ($err) {
            throw new Exception('Erro ao gerar QR Code: ' . $err);
        }

        if ($httpCode !== 200) {
            throw new Exception('Erro ao gerar QR Code. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
        }

        $qrCodeData = json_decode($response, true);
        if (!isset($qrCodeData['qrcode'])) {
            throw new Exception('Resposta inválida ao gerar QR Code');
        }

        return $qrCodeData;
    }

    public function checkPayment($txid) {
        if (empty($txid)) {
            throw new Exception('TXID não fornecido');
        }

        // Log para debug
        error_log("\n=== Iniciando checkPayment ===");
        error_log("TXID: " . $txid);
        error_log("URL da API: " . EFI_API_URL . '/v2/cob/' . $txid);
        error_log("Certificado Path: " . EFI_CERTIFICATE_PATH);

        // Verificar certificado
        if (!file_exists(EFI_CERTIFICATE_PATH)) {
            error_log("Certificado não encontrado em: " . EFI_CERTIFICATE_PATH);
            throw new Exception('Certificado não encontrado');
        }

        // Verificar token
        if (empty($this->access_token)) {
            error_log("Token de acesso não disponível. Tentando reautenticar...");
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

        error_log("Configurando CURL com opções: " . print_r($curlOptions, true));
        curl_setopt_array($curl, $curlOptions);

        // Capturar output verbose do CURL
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        // Log do resultado
        error_log("HTTP Code: " . $httpCode);
        if ($err) {
            error_log("Erro CURL: " . $err);
        }
        error_log("Resposta bruta: " . $response);

        // Log verbose do CURL
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("CURL Verbose Log: " . $verboseLog);
        fclose($verbose);

        curl_close($curl);

        if ($err) {
            error_log("ERRO na verificação: " . $err);
            throw new Exception('Erro ao verificar pagamento: ' . $err);
        }

        if ($httpCode !== 200) {
            error_log("ERRO: Código HTTP inválido - " . $httpCode);
            throw new Exception('Erro ao verificar pagamento. HTTP Code: ' . $httpCode);
        }

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERRO: Falha ao decodificar JSON - " . json_last_error_msg());
            throw new Exception('Erro ao decodificar resposta da API');
        }

        error_log("Resposta decodificada: " . print_r($responseData, true));

        // Verificar se a resposta contém os campos necessários
        if (!isset($responseData['status'])) {
            error_log("ERRO: Campo 'status' não encontrado na resposta");
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
            error_log("ERRO: Valor original não encontrado na resposta");
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
                
                error_log("Transação será aprovada. Dados da transação: " . print_r($transacao, true));
                
                // Buscar saldo atual (soma de todas as transações aprovadas que afetam saldo)
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(CASE 
                        WHEN tipo IN ('deposito', 'premio', 'bonus') THEN valor 
                        WHEN tipo IN ('saque', 'aposta') THEN -valor 
                    END), 0) as saldo_atual
                    FROM transacoes 
                    WHERE conta_id = ? 
                    AND status = 'aprovado' 
                    AND afeta_saldo = TRUE
                ");
                $stmt->execute([$transacao['conta_id']]);
                $result = $stmt->fetch();
                $saldoAtual = $result['saldo_atual'];
                error_log("Saldo atual calculado: " . $saldoAtual);

                // Atualizar saldos na transação
                $stmt = $this->pdo->prepare("
                    UPDATE transacoes 
                    SET status = ?, 
                        saldo_anterior = ?,
                        saldo_posterior = ?,
                        data_processamento = NOW(),
                        afeta_saldo = 1
                    WHERE txid = ?
                ");
                error_log("Executando update com parâmetros: " . json_encode([
                    'status' => $novoStatus,
                    'saldo_anterior' => $saldoAtual,
                    'saldo_posterior' => $saldoAtual + $valorPago,
                    'txid' => $txid,
                    'afeta_saldo' => 1
                ]));
                $stmt->execute([
                    $novoStatus,
                    $saldoAtual,
                    $saldoAtual + $valorPago,
                    $txid
                ]);
                error_log("Update executado com sucesso");
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

            error_log("Status final determinado: " . $novoStatus);
            error_log("Valor cobrado: " . $valorCobrado);
            error_log("Valor pago: " . $valorPago);
            error_log("=== Fim checkPayment ===\n");

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
} 