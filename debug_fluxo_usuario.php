<?php
/**
 * Debug do fluxo real do usuário - Sistema de Afiliação
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h2>Debug do Fluxo Real do Usuário</h2>";

// Simular chegada via URL com parâmetro ref
echo "<h3>1. Simulando chegada via URL com ?ref=</h3>";
$_GET['ref'] = 'af2E4FAB3A'; // Simular parâmetro da URL
echo "<p>URL simulada: cadastro.php?ref=af2E4FAB3A</p>";

// Executar a mesma lógica do cadastro.php
$referralCode = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referralCode = trim($_GET['ref']);
    $_SESSION['referral_code'] = $referralCode;
    echo "<p style='color: green;'>✓ Parâmetro ref capturado: <strong>" . $referralCode . "</strong></p>";
    echo "<p style='color: green;'>✓ Salvo na sessão: <strong>" . $_SESSION['referral_code'] . "</strong></p>";
} elseif (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
    $referralCode = $_SESSION['referral_code'];
    echo "<p style='color: blue;'>ℹ Recuperado da sessão: <strong>" . $referralCode . "</strong></p>";
} else {
    echo "<p style='color: red;'>✗ Nenhum código de referência encontrado</p>";
}

// Simular dados iniciais do formulário
$formData = [
    'nome' => '',
    'email' => '',
    'telefone' => '',
    'referral_code' => $referralCode
];

echo "<h3>2. Dados iniciais do formulário:</h3>";
echo "<pre>";
print_r($formData);
echo "</pre>";

echo "<h3>3. Simulando envio do formulário (POST):</h3>";

// Simular dados do POST
$_POST = [
    'nome' => 'Usuário Teste Real',
    'email' => 'usuario_real_' . time() . '@teste.com',
    'telefone' => '(21) 98888-8888',
    'referral_code' => '', // Campo vazio no formulário
    'password' => '123456',
    'confirm_password' => '123456',
    'terms' => '1'
];

echo "<p>Dados enviados via POST:</p>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

// Executar a mesma lógica do processamento POST
$formData = [
    'nome' => trim($_POST['nome'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'telefone' => trim($_POST['telefone'] ?? ''),
    'referral_code' => !empty(trim($_POST['referral_code'] ?? '')) ? trim($_POST['referral_code'] ?? '') : $referralCode
];

echo "<h3>4. Dados processados após POST:</h3>";
echo "<pre>";
print_r($formData);
echo "</pre>";

$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validações básicas
$errors = [];
if (empty($formData['nome'])) {
    $errors[] = 'O campo nome é obrigatório.';
}
if (empty($formData['email'])) {
    $errors[] = 'O campo e-mail é obrigatório.';
} elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Por favor, insira um e-mail válido.';
} else {
    $existingUser = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$formData['email']]);
    if ($existingUser) {
        $errors[] = 'Este e-mail já está cadastrado.';
    }
}
if (empty($password)) {
    $errors[] = 'O campo senha é obrigatório.';
}
if ($password !== $confirmPassword) {
    $errors[] = 'As senhas não coincidem.';
}

// Validar código de afiliado
if (!empty($formData['referral_code'])) {
    $referral = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'", 
                           [$formData['referral_code']]);
    if (!$referral) {
        $errors[] = 'Código de afiliado inválido.';
        echo "<p style='color: red;'>✗ Código de afiliado inválido: " . $formData['referral_code'] . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Código de afiliado válido: " . $formData['referral_code'] . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ Nenhum código de afiliado para validar</p>";
}

echo "<h3>5. Erros de validação:</h3>";
if (!empty($errors)) {
    echo "<ul style='color: red;'>";
    foreach ($errors as $error) {
        echo "<li>" . $error . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✓ Nenhum erro de validação</p>";
    
    // Prosseguir com a criação da conta
    echo "<h3>6. Criando conta...</h3>";
    
    // Gerar código de afiliado único
    do {
        $codigoAfiliado = 'af' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $existeCodigo = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$codigoAfiliado]);
    } while ($existeCodigo);
    
    echo "<p>Código de afiliado gerado: <strong>" . $codigoAfiliado . "</strong></p>";
    
    $afiliadoStatus = 'ativo';
    
    $userData = [
        'nome' => $formData['nome'],
        'email' => $formData['email'],
        'senha' => hashPassword($password),
        'telefone' => $formData['telefone'],
        'data_cadastro' => date('Y-m-d H:i:s'),
        'status' => 'ativo',
        'codigo_afiliado' => $codigoAfiliado,
        'ref_indicacao' => !empty($formData['referral_code']) ? $formData['referral_code'] : null,
        'afiliado_ativo' => $afiliadoStatus
    ];
    
    echo "<h3>7. Dados preparados para inserção:</h3>";
    echo "<pre>";
    print_r($userData);
    echo "</pre>";
    
    // Tentar inserir
    $userId = dbInsert('jogador', $userData);
    
    if ($userId) {
        echo "<p style='color: green; font-weight: bold;'>✓ SUCESSO! Usuário inserido com ID: " . $userId . "</p>";
        
        // Limpar sessão
        if (isset($_SESSION['referral_code'])) {
            unset($_SESSION['referral_code']);
            echo "<p style='color: blue;'>ℹ Código de referência removido da sessão</p>";
        }
        
        // Verificar dados salvos
        $usuarioSalvo = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$userId]);
        echo "<h3>8. Dados salvos no banco:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        foreach ($usuarioSalvo as $campo => $valor) {
            $cor = '';
            if ($campo == 'codigo_afiliado' || $campo == 'ref_indicacao' || $campo == 'afiliado_ativo') {
                $cor = 'background-color: yellow;';
            }
            echo "<tr style='$cor'><td><strong>$campo</strong></td><td>$valor</td></tr>";
        }
        echo "</table>";
        
        // Criar conta
        $contaData = [
            'jogador_id' => $userId,
            'status' => 'ativo'
        ];
        
        $contaId = dbInsert('contas', $contaData);
        if ($contaId) {
            echo "<p style='color: green;'>✓ Conta criada com ID: " . $contaId . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Erro ao criar conta</p>";
        }
        
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ ERRO ao inserir usuário!</p>";
        
        // Verificar logs de erro
        $errorLog = error_get_last();
        if ($errorLog) {
            echo "<h3>Último erro:</h3>";
            echo "<pre>";
            print_r($errorLog);
            echo "</pre>";
        }
    }
}

echo "<hr>";
echo "<h3>9. Estado final da sessão:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>