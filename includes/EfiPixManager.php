<?php
require_once __DIR__ . '/../config/efi_config_db.php';
require_once __DIR__ . '/../config/database.php';

class EfiPixManager {
    private $access_token;
    private $client;
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authenticate();
    }

    private function authenticate() {
        error_log("\n=== Iniciando autenticação EFIBANK ===");
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
        
        $this->access_token = $result['access_token'];
        error_log("Autenticação concluída com sucesso - Token recebido");
        error_log("=== Fim da autenticação ===\n");
    }

    public function createCharge($user_id, $bolao_id) {
        // Buscar valor do bolão
        $stmt = $this->pdo->prepare("SELECT valor_participacao FROM dados_boloes WHERE id = ?");
        $stmt->execute([$bolao_id]);
        $bolao = $stmt->fetch();

        if (!$bolao) {
            throw new Exception('Bolão não encontrado');
        }

        $valorBolao = $bolao['valor_participacao'];
        if (!is_numeric($valorBolao) || $valorBolao <= 0) {
            throw new Exception('Valor do bolão inválido');
        }

        // Gerar novo TXID
        $timestamp = time();
        $random = bin2hex(random_bytes(8)); // 16 caracteres
        $prefix = 'BOL';
        $userId = str_pad($user_id, 3, '0', STR_PAD_LEFT);
        $bolaoId = str_pad($bolao_id, 3, '0', STR_PAD_LEFT);
        $txid = $prefix . $userId . $bolaoId . $timestamp . $random;
        $txid = substr($txid, 0, 35); // Garantir máximo de 35 caracteres

        $curl = curl_init();
        
        $data = [
            'calendario' => [
                'expiracao' => 3600
            ],
            'valor' => [
                'original' => number_format($valorBolao, 2, '.', '')
            ],
            'chave' => EFI_PIX_KEY,
            'solicitacaoPagador' => "Pagamento Bolão #" . $bolao_id
        ];

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
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception('Erro ao criar cobrança: ' . $err);
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['nome']) && $responseData['nome'] === 'txid_duplicado') {
            // Se o TXID estiver duplicado, tentar novamente com um novo TXID
            return $this->createCharge($user_id, $bolao_id);
        }

        // Salvar TXID para o usuário
        $stmt = $this->pdo->prepare("UPDATE jogador SET txid_pagamento = ? WHERE id = ?");
        $stmt->execute([$txid, $user_id]);

        return $responseData;
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
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception('Erro ao gerar QR Code: ' . $err);
        }

        return json_decode($response, true);
    }

    public function checkPayment($txid) {
        if (empty($txid)) {
            throw new Exception('TXID não fornecido');
        }

        // Log para debug
        error_log("Iniciando verificação de pagamento para TXID: " . $txid);
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

        // Log das opções do CURL
        error_log("Opções do CURL: " . print_r($curlOptions, true));

        curl_setopt_array($curl, $curlOptions);

        // Capturar output verbose do CURL
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        // Log do resultado da chamada
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
            throw new Exception('Erro ao verificar pagamento: ' . $err);
        }

        if ($httpCode !== 200) {
            throw new Exception('Erro ao verificar pagamento. HTTP Code: ' . $httpCode . '. Resposta: ' . $response);
        }

        $result = json_decode($response, true);
        if ($result === null) {
            error_log("Erro ao decodificar JSON: " . json_last_error_msg());
            throw new Exception('Erro ao decodificar resposta da API: ' . json_last_error_msg());
        }

        return $result;
    }
} 