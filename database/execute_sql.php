<?php
require_once __DIR__ . '/../config/database.php';

$sql = file_get_contents(__DIR__ . '/update_config_table.sql');

// Dividir o SQL em comandos individuais
$commands = array_filter(
    array_map(
        'trim',
        explode(';', $sql)
    ),
    'strlen'
);

// Executar cada comando separadamente
foreach ($commands as $command) {
    try {
        $pdo->exec($command);
        echo "Comando SQL executado com sucesso: " . substr($command, 0, 50) . "...\n";
    } catch (PDOException $e) {
        echo "Erro ao executar SQL: " . $e->getMessage() . "\n";
        echo "Comando que falhou: " . $command . "\n";
    }
} 