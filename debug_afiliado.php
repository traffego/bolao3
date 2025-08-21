<?php
/**
 * Debug do sistema de afiliação
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Simular dados de teste
$testData = [
    'nome' => 'Teste Afiliado',
    'email' => 'teste_afiliado_' . time() . '@teste.com',
    'telefone' => '(21) 99999-9999',
    'referral_code' => 'af12345678' // Código de teste
];

echo "<h2>Debug do Sistema de Afiliação</h2>";
echo "<h3>1. Dados de entrada:</h3>";
echo "<pre>";
print_r($testData);
echo "</pre>";

// Verificar se existe um afiliado ativo para testar
$afiliadoTeste = dbFetchOne("SELECT id, codigo_afiliado FROM jogador WHERE afiliado_ativo = 'ativo' LIMIT 1");
echo "<h3>2. Afiliado de teste encontrado:</h3>";
echo "<pre>";
print_r($afiliadoTeste);
echo "</pre>";

if ($afiliadoTeste) {
    $testData['referral_code'] = $afiliadoTeste['codigo_afiliado'];
    echo "<p><strong>Usando código de afiliado real:</strong> " . $afiliadoTeste['codigo_afiliado'] . "</p>";
}

// Gerar código de afiliado único
do {
    $codigoAfiliado = 'af' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $existeCodigo = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$codigoAfiliado]);
} while ($existeCodigo);

echo "<h3>3. Código de afiliado gerado:</h3>";
echo "<p><strong>" . $codigoAfiliado . "</strong></p>";

// Preparar dados para inserção
$userData = [
    'nome' => $testData['nome'],
    'email' => $testData['email'],
    'senha' => hashPassword('123456'),
    'telefone' => $testData['telefone'],
    'data_cadastro' => date('Y-m-d H:i:s'),
    'status' => 'ativo',
    'codigo_afiliado' => $codigoAfiliado,
    'ref_indicacao' => !empty($testData['referral_code']) ? $testData['referral_code'] : null,
    'afiliado_ativo' => 'ativo'
];

echo "<h3>4. Dados preparados para inserção:</h3>";
echo "<pre>";
print_r($userData);
echo "</pre>";

// Tentar inserir
echo "<h3>5. Tentando inserir no banco...</h3>";
$userId = dbInsert('jogador', $userData);

if ($userId) {
    echo "<p style='color: green;'><strong>✓ Usuário inserido com sucesso! ID: " . $userId . "</strong></p>";
    
    // Verificar se os dados foram salvos corretamente
    $usuarioSalvo = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$userId]);
    echo "<h3>6. Dados salvos no banco:</h3>";
    echo "<pre>";
    print_r($usuarioSalvo);
    echo "</pre>";
    
    // Criar conta
    $contaData = [
        'jogador_id' => $userId,
        'status' => 'ativo'
    ];
    
    $contaId = dbInsert('contas', $contaData);
    if ($contaId) {
        echo "<p style='color: green;'><strong>✓ Conta criada com sucesso! ID: " . $contaId . "</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Erro ao criar conta</strong></p>";
    }
    
} else {
    echo "<p style='color: red;'><strong>✗ Erro ao inserir usuário</strong></p>";
    
    // Verificar logs de erro
    $errorLog = error_get_last();
    if ($errorLog) {
        echo "<h3>Último erro:</h3>";
        echo "<pre>";
        print_r($errorLog);
        echo "</pre>";
    }
}

echo "<hr>";
echo "<h3>7. Estrutura da tabela jogador:</h3>";
$estrutura = dbFetchAll("DESCRIBE jogador");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($estrutura as $campo) {
    echo "<tr>";
    echo "<td>" . $campo['Field'] . "</td>";
    echo "<td>" . $campo['Type'] . "</td>";
    echo "<td>" . $campo['Null'] . "</td>";
    echo "<td>" . $campo['Key'] . "</td>";
    echo "<td>" . $campo['Default'] . "</td>";
    echo "<td>" . $campo['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>