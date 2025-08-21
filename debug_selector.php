<?php
/**
 * Debug do Seletor de Bolão
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Get all available bolões for the selector
$todosBoloes = dbFetchAll("SELECT id, nome, data_inicio, data_fim FROM dados_boloes WHERE status = 1 ORDER BY data_inicio DESC");

echo "<h2>Debug do Seletor de Bolão</h2>";
echo "<p><strong>Total de bolões encontrados:</strong> " . count($todosBoloes) . "</p>";

if (!empty($todosBoloes)) {
    echo "<h3>Bolões disponíveis:</h3>";
    echo "<ul>";
    foreach ($todosBoloes as $bolao) {
        echo "<li>ID: {$bolao['id']} - Nome: {$bolao['nome']} - Início: {$bolao['data_inicio']} - Fim: {$bolao['data_fim']}</li>";
    }
    echo "</ul>";
    
    echo "<h3>Teste do Seletor HTML:</h3>";
    echo '<div class="bolao-selector" style="margin: 20px; padding: 20px; border: 2px solid #ccc; background: #f9f9f9;">';
    echo '<select id="bolaoSelect" class="form-select" style="width: 100%; padding: 10px; font-size: 16px; border: 1px solid #ddd;">';
    echo '<option value="">Selecione um bolão</option>';
    foreach ($todosBoloes as $bolaoOption) {
        echo '<option value="' . $bolaoOption['id'] . '">';
        echo htmlspecialchars($bolaoOption['nome']);
        echo ' (' . date('d/m/Y', strtotime($bolaoOption['data_inicio'])) . ' - ' . date('d/m/Y', strtotime($bolaoOption['data_fim'])) . ')';
        echo '</option>';
    }
    echo '</select>';
    echo '</div>';
} else {
    echo "<p style='color: red;'><strong>PROBLEMA:</strong> Nenhum bolão encontrado com status = 1</p>";
    
    // Verificar se existem bolões com outros status
    $todosBoloesSemFiltro = dbFetchAll("SELECT id, nome, data_inicio, data_fim, status FROM dados_boloes ORDER BY data_inicio DESC");
    echo "<h3>Todos os bolões (sem filtro de status):</h3>";
    if (!empty($todosBoloesSemFiltro)) {
        echo "<ul>";
        foreach ($todosBoloesSemFiltro as $bolao) {
            echo "<li>ID: {$bolao['id']} - Nome: {$bolao['nome']} - Status: {$bolao['status']} - Início: {$bolao['data_inicio']} - Fim: {$bolao['data_fim']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>Nenhum bolão encontrado na tabela dados_boloes!</p>";
    }
}
?>