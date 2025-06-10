<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Verificar se é um POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método não permitido');
}

// Obter IDs importantes
$jogadorId = getCurrentUserId(); // ID do jogador logado
$bolaoId = isset($_POST['bolao_id']) ? (int)$_POST['bolao_id'] : 0; // ID do bolão vem do POST

// Validar dados
if (!$jogadorId || !$bolaoId) {
    die('Dados inválidos');
}

try {
    // Atualizar status do palpite
    $stmt = $pdo->prepare("
        UPDATE palpites 
        SET status = 'pago' 
        WHERE jogador_id = ? 
        AND bolao_id = ? 
        AND id = (
            SELECT id 
            FROM (
                SELECT id 
                FROM palpites 
                WHERE jogador_id = ? 
                AND bolao_id = ? 
                ORDER BY data_palpite DESC 
                LIMIT 1
            ) as sub
        )
    ");

    $stmt->execute([$jogadorId, $bolaoId, $jogadorId, $bolaoId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhum palpite encontrado para atualizar']);
    }

} catch (PDOException $e) {
    error_log("Erro ao atualizar status do palpite: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status do palpite']);
} 