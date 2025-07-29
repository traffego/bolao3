<?php
// Teste básico do servidor
echo "<h1>Teste do Servidor PHP</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Data/Hora: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>HOST: " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p>REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Teste de constantes
echo "<h2>Teste de Configurações</h2>";

// Definir se é localhost
$isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || 
               strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0;

echo "<p>Is Localhost: " . ($isLocalhost ? 'SIM' : 'NÃO') . "</p>";
echo "<p>HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "</p>";

// Testar if DEBUG_MODE
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', $isLocalhost);
    echo "<p>DEBUG_MODE definido: " . (DEBUG_MODE ? 'TRUE' : 'FALSE') . "</p>";
} else {
    echo "<p>DEBUG_MODE já definido: " . (DEBUG_MODE ? 'TRUE' : 'FALSE') . "</p>";
}

// Testar APP_URL
$appUrl = $isLocalhost ? 'http://bolao.traffego.agency' : 'https://bolao.traffego.agency';
echo "<p>APP_URL seria: " . $appUrl . "</p>";

// Testar carregamento de arquivos
echo "<h2>Teste de Includes</h2>";

$configPath = __DIR__ . '/config/config.php';
$databasePath = __DIR__ . '/config/database.php';

echo "<p>Config path: " . $configPath . " - Exists: " . (file_exists($configPath) ? 'SIM' : 'NÃO') . "</p>";
echo "<p>Database path: " . $databasePath . " - Exists: " . (file_exists($databasePath) ? 'SIM' : 'NÃO') . "</p>";

// Testar require dos arquivos
echo "<h2>Teste de Require</h2>";
try {
    require_once $configPath;
    echo "<p>✅ Config carregado com sucesso</p>";
    echo "<p>APP_URL: " . (defined('APP_URL') ? APP_URL : 'NÃO DEFINIDO') . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro ao carregar config: " . $e->getMessage() . "</p>";
}

?>
