<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h2>Debug do Sistema de Cadastro com Afiliação</h2>";

// 1. Verificar se existe pelo menos um afiliado ativo
echo "<h3>1. Verificando afiliados ativos:</h3>";
$afiliadosAtivos = dbFetchAll("SELECT id, nome, codigo_afiliado, afiliado_ativo FROM jogador WHERE afiliado_ativo = 'ativo' LIMIT 5");

if (empty($afiliadosAtivos)) {
    echo "<p style='color: red;'>❌ PROBLEMA: Nenhum afiliado ativo encontrado!</p>";
    
    // Ativar o primeiro jogador como teste
    $primeiroJogador = dbFetchOne("SELECT id, nome, codigo_afiliado FROM jogador WHERE id = 1");
    if ($primeiroJogador) {
        $result = dbExecute("UPDATE jogador SET afiliado_ativo = 'ativo' WHERE id = 1");
        if ($result) {
            echo "<p style='color: green;'>✅ Ativei o jogador ID 1 como afiliado de teste</p>";
            $afiliadosAtivos = dbFetchAll("SELECT id, nome, codigo_afiliado, afiliado_ativo FROM jogador WHERE afiliado_ativo = 'ativo' LIMIT 5");
        }
    }
} 

if (!empty($afiliadosAtivos)) {
    echo "<p style='color: green;'>✅ Encontrados " . count($afiliadosAtivos) . " afiliados ativos:</p>";
    foreach ($afiliadosAtivos as $afiliado) {
        echo "<li>ID: {$afiliado['id']} - {$afiliado['nome']} - Código: <strong>{$afiliado['codigo_afiliado']}</strong></li>";
    }
}

// 2. Simular processo de cadastro
echo "<h3>2. Simulando processo de cadastro:</h3>";

if (!empty($afiliadosAtivos)) {
    $codigoTeste = $afiliadosAtivos[0]['codigo_afiliado'];
    echo "<p>Testando com código: <strong>{$codigoTeste}</strong></p>";
    
    // Simular dados do formulário
    $formData = [
        'nome' => 'Usuário Teste ' . date('H:i:s'),
        'email' => 'teste' . time() . '@teste.com',
        'telefone' => '(11) 99999-9999',
        'referral_code' => $codigoTeste
    ];
    
    $password = '123456';
    $errors = [];
    
    echo "<h4>Dados do teste:</h4>";
    echo "<ul>";
    foreach ($formData as $key => $value) {
        echo "<li>{$key}: {$value}</li>";
    }
    echo "<li>password: {$password}</li>";
    echo "</ul>";
    
    // Validar código de afiliado
    echo "<h4>Validação do código de afiliado:</h4>";
    if (!empty($formData['referral_code'])) {
        $referral = dbFetchOne("SELECT id, nome FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'", 
                               [$formData['referral_code']]);
        if (!$referral) {
            $errors[] = 'Código de afiliado inválido.';
            echo "<p style='color: red;'>❌ Código inválido</p>";
        } else {
            echo "<p style='color: green;'>✅ Código válido - Afiliado: {$referral['nome']} (ID: {$referral['id']})</p>";
        }
    }
    
    // Verificar se email já existe
    $existingUser = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$formData['email']]);
    if ($existingUser) {
        $errors[] = 'Este e-mail já está cadastrado.';
        echo "<p style='color: red;'>❌ Email já existe</p>";
    } else {
        echo "<p style='color: green;'>✅ Email disponível</p>";
    }
    
    if (empty($errors)) {
        echo "<h4>Criando usuário:</h4>";
        
        // Gerar código de afiliado único
        do {
            $codigoAfiliado = 'af' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            $existeCodigo = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$codigoAfiliado]);
        } while ($existeCodigo);
        
        echo "<p>Código gerado para novo usuário: <strong>{$codigoAfiliado}</strong></p>";
        
        // Se o usuário veio através de um link de afiliado, ativa automaticamente como afiliado
        $afiliadoStatus = !empty($formData['referral_code']) ? 'ativo' : 'inativo';
        echo "<p>Status de afiliado para novo usuário: <strong>{$afiliadoStatus}</strong></p>";
        
        $userData = [
            'nome' => $formData['nome'],
            'email' => $formData['email'],
            'senha' => password_hash($password, PASSWORD_DEFAULT),
            'telefone' => $formData['telefone'],
            'data_cadastro' => date('Y-m-d H:i:s'),
            'status' => 'ativo',
            'codigo_afiliado' => $codigoAfiliado,
            'ref_indicacao' => !empty($formData['referral_code']) ? $formData['referral_code'] : null,
            'afiliado_ativo' => $afiliadoStatus
        ];
        
        echo "<h4>Dados que serão inseridos:</h4>";
        echo "<ul>";
        foreach ($userData as $key => $value) {
            if ($key === 'senha') {
                echo "<li>{$key}: [HASH GERADO]</li>";
            } else {
                echo "<li>{$key}: " . ($value ?? 'NULL') . "</li>";
            }
        }
        echo "</ul>";
        
        // Inserir no banco (comentado para não criar usuários de teste)
        // $userId = dbInsert('jogador', $userData);
        echo "<p style='color: blue;">ℹ️ Inserção no banco comentada para evitar criar usuários de teste</p>";
        echo "<p style='color: green;">✅ Processo de cadastro validado com sucesso!</p>";
        
    } else {
        echo "<h4>Erros encontrados:</h4>";
        foreach ($errors as $error) {
            echo "<p style='color: red;'>❌ {$error}</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Não é possível testar sem afiliados ativos</p>";
}

echo "<hr>";
echo "<p><a href='teste_afiliado.php'>Ver afiliados ativos</a></p>";
echo "<p><a href='debug_cadastro.php'>Recarregar teste</a></p>";
?>