<?php
/**
 * Teste de cadastro real simplificado
 */

// Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'config/config.php';
    require_once 'includes/functions.php';
} catch (Exception $e) {
    die("Erro ao carregar arquivos: " . $e->getMessage());
}

echo "<h1>Teste de Cadastro Real - Versão Simplificada</h1>";

// Dados de teste fixos
$dadosInsercao = [
    'nome' => 'Teste Usuario Real',
    'email' => 'teste_real_' . time() . '@teste.com',
    'telefone' => '11999999999',
    'senha' => hashPassword('123456'),
    'codigo_afiliado' => generateRandomString(10),
    'ref_indicacao' => 'af2E4FAB3A',
    'afiliado_ativo' => 0,
    'data_cadastro' => date('Y-m-d H:i:s')
];

echo "<h2>Dados para inserção:</h2>";
echo "<pre>";
print_r($dadosInsercao);
echo "</pre>";

// Fazer a inserção real
echo "<h2>Executando inserção:</h2>";
try {
    $resultado = dbInsert('jogador', $dadosInsercao);
    
    if ($resultado) {
        echo "✓ Inserção realizada com sucesso! ID: $resultado<br>";
        
        // Verificar dados salvos
        $dadosSalvos = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$resultado]);
        echo "<h3>Dados salvos no banco:</h3>";
        echo "<pre>";
        print_r($dadosSalvos);
        echo "</pre>";
        
        echo "<h3>Verificação ref_indicacao:</h3>";
        echo "ref_indicacao: " . ($dadosSalvos['ref_indicacao'] ?? 'NULL') . "<br>";
        
    } else {
        echo "✗ Falha na inserção<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "<br>";
}

echo "<br><h2>Teste concluído!</h2>";
?>