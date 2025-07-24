<?php
// Configurações do banco de dados local
define('DB_HOST', 'localhost');
define('DB_NAME', 'bolao_football');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Conectar ao MySQL
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Criar banco de dados se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Banco de dados criado ou já existe.\n";
    
    // Selecionar o banco
    $pdo->exec("USE " . DB_NAME);
    
    // Ler o arquivo SQL
    $sql = file_get_contents('bkp1.sql');
    if ($sql === false) {
        throw new Exception("Erro ao ler o arquivo bkp1.sql");
    }
    
    // Executar o SQL
    $pdo->exec($sql);
    echo "Backup importado com sucesso!\n";
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
} 