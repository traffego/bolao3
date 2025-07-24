<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Garantir que a resposta será sempre JSON
header('Content-Type: application/json');

// Verificar se é admin
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_GET, 'action');

    switch ($method) {
        case 'GET':
            if ($id) {
                // Buscar usuário específico
                $sql = "SELECT * FROM jogador WHERE id = ?";
                $usuario = dbFetchOne($sql, [$id]);
                
                if (!$usuario) {
                    throw new Exception('Usuário não encontrado', 404);
                }
                
                echo json_encode($usuario);
            } else {
                throw new Exception('ID não fornecido', 400);
            }
            break;

        case 'POST':
            // Criar novo usuário
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Dados inválidos', 400);
            }
            
            // Validar dados obrigatórios
            if (empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
                throw new Exception('Nome, e-mail e senha são obrigatórios', 400);
            }
            
            // Verificar se e-mail já existe
            $existente = dbFetchOne("SELECT id FROM jogador WHERE email = ?", [$data['email']]);
            if ($existente) {
                throw new Exception('E-mail já cadastrado', 400);
            }
            
            // Criar usuário
            $sql = "INSERT INTO jogador (nome, email, senha, telefone, status, data_cadastro) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['nome'],
                $data['email'],
                hashPassword($data['senha']),
                $data['telefone'] ?? null,
                $data['status'] ?? 'ativo'
            ];
            
            dbExecute($sql, $params);
            $novoId = dbLastInsertId();
            
            echo json_encode(['success' => true, 'id' => $novoId]);
            break;

        case 'PUT':
            if (!$id) {
                throw new Exception('ID não fornecido', 400);
            }

            // Verificar se usuário existe
            $usuario = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$id]);
            if (!$usuario) {
                throw new Exception('Usuário não encontrado', 404);
            }

            if ($action) {
                // Ações especiais
                switch ($action) {
                    case 'block':
                        dbExecute("UPDATE jogador SET status = 'bloqueado' WHERE id = ?", [$id]);
                        echo json_encode(['success' => true]);
                        break;
                        
                    case 'unblock':
                        dbExecute("UPDATE jogador SET status = 'ativo' WHERE id = ?", [$id]);
                        echo json_encode(['success' => true]);
                        break;
                        
                    default:
                        throw new Exception('Ação inválida', 400);
                }
            } else {
                // Atualização normal
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$data) {
                    throw new Exception('Dados inválidos', 400);
                }
                
                // Verificar se e-mail já existe (exceto para o próprio usuário)
                if ($data['email'] !== $usuario['email']) {
                    $existente = dbFetchOne("SELECT id FROM jogador WHERE email = ? AND id != ?", 
                                          [$data['email'], $id]);
                    if ($existente) {
                        throw new Exception('E-mail já cadastrado', 400);
                    }
                }
                
                // Montar query de update
                $campos = [];
                $params = [];
                
                if (!empty($data['nome'])) {
                    $campos[] = "nome = ?";
                    $params[] = $data['nome'];
                }
                
                if (!empty($data['email'])) {
                    $campos[] = "email = ?";
                    $params[] = $data['email'];
                }
                
                if (!empty($data['senha'])) {
                    $campos[] = "senha = ?";
                    $params[] = hashPassword($data['senha']);
                }
                
                if (isset($data['telefone'])) {
                    $campos[] = "telefone = ?";
                    $params[] = $data['telefone'];
                }
                
                if (!empty($data['status'])) {
                    $campos[] = "status = ?";
                    $params[] = $data['status'];
                }
                
                if (!empty($campos)) {
                    $params[] = $id;
                    $sql = "UPDATE jogador SET " . implode(", ", $campos) . " WHERE id = ?";
                    dbExecute($sql, $params);
                }
                
                echo json_encode(['success' => true]);
            }
            break;

        case 'DELETE':
            if (!$id) {
                throw new Exception('ID não fornecido', 400);
            }
            
            // Verificar se usuário existe
            $usuario = dbFetchOne("SELECT * FROM jogador WHERE id = ?", [$id]);
            if (!$usuario) {
                throw new Exception('Usuário não encontrado', 404);
            }
            
            // Excluir usuário
            dbExecute("DELETE FROM jogador WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Método não permitido', 405);
    }

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $code
    ]);
} 