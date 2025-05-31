<?php
require_once __DIR__ . '/config/efi_config_db.php';

echo "Configurações da EFIBANK:\n";
echo "------------------------\n";
echo "Client ID: " . EFI_CLIENT_ID . "\n";
echo "Client Secret: " . (EFI_CLIENT_SECRET ? "[Configurado]" : "[Não configurado]") . "\n";
echo "Chave PIX: " . EFI_PIX_KEY . "\n";
echo "API URL: " . EFI_API_URL . "\n";
echo "Certificado: " . (file_exists(EFI_CERTIFICATE_PATH) ? "[Instalado]" : "[Não instalado]") . "\n"; 