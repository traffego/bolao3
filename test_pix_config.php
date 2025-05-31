<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/database_functions.php';

echo "Configurações do PIX no banco de dados:\n";
echo "--------------------------------\n";

// Buscar diretamente da tabela
$stmt = $pdo->prepare("SELECT * FROM configuracoes WHERE nome_configuracao = 'efi_pix_config' AND categoria = 'pagamentos'");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if ($config) {
    echo "ID: " . $config['id'] . "\n";
    echo "Nome: " . $config['nome_configuracao'] . "\n";
    echo "Categoria: " . $config['categoria'] . "\n";
    echo "Valor: " . $config['valor'] . "\n";
    echo "\nValor decodificado:\n";
    print_r(json_decode($config['valor'], true));
} else {
    echo "Nenhuma configuração encontrada.\n";
}

// Verificar todas as configurações
echo "\n\nTodas as configurações na tabela:\n";
echo "--------------------------------\n";
$stmt = $pdo->query("SELECT * FROM configuracoes");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\nID: " . $row['id'] . "\n";
    echo "Nome: " . $row['nome_configuracao'] . "\n";
    echo "Categoria: " . $row['categoria'] . "\n";
    echo "Valor: " . $row['valor'] . "\n";
    echo "---\n";
} 