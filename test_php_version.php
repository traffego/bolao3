<?php
// Teste específico de versão PHP e compatibilidade

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🐘 TESTE DE VERSÃO PHP</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Informações básicas do PHP
echo "<h2>📊 Informações do PHP</h2>";
echo "<p><strong>Versão Atual:</strong> " . phpversion() . "</p>";
echo "<p><strong>Versão Maior:</strong> " . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "</p>";
echo "<p><strong>SAPI:</strong> " . php_sapi_name() . "</p>";

// Verificar compatibilidade
echo "<h2>✅ Compatibilidade do Projeto</h2>";

// Requisito mínimo: PHP 7.4+
$minVersion = '7.4.0';
$currentVersion = phpversion();
$isCompatible = version_compare($currentVersion, $minVersion, '>=');

echo "<p><strong>Versão Mínima Requerida:</strong> PHP $minVersion</p>";
echo "<p><strong>Versão Atual:</strong> PHP $currentVersion</p>";
echo "<p><strong>Compatível:</strong> " . ($isCompatible ? '✅ SIM' : '❌ NÃO') . "</p>";

if (!$isCompatible) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>🚨 PROBLEMA CRÍTICO</h3>";
    echo "<p>A versão do PHP é incompatível com o projeto!</p>";
    echo "<p><strong>Solução:</strong> Atualizar para PHP 7.4 ou superior.</p>";
    echo "</div>";
}

// Testar recursos específicos usados no projeto
echo "<h2>🔧 Teste de Recursos PHP</h2>";

// Null coalescing operator (??) - PHP 7.0+
echo "<h3>1. Null Coalescing Operator (??) - PHP 7.0+</h3>";
try {
    $test = null;
    $result = $test ?? 'padrão';
    echo "<p>✅ <strong>Funciona:</strong> $result</p>";
} catch (ParseError $e) {
    echo "<p>❌ <strong>Erro:</strong> " . $e->getMessage() . "</p>";
}

// PDO com MySQL
echo "<h3>2. PDO MySQL - Extensão</h3>";
if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
    echo "<p>✅ <strong>PDO MySQL disponível</strong></p>";
} else {
    echo "<p>❌ <strong>PDO MySQL não disponível</strong></p>";
}

// JSON functions
echo "<h3>3. Funções JSON</h3>";
if (function_exists('json_encode') && function_exists('json_decode')) {
    $testArray = ['teste' => 'valor'];
    $json = json_encode($testArray);
    $decoded = json_decode($json, true);
    echo "<p>✅ <strong>JSON OK:</strong> " . $json . "</p>";
} else {
    echo "<p>❌ <strong>Funções JSON não disponíveis</strong></p>";
}

// Session functions
echo "<h3>4. Sessões PHP</h3>";
if (function_exists('session_start')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "<p>✅ <strong>Sessões funcionando</strong></p>";
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
} else {
    echo "<p>❌ <strong>Sessões não disponíveis</strong></p>";
}

// Extensões necessárias
echo "<h2>📦 Extensões Necessárias</h2>";
$requiredExtensions = [
    'mysqli' => 'MySQL Improved',
    'pdo' => 'PHP Data Objects',
    'pdo_mysql' => 'PDO MySQL Driver',
    'json' => 'JSON Support',
    'mbstring' => 'Multibyte String',
    'session' => 'Session Support',
    'curl' => 'cURL Support'
];

foreach ($requiredExtensions as $ext => $name) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅ Carregada' : '❌ Ausente';
    echo "<p><strong>$name ($ext):</strong> $status</p>";
}

// Configurações importantes
echo "<h2>⚙️ Configurações Importantes</h2>";
$configs = [
    'memory_limit' => 'Limite de Memória',
    'max_execution_time' => 'Tempo Máximo de Execução',
    'upload_max_filesize' => 'Tamanho Máximo de Upload',
    'post_max_size' => 'Tamanho Máximo POST',
    'display_errors' => 'Exibir Erros',
    'log_errors' => 'Log de Erros'
];

foreach ($configs as $config => $name) {
    $value = ini_get($config);
    echo "<p><strong>$name:</strong> $value</p>";
}

// Teste de sintaxe do projeto
echo "<h2>🧪 Teste de Sintaxe do Projeto</h2>";

// Testar carregamento dos arquivos principais
$coreFiles = [
    'config/config.php' => 'Configuração Principal',
    'config/database.php' => 'Configuração de Banco',
    'includes/functions.php' => 'Funções Principais'
];

foreach ($coreFiles as $file => $description) {
    echo "<h4>$description</h4>";
    $fullPath = __DIR__ . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "<p>❌ <strong>Arquivo não encontrado:</strong> $file</p>";
        continue;
    }
    
    // Verificar sintaxe usando php -l
    $command = "php -l " . escapeshellarg($fullPath) . " 2>&1";
    $output = shell_exec($command);
    
    if (strpos($output, 'No syntax errors') !== false) {
        echo "<p>✅ <strong>Sintaxe OK:</strong> $file</p>";
    } else {
        echo "<p>❌ <strong>Erro de sintaxe:</strong> $file</p>";
        echo "<pre>$output</pre>";
    }
}

// Resumo final
echo "<h2>📋 Resumo</h2>";
if ($isCompatible) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>✅ PHP COMPATÍVEL</h3>";
    echo "<p>A versão do PHP está compatível com o projeto.</p>";
    echo "<p>Se ainda há erro 500, o problema não é de compatibilidade PHP.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>❌ PHP INCOMPATÍVEL</h3>";
    echo "<p>A versão do PHP pode estar causando o erro 500.</p>";
    echo "<p><strong>Solução:</strong> Configurar o servidor para usar PHP 7.4+</p>";
    echo "</div>";
}

echo "<h2>🛠️ Próximos Passos</h2>";
echo "<ol>";
echo "<li><strong>Se PHP incompatível:</strong> Alterar versão no cPanel</li>";
echo "<li><strong>Se faltam extensões:</strong> Instalar/ativar extensões necessárias</li>";
echo "<li><strong>Se sintaxe com erro:</strong> Corrigir arquivos com problemas</li>";
echo "<li><strong>Se tudo OK:</strong> Problema não é relacionado ao PHP</li>";
echo "</ol>";
?>
