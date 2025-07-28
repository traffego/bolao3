<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Teste de Conexão com Banco de Dados</h2>";

try {
    // Testar configurações do ambiente
    $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || 
                   strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
                   strpos($_SERVER['HTTP_HOST'], '.local') !== false;
    
    echo "Ambiente detectado: " . ($isLocalhost ? 'Local' : 'Produção') . "<br>";
    echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br><br>";
    
    // Definir configurações do banco
    if ($isLocalhost) {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'bolao_football');
        define('DB_USER', 'root');
        define('DB_PASS', '');
    } else {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'platafo5_bolao3');
        define('DB_USER', 'platafo5_bolao3');
        define('DB_PASS', 'Traffego444#');
    }
    
    echo "Configurações do banco:<br>";
    echo "Host: " . DB_HOST . "<br>";
    echo "Database: " . DB_NAME . "<br>";
    echo "User: " . DB_USER . "<br>";
    echo "Password: " . (empty(DB_PASS) ? '(vazia)' : '(definida)') . "<br><br>";
    
    // Testar conexão PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    echo "Tentando conectar...<br>";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "✓ Conexão PDO estabelecida com sucesso!<br>";
    
    // Testar query simples
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✓ Query de teste executada com sucesso!<br>";
    echo "Resultado: " . $result['test'] . "<br>";
    
    // Testar se as tabelas existem
    $tables = ['dados_boloes', 'jogadores', 'palpites'];
    echo "<br>Verificando tabelas principais:<br>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✓ Tabela '$table' existe<br>";
            } else {
                echo "✗ Tabela '$table' NÃO existe<br>";
            }
        } catch (Exception $e) {
            echo "✗ Erro ao verificar tabela '$table': " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>✓ Teste de banco de dados concluído com sucesso!";
    
} catch (PDOException $e) {
    echo "<br>✗ ERRO de conexão com banco de dados:<br>";
    echo "Mensagem: " . $e->getMessage() . "<br>";
    echo "Código: " . $e->getCode() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "<br>✗ ERRO geral:<br>";
    echo "Mensagem: " . $e->getMessage() . "<br>";
    echo "Arquivo: " . $e->getFile() . "<br>";
    echo "Linha: " . $e->getLine() . "<br>";
}
?> 