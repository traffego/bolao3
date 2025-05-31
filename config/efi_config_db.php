<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/database_functions.php';

// Buscar configurações do Pix no banco
$pixConfig = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
$pixConfig = $pixConfig ? json_decode($pixConfig['valor'], true) : [];

// Definir constantes com base nas configurações do banco
define('EFI_CLIENT_ID', $pixConfig['client_id'] ?? '');
define('EFI_CLIENT_SECRET', $pixConfig['client_secret'] ?? '');
define('EFI_CERTIFICATE_PATH', __DIR__ . '/certificates/certificate.p12');
define('EFI_API_URL', $pixConfig['ambiente'] === 'homologacao' ? 'https://pix-h.api.efipay.com.br' : 'https://pix.api.efipay.com.br');
define('EFI_PIX_KEY', $pixConfig['pix_key'] ?? ''); 