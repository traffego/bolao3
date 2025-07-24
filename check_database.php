<?php
/**
 * Script para verificar o banco de dados
 */

// Configurações do banco local
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'bolao_football';

echo "<h2>Verificação do Banco de Dados</h2>";

try {
    // Conectar sem especificar o banco
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p><strong>✅ Conectado ao MySQL com sucesso!</strong></p>";
    
    // Verificar se o banco existe
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    $database = $stmt->fetch();
    
    if ($database) {
        echo "<p><strong>✅ Banco '$dbname' existe!</strong></p>";
        
        // Conectar ao banco específico
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Verificar tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p><strong>📋 Tabelas encontradas (" . count($tables) . "):</strong></p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        if (empty($tables)) {
            echo "<p><strong>⚠️ O banco existe mas não tem tabelas!</strong></p>";
            echo "<p>Você precisa executar as migrações para criar as tabelas.</p>";
        }
        
    } else {
        echo "<p><strong>❌ Banco '$dbname' NÃO existe!</strong></p>";
        echo "<p>Você precisa criar o banco primeiro.</p>";
        
        // Mostrar bancos disponíveis
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p><strong>Bancos disponíveis:</strong></p>";
        echo "<ul>";
        foreach ($databases as $db) {
            echo "<li>$db</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<p><strong>❌ Erro de conexão:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Verifique se o MySQL está rodando no XAMPP.</p>";
}

echo "<hr>";
echo "<h3>Próximos Passos:</h3>";
echo "<ol>";
echo "<li>Se o banco não existe: Criar o banco 'bolao_football'</li>";
echo "<li>Se não tem tabelas: Executar as migrações</li>";
echo "<li>Se tudo está ok: O sistema está pronto para uso</li>";
echo "</ol>";
?> 