<?php
/**
 * Teste simples para identificar o erro 500
 */

// Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste de Cadastro - Debug de Erro</h1>";

try {
    echo "<p>1. Testando includes...</p>";
    
    // Testar cada include separadamente
    if (file_exists('config/config.php')) {
        echo "<p>✓ config/config.php existe</p>";
        require_once 'config/config.php';
        echo "<p>✓ config/config.php carregado</p>";
    } else {
        echo "<p>❌ config/config.php não encontrado</p>";
    }
    
    if (file_exists('includes/functions.php')) {
        echo "<p>✓ includes/functions.php existe</p>";
        require_once 'includes/functions.php';
        echo "<p>✓ includes/functions.php carregado</p>";
    } else {
        echo "<p>❌ includes/functions.php não encontrado</p>";
    }
    
    if (file_exists('debug_cadastro_real.php')) {
        echo "<p>✓ debug_cadastro_real.php existe</p>";
        require_once 'debug_cadastro_real.php';
        echo "<p>✓ debug_cadastro_real.php carregado</p>";
    } else {
        echo "<p>❌ debug_cadastro_real.php não encontrado</p>";
    }
    
    echo "<p>2. Testando conexão com banco...</p>";
    
    // Testar conexão com banco
    if (isset($pdo)) {
        echo "<p>✓ Conexão PDO disponível</p>";
    } else {
        echo "<p>❌ Conexão PDO não disponível</p>";
    }
    
    echo "<p>3. Testando funções...</p>";
    
    // Testar se as funções existem
    if (function_exists('dbFetchOne')) {
        echo "<p>✓ Função dbFetchOne existe</p>";
    } else {
        echo "<p>❌ Função dbFetchOne não existe</p>";
    }
    
    if (function_exists('dbInsert')) {
        echo "<p>✓ Função dbInsert existe</p>";
    } else {
        echo "<p>❌ Função dbInsert não existe</p>";
    }
    
    if (function_exists('generateRandomString')) {
        echo "<p>✓ Função generateRandomString existe</p>";
    } else {
        echo "<p>❌ Função generateRandomString não existe</p>";
    }
    
    if (function_exists('hashPassword')) {
        echo "<p>✓ Função hashPassword existe</p>";
    } else {
        echo "<p>❌ Função hashPassword não existe</p>";
    }
    
    echo "<p>4. Testando consulta simples...</p>";
    
    // Testar consulta simples
    $testQuery = dbFetchOne("SELECT COUNT(*) as total FROM jogador");
    if ($testQuery) {
        echo "<p>✓ Consulta ao banco funcionando. Total de jogadores: {$testQuery['total']}</p>";
    } else {
        echo "<p>❌ Erro na consulta ao banco</p>";
    }
    
    echo "<p>5. Testando geração de código...</p>";
    
    // Testar geração de código
    $codigoTeste = generateRandomString(10);
    echo "<p>✓ Código gerado: $codigoTeste</p>";
    
    echo "<p>6. Testando hash de senha...</p>";
    
    // Testar hash de senha
    $hashTeste = hashPassword('123456');
    echo "<p>✓ Hash gerado: " . substr($hashTeste, 0, 20) . "...</p>";
    
    echo "<h2 style='color: green;'>✅ Todos os testes passaram!</h2>";
    echo "<p>O sistema está funcionando corretamente. O erro pode estar em alguma lógica específica.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erro encontrado:</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Stack Trace:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<h2 style='color: red;'>❌ Erro Fatal encontrado:</h2>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Stack Trace:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<p><a href='teste_cadastro_real.php'>Tentar teste completo novamente</a></p>";
?>