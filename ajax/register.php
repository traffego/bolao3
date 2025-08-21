<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

// Get registration data
$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';
$referralCode = $_POST['referral_code'] ?? '';

// Validate input
if (empty($nome) || empty($email) || empty($senha)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    // Check if email already exists
    $existingUser = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$email]);
    
    if ($existingUser) {
        echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado']);
        exit;
    }
    
    // Hash password
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Gerar código de afiliado único
    $codigoAfiliado = generateUniqueAffiliateCode();
    
    // Insert new user
    $userData = [
        'nome' => $nome,
        'email' => $email,
        'senha' => $senhaHash,
        'data_cadastro' => date('Y-m-d H:i:s'),
        'status' => 'ativo',
        'codigo_afiliado' => $codigoAfiliado,
        'ref_indicacao' => !empty($referralCode) ? $referralCode : null,
        'afiliado_ativo' => 'ativo'
    ];
    
    $userId = dbInsert('jogador', $userData);
    
    if ($userId) {
        // Set session data
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $nome;
        $_SESSION['user_email'] = $email;
        
        echo json_encode(['success' => true, 'message' => 'Cadastro realizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar cadastro']);
    }

} catch (Exception $e) {
    error_log("Erro no cadastro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao realizar cadastro']);
}