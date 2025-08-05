<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Buscar bolões ativos e públicos
$condition = "status = 1";
$params = [];

// Para usuários não logados, mostrar apenas bolões públicos
$condition .= " AND publico = 1";

// Buscar bolões ativos
$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM palpites p WHERE p.bolao_id = b.id AND p.status = 'pago') as total_participantes
        FROM dados_boloes b 
        WHERE {$condition} 
        ORDER BY b.data_criacao DESC";
$boloes = dbFetchAll($sql, $params);

// Exibir resultados
echo "Total de bolões encontrados: " . count($boloes) . "\n\n";
foreach ($boloes as $bolao) {
    echo "ID: " . $bolao['id'] . "\n";
    echo "Nome: " . $bolao['nome'] . "\n";
    echo "Status: " . $bolao['status'] . "\n";
    echo "Público: " . $bolao['publico'] . "\n";
    echo "Total Participantes: " . $bolao['total_participantes'] . "\n";
    echo "Data Início: " . $bolao['data_inicio'] . "\n";
    echo "Data Fim: " . $bolao['data_fim'] . "\n";
    echo "-------------------\n";
}