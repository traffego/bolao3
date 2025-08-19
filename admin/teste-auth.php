<?php
require_once '../config/config.php';
require_once '../includes/auth_admin.php';
require_once '../includes/functions.php';

echo "<h1>Teste de Autenticação</h1>";
echo "<p>Se você está vendo esta mensagem, a autenticação está funcionando!</p>";
echo "<p>Admin ID: " . ($_SESSION['admin_id'] ?? 'Não definido') . "</p>";
echo "<p>Função isAdmin(): " . (isAdmin() ? 'true' : 'false') . "</p>";
echo "<p>APP_URL: " . APP_URL . "</p>";
echo "<p>Sessão atual:</p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
?>