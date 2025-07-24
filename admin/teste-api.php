<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Buscar configuração da API
$apiConfig = getConfig('api_football');

echo "<pre>";
print_r($apiConfig);
echo "</pre>";

// Testar uma requisição simples
$response = apiFootballRequest('status');

echo "<h3>Resposta da API:</h3>";
echo "<pre>";
print_r($response);
echo "</pre>"; 