<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Teste de Estrutura</h2>";

// Verificar se os diretórios existem
$dirs = [
    'config',
    'includes',
    'templates',
    'public',
    'logs',
    'uploads'
];

echo "<h3>Verificando diretórios:</h3>";
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ $dir existe<br>";
        if (is_readable($dir)) {
            echo "  ✓ $dir é legível<br>";
        } else {
            echo "  ✗ $dir NÃO é legível<br>";
        }
        if (is_writable($dir)) {
            echo "  ✓ $dir é gravável<br>";
        } else {
            echo "  ✗ $dir NÃO é gravável<br>";
        }
    } else {
        echo "✗ $dir NÃO existe<br>";
    }
}

// Verificar se os arquivos principais existem
$files = [
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'templates/header.php',
    'templates/footer.php'
];

echo "<h3>Verificando arquivos:</h3>";
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file existe<br>";
        if (is_readable($file)) {
            echo "  ✓ $file é legível<br>";
        } else {
            echo "  ✗ $file NÃO é legível<br>";
        }
    } else {
        echo "✗ $file NÃO existe<br>";
    }
}

// Verificar informações do servidor
echo "<h3>Informações do servidor:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "<br>";

// Verificar extensões PHP necessárias
$extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
echo "<h3>Verificando extensões PHP:</h3>";
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext está carregada<br>";
    } else {
        echo "✗ $ext NÃO está carregada<br>";
    }
}
?> 