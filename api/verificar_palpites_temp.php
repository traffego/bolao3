<?php
session_start();
header('Content-Type: application/json');

// Verificar se há palpites temporários preservados
$tem_palpites = isset($_SESSION['palpites_temp']) && !empty($_SESSION['palpites_temp']);
$bolao_redirect = isset($_SESSION['bolao_redirect']) ? $_SESSION['bolao_redirect'] : null;
$saldo_insuficiente = isset($_SESSION['saldo_insuficiente']) ? $_SESSION['saldo_insuficiente'] : false;

// Resposta JSON
echo json_encode([
    'tem_palpites' => $tem_palpites,
    'bolao_redirect' => $bolao_redirect,
    'saldo_insuficiente' => $saldo_insuficiente,
    'palpites_count' => $tem_palpites ? count($_SESSION['palpites_temp']) : 0
]);
?>