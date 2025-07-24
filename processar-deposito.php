<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/ContaManager.php';
require_once 'includes/classes/SecurityValidator.php';

// Verifica se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para fazer um depósito.');
    redirect(APP_URL . '/login.php');
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('warning', 'Método inválido.');
    redirect(APP_URL . '/minha-conta.php');
}

// Valida campos
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$metodo = filter_input(INPUT_POST, 'metodo', FILTER_SANITIZE_STRING);

if (!$valor || !$metodo) {
    setFlashMessage('danger', 'Todos os campos são obrigatórios.');
    redirect(APP_URL . '/minha-conta.php');
}

try {
    $contaManager = new ContaManager();
    $securityValidator = new SecurityValidator();
    $jogadorId = getCurrentUserId();
    
    // Validar dados do usuário
    if (!$securityValidator->validarDadosUsuario($jogadorId)) {
        throw new Exception('Por favor, complete seus dados cadastrais antes de realizar um depósito.');
    }
    
    // Busca conta do jogador
    $conta = $contaManager->buscarContaPorJogador($jogadorId);
    if (!$conta) {
        throw new Exception('Conta não encontrada.');
    }
    
    // Validar transação
    $securityValidator->validarTransacao(
        $conta['id'],
        'deposito',
        $valor,
        [
            'metodo' => $metodo,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]
    );
    
    // Gera referência única
    $referencia = 'DEP' . time() . rand(1000, 9999);
    
    // Processa depósito
    $contaManager->depositar($conta['id'], $valor, $metodo, $referencia);
    
    // Redireciona para página de pagamento conforme método
    switch ($metodo) {
        case 'pix':
            redirect(APP_URL . '/pagamento/pix.php?ref=' . $referencia);
            break;
            
        case 'cartao_credito':
            redirect(APP_URL . '/pagamento/cartao.php?ref=' . $referencia);
            break;
            
        default:
            throw new Exception('Método de pagamento inválido.');
    }
    
} catch (Exception $e) {
    setFlashMessage('danger', 'Erro ao processar depósito: ' . $e->getMessage());
    redirect(APP_URL . '/minha-conta.php');
} 