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

// Get login data
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

// Validate input
if (empty($email) || empty($senha)) {
    echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
    exit;
}

try {
    // Check if user exists
    $usuario = dbFetchOne("SELECT * FROM jogador WHERE email = ?", [$email]);
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Email ou senha incorretos']);
        exit;
    }
    
    // Verify password
    if (!password_verify($senha, $usuario['senha'])) {
        echo json_encode(['success' => false, 'message' => 'Email ou senha incorretos']);
        exit;
    }
    
    // Set session data
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_name'] = $usuario['nome'];
    $_SESSION['user_email'] = $usuario['email'];
    
    echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso']);

} catch (Exception $e) {
    error_log("Erro no login: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao realizar login']);
} 