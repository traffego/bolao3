<?php
/**
 * Debug completo do fluxo de referência
 * Testa cada etapa do processo
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h2>Debug Completo do Fluxo de Referência</h2>";
echo "<style>body { font-family: Arial; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

// 1. Testar se as funções estão disponíveis
echo "<h3>1. Verificação de Funções</h3>";
echo "<p class='" . (function_exists('initReferralSystem') ? 'success' : 'error') . "'>initReferralSystem: " . (function_exists('initReferralSystem') ? '✓ Disponível' : '✗ Não encontrada') . "</p>";
echo "<p class='" . (function_exists('validateReferralCode') ? 'success' : 'error') . "'>validateReferralCode: " . (function_exists('validateReferralCode') ? '✓ Disponível' : '✗ Não encontrada') . "</p>";
echo "<p class='" . (function_exists('generateUniqueAffiliateCode') ? 'success' : 'error') . "'>generateUniqueAffiliateCode: " . (function_exists('generateUniqueAffiliateCode') ? '✓ Disponível' : '✗ Não encontrada') . "</p>";

// 2. Simular URL com parâmetro ref
echo "<h3>2. Simulação de URL com ?ref=CVh9Gu4d84</h3>";
$_GET['ref'] = 'CVh9Gu4d84';
echo "<p class='info'>Parâmetro GET[ref] definido: {$_GET['ref']}</p>";

// 3. Testar inicialização do sistema
echo "<h3>3. Teste de Inicialização</h3>";
try {
    initReferralSystem();
    echo "<p class='success'>✓ initReferralSystem() executado sem erros</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro na inicialização: " . $e->getMessage() . "</p>";
}

// 4. Testar validação do código
echo "<h3>4. Teste de Validação do Código</h3>";
try {
    $isValid = validateReferralCode('CVh9Gu4d84');
    echo "<p class='" . ($isValid ? 'success' : 'error') . "'>Código CVh9Gu4d84: " . ($isValid ? '✓ Válido' : '✗ Inválido') . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro na validação: " . $e->getMessage() . "</p>";
}

// 5. Verificar se existe um usuário com esse código
echo "<h3>5. Verificação no Banco de Dados</h3>";
try {
    $usuario = dbFetchOne("SELECT id, nome, codigo_afiliado FROM jogador WHERE codigo_afiliado = ?", ['CVh9Gu4d84']);
    if ($usuario) {
        echo "<p class='success'>✓ Usuário encontrado: ID {$usuario['id']}, Nome: {$usuario['nome']}</p>";
    } else {
        echo "<p class='error'>✗ Nenhum usuário encontrado com código CVh9Gu4d84</p>";
        
        // Criar um usuário de teste
        echo "<p class='info'>Criando usuário de teste...</p>";
        $userData = [
            'nome' => 'Afiliado Teste',
            'email' => 'afiliado_teste@teste.com',
            'senha' => password_hash('123456', PASSWORD_DEFAULT),
            'data_cadastro' => date('Y-m-d H:i:s'),
            'status' => 'ativo',
            'codigo_afiliado' => 'CVh9Gu4d84',
            'ref_indicacao' => null,
            'afiliado_ativo' => 'ativo'
        ];
        
        $userId = dbInsert('jogador', $userData);
        if ($userId) {
            echo "<p class='success'>✓ Usuário afiliado criado com sucesso (ID: {$userId})</p>";
        } else {
            echo "<p class='error'>✗ Erro ao criar usuário afiliado</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro na consulta: " . $e->getMessage() . "</p>";
}

// 6. Testar geração de código único
echo "<h3>6. Teste de Geração de Código Único</h3>";
try {
    $novoCodigo = generateUniqueAffiliateCode();
    echo "<p class='success'>✓ Código gerado: {$novoCodigo}</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro na geração: " . $e->getMessage() . "</p>";
}

// 7. Simular cadastro via AJAX
echo "<h3>7. Simulação de Cadastro via AJAX</h3>";
echo "<p class='info'>Simulando dados POST para ajax/register.php:</p>";

$testData = [
    'nome' => 'Usuário Teste Ref',
    'email' => 'teste_ref_' . time() . '@teste.com',
    'senha' => '123456',
    'referral_code' => 'CVh9Gu4d84'
];

echo "<ul>";
foreach ($testData as $key => $value) {
    echo "<li>{$key}: {$value}</li>";
}
echo "</ul>";

// Simular o processamento do ajax/register.php
try {
    // Verificar se email já existe
    $existingUser = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$testData['email']]);
    
    if ($existingUser) {
        echo "<p class='error'>✗ Email já existe</p>";
    } else {
        // Hash da senha
        $senhaHash = password_hash($testData['senha'], PASSWORD_DEFAULT);
        
        // Gerar código de afiliado único
        $codigoAfiliado = generateUniqueAffiliateCode();
        
        // Dados para inserção
        $userData = [
            'nome' => $testData['nome'],
            'email' => $testData['email'],
            'senha' => $senhaHash,
            'data_cadastro' => date('Y-m-d H:i:s'),
            'status' => 'ativo',
            'codigo_afiliado' => $codigoAfiliado,
            'ref_indicacao' => !empty($testData['referral_code']) ? $testData['referral_code'] : null,
            'afiliado_ativo' => 'ativo'
        ];
        
        echo "<p class='info'>Dados para inserção:</p>";
        echo "<ul>";
        foreach ($userData as $key => $value) {
            if ($key === 'senha') {
                echo "<li>{$key}: [hash da senha]</li>";
            } else {
                echo "<li>{$key}: " . ($value ?: 'NULL') . "</li>";
            }
        }
        echo "</ul>";
        
        $userId = dbInsert('jogador', $userData);
        
        if ($userId) {
            echo "<p class='success'>✓ Usuário cadastrado com sucesso (ID: {$userId})</p>";
            
            // Verificar o que foi realmente salvo
            $savedUser = dbFetchOne("SELECT id, nome, email, codigo_afiliado, ref_indicacao FROM jogador WHERE id = ?", [$userId]);
            
            echo "<p class='info'>Dados salvos no banco:</p>";
            echo "<ul>";
            echo "<li>ID: {$savedUser['id']}</li>";
            echo "<li>Nome: {$savedUser['nome']}</li>";
            echo "<li>Email: {$savedUser['email']}</li>";
            echo "<li>Código Afiliado: {$savedUser['codigo_afiliado']}</li>";
            echo "<li><strong>ref_indicacao: " . ($savedUser['ref_indicacao'] ?: 'NULL') . "</strong></li>";
            echo "</ul>";
            
            if ($savedUser['ref_indicacao'] === 'CVh9Gu4d84') {
                echo "<p class='success' style='font-size: 18px; font-weight: bold;'>🎉 SUCESSO! O ref_indicacao foi salvo corretamente!</p>";
            } else {
                echo "<p class='error' style='font-size: 18px; font-weight: bold;'>❌ PROBLEMA! O ref_indicacao não foi salvo corretamente.</p>";
                echo "<p>Esperado: CVh9Gu4d84</p>";
                echo "<p>Encontrado: " . ($savedUser['ref_indicacao'] ?: 'NULL') . "</p>";
            }
        } else {
            echo "<p class='error'>✗ Erro ao cadastrar usuário</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro no cadastro: " . $e->getMessage() . "</p>";
}

// 8. Teste do JavaScript
echo "<h3>8. Teste do JavaScript (localStorage)</h3>";
echo "<div id='js-test'>Aguardando JavaScript...</div>";

echo "<script>";
echo "// Simular captura do parâmetro ref da URL";
echo "const urlParams = new URLSearchParams('?ref=CVh9Gu4d84');";
echo "const refCode = urlParams.get('ref');";
echo "console.log('Código capturado da URL:', refCode);";
echo "";
echo "// Salvar no localStorage";
echo "if (refCode) {";
echo "    localStorage.setItem('bolao_referral_code', refCode);";
echo "    console.log('Código salvo no localStorage:', refCode);";
echo "}";
echo "";
echo "// Verificar se foi salvo";
echo "const storedCode = localStorage.getItem('bolao_referral_code');";
echo "console.log('Código armazenado:', storedCode);";
echo "";
echo "// Atualizar a página";
echo "document.addEventListener('DOMContentLoaded', function() {";
echo "    const testDiv = document.getElementById('js-test');";
echo "    if (testDiv) {";
echo "        if (storedCode === 'CVh9Gu4d84') {";
echo "            testDiv.innerHTML = '<p class=\"success\">✓ JavaScript funcionando: Código ' + storedCode + ' salvo no localStorage</p>';";
echo "        } else {";
echo "            testDiv.innerHTML = '<p class=\"error\">✗ JavaScript com problema: Código esperado CVh9Gu4d84, encontrado ' + (storedCode || 'NULL') + '</p>';";
echo "        }";
echo "    }";
echo "});";
echo "</script>";

echo "<hr>";
echo "<h3>Conclusão</h3>";
echo "<p>Este debug testa todo o fluxo de referência. Se alguma etapa falhar, o problema estará identificado.</p>";
echo "<p><a href='debug_ref_fluxo.php'>Recarregar teste</a></p>";
echo "<p><a href='bolao.php?ref=CVh9Gu4d84'>Testar fluxo real no bolao.php</a></p>";
?>