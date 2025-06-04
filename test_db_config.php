<?php
require_once __DIR__ . '/config/database.php';

$pixConfig = dbFetchOne("SELECT * FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");

echo "Configuração no banco de dados:\n";
echo "--------------------------\n";
if ($pixConfig) {
    echo "ID: " . $pixConfig['id'] . "\n";
    echo "Nome: " . $pixConfig['nome_configuracao'] . "\n";
    echo "Categoria: " . $pixConfig['categoria'] . "\n";
    echo "Valor: " . $pixConfig['valor'] . "\n";
} else {
    echo "Nenhuma configuração encontrada.\n";
} 