<?php
/**
 * Teste específico para verificar o problema do ref_indicacao
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h2>Teste de Cadastro com Referência</h2>";

// Simular chegada via URL com parâmetro ref
echo "<h3>1. Simulando URL: cadastro.php?ref=af2E4FAB3A</h3>";
$_GET['ref'] = 'af2E4FAB3A';

// Executar a mesma lógica do cadastro.php
$referralCode = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referralCode = trim($_GET['ref']);
    $_SESSION['referral_code'] = $referralCode;
    echo "<p style='color: green;'>✓ Parâmetro ref capturado: <strong>" . $referralCode . "</strong></p>";
    echo "<p style='color: green;'>✓ Salvo na sessão: <strong>" . $_SESSION['referral_code'] . "</strong></p>";
} else {
    echo "<p style='color: red;'>✗ Nenhum código de referência encontrado</p>";
}

// Simular dados do formulário
echo "<h3>2. Simulando envio do formulário POST</h3>";
$_POST = [
    'nome' => 'Teste Usuario',
    'email' => 'teste' . time() . '@teste.com',
    'telefone' => '(21) 99999-9999',
    'password' => '123456',
    'confirm_password' => '123456',
    'referral_code' => $referralCode,
    'terms' => '1'
];

echo "<p>Dados POST simulados:</p>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

// Processar como no cadastro.php
$formData = [
    'nome' => trim($_POST['nome'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'telefone' => trim($_POST['telefone'] ?? ''),
    'referral_code' => trim($_POST['referral_code'] ?? '') ?: $referralCode
];

echo "<h3>3. Dados processados do formulário:</h3>";
echo "<pre>";
print_r($formData);
echo "</pre>";

// Verificar se o código de referência está correto
echo "<h3>4. Verificação do código de referência:</h3>";
echo "<p>referral_code do formulário: <strong>" . ($formData['referral_code'] ?? 'VAZIO') . "</strong></p>";
echo "<p>Código está vazio? " . (empty($formData['referral_code']) ? 'SIM' : 'NÃO') . "</p>";

// Verificar se o afiliado existe
if (!empty($formData['referral_code'])) {
    $afiliadoExiste = dbFetchOne("SELECT id, nome FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'", [$formData['referral_code']]);
    if ($afiliadoExiste) {
        echo "<p style='color: green;'>✓ Afiliado encontrado: " . $afiliadoExiste['nome'] . " (ID: " . $afiliadoExiste['id'] . ")</p>";
    } else {
        echo "<p style='color: red;'>✗ Afiliado não encontrado ou inativo</p>";
    }
}

// Gerar código de afiliado
do {
    $codigoAfiliado = 'af' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $existeCodigo = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$codigoAfiliado]);
} while ($existeCodigo);

echo "<h3>5. Código de afiliado gerado:</h3>";
echo "<p>Código: <strong>" . $codigoAfiliado . "</strong></p>";

// Preparar dados para inserção
$userData = [
    'nome' => $formData['nome'],
    'email' => $formData['email'],
    'senha' => hashPassword($_POST['password']),
    'telefone' => $formData['telefone'],
    'data_cadastro' => date('Y-m-d H:i:s'),
    'status' => 'ativo',
    'codigo_afiliado' => $codigoAfiliado,
    'ref_indicacao' => !empty($formData['referral_code']) ? $formData['referral_code'] : null,
    'afiliado_ativo' => 'ativo'
];

echo "<h3>6. Dados preparados para inserção:</h3>";
echo "<pre>";
print_r($userData);
echo "</pre>";

echo "<h3>7. Verificação específica do ref_indicacao:</h3>";
echo "<p>Valor: <strong>" . ($userData['ref_indicacao'] ?? 'NULL') . "</strong></p>";
echo "<p>Tipo: " . gettype($userData['ref_indicacao']) . "</p>";
echo "<p>É null? " . (is_null($userData['ref_indicacao']) ? 'SIM' : 'NÃO') . "</p>";
echo "<p>Está vazio? " . (empty($userData['ref_indicacao']) ? 'SIM' : 'NÃO') . "</p>";

// Tentar inserir (comentado para não criar usuários de teste)
echo "<h3>8. Simulação da inserção:</h3>";
echo "<p style='color: blue;'>ℹ️ Inserção comentada para evitar criar usuários de teste</p>";
echo "<p>SQL que seria executado:</p>";
echo "<code>INSERT INTO jogador (" . implode(', ', array_keys($userData)) . ") VALUES (" . implode(', ', array_fill(0, count($userData), '?')) . ")</code>";

echo "<hr>";
echo "<p><a href='teste_cadastro_ref.php'>Recarregar teste</a></p>";
echo "<p><a href='cadastro.php?ref=af2E4FAB3A'>Testar cadastro real</a></p>";
?>