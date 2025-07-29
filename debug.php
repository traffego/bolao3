<?php
// Arquivo de debug para identificar problemas no servidor

// Ativar todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>🔍 DEBUG - Servidor Bolão</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Host:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";

// Teste 1: Detecção de ambiente
$isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || 
               strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0;

echo "<h2>🌍 Detecção de Ambiente</h2>";
echo "<p><strong>HTTP_HOST:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p><strong>É Localhost:</strong> " . ($isLocalhost ? '✅ SIM' : '❌ NÃO') . "</p>";

// Teste 2: Carregamento de configurações
echo "<h2>⚙️ Teste de Configurações</h2>";
try {
    require_once __DIR__ . '/config/config.php';
    echo "<p>✅ <strong>Config carregado com sucesso!</strong></p>";
    echo "<p><strong>APP_URL:</strong> " . (defined('APP_URL') ? APP_URL : 'NÃO DEFINIDO') . "</p>";
    echo "<p><strong>DEBUG_MODE:</strong> " . (defined('DEBUG_MODE') && DEBUG_MODE ? '✅ ATIVO' : '❌ INATIVO') . "</p>";
} catch (Exception $e) {
    echo "<p>❌ <strong>Erro ao carregar config:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Trace:</strong> " . $e->getTraceAsString() . "</p>";
}

// Teste 3: Conexão com banco de dados
echo "<h2>🗄️ Teste de Banco de Dados</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "<p>✅ <strong>Database config carregado!</strong></p>";
    echo "<p><strong>DB_HOST:</strong> " . (defined('DB_HOST') ? DB_HOST : 'NÃO DEFINIDO') . "</p>";
    echo "<p><strong>DB_NAME:</strong> " . (defined('DB_NAME') ? DB_NAME : 'NÃO DEFINIDO') . "</p>";
    echo "<p><strong>DB_USER:</strong> " . (defined('DB_USER') ? DB_USER : 'NÃO DEFINIDO') . "</p>";
    echo "<p><strong>DB_PASS:</strong> " . (defined('DB_PASS') ? '[DEFINIDO]' : 'NÃO DEFINIDO') . "</p>";
    
    // Testar conexão
    $pdo = getPDO();
    echo "<p>✅ <strong>Conexão com banco bem-sucedida!</strong></p>";
    
    // Testar uma query simples
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dados_boloes");
    $result = $stmt->fetch();
    echo "<p><strong>Bolões no banco:</strong> " . $result['total'] . "</p>";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Erro de banco:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Trace:</strong> " . $e->getTraceAsString() . "</p>";
}

// Teste 4: Verificar diretórios
echo "<h2>📁 Teste de Diretórios</h2>";
$dirs = [
    'templates' => __DIR__ . '/templates',
    'public' => __DIR__ . '/public',
    'logs' => __DIR__ . '/logs',
    'includes' => __DIR__ . '/includes'
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $readable = $exists ? is_readable($path) : false;
    echo "<p><strong>$name:</strong> " . ($exists ? '✅' : '❌') . " exists, " . ($readable ? '✅' : '❌') . " readable</p>";
}

// Teste 5: Verificar permissões de arquivos críticos
echo "<h2>🔒 Teste de Permissões</h2>";
$files = [
    'index.php' => __DIR__ . '/index.php',
    'config.php' => __DIR__ . '/config/config.php',
    'database.php' => __DIR__ . '/config/database.php',
    '.htaccess' => __DIR__ . '/.htaccess'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    echo "<p><strong>$name:</strong> " . ($exists ? '✅' : '❌') . " exists, " . ($readable ? '✅' : '❌') . " readable</p>";
}

echo "<h2>🎯 Próximos Passos</h2>";
echo "<ol>";
echo "<li>Se há erros de config: Corrigir configurações</li>";
echo "<li>Se há erros de banco: Verificar credenciais</li>";
echo "<li>Se há erros de permissão: Ajustar permissões de arquivos</li>";
echo "<li>Se tudo ok: Testar o index.php</li>";
echo "</ol>";
?>
