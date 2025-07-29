<?php
// Teste específico de conexão com banco de dados

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🗄️ TESTE DE BANCO - Diagnóstico Completo</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Credenciais do banco
$host = 'localhost';
$dbname = 'platafo5_bolao3';
$user = 'platafo5_bolao3';
$pass = 'Traffego444#';

echo "<h2>📊 Informações de Conexão</h2>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Database:</strong> $dbname</p>";
echo "<p><strong>User:</strong> $user</p>";
echo "<p><strong>Password:</strong> [" . strlen($pass) . " caracteres]</p>";

// Teste 1: Verificar se MySQL está disponível
echo "<h2>🔧 Teste 1: Extensão MySQL</h2>";
if (extension_loaded('pdo_mysql')) {
    echo "<p>✅ <strong>PDO MySQL disponível!</strong></p>";
} else {
    echo "<p>❌ <strong>PDO MySQL NÃO disponível!</strong></p>";
    echo "<p>O servidor precisa ter a extensão pdo_mysql instalada.</p>";
}

// Teste 2: Conexão básica sem banco específico
echo "<h2>🔗 Teste 2: Conexão MySQL Geral</h2>";
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "<p>✅ <strong>Conectado ao MySQL com sucesso!</strong></p>";
    
    // Listar bancos disponíveis
    echo "<h3>📋 Bancos Disponíveis:</h3>";
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($databases as $db) {
        $isTarget = ($db === $dbname) ? " <strong>(TARGET)</strong>" : "";
        echo "<li>$db$isTarget</li>";
    }
    echo "</ul>";
    
    // Verificar se o banco alvo existe
    if (in_array($dbname, $databases)) {
        echo "<p>✅ <strong>Banco '$dbname' encontrado!</strong></p>";
    } else {
        echo "<p>❌ <strong>Banco '$dbname' NÃO encontrado!</strong></p>";
        echo "<p>🚨 <strong>ESTE É O PROBLEMA!</strong> O banco não existe no servidor.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro de conexão MySQL:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Código do erro:</strong> " . $e->getCode() . "</p>";
    
    // Analisar tipos de erro
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p>🚨 <strong>PROBLEMA:</strong> Credenciais incorretas (usuário/senha)</p>";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "<p>🚨 <strong>PROBLEMA:</strong> MySQL não está rodando ou não aceita conexões</p>";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p>🚨 <strong>PROBLEMA:</strong> Banco de dados não existe</p>";
    } else {
        echo "<p>🚨 <strong>PROBLEMA:</strong> Erro desconhecido - verificar com suporte do hosting</p>";
    }
}

// Teste 3: Conexão específica com o banco
echo "<h2>🎯 Teste 3: Conexão com Banco Específico</h2>";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "<p>✅ <strong>Conectado ao banco '$dbname' com sucesso!</strong></p>";
    
    // Listar tabelas
    echo "<h3>📋 Tabelas no Banco:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        echo "<p>✅ <strong>Banco tem " . count($tables) . " tabelas</strong></p>";
    } else {
        echo "<p>⚠️ <strong>Banco existe mas está vazio (sem tabelas)</strong></p>";
        echo "<p>Você precisa importar a estrutura do banco.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao conectar com banco específico:</strong> " . $e->getMessage() . "</p>";
    
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p>🚨 <strong>CONFIRMADO:</strong> O banco '$dbname' não existe!</p>";
        echo "<p><strong>Solução:</strong> Criar o banco ou verificar o nome correto.</p>";
    }
}

// Teste 4: Informações do servidor
echo "<h2>ℹ️ Informações do Servidor</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>MySQL Client Version:</strong> " . (extension_loaded('pdo_mysql') ? PDO::getAttribute(PDO::ATTR_CLIENT_VERSION) : 'N/A') . "</p>";

// Mostrar variáveis de ambiente relacionadas ao MySQL (se disponíveis)
$mysqlVars = ['MYSQL_HOST', 'DB_HOST', 'DATABASE_URL'];
echo "<h3>🌍 Variáveis de Ambiente:</h3>";
foreach ($mysqlVars as $var) {
    $value = getenv($var);
    echo "<p><strong>$var:</strong> " . ($value ?: 'não definida') . "</p>";
}

echo "<h2>🔍 Diagnóstico Final</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>Possíveis Causas do Erro 500:</strong></p>";
echo "<ol>";
echo "<li><strong>Banco não existe:</strong> O banco 'platafo5_bolao3' não foi criado no cPanel</li>";
echo "<li><strong>Credenciais incorretas:</strong> Usuário/senha não conferem</li>";
echo "<li><strong>Permissões:</strong> Usuário não tem permissão para acessar o banco</li>";
echo "<li><strong>MySQL parado:</strong> Serviço MySQL não está funcionando</li>";
echo "</ol>";
echo "</div>";

echo "<h2>✅ Soluções Recomendadas</h2>";
echo "<ol>";
echo "<li><strong>Verificar cPanel:</strong> Confirmar se banco e usuário existem</li>";
echo "<li><strong>Testar credenciais:</strong> Usar phpMyAdmin para testar conexão</li>";
echo "<li><strong>Criar banco:</strong> Se não existir, criar via cPanel</li>";
echo "<li><strong>Importar estrutura:</strong> Fazer upload do arquivo SQL</li>";
echo "</ol>";
?>
