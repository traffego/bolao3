<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/classes/ContaManager.php';

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verifica se está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// Obtém e valida os dados do saque
$data = json_decode(file_get_contents('php://input'), true);
$valor = isset($data['valor']) ? filter_var($data['valor'], FILTER_VALIDATE_FLOAT) : null;
$chave_pix = isset($data['chave_pix']) ? filter_var($data['chave_pix'], FILTER_SANITIZE_STRING) : null;
$tipo_chave = isset($data['tipo_chave']) ? filter_var($data['tipo_chave'], FILTER_SANITIZE_STRING) : null;

if (!$valor || !$chave_pix || !$tipo_chave) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos os campos são obrigatórios']);
    exit;
}

// Validar tipo de chave PIX
$tipos_validos = ['cpf', 'cnpj', 'email', 'celular', 'aleatoria'];
if (!in_array($tipo_chave, $tipos_validos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de chave PIX inválido']);
    exit;
}

try {
    // Buscar configurações de saque
    $stmt = $pdo->prepare("
        SELECT valor as valor_minimo 
        FROM config_pagamentos 
        WHERE chave = 'saque_minimo'
    ");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if ($valor < $config['valor_minimo']) {
        throw new Exception('Valor mínimo para saque é R$ ' . number_format($config['valor_minimo'], 2, ',', '.'));
    }

    $jogadorId = getCurrentUserId();
    $contaManager = new ContaManager();
    
    // Busca conta do jogador
    $conta = $contaManager->buscarContaPorJogador($jogadorId);
    if (!$conta) {
        throw new Exception('Conta não encontrada');
    }

    // Verifica saldo disponível
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE 
            WHEN tipo IN ('deposito', 'premio', 'bonus') THEN valor 
            WHEN tipo IN ('saque', 'aposta') THEN -valor 
        END), 0) as saldo_atual
        FROM transacoes 
        WHERE conta_id = ? 
        AND status = 'aprovado' 
        AND afeta_saldo = TRUE
    ");
    $stmt->execute([$conta['id']]);
    $saldo = $stmt->fetch()['saldo_atual'];

    if ($saldo < $valor) {
        throw new Exception('Saldo insuficiente');
    }

    // Gera referência única para o saque
    $referencia = 'SAQ' . time() . rand(1000, 9999);
    
    // Inicia transação no banco
    $pdo->beginTransaction();

    // Cria registro de saque pendente
    $stmt = $pdo->prepare("
        INSERT INTO transacoes (
            conta_id,
            tipo,
            valor,
            saldo_anterior,
            saldo_posterior,
            status,
            metodo_pagamento,
            afeta_saldo,
            referencia,
            descricao,
            dados_adicionais
        ) VALUES (
            ?,
            'saque',
            ?,
            ?,
            ?,
            'pendente',
            'pix',
            1,
            ?,
            'Solicitação de saque via PIX',
            ?
        )
    ");

    $dados_adicionais = json_encode([
        'chave_pix' => $chave_pix,
        'tipo_chave' => $tipo_chave
    ]);

    $stmt->execute([
        $conta['id'],
        $valor,
        $saldo,
        $saldo - $valor,
        $referencia,
        $dados_adicionais
    ]);

    $saqueId = $pdo->lastInsertId();

    // Registra log da solicitação
    $stmt = $pdo->prepare("
        INSERT INTO logs 
            (tipo, descricao, usuario_id, dados_adicionais) 
        VALUES 
            (?, ?, ?, ?)
    ");
    
    $logData = [
        'tipo' => 'saque',
        'descricao' => 'Solicitação de saque via PIX',
        'usuario_id' => $jogadorId,
        'dados' => json_encode([
            'valor' => $valor,
            'referencia' => $referencia,
            'tipo_chave' => $tipo_chave
        ])
    ];
    
    $stmt->execute([
        $logData['tipo'],
        $logData['descricao'],
        $logData['usuario_id'],
        $logData['dados']
    ]);

    // Commit da transação
    $pdo->commit();
    
    // Retorna sucesso
    echo json_encode([
        'success' => true,
        'data' => [
            'saque_id' => $saqueId,
            'referencia' => $referencia,
            'valor' => $valor,
            'status' => 'pendente',
            'mensagem' => 'Solicitação de saque recebida com sucesso. Aguardando aprovação.'
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erro ao processar solicitação de saque: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao processar solicitação de saque',
        'message' => $e->getMessage()
    ]);
} 