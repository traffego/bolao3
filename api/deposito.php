<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Otimização: Habilita compressão gzip para reduzir tempo de transferência
if (extension_loaded('zlib') && !ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Headers de otimização
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/EfiPixManager.php';
require_once '../includes/classes/Logger.php';

// Log detalhado
log_info("Iniciando geração de QR Code PIX");

/**
 * Gera um TXID aleatório alfanumérico conforme especificação EFI Pay
 * Formato: ^[a-zA-Z0-9]{26,35}$
 * 
 * @return string TXID com 32 caracteres alfanuméricos aleatórios
 */
function generateRandomTxid() {
    log_debug("Iniciando geração de TXID aleatório");
    
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $txid = '';
    $length = 32; // Usar 32 caracteres (dentro do range 26-35)
    
    for ($i = 0; $i < $length; $i++) {
        $txid .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    log_debug("TXID aleatório gerado", [
        'txid' => $txid,
        'comprimento' => strlen($txid)
    ]);
    
    return $txid;
}

// Garantir que sempre retorne JSON
header('Content-Type: application/json');

// Log de entrada
log_debug("Requisição recebida", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'data' => file_get_contents('php://input')
]);

// Tratamento de erros para capturar erros fatais
function handleError($errno, $errstr, $errfile, $errline) {
    log_error("Erro detectado", [
        'errno' => $errno,
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'details' => DEBUG_MODE ? "$errstr in $errfile on line $errline" : null
    ]);
    exit;
}
set_error_handler('handleError');

try {
    // Log do início do processo
    log_debug("Verificando método da requisição...");

    // Verifica se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    log_debug("Verificando autenticação...");
    // Verifica se está logado
    if (!isLoggedIn()) {
        throw new Exception('Usuário não autenticado', 401);
    }

    log_debug("Obtendo e validando dados do depósito...");
    // Obtém dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Dados inválidos', 400);
    }

    // Verifica se é uma retomada de pagamento
    $transacaoId = isset($data['transacao_id']) ? filter_var($data['transacao_id'], FILTER_VALIDATE_INT) : null;
    
    if ($transacaoId) {
        // Busca a transação existente
        $sql = "
            SELECT t.*, c.jogador_id 
            FROM transacoes t
            INNER JOIN contas c ON t.conta_id = c.id
            WHERE t.id = ? AND t.tipo = 'deposito' AND t.status = 'pendente'";
        $transacao = dbFetchOne($sql, [$transacaoId]);

        // Verifica se a transação existe e pertence ao usuário
        if (!$transacao || $transacao['jogador_id'] != getCurrentUserId()) {
            throw new Exception('Transação não encontrada ou já processada');
        }

        $valor = $transacao['valor'];
    } else {
        // Validação do valor para nova transação
        if (!isset($data['valor'])) {
            throw new Exception('Valor não informado');
        }

        $valor = filter_var($data['valor'], FILTER_VALIDATE_FLOAT);
        if (!$valor) {
            throw new Exception('Valor inválido');
        }

        // Busca limites de depósito
        $sql = "SELECT nome_configuracao, valor FROM configuracoes 
                WHERE nome_configuracao IN ('deposito_minimo', 'deposito_maximo') 
                AND categoria = 'pagamento'";
        $configs = dbFetchAll($sql);
        
        // Inicializa limites
        $limites = [];
        foreach ($configs as $config) {
            $limites[$config['nome_configuracao']] = floatval($config['valor']);
        }

        // Valida limites
        if (!isset($limites['deposito_minimo']) || !isset($limites['deposito_maximo'])) {
            throw new Exception('Configurações de depósito não encontradas');
        }

        if ($valor < $limites['deposito_minimo']) {
            throw new Exception("Valor mínimo para depósito é R$ " . number_format($limites['deposito_minimo'], 2, ',', '.'));
        }
        if ($valor > $limites['deposito_maximo']) {
            throw new Exception("Valor máximo para depósito é R$ " . number_format($limites['deposito_maximo'], 2, ',', '.'));
        }
    }

    // Instancia EfiPay
    $efiPix = new EfiPixManager(defined('EFI_WEBHOOK_FATAL_FAILURE') ? EFI_WEBHOOK_FATAL_FAILURE : false);
    
    // Gera identificador único para o depósito (TXID aleatório)
    $identificador = generateRandomTxid();
    
    // Cria cobrança PIX
    $cobranca = $efiPix->createCharge(
        getCurrentUserId(),
        $valor,
        $identificador,
        "Depósito #{$identificador}"
    );

    if (!isset($cobranca['qrcode'])) {
        throw new Exception('Erro ao gerar QR Code PIX');
    }

    if ($transacaoId) {
        // Atualiza transação existente
        $sql = "UPDATE transacoes SET 
                txid = ?,
                data_processamento = NOW()
                WHERE id = ?";
        dbExecute($sql, [$cobranca['txid'], $transacaoId]);
        
        $novaTransacao = $transacao;
        $novaTransacao['txid'] = $cobranca['txid'];
    } else {
        // Cria nova transação
        log_debug("Criando nova transação", [
            'user_id' => getCurrentUserId(),
            'valor' => $valor,
            'txid' => $cobranca['txid'],
            'identificador' => $identificador,
            'afeta_saldo' => 0 // Transação pendente não afeta saldo
        ]);
        
        $sql = "INSERT INTO transacoes (
                    conta_id, 
                    tipo, 
                    valor, 
                    status,
                    txid,
                    referencia,
                    data_processamento,
                    afeta_saldo
                ) VALUES (
                    (SELECT id FROM contas WHERE jogador_id = ?),
                    'deposito',
                    ?,
                    'pendente',
                    ?,
                    ?,
                    NOW(),
                    0
                )";
        
        dbExecute($sql, [getCurrentUserId(), $valor, $cobranca['txid'], $identificador]);
        $novaTransacao = [
            'id' => dbLastInsertId(),
            'valor' => $valor,
            'txid' => $cobranca['txid'],
            'afeta_saldo' => 0
        ];
        log_debug("Transação criada com sucesso", [
            'id' => $novaTransacao['id'],
            'afeta_saldo' => 0
        ]);
    }

    // Retorna dados para o frontend
    echo json_encode([
        'success' => true,
        'data' => [
            'transacao_id' => $novaTransacao['id'],
            'valor' => $novaTransacao['valor'],
            'qr_code' => $cobranca['qrcode'],
            'qr_code_texto' => $cobranca['qrcode_texto'],
            'transacao_id' => $novaTransacao['id'],
            'ambiente_local' => strpos(WEBHOOK_URL, 'localhost') !== false || strpos(WEBHOOK_URL, '127.0.0.1') !== false
        ]
    ]);

} catch (Exception $e) {
    log_error("Exceção capturada", [
        'error' => $e->getMessage(),
        'stack_trace' => $e->getTraceAsString()
    ]);
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
log_info("Fim do processamento");