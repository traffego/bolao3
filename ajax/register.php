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

// Extrair dados
$nome = trim($jsonData['nome'] ?? '');
$email = trim($jsonData['email'] ?? '');
$telefone = trim($jsonData['telefone'] ?? '');
$cpf = trim($jsonData['cpf'] ?? '');
$senha = $jsonData['senha'] ?? '';

// Validar dados
$errors = [];

if (empty($nome)) {
    $errors[] = 'O nome é obrigatório';
}

if (empty($email)) {
    $errors[] = 'O e-mail é obrigatório';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-mail inválido';
} else {
    // Verificar se e-mail já existe
    $existingUser = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$email]);
    if ($existingUser) {
        $errors[] = 'Este e-mail já está cadastrado';
    }
}

if (empty($telefone)) {
    $errors[] = 'O telefone é obrigatório';
}

if (empty($cpf)) {
    $errors[] = 'O CPF é obrigatório';
} else {
    // Limpar CPF
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Validar CPF
    if (strlen($cpf) !== 11) {
        $errors[] = 'CPF inválido';
    } else {
        // Verificar se CPF já existe
        $existingCPF = dbFetchOne("SELECT id FROM jogador WHERE cpf = ?", [$cpf]);
        if ($existingCPF) {
            $errors[] = 'Este CPF já está cadastrado';
        }
    }
}

if (empty($senha)) {
    $errors[] = 'A senha é obrigatória';
} elseif (strlen($senha) < 6) {
    $errors[] = 'A senha deve ter pelo menos 6 caracteres';
}

// Se houver erros, retornar
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    // Preparar dados para inserção
    $userData = [
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone,
        'cpf' => $cpf,
        'senha' => hashPassword($senha),
        'data_cadastro' => date('Y-m-d H:i:s'),
        'status' => 'ativo'
    ];
    
    // Inserir usuário
    $userId = dbInsert('jogador', $userData);
    
    if ($userId) {
        // Iniciar sessão do usuário
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_nome'] = $nome;
        
        echo json_encode(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar conta. Tente novamente.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar cadastro: ' . $e->getMessage()]);
} 