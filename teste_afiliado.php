<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Verificar se existem afiliados ativos
$afiliadosAtivos = dbFetchAll("SELECT id, nome, email, codigo_afiliado, afiliado_ativo FROM jogador WHERE afiliado_ativo = 'ativo'");

echo "<h2>Afiliados Ativos no Sistema:</h2>";
if (empty($afiliadosAtivos)) {
    echo "<p style='color: red;'>❌ Nenhum afiliado ativo encontrado!</p>";
    
    // Verificar todos os jogadores
    $todosJogadores = dbFetchAll("SELECT id, nome, email, codigo_afiliado, afiliado_ativo FROM jogador LIMIT 10");
    echo "<h3>Primeiros 10 jogadores no sistema:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Código Afiliado</th><th>Status Afiliado</th></tr>";
    foreach ($todosJogadores as $jogador) {
        echo "<tr>";
        echo "<td>{$jogador['id']}</td>";
        echo "<td>{$jogador['nome']}</td>";
        echo "<td>{$jogador['email']}</td>";
        echo "<td>{$jogador['codigo_afiliado']}</td>";
        echo "<td>{$jogador['afiliado_ativo']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✅ Encontrados " . count($afiliadosAtivos) . " afiliados ativos:</p>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Código Afiliado</th><th>Status</th></tr>";
    foreach ($afiliadosAtivos as $afiliado) {
        echo "<tr>";
        echo "<td>{$afiliado['id']}</td>";
        echo "<td>{$afiliado['nome']}</td>";
        echo "<td>{$afiliado['email']}</td>";
        echo "<td><strong>{$afiliado['codigo_afiliado']}</strong></td>";
        echo "<td>{$afiliado['afiliado_ativo']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Testar código específico
if (isset($_GET['test_code'])) {
    $testCode = $_GET['test_code'];
    echo "<h3>Testando código: {$testCode}</h3>";
    
    $referral = dbFetchOne("SELECT id, nome, codigo_afiliado, afiliado_ativo FROM jogador WHERE codigo_afiliado = ?", [$testCode]);
    
    if ($referral) {
        echo "<p style='color: blue;">✅ Código encontrado:</p>";
        echo "<ul>";
        echo "<li>ID: {$referral['id']}</li>";
        echo "<li>Nome: {$referral['nome']}</li>";
        echo "<li>Código: {$referral['codigo_afiliado']}</li>";
        echo "<li>Status: {$referral['afiliado_ativo']}</li>";
        echo "</ul>";
        
        if ($referral['afiliado_ativo'] === 'ativo') {
            echo "<p style='color: green;'>✅ Código VÁLIDO para uso!</p>";
        } else {
            echo "<p style='color: red;'>❌ Código encontrado mas afiliado não está ativo!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Código não encontrado no sistema!</p>";
    }
}

echo "<hr>";
echo "<p><a href='?test_code=af2E4FAB3A'>Testar código af2E4FAB3A</a></p>";
echo "<p><a href='teste_afiliado.php'>Recarregar página</a></p>";
?>