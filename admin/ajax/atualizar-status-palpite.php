<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se o administrador está logado
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Obter e validar parâmetros
$palpiteId = filter_input(INPUT_POST, 'palpite_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status');

if (!$palpiteId || !in_array($status, ['pendente', 'pago', 'cancelado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

try {
    // Atualizar status do palpite
    $success = dbExecute(
        "UPDATE palpites SET status = ? WHERE id = ?",
        [$status, $palpiteId]
    );

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
    } else {
        throw new Exception('Erro ao atualizar status');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar a requisição: ' . $e->getMessage()]);
} 