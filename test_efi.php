<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'config/efi_config_db.php';

echo "<h2>Teste de Conexão com a EFÍ</h2>";

// Verificar se o certificado existe
echo "<h3>Verificando Certificado:</h3>";
if (file_exists(EFI_CERTIFICATE_PATH)) {
    echo "✅ Certificado encontrado em: " . EFI_CERTIFICATE_PATH . "<br>";
    echo "Permissões: " . substr(sprintf('%o', fileperms(EFI_CERTIFICATE_PATH)), -4) . "<br>";
} else {
    echo "❌ Certificado NÃO encontrado em: " . EFI_CERTIFICATE_PATH . "<br>";
}

// Verificar configurações
echo "<h3>Configurações:</h3>";
echo "Client ID: " . (empty(EFI_CLIENT_ID) ? "Não configurado" : "Configurado") . "<br>";
echo "Client Secret: " . (empty(EFI_CLIENT_SECRET) ? "Não configurado" : "Configurado") . "<br>";
echo "API URL: " . EFI_API_URL . "<br>";

// Tentar autenticação
echo "<h3>Tentando Autenticação:</h3>";
try {
    $curl = curl_init();
    
    $authString = base64_encode(EFI_CLIENT_ID . ':' . EFI_CLIENT_SECRET);
    
    curl_setopt_array($curl, [
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
    ]);
    
    // Capturar output verbose do CURL
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($curl, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    
    // Log do resultado
    echo "HTTP Code: " . $httpCode . "<br>";
    if ($err) {
        echo "Erro CURL: " . $err . "<br>";
    }
    echo "Resposta: " . $response . "<br>";
    
    // Log verbose do CURL
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    echo "<pre>CURL Verbose Log: " . htmlspecialchars($verboseLog) . "</pre>";
    fclose($verbose);
    
    curl_close($curl);
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
} 