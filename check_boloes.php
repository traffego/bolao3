<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== TESTE 1: Query Simples ===\n";
$boloes_simples = dbFetchAll("SELECT * FROM dados_boloes WHERE status = 1 AND publico = 1");
echo "Bolões simples encontrados: " . count($boloes_simples) . "\n\n";

echo "=== TESTE 2: Query com Subquery ===\n";
$condition = "status = 1 AND publico = 1";
$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM palpites p WHERE p.bolao_id = b.id AND p.status = 'pago') as total_participantes
        FROM dados_boloes b 
        WHERE {$condition} 
        ORDER BY b.data_criacao DESC";
$boloes = dbFetchAll($sql, []);
echo "Bolões com subquery encontrados: " . count($boloes) . "\n";
echo "SQL: " . $sql . "\n\n";

echo "=== TESTE 3: Verificar conexão com DB ===\n";
try {
    $pdo = getPDO();
    echo "Conexão com banco: OK\n";
} catch (Exception $e) {
    echo "Erro na conexão: " . $e->getMessage() . "\n";
}

echo "\n=== RESULTADOS DETALHADOS ===\n";
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