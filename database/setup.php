<?php
require_once __DIR__ . '/../config/config.php';

try {
    // Conectar ao MySQL
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysqli->connect_error) {
        throw new Exception("Erro ao conectar ao MySQL: " . $mysqli->connect_error);
    }
    
    // Ler o arquivo SQL
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Dividir em queries individuais
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    // Executar cada query individualmente
    foreach ($queries as $query) {
        if (!empty($query)) {
            if (!$mysqli->query($query)) {
                throw new Exception("Erro ao executar query: " . $mysqli->error . "\nQuery: " . $query);
            }
        }
    }
    
    echo "Tabelas criadas com sucesso!\n";
    $mysqli->close();
    
} catch (Exception $e) {
    echo "Erro ao criar tabelas: " . $e->getMessage() . "\n";
    if (isset($mysqli)) {
        $mysqli->close();
    }
} 