<?php
/**
 * Teste de cadastro real para verificar o salvamento do ref_indicacao
 * Este arquivo fará um cadastro real no banco de dados
 */
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'debug_cadastro_real.php';

echo "<h1>Teste de Cadastro Real</h1>";

// Simular parâmetro ref na URL
$_GET['ref'] = 'af2E4FAB3A';

// Capturar parâmetro de referência
$referralCode = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referralCode = trim($_GET['ref']);
    $_SESSION['referral_code'] = $referralCode;
    echo "<p>✓ Parâmetro ref capturado: $referralCode</p>";
}

// Simular dados do formulário POST
$_POST = [
    'nome' => 'Teste Real ' . time(),
    'email' => 'teste_real_' . time() . '@teste.com',
    'telefone' => '(21) 99999-9999',
    'password' => '123456',
    'confirm_password' => '123456',
    'referral_code' => $referralCode,
    'terms' => '1'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

echo "<h2>Dados do formulário:</h2>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

// Processar dados do formulário
$formData = [
    'nome' => trim($_POST['nome']),
    'email' => trim($_POST['email']),
    'telefone' => trim($_POST['telefone']),
    'referral_code' => !empty($_POST['referral_code']) ? trim($_POST['referral_code']) : $referralCode
];

echo "<h2>Dados processados:</h2>";
echo "<pre>" . print_r($formData, true) . "</pre>";

// Verificar se o afiliado existe
if (!empty($formData['referral_code'])) {
    $afiliado = dbFetchOne("SELECT id, nome FROM jogador WHERE codigo_afiliado = ?", [$formData['referral_code']]);
    if ($afiliado) {
        echo "<p>✓ Afiliado encontrado: {$afiliado['nome']} (ID: {$afiliado['id']})</p>";
    } else {
        echo "<p>❌ Afiliado não encontrado para código: {$formData['referral_code']}</p>";
    }
}

// Gerar código de afiliado único
do {
    $codigoAfiliado = generateRandomString(10);
    $existingCode = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$codigoAfiliado]);
} while ($existingCode);

echo "<p>Código de afiliado gerado: $codigoAfiliado</p>";

// Preparar dados para inserção
$password = $_POST['password'];
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

echo "<h2>Dados preparados para inserção:</h2>";
echo "<pre>" . print_r($userData, true) . "</pre>";

echo "<h2>Verificação específica do ref_indicacao:</h2>";
echo "<p>Valor: " . ($userData['ref_indicacao'] ?? 'NULL') . "</p>";
echo "<p>Tipo: " . gettype($userData['ref_indicacao']) . "</p>";
echo "<p>É null? " . (is_null($userData['ref_indicacao']) ? 'SIM' : 'NÃO') . "</p>";
echo "<p>Está vazio? " . (empty($userData['ref_indicacao']) ? 'SIM' : 'NÃO') . "</p>";

echo "<h2>Executando inserção real...</h2>";

// INSERÇÃO REAL NO BANCO
$userId = debugDbInsert('jogador', $userData);

if ($userId) {
    echo "<p style='color: green;'>✓ Usuário criado com sucesso! ID: $userId</p>";
    
    // Verificar o que foi realmente salvo
    $savedUser = dbFetchOne("SELECT id, nome, email, codigo_afiliado, ref_indicacao, afiliado_ativo FROM jogador WHERE id = ?", [$userId]);
    
    echo "<h2>Dados realmente salvos no banco:</h2>";
    echo "<pre>" . print_r($savedUser, true) . "</pre>";
    
    if ($savedUser['ref_indicacao'] === $formData['referral_code']) {
        echo "<p style='color: green; font-weight: bold;'>✓ ref_indicacao foi salvo corretamente!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ ref_indicacao NÃO foi salvo corretamente!</p>";
        echo "<p>Esperado: {$formData['referral_code']}</p>";
        echo "<p>Salvo: {$savedUser['ref_indicacao']}</p>";
    }
    
    // Criar conta para o usuário
    $contaData = [
        'jogador_id' => $userId,
        'saldo' => 0.00,
        'data_criacao' => date('Y-m-d H:i:s')
    ];
    
    $contaId = dbInsert('contas', $contaData);
    if ($contaId) {
        echo "<p style='color: green;">✓ Conta criada com sucesso! ID: $contaId</p>";
    } else {
        echo "<p style='color: red;">❌ Erro ao criar conta</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Erro ao criar usuário</p>";
}

echo "<h2>Log de Debug:</h2>";
if (file_exists('debug_cadastro_log.txt')) {
    echo "<pre style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;'>";
    echo htmlspecialchars(file_get_contents('debug_cadastro_log.txt'));
    echo "</pre>";
} else {
    echo "<p>Nenhum log encontrado.</p>";
}

echo "<p><a href='cadastro.php?show_log=1'>Ver Log Completo</a></p>";
echo "<p><a href='cadastro.php?clear_log=1'>Limpar Log</a></p>";
?>