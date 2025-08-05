<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/classes/Logger.php';
require_once 'includes/EfiPixManager.php';

// Inicializa o logger
$logger = Logger::getInstance();

try {
    // Busca configurações do banco
    $config = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
    $pixConfig = json_decode($config['valor'], true);

    echo "=== Configurações Atuais ===\n";
    echo "Client ID: " . substr($pixConfig['client_id'], 0, 20) . "...\n";
    echo "Client Secret: " . substr($pixConfig['client_secret'], 0, 20) . "...\n";
    echo "Ambiente: " . $pixConfig['ambiente'] . "\n";
    echo "API URL: " . EFI_API_URL . "\n\n";

    // Testa autenticação básica
    $authString = base64_encode($pixConfig['client_id'] . ':' . $pixConfig['client_secret']);
    echo "=== String de Autenticação ===\n";
    echo "Authorization: Basic " . $authString . "\n\n";

    // Inicializa EfiPixManager
    $efiPix = new EfiPixManager(false);
    
    echo "=== Testando Autenticação ===\n";
    try {
        $token = $efiPix->authenticate();
        echo "✅ Autenticação bem sucedida!\n";
        echo "Token: " . substr($token, 0, 20) . "...\n";
    } catch (Exception $e) {
        echo "❌ Erro na autenticação: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}