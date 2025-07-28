<?php
// Teste de conexão com o banco de dados
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando teste de conexão...<br>";

try {
    require_once 'config/config.php';
    echo "Config carregada com sucesso<br>";
    
    require_once 'config/database.php';
    echo "Database carregada com sucesso<br>";
    
    // Testar conexão
    $pdo = getPDO();
    echo "Conexão com banco estabelecida com sucesso<br>";
    
    // Testar uma query simples
    $result = dbFetchOne("SELECT 1 as test");
    if ($result) {
        echo "Query de teste executada com sucesso<br>";
    } else {
        echo "Erro na query de teste<br>";
    }
    
    echo "Teste concluído com sucesso!";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
}
?> 