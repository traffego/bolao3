<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
// database_functions.php não é mais necessário pois está incluído em database.php
require_once __DIR__ . '/../includes/functions.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do JSON
$jsonData = json_decode(file_get_contents('php://input'), true);

if (!$jsonData) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$email = $jsonData['email'] ?? '';
$password = $jsonData['password'] ?? '';

// Validar dados
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'E-mail e senha são obrigatórios']);
    exit;
}

// Buscar usuário
$user = dbFetchOne("SELECT id, nome, senha, status FROM jogador WHERE email = ?", [$email]);

if ($user && verifyPassword($password, $user['senha'])) {
    // Verificar se usuário está ativo
    if ($user['status'] === 'inativo') {
        echo json_encode(['success' => false, 'message' => 'Sua conta está inativa. Entre em contato com o suporte.']);
        exit;
    }

    // Iniciar sessão do usuário
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nome'] = $user['nome'];
    
    // Atualizar último login
    dbUpdate('jogador', ['ultimo_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos']);
} 