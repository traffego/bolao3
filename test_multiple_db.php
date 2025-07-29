<?php
// Teste de múltiplas configurações de banco

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔄 TESTE MÚLTIPLAS CONFIGURAÇÕES</h1>";

// Diferentes configurações para testar
$configs = [
    'Config 1 - Padrão' => [
        'host' => 'localhost',
        'dbname' => 'platafo5_bolao3',
        'user' => 'platafo5_bolao3',
        'pass' => 'Traffego444#'
    ],
    'Config 2 - IP Local' => [
        'host' => '127.0.0.1',
        'dbname' => 'platafo5_bolao3', 
        'user' => 'platafo5_bolao3',
        'pass' => 'Traffego444#'
    ],
    'Config 3 - Sem Prefixo' => [
        'host' => 'localhost',
        'dbname' => 'bolao3',
        'user' => 'platafo5_bolao3', 
        'pass' => 'Traffego444#'
    ],
    'Config 4 - User Root' => [
        'host' => 'localhost',
        'dbname' => 'platafo5_bolao3',
        'user' => 'root',
        'pass' => 'Traffego444#'
    ]
];

foreach ($configs as $configName => $config) {
    echo "<h2>🧪 $configName</h2>";
    echo "<p><strong>Host:</strong> {$config['host']}</p>";
    echo "<p><strong>DB:</strong> {$config['dbname']}</p>";
    echo "<p><strong>User:</strong> {$config['user']}</p>";
    
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        echo "<p>✅ <strong>SUCESSO!</strong> Conexão estabelecida</p>";
        
        // Testar uma query simples
        $stmt = $pdo->query("SELECT DATABASE() as current_db, NOW() as current_time");
        $result = $stmt->fetch();
        echo "<p><strong>Banco atual:</strong> {$result['current_db']}</p>";
        echo "<p><strong>Hora servidor:</strong> {$result['current_time']}</p>";
        
        // Contar tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p><strong>Tabelas:</strong> " . count($tables) . "</p>";
        
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>🎉 ESTA CONFIGURAÇÃO FUNCIONA!</strong>";
        echo "</div>";
        
        break; // Se uma configuração funcionar, parar de testar
        
    } catch (PDOException $e) {
        echo "<p>❌ <strong>FALHOU:</strong> " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// Teste específico: verificar se é problema de socket vs TCP
echo "<h2>🔌 Teste de Socket vs TCP</h2>";

echo "<h3>Teste TCP (porta 3306):</h3>";
try {
    $pdo = new PDO("mysql:host=localhost;port=3306;dbname=platafo5_bolao3;charset=utf8mb4", 
                   'platafo5_bolao3', 'Traffego444#', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "<p>✅ Conexão TCP funciona!</p>";
} catch (PDOException $e) {
    echo "<p>❌ Conexão TCP falhou: " . $e->getMessage() . "</p>";
}

echo "<h3>Teste Socket Unix:</h3>";
try {
    $pdo = new PDO("mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=platafo5_bolao3;charset=utf8mb4", 
                   'platafo5_bolao3', 'Traffego444#', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "<p>✅ Conexão Socket funciona!</p>";
} catch (PDOException $e) {
    echo "<p>❌ Conexão Socket falhou: " . $e->getMessage() . "</p>";
}

// Informações do phpinfo relacionadas ao MySQL
echo "<h2>🔍 Informações PHP/MySQL</h2>";
if (function_exists('mysql_get_client_info')) {
    echo "<p><strong>MySQL Client Info:</strong> " . mysql_get_client_info() . "</p>";
}

if (extension_loaded('mysqli')) {
    echo "<p><strong>MySQLi Client Version:</strong> " . mysqli_get_client_version() . "</p>";
}

echo "<p><strong>PDO Drivers:</strong> " . implode(', ', PDO::getAvailableDrivers()) . "</p>";
?>
