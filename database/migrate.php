<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Ler o arquivo SQL
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Dividir em queries individuais
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    // Executar cada query individualmente
    foreach ($queries as $query) {
        if (!empty($query)) {
            if (!$pdo->query($query)) {
                throw new Exception("Erro ao executar query: " . $pdo->errorInfo()[2] . "\nQuery: " . $query);
            }
        }
    }
    
    echo "Migração concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
} 