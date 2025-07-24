<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/ContaManager.php';

// Verifica se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para solicitar um saque.');
    redirect(APP_URL . '/login.php');
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('warning', 'Método inválido.');
    redirect(APP_URL . '/minha-conta.php');
}

// Valida campos
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$metodoId = filter_input(INPUT_POST, 'metodo', FILTER_VALIDATE_INT);

if (!$valor || !$metodoId) {
    setFlashMessage('danger', 'Todos os campos são obrigatórios.');
    redirect(APP_URL . '/minha-conta.php');
}

try {
    $contaManager = new ContaManager();
    $jogadorId = getCurrentUserId();
    
    // Busca conta do jogador
    $conta = $contaManager->buscarContaPorJogador($jogadorId);
    if (!$conta) {
        throw new Exception('Conta não encontrada.');
    }
    
    // Verifica se método de pagamento pertence ao jogador
    $sql = "SELECT * FROM metodos_pagamento WHERE id = ? AND jogador_id = ? AND ativo = 1";
    $metodo = dbFetchOne($sql, [$metodoId, $jogadorId]);
    if (!$metodo) {
        throw new Exception('Método de pagamento inválido.');
    }
    
    // Solicita saque
    $contaManager->solicitarSaque($conta['id'], $valor, $metodo['tipo']);
    
    setFlashMessage('success', 'Saque solicitado com sucesso! Em breve nossa equipe irá processar sua solicitação.');
    redirect(APP_URL . '/minha-conta.php');
    
} catch (Exception $e) {
    setFlashMessage('danger', 'Erro ao solicitar saque: ' . $e->getMessage());
    redirect(APP_URL . '/minha-conta.php');
} 