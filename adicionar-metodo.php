<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Verifica se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para adicionar um método de pagamento.');
    redirect(APP_URL . '/login.php');
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('warning', 'Método inválido.');
    redirect(APP_URL . '/minha-conta.php');
}

// Valida campos
$tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_STRING);
$principal = isset($_POST['principal']) ? 1 : 0;

if (!$tipo) {
    setFlashMessage('danger', 'Tipo de método de pagamento é obrigatório.');
    redirect(APP_URL . '/minha-conta.php');
}

try {
    $jogadorId = getCurrentUserId();
    $dados = [];
    
    // Valida e formata dados conforme tipo
    switch ($tipo) {
        case 'pix':
            $pixTipo = filter_input(INPUT_POST, 'pix_tipo', FILTER_SANITIZE_STRING);
            $pixChave = filter_input(INPUT_POST, 'pix_chave', FILTER_SANITIZE_STRING);
            
            if (!$pixTipo || !$pixChave) {
                throw new Exception('Todos os campos do PIX são obrigatórios.');
            }
            
            $dados = [
                'tipo' => $pixTipo,
                'chave' => $pixChave
            ];
            break;
            
        case 'transferencia_bancaria':
            $banco = filter_input(INPUT_POST, 'banco', FILTER_SANITIZE_STRING);
            $agencia = filter_input(INPUT_POST, 'agencia', FILTER_SANITIZE_STRING);
            $conta = filter_input(INPUT_POST, 'conta', FILTER_SANITIZE_STRING);
            $tipoConta = filter_input(INPUT_POST, 'tipo_conta', FILTER_SANITIZE_STRING);
            
            if (!$banco || !$agencia || !$conta || !$tipoConta) {
                throw new Exception('Todos os campos da conta bancária são obrigatórios.');
            }
            
            $dados = [
                'banco' => $banco,
                'agencia' => $agencia,
                'conta' => $conta,
                'tipo_conta' => $tipoConta
            ];
            break;
            
        case 'cartao_credito':
            $numero = filter_input(INPUT_POST, 'cartao_numero', FILTER_SANITIZE_STRING);
            $validade = filter_input(INPUT_POST, 'cartao_validade', FILTER_SANITIZE_STRING);
            $cvv = filter_input(INPUT_POST, 'cartao_cvv', FILTER_SANITIZE_STRING);
            $nome = filter_input(INPUT_POST, 'cartao_nome', FILTER_SANITIZE_STRING);
            
            if (!$numero || !$validade || !$cvv || !$nome) {
                throw new Exception('Todos os campos do cartão são obrigatórios.');
            }
            
            // Remove espaços do número
            $numero = str_replace(' ', '', $numero);
            
            // Valida número do cartão (algoritmo de Luhn)
            if (!validarCartao($numero)) {
                throw new Exception('Número do cartão inválido.');
            }
            
            // Valida validade
            if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $validade)) {
                throw new Exception('Data de validade inválida.');
            }
            list($mes, $ano) = explode('/', $validade);
            $anoAtual = date('y');
            $mesAtual = date('m');
            if ($ano < $anoAtual || ($ano == $anoAtual && $mes < $mesAtual)) {
                throw new Exception('Cartão vencido.');
            }
            
            // Valida CVV
            if (!preg_match('/^[0-9]{3}$/', $cvv)) {
                throw new Exception('CVV inválido.');
            }
            
            $dados = [
                'numero' => $numero,
                'validade' => $validade,
                'cvv' => $cvv,
                'nome' => $nome
            ];
            break;
            
        default:
            throw new Exception('Tipo de método de pagamento inválido.');
    }
    
    // Se for principal, desativa outros principais
    if ($principal) {
        $sql = "UPDATE metodos_pagamento SET principal = 0 WHERE jogador_id = ?";
        dbExecute($sql, [$jogadorId]);
    }
    
    // Insere novo método
    $sql = "INSERT INTO metodos_pagamento (jogador_id, tipo, dados, principal) VALUES (?, ?, ?, ?)";
    dbExecute($sql, [$jogadorId, $tipo, json_encode($dados), $principal]);
    
    setFlashMessage('success', 'Método de pagamento adicionado com sucesso!');
    redirect(APP_URL . '/minha-conta.php');
    
} catch (Exception $e) {
    setFlashMessage('danger', 'Erro ao adicionar método de pagamento: ' . $e->getMessage());
    redirect(APP_URL . '/minha-conta.php');
}

/**
 * Valida número de cartão usando algoritmo de Luhn
 */
function validarCartao($numero) {
    // Remove espaços e traços
    $numero = preg_replace('/[^0-9]/', '', $numero);
    
    // Verifica comprimento
    if (strlen($numero) < 13 || strlen($numero) > 19) {
        return false;
    }
    
    // Algoritmo de Luhn
    $soma = 0;
    $numArray = str_split($numero);
    $arrayLength = count($numArray);
    
    for ($i = 0; $i < $arrayLength; $i++) {
        if (($arrayLength + $i) % 2 == 0) {
            $valor = $numArray[$i] * 2;
            if ($valor > 9) {
                $valor = $valor - 9;
            }
        } else {
            $valor = $numArray[$i];
        }
        $soma += $valor;
    }
    
    return ($soma % 10 == 0);
} 