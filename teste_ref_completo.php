<?php
/**
 * Teste completo do fluxo de referência
 * Simula o processo completo: URL -> localStorage -> AJAX -> Banco
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h2>Teste Completo do Fluxo de Referência</h2>";

// Simular URL com parâmetro ref
echo "<h3>1. Simulando URL: bolao.php?ref=CVh9Gu4d84</h3>";
echo "<p>Esta é a URL que o usuário clicou</p>";

// Verificar se o código existe no banco
echo "<h3>2. Verificando se o código CVh9Gu4d84 existe no banco</h3>";
$codigoExiste = dbFetchOne("SELECT id, nome, codigo_afiliado FROM jogador WHERE codigo_afiliado = ?", ['CVh9Gu4d84']);

if ($codigoExiste) {
    echo "<p style='color: green;'>✓ Código encontrado no banco:</p>";
    echo "<ul>";
    echo "<li>ID: {$codigoExiste['id']}</li>";
    echo "<li>Nome: {$codigoExiste['nome']}</li>";
    echo "<li>Código: {$codigoExiste['codigo_afiliado']}</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ Código CVh9Gu4d84 NÃO encontrado no banco</p>";
    echo "<p>Vou criar um usuário com este código para o teste...</p>";
    
    // Criar usuário de teste
    $userData = [
        'nome' => 'Afiliado Teste',
        'email' => 'afiliado_teste_' . time() . '@teste.com',
        'senha' => password_hash('123456', PASSWORD_DEFAULT),
        'data_cadastro' => date('Y-m-d H:i:s'),
        'status' => 'ativo',
        'codigo_afiliado' => 'CVh9Gu4d84',
        'ref_indicacao' => null,
        'afiliado_ativo' => 'ativo'
    ];
    
    $userId = dbInsert('jogador', $userData);
    
    if ($userId) {
        echo "<p style='color: green;'>✓ Usuário afiliado criado com sucesso (ID: {$userId})</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro ao criar usuário afiliado</p>";
    }
}

echo "<h3>3. Teste do JavaScript (localStorage)</h3>";
echo "<p>Simulando o que acontece quando o usuário acessa bolao.php?ref=CVh9Gu4d84:</p>";

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
echo "// Exibir na página";
echo "document.addEventListener('DOMContentLoaded', function() {";
echo "    const resultDiv = document.getElementById('js-result');";
echo "    if (resultDiv) {";
echo "        resultDiv.innerHTML = '<p style=\"color: green;\">✓ Código salvo no localStorage: <strong>' + storedCode + '</strong></p>';";
echo "    }";
echo "});";
echo "</script>";

echo "<div id='js-result'><p>Aguardando JavaScript...</p></div>";

echo "<h3>4. Teste do Formulário AJAX</h3>";
echo "<p>Agora vamos testar o formulário de cadastro que deve capturar o código do localStorage:</p>";

echo "<form id='testForm' style='border: 1px solid #ccc; padding: 20px; margin: 20px 0;'>";
echo "    <div style='margin-bottom: 10px;'>";
echo "        <label>Nome:</label><br>";
echo "        <input type='text' id='nome' name='nome' value='Usuário Teste' style='width: 300px; padding: 5px;'>";
echo "    </div>";
echo "    <div style='margin-bottom: 10px;'>";
echo "        <label>Email:</label><br>";
echo "        <input type='email' id='email' name='email' value='teste_" . time() . "@teste.com' style='width: 300px; padding: 5px;'>";
echo "    </div>";
echo "    <div style='margin-bottom: 10px;'>";
echo "        <label>Senha:</label><br>";
echo "        <input type='password' id='senha' name='senha' value='123456' style='width: 300px; padding: 5px;'>";
echo "    </div>";
echo "    <div style='margin-bottom: 10px;'>";
echo "        <label>Código de Referência (será preenchido automaticamente):</label><br>";
echo "        <input type='text' id='referral_code' name='referral_code' value='' style='width: 300px; padding: 5px; background: #f0f0f0;' readonly>";
echo "    </div>";
echo "    <button type='submit' style='padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;'>Cadastrar</button>";
echo "</form>";

echo "<div id='form-result'></div>";

echo "<script>";
echo "document.addEventListener('DOMContentLoaded', function() {";
echo "    // Preencher campo referral_code com valor do localStorage";
echo "    const referralInput = document.getElementById('referral_code');";
echo "    const storedCode = localStorage.getItem('bolao_referral_code');";
echo "    if (referralInput && storedCode) {";
echo "        referralInput.value = storedCode;";
echo "        console.log('Campo referral_code preenchido com:', storedCode);";
echo "    }";
echo "    ";
echo "    // Interceptar envio do formulário";
echo "    document.getElementById('testForm').addEventListener('submit', function(e) {";
echo "        e.preventDefault();";
echo "        ";
echo "        const formData = new FormData();";
echo "        formData.append('nome', document.getElementById('nome').value);";
echo "        formData.append('email', document.getElementById('email').value);";
echo "        formData.append('senha', document.getElementById('senha').value);";
echo "        formData.append('referral_code', document.getElementById('referral_code').value);";
echo "        ";
echo "        console.log('Enviando dados:', {";
echo "            nome: formData.get('nome'),";
echo "            email: formData.get('email'),";
echo "            referral_code: formData.get('referral_code')";
echo "        });";
echo "        ";
echo "        fetch('ajax/register.php', {";
echo "            method: 'POST',";
echo "            body: formData";
echo "        })";
echo "        .then(response => response.json())";
echo "        .then(data => {";
echo "            console.log('Resposta do servidor:', data);";
echo "            const resultDiv = document.getElementById('form-result');";
echo "            if (data.success) {";
echo "                resultDiv.innerHTML = '<p style=\"color: green;\">✓ Cadastro realizado com sucesso!</p>';";
echo "                // Verificar se foi salvo no banco";
echo "                setTimeout(() => {";
echo "                    window.location.href = 'teste_ref_completo.php?verificar_banco=1&email=' + encodeURIComponent(formData.get('email'));";
echo "                }, 2000);";
echo "            } else {";
echo "                resultDiv.innerHTML = '<p style=\"color: red;\">✗ Erro: ' + data.message + '</p>';";
echo "            }";
echo "        })";
echo "        .catch(error => {";
echo "            console.error('Erro:', error);";
echo "            document.getElementById('form-result').innerHTML = '<p style=\"color: red;\">✗ Erro na requisição</p>';";
echo "        });";
echo "    });";
echo "});";
echo "</script>";

// Verificar se foi solicitada verificação no banco
if (isset($_GET['verificar_banco']) && isset($_GET['email'])) {
    echo "<h3>5. Verificação no Banco de Dados</h3>";
    $email = $_GET['email'];
    
    $usuario = dbFetchOne("SELECT id, nome, email, codigo_afiliado, ref_indicacao FROM jogador WHERE email = ?", [$email]);
    
    if ($usuario) {
        echo "<p style='color: green;'>✓ Usuário encontrado no banco:</p>";
        echo "<ul>";
        echo "<li>ID: {$usuario['id']}</li>";
        echo "<li>Nome: {$usuario['nome']}</li>";
        echo "<li>Email: {$usuario['email']}</li>";
        echo "<li>Código Afiliado: {$usuario['codigo_afiliado']}</li>";
        echo "<li><strong>ref_indicacao: " . ($usuario['ref_indicacao'] ?: 'NULL') . "</strong></li>";
        echo "</ul>";
        
        if ($usuario['ref_indicacao'] === 'CVh9Gu4d84') {
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 SUCESSO! O ref_indicacao foi salvo corretamente!</p>";
        } else {
            echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ PROBLEMA! O ref_indicacao não foi salvo corretamente.</p>";
            echo "<p>Esperado: CVh9Gu4d84</p>";
            echo "<p>Encontrado: " . ($usuario['ref_indicacao'] ?: 'NULL') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Usuário não encontrado no banco</p>";
    }
}

echo "<hr>";
echo "<p><a href='teste_ref_completo.php'>Recarregar teste</a></p>";
echo "<p><a href='bolao.php?ref=CVh9Gu4d84'>Testar fluxo real</a></p>";
?>