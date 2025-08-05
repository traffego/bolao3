<?php
require_once 'config/database.php';

// Nova configuração com as credenciais fornecidas
$newConfig = [
    'ambiente' => 'producao',
    'client_id' => 'Client_Id_3e9ce7b7f569d0a4aa8f9ec8b172c3ed7dd9d948',
    'client_secret' => 'Client_Secret_31e8f33edba74286002f4c91a2df6896f2764fd1',
    'pix_key' => '60409292-a359-4992-9f5f-5886bace6fe6',
    'webhook_url' => 'https://bolao.traffego.agency/webhook_pix.php'
];

// Atualizar configuração
$sql = "UPDATE configuracoes SET valor = ? WHERE nome_configuracao = ? AND categoria = ?";
$result = dbExecute($sql, [json_encode($newConfig), 'efi_pix_config', 'pagamentos']);

if ($result) {
    echo "✅ Configuração atualizada com sucesso!\n";
    echo "\nNova configuração:\n";
    echo json_encode($newConfig, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Erro ao atualizar configuração!\n";
}